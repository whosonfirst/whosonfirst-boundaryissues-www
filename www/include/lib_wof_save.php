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

	function wof_save_string($input_str) {

		$rsp = wof_save_encode_geojson($input_str);
		if (! $rsp['ok']) {
			$rsp['error'] = $rsp['error'] ? $rsp['error'] : 'Error encoding GeoJSON.';
			return $rsp;
		}

		$geojson = $rsp['geojson'];
		$geojson_data = $rsp['geojson_data'];
		$is_new_record = $rsp['is_new_record'];

		$rsp = wof_save_to_github($geojson, $geojson_data, $is_new_record);
		if (! $rsp['ok']) {
			$rsp['error'] = $rsp['error'] ? $rsp['error'] : 'Error saving to GitHub.';
			return $rsp;
		}

		$rsp = wof_save_to_disk($geojson, $geojson_data);
		if (! $rsp['ok']) {
			$rsp['error'] = $rsp['error'] ? $rsp['error'] : 'Error saving to disk.';
			return $rsp;
		}

		return array(
			'ok' => 1,
			'wof_id' => $geojson_data['properties']['wof:id']
		);
	}

	function wof_save_encode_geojson($geojson) {

		$is_new_record = false;

		// Parse the string into a data structure
		$geojson_data = json_decode($geojson, true);
		if (! $geojson_data){
			return array(
				'ok' => 0,
				'error' => "Could not parse input; invalid JSON."
			);
		}

		if (! $geojson_data['properties']) {
			$geojson_data['properties'] = array();
		}

		if (! $geojson_data['properties']['wof:id'] ||
		    ! is_numeric($geojson_data['properties']['wof:id'])) {

			$is_new_record = true;

			// Mint a new artisanal integer wof:id
			$rsp = artisanal_integers_create();
			if (! $rsp['ok']) {
				$rsp['error'] = $rsp['error'] ? $rsp['error'] : 'Could not create a new artisanal integer.';
				return $rsp;
			}

			// Write WOF ID to the top-level 'id' and 'properties/wof:id'
			$geojson_data['id'] = intval($rsp['integer']);
			$geojson_data['properties']['wof:id'] = intval($rsp['integer']);
		}

		// Make sure parent_id is an integer
		$geojson_data['properties']['wof:parent_id'] = intval($geojson_data['properties']['wof:parent_id']);

		$rsp = wof_utils_encode(json_encode($geojson_data));
		if (! $rsp['ok']) {
			$rsp['error'] = $rsp['error'] ? $rsp['error'] : 'Error talking to the GeoJSON pony.';
			return $rsp;
		}

		return array(
			'ok' => 1,
			'geojson' => $rsp['encoded'],
			'geojson_data' => $geojson_data,
			'is_new_record' => $is_new_record
		);
	}

	function wof_save_to_github($geojson, $geojson_data, $is_new_record) {

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

		$what_happened = ($is_new_record) ? 'created' : 'updated';
		$filename = basename($path);

		$args = array(
			'path' => $path,
			'message' => "Boundary Issues $what_happened $filename ($wof_name)",
			'content' => base64_encode($geojson)
		);

		// If the file exists, find its SHA hash
		if (! $is_new_record) {
			$rsp = github_api_call('GET', $github_path, $oauth_token);
			if ($rsp['ok']) {
				$args['sha'] = $rsp['rsp']['sha'];
			}
		}

		// Save to GitHub
		$rsp = github_api_call('PUT', $github_path, $oauth_token, $args);
		if (! $rsp['ok']) {
			return $rsp;
		}

		return array(
			'ok' => 1,
			'url' => $rsp['rsp']['content']['_links']['html']
		);
	}

	function wof_save_to_disk($geojson, $geojson_data) {

		$wof_id = $geojson_data['properties']['wof:id'];
		$path = wof_utils_id2abspath(
			$GLOBALS['cfg']['wof_data_dir'],
			$wof_id
		);

		try {
			$dir = dirname($path);
			if (! file_exists($dir)) {
				mkdir($dir, 0775, true);
			}
			file_put_contents($path, $geojson);
		} catch(Exception $e) {
			return array(
				'ok' => 0,
				'error' => $e->getMessage()
			);
		}

		return array(
			'ok' => 1,
			'path' => $path
		);
	}
