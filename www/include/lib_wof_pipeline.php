<?php

	// The 'photos' prefix here is temporary until we sort out the
	// requisite S3 permissions. (20170530/dphiffer)
	$GLOBALS['cfg']['wof_pipeline_base_path'] = 'photos/pipeline/';

	loadlib('wof_s3');
	loadlib('slack_bot');
	loadlib('wof_pipeline_utils');
	loadlib('wof_pipeline_neighbourhood');
	loadlib('wof_pipeline_remove_properties');

	########################################################################

	function wof_pipeline_create($meta) {

		if (! $GLOBALS['cfg']['enable_feature_pipeline']) {
			return array(
				'ok' => 0,
				'error' => 'Pipeline feature is disabled.'
			);
		}

		$fn = "wof_pipeline_{$meta['type']}_defaults";
		if (function_exists($fn)) {
			$meta = $fn($meta);
		}

		$meta_json = json_encode($meta);
		$meta_json_esc = addslashes($meta_json);
		$now = date('Y-m-d H:i:s');

		if ($meta['slack_handle']) {
			users_settings_set($GLOBALS['cfg']['user'], 'slack_handle', $meta['slack_handle']);
		}

		if ($meta['upload']) {
			$upload = $meta['upload'];
			unset($meta['upload']);
			$rsp = wof_pipeline_validate_zip($upload, $meta);
			if (! $rsp['ok']) {
				return $rsp;
			}
			$meta = $rsp['meta'];

			$filename = $upload['name'];
			$filename_esc = addslashes($filename);
		} else {
			$filename = null;
			$filename_esc = null;
		}

		$rsp = db_insert('boundaryissues_pipeline', array(
			'filename' => $filename_esc,
			'type' => $meta['type'],
			'meta' => $meta_json_esc,
			'phase' => 'pending',
			'repo' => $meta['repo'],
			'created' => $now,
			'updated' => $now
		));
		if (! $rsp['ok']) {
			return $rsp;
		}
		$pipeline_id = $rsp['insert_id'];

		if (! $filename_esc) {
			$filename_esc = addslashes("Pipeline $pipeline_id");
		}

		if ($upload) {
			$rsp = wof_pipeline_upload_files($upload, $meta, $pipeline_id);
			if (! $rsp['ok']) {
				return $rsp;
			}
		}

		wof_pipeline_log($pipeline_id, "Created pipeline $pipeline_id", $meta);
		$url = $GLOBALS['cfg']['abs_root_url'] . "pipeline/$pipeline_id/";
		slack_bot_msg("pending: <$url|$filename> ({$meta['type']} pipeline $pipeline_id)");

		// Ok, here is where we encode the URL into the DB record, since
		// we are going to need it later from the cron-run
		// process_pipeline.php (which doesn't know how to figure out
		// the proper abs_root_url. (20170601/dphiffer)
		db_update('boundaryissues_pipeline', array(
			'filename' => $filename_esc,
			'url' => $url
		), "id = $pipeline_id");

		return array(
			'ok' => 1,
			'pipeline_id' => $pipeline_id
		);
	}

	########################################################################

	function wof_pipeline_get($id) {
		$id = intval($id);
		$rsp = db_fetch("
			SELECT *
			FROM boundaryissues_pipeline
			WHERE id = $id
		");
		if (! $rsp['ok'] ||
		    ! $rsp['rows']) {
			return $rsp;
		}

		$pipeline = $rsp['rows'][0];
		$pipeline['meta'] = json_decode($pipeline['meta'], 'as hash');

		return array(
			'ok' => 1,
			'pipeline' => $pipeline
		);
	}

	########################################################################

	function wof_pipeline_upload_files($upload, $meta, $pipeline_id) {

		$dir = "{$GLOBALS['cfg']['wof_pipeline_base_path']}$pipeline_id/";

		// Upload zip file
		$data = file_get_contents($upload['tmp_name']);
		$path = "$dir{$upload['name']}";
		$args = array('acl' => rawurlencode('public-read'));
		$rsp = wof_s3_put($data, $path, $args);

		// Read contents of files from zip file
		$rsp = wof_pipeline_read_zip_contents($upload, $meta['files']);
		if (! $rsp['ok']) {
			return $rsp;
		}
		$contents = $rsp['contents'];

		// Upload each file
		foreach ($contents as $file => $data) {
			$path = "$dir$file";
			$rsp = wof_s3_put($data, $path);
			if (! $rsp['ok']) {
				return $rsp;
			}
		}

		$result = array(
			'ok' => 1,
			'url' => "http://{$GLOBALS['cfg']['aws']['s3_bucket']}.s3.amazonaws.com/$dir",
			'files' => $meta['files']
		);

		wof_pipeline_log($pipeline_id, "Uploaded files to S3", $result);

		return $result;
	}

	########################################################################

	function wof_pipeline_download_files($pipeline) {

		$pipeline_id = intval($pipeline['id']);
		$dir = "{$GLOBALS['cfg']['wof_pending_dir']}/pipeline/$pipeline_id/";
		if (! file_exists($dir)) {
			mkdir($dir, 0755, true);
		}

		$remote_dir = "{$GLOBALS['cfg']['wof_pipeline_base_path']}$pipeline_id/";

		foreach ($pipeline['meta']['files'] as $file) {
			$rsp = wof_s3_get("$remote_dir$file");
			if (! $rsp['ok']) {
				return $rsp;
			}
			file_put_contents("$dir$file", $rsp['body']);
		}

		$result = array(
			'ok' => 1,
			'dir' => $dir,
			'files' => $pipeline['meta']['files']
		);

		wof_pipeline_log($pipeline_id, "Downloaded files from S3", $result);

		return $result;
	}

	########################################################################

	function wof_pipeline_prepare($pipeline) {

		wof_pipeline_log($pipeline['id'], "Preparing {$pipeline['type']} pipeline", $rsp);

		if (! preg_match('/[0-9a-zA-Z_-]+/', $pipeline['repo'])) {
			// Safety check: make sure the repo looks ok
			wof_pipeline_finish($pipeline, 'error', "Bad repo value: {$pipeline['repo']}");
			return false;
		}

		$pipeline['handler'] = "wof_pipeline_{$pipeline['type']}";
		if (! function_exists($pipeline['handler'])) {
			wof_pipeline_finish($pipeline, 'error', "Could not find {$pipeline['handler']} handler");
			return false;
		}

		$repo_path = wof_pipeline_repo_path($pipeline);

		$rsp = git_execute($repo_path, "checkout master");
		if (! $rsp['ok']) {
			wof_pipeline_finish($pipeline, 'error', "Could not checkout master branch", $rsp);
			return false;
		}

		$rsp = git_pull($repo_path, 'origin', 'master');
		if (! $rsp['ok']) {
			wof_pipeline_finish($pipeline, 'error', "Could not pull from origin master", $rsp);
			return false;
		}

		if ($pipeline['branch_merge']) {
			$pipeline['branch'] = "pipeline-{$pipeline['id']}";
			$rsp = git_execute($repo_path, "checkout -b $branch");
			wof_pipeline_log($pipeline['id'], "New {$pipeline['repo']} branch: {$pipeline['branch']}", $rsp);
			if (! $rsp['ok']) {
				wof_pipeline_finish($pipeline, 'error', "Could not create branch {$pipeline['branch']}", $rsp);
				return false;
			}
		}

		wof_pipeline_phase($pipeline, 'execute');

		return true;
	}

	########################################################################

	function wof_pipeline_execute($pipeline) {

		$handler = $pipeline['handler'];

		if ($pipeline['files']) {
			$rsp = wof_pipeline_download_files($pipeline);
			if (! $rsp['ok']) {
				wof_pipeline_finish($pipeline, 'error', "Could not download files", $rsp);
				return false;
			}
			$pipeline['dir'] = $rsp['dir'];
		}

		// Dry run of the pipeline function
		$rsp = $handler($pipeline, 'dry run');
		wof_pipeline_log($pipeline['id'], "Dry run: $handler", $rsp);
		if (! $rsp['ok']) {
			wof_pipeline_finish($pipeline, 'error', "Dry run error from $handler", $rsp);
			return false;
		}

		// Actual f'realz run of the pipeline function
		$rsp = $handler($pipeline);
		wof_pipeline_log($pipeline['id'], "Execute: $handler", $rsp);
		if (! $rsp['ok']) {
			wof_pipeline_finish($pipeline, 'error', "Execution error from $handler", $rsp);
			return false;
		}

		$updated = $rsp['updated'];
		if (count($updated) == 0) {
			wof_pipeline_finish($pipeline, 'error', "No files modified, bailing out");
			return false;
		}

		wof_pipeline_phase($pipeline, 'commit');

		return true;
	}

	########################################################################

	function wof_pipeline_commit($pipeline) {

		$repo_path = wof_pipeline_repo_path($pipeline);

		$rsp = wof_pipeline_preprocess($repo_path);
		if (! $rsp['ok']) {
			wof_pipeline_finish($pipeline, 'error', "Preprocessing error from $repo_path", $rsp);
			return false;
		}

		foreach ($updated as $path) {
			$rsp = git_add($repo_path, $path);
			$basename = basename($path);
			wof_pipeline_log($pipeline['id'], "Add $basename to git index", $rsp);
			if (! $rsp['ok']) {
				wof_pipeline_finish($pipeline, 'error', "Error adding $basename to git index", $rsp);
				return false;
			}
		}

		$pipeline_id = intval($pipeline['id']);
		$emoji = ':horse:';

		if ($pipeline['commit_emoji']) {
			$emoji = $pipeline['commit_emoji'];
		}

		$commit_msg = "$emoji pipeline $pipeline_id: {$pipeline['filename']} ({$pipeline['type']})";
		$rsp = git_commit($repo_path, $commit_msg);
		if (! $rsp['ok']) {
			wof_pipeline_finish($pipeline, 'error', "Error committing to $repo_path", $rsp);
			return false;
		}

		$how_many = count($updated);
		if ($how_many == 1) {
			$how_many .= ' file';
		} else {
			$how_many .= ' files';
		}
		wof_pipeline_log($pipeline['id'], "Commit changes to $how_many", $rsp);

		wof_pipeline_phase($pipeline, 'push');

		return true;
	}

	########################################################################

	function wof_pipeline_push($pipeline) {

		$repo_path = wof_pipeline_repo_path($pipeline);

		if ($pipeline['branch_merge']) {
			$branch = $pipeline['branch'];
		} else {
			$branch = 'master';
		}

		$rsp = git_push($repo_path);
		wof_pipeline_log($pipeline['id'], "Push commit to origin $branch", $rsp);
		if (! $rsp['ok']) {
			wof_pipeline_finish($pipeline, 'error', "Error pushing $repo_path (branch $branch)", $rsp);
			return false;
		}

		if ($pipeline['user_confirmation']) {
			wof_pipeline_phase($pipeline, 'confirm');
			return false;
		} else {
			wof_pipeline_phase($pipeline, 'merge');
		}

		return true;
	}

	########################################################################

	function wof_pipeline_merge($pipeline) {

		$repo_path = wof_pipeline_repo_path($pipeline);

		if (! $pipeline['branch_merge']) {
			wof_pipeline_log($pipeline['id'], "No merge required");
			return true;
		}

		$rsp = git_execute($repo_path, "checkout staging-work");
		wof_pipeline_log($pipeline['id'], "Checkout staging-work branch", $rsp);
		if (! $rsp['ok']) {
			wof_pipeline_finish($pipeline, 'error', "$repo_path error checking out staging-work branch", $rsp);
			return false;
		}

		$rsp = git_pull($repo_path, 'origin', 'staging-work');
		if (! $rsp['ok']) {
			wof_pipeline_finish($pipeline, 'error', "$repo_path error pulling from origin staging-work", $rsp);
			return false;
		}

		$rsp = git_pull($repo_path, 'origin', $branch);
		wof_pipeline_log($pipeline['id'], "Merge into staging-work branch", $rsp);
		if (! $rsp['ok']) {
			wof_pipeline_finish($pipeline, 'error', "$repo_path error merging into staging-work", $rsp);
			return false;
		}

		$rsp = git_push($repo_path);
		wof_pipeline_log($pipeline['id'], "Push commit to origin staging-work", $rsp);
		if (! $rsp['ok']) {
			wof_pipeline_finish($pipeline, 'error', "$repo_path error pushing to origin staging-work", $rsp);
			return false;
		}

		$rsp = git_execute($repo_path, "checkout master");
		wof_pipeline_log($pipeline['id'], "Checkout master branch", $rsp);
		if (! $rsp['ok']) {
			wof_pipeline_finish($pipeline, 'error', "$repo_path error checking out master branch", $rsp);
			return false;
		}

		$rsp = git_pull($repo_path, 'origin', $branch);
		wof_pipeline_log($pipeline['id'], "Merge into master branch", $rsp);
		if (! $rsp['ok']) {
			wof_pipeline_finish($pipeline, 'error', "$repo_path error merging $branch into master", $rsp);
			return false;
		}

		$rsp = git_execute($repo_path, "branch -d $branch");
		wof_pipeline_log($pipeline['id'], "Delete local branch $branch", $rsp);
		if (! $rsp['ok']) {
			wof_pipeline_finish($pipeline, 'error', "$repo_path error deleting local $branch branch", $rsp);
			return false;
		}

		$rsp = git_execute($repo_path, "push origin --delete $branch");
		wof_pipeline_log($pipeline['id'], "Delete remote branch $branch", $rsp);
		if (! $rsp['ok']) {
			wof_pipeline_finish($pipeline, 'error', "$repo_path error deleting remote $branch branch", $rsp);
			return false;
		}

		$rsp = git_push($repo_path);
		wof_pipeline_log($pipeline['id'], "Push commit to origin master", $rsp);
		if (! $rsp['ok']) {
			wof_pipeline_finish($pipeline, 'error', "$repo_path pushing to origin master", $rsp);
			continue;
		}

		return true;
	}

	########################################################################

	function wof_pipeline_finish($pipeline, $phase, $debug = null, $rsp = null) {

		wof_pipeline_phase($pipeline, $phase);

		if ($phase == 'success') {
			wof_pipeline_cleanup($pipeline);
			$repo_path = wof_pipeline_repo_path($pipeline);
			$rsp = git_execute($repo_path, "checkout master");
			wof_pipeline_log($pipeline['id'], "Resetting {$pipeline['repo']} to master branch", $rsp);
		} else {
			if (! $debug) {
				$debug = "Something went wrong, but I don’t know what";
			}
			wof_pipeline_log($pipeline['id'], $debug, $rsp);
			$debug = "Pipeline {$pipeline['id']}: $debug";
			if ($rsp) {
				$debug .= "\nResponse:\n";
				$debug .= var_export($rsp, 'return values');
			}
			$debug .= "\nDetails:\n";
			$debug .= var_export($pipeline, 'return values');

			wof_repo_set_status($pipeline['repo'], 'inactive', $debug);
		}
	}

	########################################################################

	function wof_pipeline_validate_zip($upload, $meta = null) {

		$names = array();
		$basename = basename($upload['name'], '.zip');

		if (! preg_match('/^[a-zA-Z0-9_-]+\.zip$/', $upload['name'])) {
			return array(
				'ok' => 0,
				'error' => 'Invalid filename. Please use only alphanumerics, _ (underbar), or - (hyphen).'
			);
		}

		$fh = zip_open($upload['tmp_name']);

		while ($entry = zip_read($fh)) {
			$name = zip_entry_name($entry);
			if (! $meta && $name == "$basename/meta.json") {
				$json = zip_entry_read($entry);
				$meta = json_decode($json, 'as hash');
			} else if (preg_match("/^$basename\/([^\/]+\.(geojson|csv))\$/", $name, $matches)) {
				$names[] = $matches[1];
			}
		}

		$meta['files'] = $names;

		if (! $meta) {
			return array(
				'ok' => 0,
				'error' => 'No meta.json file found'
			);
		}

		if (! $meta['type']) {
			return array(
				'ok' => 0,
				'error' => "meta.json has no 'type' property"
			);
		}

		$fn = "wof_pipeline_{$meta['type']}_validate";
		if (function_exists($fn)) {
			$rsp = $fn($meta, $names);
			if (! $rsp['ok']) {
				return $rsp;
			}
		}

		$fn = "wof_pipeline_{$meta['type']}_repo";
		if (function_exists($fn)) {
			$rsp = $fn($upload, $names);
			if (! $rsp['ok']) {
				return $rsp;
			}
			$meta['repo'] = $rsp['repo'];
		} else if (! $meta['repo']) {
			return array(
				'ok' => 0,
				'error' => 'Could not determine repo for pipeline.'
			);
		}

		zip_close($fh);

		return array(
			'ok' => 1,
			'meta' => $meta
		);
	}

	########################################################################

	function wof_pipeline_read_zip_contents($upload, $files) {

		$basename = basename($upload['name'], '.zip');
		$fh = zip_open($upload['tmp_name']);
		$data = array();

		while ($entry = zip_read($fh)) {
			$path = zip_entry_name($entry);
			$name = preg_replace("/^$basename\//", '', $path);
			if (in_array($name, $files) || $name == 'meta.json') {
				$data[$name] = zip_entry_read($entry);
			}
		}

		zip_close($fh);

		return array(
			'ok' => 1,
			'contents' => $data
		);
	}

	########################################################################

	function wof_pipeline_log($pipeline_id, $summary, $details = '') {
		if (! is_scalar($details)) {
			$details = var_export($details, true);
		}
		$pipeline_id = intval($pipeline_id);
		$summary_esc = addslashes($summary);
		$details_esc = addslashes($details);
		$now = date('Y-m-d H:i:s');
		$rsp = db_insert('boundaryissues_pipeline_log', array(
			'pipeline_id' => $pipeline_id,
			'summary' => $summary_esc,
			'details' => $details_esc,
			'created_at' => $now
		));
		return $rsp;
	}

	########################################################################

	function wof_pipeline_log_dump($id) {

		$id = intval($id);
		$rsp = db_fetch("
			SELECT *
			FROM boundaryissues_pipeline_log
			WHERE pipeline_id = $id
			ORDER BY created_at
		");
		if (! $rsp['ok']) {
			return $rsp;
		}

		return array(
			'ok' => 1,
			'logs' => $rsp['rows']
		);
	}

	########################################################################

	function wof_pipeline_phase($pipeline, $phase) {

		$pipeline_id = intval($pipeline['id']);

		$pipeline['meta']['last_phase'] = $pipeline['phase'];
		$meta = json_encode($pipeline['meta']);

		$phase_esc = addslashes($phase);
		$now = date('Y-m-d H:i:s');

		$rsp = db_update('boundaryissues_pipeline', array(
			'meta' => $meta,
			'phase' => $phase_esc,
			'updated' => $now
		), "id = $pipeline_id");

		$notification = '';
		if ($phase == 'error') {
			if ($pipeline['meta']['slack_handle']) {
				$notification = " @{$pipeline['meta']['slack_handle']}";
			} else {
				$notification = ' <!here>';
			}
		} else if ($phase == 'success') {
			if ($pipeline['meta']['slack_handle']) {
				$notification = " @{$pipeline['meta']['slack_handle']}";
			}
		}

		wof_pipeline_log($pipeline_id, "Phase set to $phase", $rsp);
		slack_bot_msg("$phase: <{$pipeline['url']}|{$pipeline['filename']}> ({$pipeline['type']} pipeline $pipeline_id)$notification");

		return $rsp;
	}

	########################################################################

	function wof_pipeline_cleanup($pipeline) {

		$meta = $pipeline['meta'];
		$zip_file = $pipeline['filename'];
		$files = array();

		$rsp = wof_pipeline_cleanup_file($pipeline, $zip_file);
		if (! $rsp['ok']) {
			return $rsp;
		}
		$files[] = $zip_file;

		$rsp = wof_pipeline_cleanup_file($pipeline, 'meta.json');
		if (! $rsp['ok']) {
			return $rsp;
		}
		$files[] = 'meta.json';

		foreach ($meta['files'] as $filename) {
			$rsp = wof_pipeline_cleanup_file($pipeline, $filename);
			if (! $rsp['ok']) {
				return $rsp;
			}
			$files[] = $filename;
		}

		$pipeline_id = intval($pipeline['id']);
		$local_dir = "{$GLOBALS['cfg']['wof_pending_dir']}/pipeline/$pipeline_id/";
		if (file_exists($local_dir)) {
			rmdir($local_dir);
		}

		$result = array(
			'ok' => 1,
			'files' => $files
		);
		wof_pipeline_log($pipeline_id, "Cleaned up files", $result);

		return $result;
	}

	########################################################################

	function wof_pipeline_cleanup_file($pipeline, $filename) {
		$pipeline_id = intval($pipeline['id']);
		$remote_dir = "{$GLOBALS['cfg']['wof_pipeline_base_path']}$pipeline_id/";
		$remote_path = "$remote_dir$filename";
		$rsp = wof_s3_delete($remote_path);

		$local_dir = "{$GLOBALS['cfg']['wof_pending_dir']}/pipeline/$pipeline_id/";
		$local_path = "$local_dir$filename";
		if (file_exists($local_path)) {
			unlink($local_path);
		}

		return $rsp;
	}

	########################################################################

	function wof_pipeline_next() {
		$rsp = db_fetch("
			SELECT *
			FROM boundaryissues_pipeline
			WHERE phase = 'pending'
			GROUP BY repo
			ORDER BY created
		");
		if (! $rsp['ok']) {
			return $rsp;
		}

		if (! $rsp['rows']) {
			return array(
				'ok' => 1,
				'next' => array()
			);
		}

		$pending = $rsp['rows'];
		$rsp = db_fetch("
			SELECT repo
			FROM boundaryissues_pipeline
			WHERE phase = 'in_progress'
			   OR phase = 'next'
			GROUP BY repo
		");
		if (! $rsp['ok']) {
			return $rsp;
		}

		$repo_locked = array();
		foreach ($rsp['rows'] as $in_progress) {
			$repo_locked[] = $in_progress['repo'];
		}

		$next = array();
		$ids = array();
		foreach ($pending as $pipeline) {
			if (in_array($pipeline['repo'], $repo_locked)) {
				continue;
			}
			$pipeline['meta'] = json_decode($pipeline['meta'], 'as hash');
			$next[] = $pipeline;
			$ids[] = intval($pipeline['id']);
		}

		$id_list = implode(', ', $ids);
		$now = date('Y-m-d H:i:s');
		$rsp = db_update('boundaryissues_pipeline', array(
			'phase' => 'next',
			'updated' => $now
		), "id IN ($id_list)");
		if (! $rsp['ok']) {
			return $rsp;
		}

		return array(
			'ok' => 1,
			'next' => $next
		);
	}

	########################################################################

	function wof_pipeline_preprocess($repo_path) {

		$root = dirname(dirname(__DIR__));
		$ensure_props = array('wof:parent_id', 'wof:repo');

		if (substr($repo_path, 0, 15) != '/usr/local/data') {
			return array(
				'ok' => 0,
				'error' => 'repo_path must be inside of /usr/local/data'
			);
		}

		if (substr($repo_path, -6, 6) == '/data/') {
			// Trim the trailing 'data/'
			$repo_path = substr($repo_path, 0, -5);
		}

		foreach ($ensure_props as $prop) {

			$output = array();
			$cmd = "$root/bin/wof-ensure-property -repo $repo_path -property $prop";
			exec($cmd, $output);

			if (! $output || $output[0] != 'id,path,details') {
				return array(
					'ok' => 0,
					'cmd' => $cmd,
					'error' => implode("\n", $output)
				);
			}

			if (count($output) > 1) {
				$wof_ids = array();
				for ($i = 1; $i < count($output); $i++) {
					$row = str_getcsv($output[$i]);
					$wof_ids[] = $row[0];
				}
				return array(
					'ok' => 0,
					'error' => "Could not find '$prop' in WOF records: " . implode(', ', $wof_ids)
				);
			}
		}

		return array(
			'ok' => 1,
			'ensured_properties' => $ensure_props,
			'repo_path' => $repo_path
		);
	}

	# the end
