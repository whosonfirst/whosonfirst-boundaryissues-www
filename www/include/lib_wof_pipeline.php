<?php

	// The 'photos' prefix here is temporary until we sort out the
	// requisite S3 permissions. (20170530/dphiffer)
	$GLOBALS['cfg']['wof_pipeline_base_path'] = 'photos/pipeline/';

	loadlib('wof_s3');
	loadlib('slack_bot');

	########################################################################

	function wof_pipeline_create($upload, $meta) {

		$now = date('Y-m-d H:i:s');
		$meta_json = json_encode($meta);
		$meta_json_esc = addslashes($meta_json);

		$filename = $_FILES["upload_file"]['name'];
		if (! preg_match('/^[a-zA-Z0-9_-]+\.zip$/', $filename)) {
			return array(
				'ok' => 0,
				'error' => 'Invalid filename. Please use only alphanumerics, _ (underbar), or - (hyphen).'
			);
		}
		$filename_esc = addslashes($filename);

		$rsp = db_insert('boundaryissues_pipeline', array(
			'filename' => $filename_esc,
			'type' => $meta['type'],
			'meta' => $meta_json_esc,
			'phase' => 'pending',
			'created' => $now,
			'updated' => $now
		));
		if (! $rsp['ok']) {
			return $rsp;
		}

		$pipeline_id = $rsp['insert_id'];
		wof_pipeline_log($pipeline_id, "Created pipeline $pipeline_id", $meta);
		slack_bot_msg("$filename ({$meta['type']} pipeline $pipeline_id): pending");

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
		wof_pipeline_log($pipeline_id, "Uploaded {$upload['name']}", $rsp);

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
			wof_pipeline_log($pipeline_id, "Uploaded $file", $rsp);
			if (! $rsp['ok']) {
				return $rsp;
			}
		}

		return array(
			'ok' => 1,
			'uploaded' => $meta['files']
		);
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
			wof_pipeline_log($pipeline_id, "Downloaded $file", $rsp);
			if (! $rsp['ok']) {
				return $rsp;
			}
			file_put_contents("$dir$file", $rsp['body']);
		}

		return array(
			'ok' => 1,
			'dir' => $dir,
			'files' => $pipeline['meta']['files']
		);
	}

	########################################################################

	function wof_pipeline_validate_zip($upload) {

		$names = array();
		$err = array();
		$basename = basename($upload['name'], '.zip');

		$fh = zip_open($upload['tmp_name']);

		while ($entry = zip_read($fh)) {
			$name = zip_entry_name($entry);
			if ($name == "$basename/meta.json") {
				$json = zip_entry_read($entry);
				$meta = json_decode($json, 'as hash');
			} else if (preg_match("/^$basename\/([^\/]+\.geojson)\$/", $name, $matches)) {
				$names[] = $matches[1];
			}
		}
		$meta['files'] = $names;

		if (! $meta) {
			$err[] = 'No meta.json file found';
		} else {
			if (! $meta['type']) {
				$err[] = "meta.json has no 'type' property";
			} else {
				$fn = "wof_pipeline_validate_{$meta['type']}_zip";
				if (function_exists($fn)) {
					$rsp = $fn($meta, $names);
					$err = array_merge($err, $rsp);
				}
			}
		}

		zip_close($fh);

		if ($err) {
			return array('ok' => 0, 'errors' => $err);
		} else {
			return array('ok' => 1, 'meta' => $meta);
		}
	}

	########################################################################

	function wof_pipeline_validate_neighbourhood_zip($meta, $names) {
		$err = array();
		if (! $meta['parent_id']) {
			$err[] = "No 'parent_id' property found in meta.json";
		}
		return $err;
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
		$phase_esc = addslashes($phase);
		$now = date('Y-m-d H:i:s');

		$rsp = db_update('boundaryissues_pipeline', array(
			'phase' => $phase_esc,
			'updated' => $now
		), "id = $pipeline_id");

		wof_pipeline_log($pipeline_id, "Phase set to $phase", $rsp);
		slack_bot_msg("{$pipeline['filename']} ({$pipeline['type']} pipeline $pipeline_id): $phase");

		return $rsp;
	}

	########################################################################

	function wof_pipeline_cleanup($pipeline) {

		$meta = $pipeline['meta'];
		$zip_file = $pipeline['filename'];

		$rsp = wof_pipeline_cleanup_file($pipeline, $zip_file);
		if (! $rsp['ok']) {
			return $rsp;
		}

		$rsp = wof_pipeline_cleanup_file($pipeline, 'meta.json');
		if (! $rsp['ok']) {
			return $rsp;
		}

		foreach ($meta['files'] as $filename) {
			$rsp = wof_pipeline_cleanup_file($pipeline, $filename);
			if (! $rsp['ok']) {
				return $rsp;
			}
		}

		$pipeline_id = intval($pipeline['id']);
		$local_dir = "{$GLOBALS['cfg']['wof_pending_dir']}/pipeline/$pipeline_id/";
		rmdir($local_dir);

		return array(
			'ok' => 1
		);
	}

	########################################################################

	function wof_pipeline_cleanup_file($pipeline, $filename) {
		$pipeline_id = intval($pipeline['id']);
		$remote_dir = "{$GLOBALS['cfg']['wof_pipeline_base_path']}$pipeline_id/";
		$remote_path = "$remote_dir$filename";
		$rsp = wof_s3_delete($remote_path);
		wof_pipeline_log($pipeline_id, "Deleted $filename", $rsp);

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
			ORDER BY created
			LIMIT 1
		");
		if (! $rsp['ok']) {
			return $rsp;
		}

		if (! $rsp['rows']) {
			return array(
				'ok' => 1,
				'pipeline' => null
			);
		}

		$pipeline = $rsp['rows'][0];

		$pipeline['meta'] = json_decode($pipeline['meta'], 'as hash');
		return array(
			'ok' => 1,
			'pipeline' => $pipeline
		);
	}

	# the end
