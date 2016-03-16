<?php
	loadlib('wof_utils');
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

		$rsp = wof_utils_save($geojson);

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

		$rsp = wof_save_to_github($geojson, $geojson_data);
		if (! $rsp['ok']) {
			$rsp['error'] = "Error saving to GitHub: {$rsp['error']}";
			return $rsp;
		}

		register_shutdown_function('wof_utils_update_elasticsearch', $wof_id);

		return array(
			'ok' => 1,
			'wof_id' => $wof_id
		);
	}

	function wof_save_to_github($geojson, $geojson_data) {

		// Get the GitHub oauth token
		$rsp = github_users_curr_oauth_token();
		if (! $rsp['ok']) {
			return $rsp;
		}

		$oauth_token = $rsp['oauth_token'];
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
