<?php

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
		wof_pipeline_log($pipeline_id, "Created pipeline $pipeline_id", $meta_json);

		return array(
			'ok' => 1,
			'pipeline_id' => $pipeline_id
		);
	}

	########################################################################

	function wof_pipeline_upload_files($upload, $meta, $pipeline_id) {

		// The 'photos' prefix here is temporary until we sort out the
		// requisite S3 permissions. (20170530/dphiffer)
		$dir = "photos/pipeline/$pipeline_id";

		foreach ($meta['files'] as $file) {
			$path = "$dir/$file";
			$data = wof_pipeline_read_zip_file($upload, $file);
			$rsp = wof_s3_put_data($data, $path);
			$rsp = array('ok' => 1);
			wof_pipeline_log($pipeline_id, "Uploaded $file", json_encode($rsp));
			if (! $rsp['ok']) {
				api_output_ok($rsp);
			}
		}

		return array(
			'ok' => 1,
			'uploaded' => $meta['files']
		);
	}

	########################################################################

	function wof_pipeline_validate_zip($upload) {

		$names = array();
		$err = array();
		$basename = basename($upload['name'], '.zip');

		$rsp = wof_pipeline_zip_file_handle($upload['tmp_name']);
		if (! $rsp['ok']) {
			return $rsp;
		}
		$fh = $rsp['handle'];

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

		if ($err) {
			return array('ok' => 0, 'errors' => $err);
		} else {
			return array('ok' => 1, 'meta' => $meta);
		}
	}

	########################################################################

	function wof_pipeline_zip_file_handle($filename) {
		if (! $GLOBALS['cfg']["zip_file_$filename"]) {
			$GLOBALS['cfg']["zip_file_$filename"] = zip_open($filename);
		}
		if (! is_resource($GLOBALS['cfg']["zip_file_$filename"])) {
			return array(
				'ok' => 0,
				'error' => "Could not open zip file $filename"
			);
		}
		return array(
			'ok' => 1,
			'handle' => $GLOBALS['cfg']["zip_file_$filename"]
		);
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

	function wof_pipeline_read_zip_file($upload, $zipped_filename) {

		$basename = basename($upload['name'], '.zip');

		$rsp = wof_pipeline_zip_file_handle($upload['tmp_name']);
		if (! $rsp['ok']) {
			return $rsp;
		}
		$fh = $rsp['handle'];

		while ($entry = zip_read($fh)) {
			$name = zip_entry_name($entry);
			if ($name == "$basename/$zipped_filename") {
				$data = zip_entry_read($entry);
				return array(
					'ok' => 1,
					'data' => $data
				);
			}
		}

		return array(
			'ok' => 0,
			'error' => "Could not read $zipped_filename from {$upload['name']} ({$upload['tmp_name']})"
		);
	}

	########################################################################

	function wof_pipeline_log($pipeline_id, $summary, $details) {
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

	# the end
