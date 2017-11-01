<?php

	// The 'photos' prefix here is temporary until we sort out the
	// requisite S3 permissions. (20170530/dphiffer)
	$GLOBALS['cfg']['wof_pipeline_base_path'] = 'photos/pipeline/';

	loadlib('slack_bot');
	loadlib('wof_pipeline_utils');
	loadlib('wof_pipeline_neighbourhood');
	loadlib('wof_pipeline_remove_properties');
	loadlib('wof_pipeline_fix_property_type');
	loadlib('wof_pipeline_merge_pr');
	loadlib('wof_pipeline_update_repo');
	loadlib('wof_pipeline_venues');
	loadlib('wof_repo');
	loadlib('wof_s3');
	loadlib('chatterbox');

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
			// Merge in pipeline default settings
			$meta = $fn($meta);
		}

		$rsp = wof_pipeline_validate($meta);
		if (! $rsp['ok']) {
			return $rsp;
		}

		if ($meta['upload']) {
			$filename = $meta['upload']['name'];
			unset($meta['upload']);
		} else if ($meta['name']) {
			$filename = $meta['name'];
		} else if ($meta['repo']) {
			$filename = $meta['repo'];
		} else {
			$filename = null;
		}

		$meta_json = json_encode($meta);
		$meta_json_esc = addslashes($meta_json);
		$now = date('Y-m-d H:i:s');

		if ($meta['slack_handle']) {
			users_settings_set($GLOBALS['cfg']['user'], 'slack_handle', $meta['slack_handle']);
		}

		$filename_esc = addslashes($filename);

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
			$meta['files'] = $rsp['files'];
			$meta_json = json_encode($meta);
			$meta_json_esc = addslashes($meta_json);
		}

		wof_pipeline_log($pipeline_id, "Created pipeline $pipeline_id", $meta);
		$url = $GLOBALS['cfg']['abs_root_url'] . "pipeline/$pipeline_id/";
		slack_bot_msg("pending: <$url|$filename> ({$meta['type']} $pipeline_id)");

		// Ok, here is where we encode the URL into the DB record, since
		// we are going to need it later from the cron-run
		// process_pipeline.php (which doesn't know how to figure out
		// the proper abs_root_url. (20170601/dphiffer)

		// Also, add the list of files that were uploaded. (20171012/dphiffer)
		db_update('boundaryissues_pipeline', array(
			'filename' => $filename_esc,
			'url' => $url,
			'meta' => $meta_json_esc
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

		if (empty($contents)) {
			// No files to upload
			return array('ok' => 1, 'files' => array());
		}

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
		$dir = "{$GLOBALS['cfg']['wof_pending_dir']}pipeline/$pipeline_id/";
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

	function wof_pipeline_cancel($pipeline) {
		wof_pipeline_phase($pipeline, 'cancelled');
		wof_pipeline_cleanup($pipeline);
		$rsp = wof_repo_get_status($pipeline['repo']);
		$id = $pipeline['id'];
		if ($rsp['status'] &&
		    strpos($rsp['status'], "pipeline $id") !== false) {
			wof_repo_set_status($pipeline['repo'], 'ready', "status updated after cancelling pipeline $id");
		}
	}

	########################################################################

	function wof_pipeline_prepare($pipeline) {

		wof_pipeline_log($pipeline['id'], "Preparing {$pipeline['type']} pipeline");

		if (! preg_match('/[0-9a-zA-Z_-]+/', $pipeline['repo'])) {
			// Safety check: make sure the repo looks ok
			wof_pipeline_finish($pipeline, 'error', "Bad repo value: {$pipeline['repo']}");
			return false;
		}

		$handler = "wof_pipeline_{$pipeline['type']}";
		if (! function_exists($handler)) {
			wof_pipeline_finish($pipeline, 'error', "Could not find {$handler} handler");
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

		if ($pipeline['type'] == 'update_repo') {
			wof_pipeline_log($pipeline['id'], "Updated {$pipeline['meta']['repo']}", $rsp['stdout'], $rsp);
			wof_pipeline_finish($pipeline, 'success');
			return false;
		} else if ($pipeline['meta']['branch_merge']) {
			wof_pipeline_phase($pipeline, 'branch');
		} else {
			wof_pipeline_phase($pipeline, 'execute');
		}

		return true;
	}

	########################################################################

	function wof_pipeline_branch($pipeline) {

		if (! $pipeline['meta']['branch_merge']) {
			return true;
		}

		$repo_path = wof_pipeline_repo_path($pipeline);
		$branch = "pipeline-{$pipeline['id']}";

		$rsp = git_execute($repo_path, "checkout -b $branch");
		wof_pipeline_log($pipeline['id'], "New {$pipeline['repo']} branch: $branch", $rsp);

		if (! $rsp['ok']) {
			wof_pipeline_finish($pipeline, 'error', "Could not create branch $branch", $rsp);
			return false;
		}

		wof_pipeline_phase($pipeline, 'execute');

		return true;
	}

	########################################################################

	function wof_pipeline_execute(&$pipeline) {

		$handler = "wof_pipeline_{$pipeline['type']}";

		if ($pipeline['meta']['files']) {
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

		if ($rsp['commit_all']) {
			$pipeline['updated'] = array('*');
		} else if ($rsp['updated']) {
			$pipeline['updated'] = $rsp['updated'];
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

		if (empty($pipeline['updated'])) {
			// Nothing to commit, we can skip this step
			wof_pipeline_phase($pipeline, 'push');
			return true;
		}

		foreach ($pipeline['updated'] as $path) {
			$rsp = git_add($repo_path, $path);
			$basename = basename($path);
			wof_pipeline_log($pipeline['id'], "Add $basename to git index", $rsp);
			if (! $rsp['ok']) {
				wof_pipeline_finish($pipeline, 'error', "Error adding $basename to git index", $rsp);
				return false;
			}
		}

		$pipeline_id = intval($pipeline['id']);
		$emoji = ':tractor:';

		if ($pipeline['commit_emoji']) {
			$emoji = $pipeline['commit_emoji'];
		}

		$commit_msg = "$emoji pipeline $pipeline_id: {$pipeline['filename']} ({$pipeline['type']})";
		$rsp = git_commit($repo_path, $commit_msg);
		if (! $rsp['ok']) {
			wof_pipeline_finish($pipeline, 'error', "Error committing to $repo_path", $rsp);
			return false;
		}

		$how_many = count($pipeline['updated']);
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

	function wof_pipeline_push(&$pipeline) {

		$repo_path = wof_pipeline_repo_path($pipeline);

		if ($pipeline['meta']['branch_merge']) {
			$branch = "pipeline-{$pipeline['id']}";
		} else {
			$branch = 'master';
		}

		$rsp = git_push($repo_path);
		wof_pipeline_log($pipeline['id'], "Push commit to origin $branch", $rsp);
		if (! $rsp['ok']) {
			wof_pipeline_finish($pipeline, 'error', "Error pushing $repo_path (branch $branch)", $rsp);
			return false;
		}

		if ($pipeline['meta']['user_confirmation']) {
			wof_pipeline_phase($pipeline, 'confirm');
			return false;
		} else if ($pipeline['meta']['branch_merge']) {
			wof_pipeline_phase($pipeline, 'merge');
		} else {
			wof_pipeline_finish($pipeline, 'success');
		}

		return true;
	}

	########################################################################

	function wof_pipeline_merge(&$pipeline) {

		if (! $pipeline['meta']['branch_merge']) {
			return true;
		}

		$repo_path = wof_pipeline_repo_path($pipeline);
		$branch = "pipeline-{$pipeline['id']}";

		$rsp = git_pull($repo_path, 'origin', $branch);
		if (! $rsp['ok']) {
			wof_pipeline_finish($pipeline, 'error', "Could not pull from origin $branch", $rsp);
			return false;
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

		wof_pipeline_finish($pipeline, 'success');

		return true;
	}

	########################################################################

	function wof_pipeline_finish(&$pipeline, $phase, $debug = null, $rsp = null) {

		wof_pipeline_phase($pipeline, $phase);
		$id = $pipeline['id'];

		if ($phase == 'success') {
			wof_pipeline_cleanup($pipeline);
			wof_repo_set_status($pipeline['repo'], 'ready', "status updated after finishing pipeline $id");

			if ($pipeline['meta']['generate_meta_files']) {
				wof_pipeline_create(array(
					'type' => 'meta_files',
					'repo' => $pipeline['repo']
				));
			}
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

			wof_repo_set_status($pipeline['repo'], "pipeline {$pipeline['id']} error", $debug);
		}
	}

	########################################################################

	function wof_pipeline_run_script($pipeline, $script, $args = '') {

		$pipeline_id = intval($pipeline['id']);
		$dir = "{$GLOBALS['cfg']['wof_pending_dir']}pipeline/$pipeline_id/";

		$stdout_path = "{$dir}stdout.log";
		$stderr_path = "{$dir}stderr.log";

		$pipes = ">$stdout_path 2>$stderr_path";

		$cmd = "$script $args $pipes";

		$output = array();
		$exit_code = -1;
		exec($cmd, $output, $exit_code);

		$stdout = file_get_contents($stdout_path);
		$stderr = file_get_contents($stderr_path);
		$ok = ($exit_code == 0) ? 1 : 0;

		$stdout_ret = $stdout;
		$stderr_ret = $stderr;
		if (strlen($stdout) > 1024) {
			$stdout_ret = substr($stdout, -1024, 1024);
		}
		if (strlen($stderr) > 1024) {
			$stderr_ret = substr($stderr, -1024, 1024);
		}

		$ret = array(
			'ok' => $ok,
			'cwd' => $dir,
			'cmd' => $cmd,
			'stdout' => trim($stdout_ret),
			'stderr' => trim($stderr_ret)
		);

		if (! $ok) {
			$sep = ($ret['stdout'] && $ret['stderr']) ? "\n" : '';
			$ret['error'] = "{$ret['stdout']}{$sep}{$ret['stderr']}";

			$s3_dir = "{$GLOBALS['cfg']['wof_pipeline_base_path']}$pipeline_id/";
			$stdout_s3_path = "{$s3_dir}stdout.log";
			$stderr_s3_path = "{$s3_dir}stderr.log";
			$args = array('acl' => rawurlencode('public-read'));
			$rsp = wof_s3_put($stdout, $stdout_s3_path, $args);
			if (! $rsp['ok']) {
				error_log('PIPELINE FAILED AND I COULD NOT UPLOAD OUTPUT TO S3 EVERYTHING IS TERRIBLE');
			}
			$rsp = wof_s3_put($stderr, $stderr_s3_path, $args);
			if (! $rsp['ok']) {
				error_log('PIPELINE FAILED AND I COULD NOT UPLOAD STDERR TO S3 EVERYTHING IS TERRIBLE');
			}
		}

		unlink($stdout_path);
		unlink($stderr_path);

		return $ret;
	}

	########################################################################

	function wof_pipeline_validate(&$meta) {

		if ($meta['upload']) {

			$upload = $meta['upload'];
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
					$uploaded_meta = json_decode($json, 'as hash');
					$meta = array_merge($meta, $uploaded_meta);
				} else if (preg_match("/^$basename\/([^\/]+\.(geojson|csv))\$/", $name, $matches)) {
					$names[] = $matches[1];
				}
			}
			zip_close($fh);

			$meta['files'] = $names;
		}

		if (! $meta['type']) {
			return array(
				'ok' => 0,
				'error' => "Pipeline has no 'type'"
			);
		}

		$fn = "wof_pipeline_{$meta['type']}_repo";
		if (function_exists($fn)) {
			$rsp = $fn($meta);
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

		$fn = "wof_pipeline_{$meta['type']}_validate";
		if (function_exists($fn)) {
			$rsp = $fn($meta);
			if (! $rsp['ok']) {
				return $rsp;
			}
		}

		return array(
			'ok' => 1
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
				$file_contents = '';
				while ($bytes = zip_entry_read($entry)) {
					$file_contents .= $bytes;
				}
				$data[$name] = $file_contents;
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

		if ($GLOBALS['cfg']['wof_pipeline_verbose']) {
			echo "[pipeline $pipeline_id] $summary\n";
			if ($details) {
				echo "$details\n";
			}
		}

		chatterbox_dispatch(array(
			'pipeline' => $pipeline_id,
			'summary' => $summary,
			'details' => $details
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

		// Reload the pipeline, to get the current phase
		$rsp = wof_pipeline_get($pipeline_id);
		if (! $rsp['ok']) {
			return $rsp;
		}
		$pipeline = $rsp['pipeline'];

		if ($pipeline['phase'] == 'cancelled') {
			return array(
				'ok' => 0,
				'error' => 'Pipeline was cancelled.'
			);
		}

		if ($pipeline['phase'] != 'error') {
			$pipeline['meta']['last_phase'] = $pipeline['phase'];
		}
		$meta = json_encode($pipeline['meta']);

		$phase_esc = addslashes($phase);
		$now = date('Y-m-d H:i:s');

		$rsp = db_update('boundaryissues_pipeline', array(
			'meta' => $meta,
			'phase' => $phase_esc,
			'updated' => $now
		), "id = $pipeline_id");

		$slack_handle = trim($pipeline['meta']['slack_handle']);
		if (substr($slack_handle, 0, 1) != '@') {
			$slack_handle = "@$slack_handle";
		}

		$notification = '';
		if ($phase == 'error') {
			if ($pipeline['meta']['slack_handle']) {
				$notification = " $slack_handle";
			} else {
				$notification = ' <!here>';
			}
		} else if ($phase == 'success' ||
		           $phase == 'confirm') {
			if ($pipeline['meta']['slack_handle']) {
				$notification = " $slack_handle";
			}
		}

		wof_pipeline_log($pipeline_id, "Phase set to $phase");

		$extras = array();
		if ($phase == 'confirm' &&
		    $GLOBALS['cfg']['slack_bot_verification_token']) {
			$extras['attachments'] = array(
				array(
					'fallback' => 'You are unable to see the approval buttons.',
					'callback_id' => 'pipeline_' . $pipeline_id,
					'color' => '#FF0081',
					'attachment_type' => 'default',
					'actions' => array(
						array(
							'name' => 'confirmation',
							'text' => 'Confirm',
							'type' => 'button',
							'value' => 'confirm',
							'style' => 'primary'
						),
						array(
							'name' => 'confirmation',
							'text' => 'Cancel',
							'type' => 'button',
							'value' => 'cancel'
						)
					)
				)
			);
		}

		$host = gethostname();
		$host = explode(".", $host);
		$host = $host[0];

		slack_bot_msg("$phase: <{$pipeline['url']}|{$pipeline['filename']}> ({$pipeline['type']} $pipeline_id on $host)$notification", $extras);

		return $rsp;
	}

	########################################################################

	function wof_pipeline_cleanup($pipeline) {

		$meta = $pipeline['meta'];
		$zip_file = $pipeline['filename'];
		$files = array();

		$rsp = wof_pipeline_cleanup_file($pipeline, $zip_file);
		if ($rsp['ok']) {
			$files[] = $zip_file;
		}

		$rsp = wof_pipeline_cleanup_file($pipeline, 'meta.json');
		if ($rsp['ok']) {
			$files[] = 'meta.json';
		}

		if ($meta['files']) {
			foreach ($meta['files'] as $filename) {
				$rsp = wof_pipeline_cleanup_file($pipeline, $filename);
				if (! $rsp['ok']) {
					$files[] = $filename;
				}
			}
		}

		$pipeline_id = intval($pipeline['id']);
		$local_dir = "{$GLOBALS['cfg']['wof_pending_dir']}pipeline/$pipeline_id/";
		if (file_exists($local_dir)) {
			rmdir($local_dir);
		}

		$result = array(
			'ok' => 1,
			'files' => $files
		);
		wof_pipeline_log($pipeline_id, "Cleaned up files", $result);

		$repo_path = wof_pipeline_repo_path($pipeline);

		$rsp = git_execute($repo_path, "stash");
		wof_pipeline_log($pipeline['id'], "Stashed modifications from {$pipeline['repo']}", $rsp);

		$rsp = git_execute($repo_path, "checkout master");
		wof_pipeline_log($pipeline['id'], "Reset {$pipeline['repo']} to master branch", $rsp);

		return $result;
	}

	########################################################################

	function wof_pipeline_cleanup_file($pipeline, $filename) {
		$pipeline_id = intval($pipeline['id']);
		$remote_dir = "{$GLOBALS['cfg']['wof_pipeline_base_path']}$pipeline_id/";
		$remote_path = "$remote_dir$filename";
		$rsp = wof_s3_delete($remote_path);

		$local_dir = "{$GLOBALS['cfg']['wof_pending_dir']}pipeline/$pipeline_id/";
		$local_path = "$local_dir$filename";
		if (file_exists($local_path)) {
			unlink($local_path);
		}

		return $rsp;
	}

	########################################################################

	function wof_pipeline_next($verbose = false) {
		$rsp = db_fetch("
			SELECT *
			FROM boundaryissues_pipeline
			WHERE phase = 'pending'
			   OR phase = 'confirmed'
			   OR phase = 'retry'
			GROUP BY repo
			ORDER BY created
		");

		if (! $rsp['ok']) {
			return $rsp;
		}

		if (! $rsp['rows']) {
			if ($verbose) {
				echo "wof_pipeline_next: no pending pipelines found\n";
			}
			return array(
				'ok' => 1,
				'next' => array()
			);
		}

		$next = array();
		foreach ($rsp['rows'] as $pipeline) {

			$pipeline['meta'] = json_decode($pipeline['meta'], 'as hash');

			if ($verbose) {
				echo "wof_pipeline_next: checking pipeline {$pipeline['id']}\n";
			}

			if ($pipeline['phase'] == 'pending' &&
			    ! wof_repo_is_ready($pipeline['repo'])) {
				if ($verbose) {
					echo "wof_pipeline_next: repo {$pipeline['repo']} is not ready\n";
				}
				continue;
			}

			$host = gethostname();
			$host = explode(".", $host);
			$host = $host[0];

			// Make sure pipelines run each phase on the same host
			if ($pipeline['meta']['host']) {
				if ($pipeline['meta']['host'] != $host) {
					if ($verbose) {
						echo "wof_pipeline_next: this should run on host {$pipeline['meta']['repo']} not $host\n";
					}
					continue;
				}
			} else {
				$pipeline['meta']['host'] = $host;
				$meta = json_encode($pipeline['meta']);
				$esc_meta = addslashes($meta);
				$pipeline_id = intval($pipeline['id']);
				$rsp = db_update('boundaryissues_pipeline', array(
					'meta' => $esc_meta
				), "id = $pipeline_id");
				if (! $rsp['ok']) {
					return $rsp;
				}
			}

			wof_repo_set_status($pipeline['repo'], "pipeline {$pipeline['id']}", 'status updated after choosing next pipeline');

			$next[] = $pipeline;
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
