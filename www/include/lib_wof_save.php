<?php
	loadlib('wof_utils');
	loadlib('wof_geojson');
	loadlib('wof_elasticsearch');
	loadlib('artisanal_integers');
	loadlib("github_api");
	loadlib("github_users");
	loadlib("offline_tasks");
	loadlib("offline_tasks_gearman");

	function wof_save_file($input_path) {

		$filename = basename($input_path);

		if (! file_exists($input_path)){
			return array(
				'ok' => 0,
				'error' => "$filename not found."
			);
		}

		$geojson = file_get_contents($input_path);
		$rsp = wof_save_feature($geojson);

		// Clean up the uploaded tmp file
		if (is_uploaded_file($input_path)) {
			unlink($input_path);
		}

		// It worked \o/
		return $rsp;

	}

	function wof_save_feature($geojson) {

		$geojson_data = json_decode($geojson, true);
		if (! $geojson_data) {
			return array(
				'ok' => 0,
				'error' => "Could not parse input 'geojson' param."
			);
		}

		// Validation happens on the GeoJSON service side of things
		$rsp = wof_geojson_save($geojson);

		if (! $rsp['ok']) {
			$rsp['error'] = "Error saving via GeoJSON service: {$rsp['error']}";
			return $rsp;
		}

		$geojson = $rsp['geojson'];
		$geojson_data = json_decode($geojson, true);
		if (! $geojson_data) {
			return array(
				'ok' => 0,
				'error' => 'GeoJSON was saved to disk, but it seems to be unparseable.'
			);
		}

		$wof_id = $geojson_data['properties']['wof:id'];

		$rsp = offline_tasks_schedule_task('commit', array(
			'wof_id' => $wof_id,
			'user_id' => $GLOBALS['cfg']['user']['id']
		));
		if (! $rsp['ok']) {
			return $rsp;
		}

		$rsp = offline_tasks_schedule_task('index', array(
			'geojson_data' => $geojson_data
		));
		if (! $rsp['ok']) {
			return $rsp;
		}

		return array(
			'ok' => 1,
			'wof_id' => $wof_id,
			'geojson' => $geojson_data
		);
	}

	function wof_save_batch($batch_ids, $batch_properties) {
		$errors = array();
		$saved = array();
		foreach ($batch_ids as $wof_id) {
			$geojson_path = wof_utils_id2abspath($GLOBALS['cfg']['wof_data_dir'], $wof_id);
			if (file_exists($geojson_path)) {
				$existing_geojson = file_get_contents($geojson_path);
				$existing_feature = json_decode($existing_geojson, true);
				$existing_feature['properties'] = array_merge(
					$existing_feature['properties'],
					$batch_properties
				);
				$updated_geojson = json_encode($existing_feature);
				$rsp = wof_save_feature($updated_geojson);

				if (! $rsp['ok']) {
					$errors[$wof_id] = $rsp['error'];
				} else {
					$saved[] = $rsp['geojson'];
				}
			} else {
				$errors[$wof_id] = "Could not find WOF GeoJSON file.";
			}
		}

		if (! $errors) {
			return array(
				'ok' => 1,
				'properties' => $batch_properties,
				'saved' => $saved
			);
		} else {
			return array(
				'ok' => 0,
				'properties' => $batch_properties,
				'error' => 'Error batch saving WOF documents.',
				'details' => $errors,
				'saved' => $saved
			);
		}
	}

	function wof_save_to_github($wof_id, $oauth_token = null) {

		if (! $oauth_token) {
			// Get the GitHub oauth token if none was specified
			$rsp = github_users_curr_oauth_token();
			if (! $rsp['ok']) {
				return $rsp;
			}
			$oauth_token = $rsp['oauth_token'];
		}

		$rel_path = wof_utils_id2relpath($wof_id);
		$abs_path = wof_utils_id2abspath(
			$GLOBALS['cfg']['wof_data_dir'],
			$wof_id
		);

		$geojson_str = file_get_contents($abs_path);
		$feature = json_decode($geojson_str, "as hash");

		$owner = $GLOBALS['cfg']['wof_github_owner'];
		$repo = $GLOBALS['cfg']['wof_github_repo'];
		$wof_name = $feature['properties']['wof:name'];

		$github_path = "repos/$owner/$repo/contents/data/$rel_path";
		$filename = basename($rel_path);

		$args = array(
			'path' => "data/$rel_path",
			'content' => base64_encode($geojson_str)
		);

		// If the file exists, find its SHA hash
		$rsp = github_api_call('GET', $github_path, $oauth_token);
		if ($rsp['ok']) {
			$what_happened = 'updated';
			$args['sha'] = $rsp['rsp']['sha'];
		} else {
			$what_happened = 'created';
		}

		$args['message'] = "Boundary Issues $what_happened $filename ($wof_name)";

		// Save to GitHub
		$rsp = github_api_call('PUT', $github_path, $oauth_token, $args);
		if (! $rsp['ok']) {
			$rsp['error'] = "HTTP PUT to '$github_path': {$rsp['error']}";
			return $rsp;
		}

		return array(
			'ok' => 1,
			'url' => $rsp['rsp']['content']['_links']['html']
		);
	}
