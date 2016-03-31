<?php
	loadlib('wof_utils');
	loadlib('wof_geojson');
	loadlib('wof_elasticsearch');
	loadlib('artisanal_integers');
	loadlib("github_api");
	loadlib("github_users");

	function wof_save_file($input_path) {

		$filename = basename($input_path);

		if (! file_exists($input_path)){
			return array(
				'ok' => 0,
				'error' => "$filename not found."
			);
		}

		$geojson = file_get_contents($input_path);
		$rsp = wof_save_string($geojson);

		// Clean up the uploaded tmp file
		if (is_uploaded_file($input_path)) {
			unlink($input_path);
		}

		// It worked \o/
		return $rsp;

	}

	function wof_save_string($geojson) {

		if (! $GLOBALS['gearman_client']) {
			$gearman_client = new GearmanClient();
			$gearman_client->addServer();
			$GLOBALS['gearman_client'] = $gearman_client;
		} else {
			$gearman_client = $GLOBALS['gearman_client'];
		}

		$geojson_data = json_decode($geojson, true);
		if (! $geojson_data) {
			return array(
				'ok' => 0,
				'error' => "Could not parse input 'geojson' param."
			);
		}

		// Since the editor currently doesn't support *all* properties, we'll
		// grab the existing file and merge in our changes on top of it. That way
		// anything that isn't represented in the editor won't be lost.
		// (20160316/dphiffer)
		if ($geojson_data['properties']['wof:id']) {
			$path = wof_utils_id2abspath(
				$GLOBALS['cfg']['wof_data_dir'],
				$geojson_data['properties']['wof:id']
			);
			if (file_exists($path)) {
				$existing_geojson = file_get_contents($path);
				$existing_geojson_data = json_decode($existing_geojson, true);
				$geojson_data['properties'] = array_merge(
					$existing_geojson_data['properties'],
					$geojson_data['properties']
				);
				$geojson = json_encode($geojson_data);
			}
		}

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

		$github_details = serialize(array(
			'geojson' => $geojson,
			'geojson_data' => $geojson_data,
			'user_id' => $GLOBALS['cfg']['user']['id']
		));
		//dbug('gearman_client: save_to_github');
		$gearman_client->doBackground("save_to_github", $github_details);

		$search_details = serialize(array(
			'geojson_data' => $geojson_data
		));
		//dbug('gearman_client: update_search_index');
		$gearman_client->doBackground("update_search_index", $search_details);

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
				$rsp = wof_save_string($updated_geojson);

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

	function wof_save_to_github($geojson, $geojson_data, $oauth_token = null) {

		if (! $oauth_token) {
			// Get the GitHub oauth token if none was specified
			$rsp = github_users_curr_oauth_token();
			if (! $rsp['ok']) {
				return $rsp;
			}
			$oauth_token = $rsp['oauth_token'];
		}

		$owner = $GLOBALS['cfg']['wof_github_owner'];
		$repo = $GLOBALS['cfg']['wof_github_repo'];
		$wof_id = $geojson_data['properties']['wof:id'];
		$wof_name = $geojson_data['properties']['wof:name'];

		$path = 'data/' . wof_utils_id2relpath($wof_id);
		$github_path = "repos/$owner/$repo/contents/$path";
		$filename = basename($path);

		$args = array(
			'path' => $path,
			'content' => base64_encode($geojson)
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
