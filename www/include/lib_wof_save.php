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
				'error_msg' => "$filename not found."
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
		
		$new_wof_record = false;
		
		$geojson_data = json_decode($geojson, true);
		if (! $geojson_data){
			return array(
				'ok' => 0,
				'error_msg' => "Could not parse input; invalid JSON."
			);
		}

		if (! $geojson_data['properties']){
			$geojson_data['properties'] = array();
		}

		if (! $geojson_data['properties']['wof:id'] ||
		    ! is_numeric($geojson_data['properties']['wof:id'])){

			$new_wof_record = true;

			// Mint a new artisanal integer wof:id
			$rsp = artisanal_integers_create();
			if (! $rsp['ok']){
				// Weird, this message doesn't seem to make it back to the AJAX requester
				$rsp['error_msg'] = 'Could not load artisanal integer.';
				return $rsp;
			}

			// Write WOF ID to the top-level 'id' and 'properties/wof:id'
			$geojson_data['id'] = intval($rsp['integer']);
			$geojson_data['properties']['wof:id'] = intval($rsp['integer']);
		}

		// Send GeoJSON to Python script to get prettied up
		$rsp = http_post('http://localhost:5000/geojson-encode', array(
			'geojson' => json_encode($geojson_data)
		));
		if (! $rsp['ok']) {
			$rsp['error_msg'] = 'Error encoding GeoJSON.';
			return $rsp;
		}
		$geojson = $rsp['body'];

		// Figure out where we're going to put the incoming file

		$geojson_abs_path = wof_utils_id2abspath(
			$GLOBALS['cfg']['wof_data_dir'],
			$geojson_data['properties']['wof:id']
		);
		
		$geojson_rel_path = wof_utils_id2relpath(
			$geojson_data['properties']['wof:id']
		);

		# Because the following emit E_WARNINGS when things don't
		# work and that makes the JSON returned to the server sad
		# (20160226/thisisaaronland)

		$reporting_level = error_reporting();
		error_reporting(E_ERROR);

		$rsp = github_users_curr_oauth_token();
		if (! $rsp['ok']) {
			return $rsp;
		}

		$oauth_token = $rsp['oauth_token'];
		$owner = $GLOBALS['cfg']['wof_github_owner'];
		$repo = $GLOBALS['cfg']['wof_github_repo'];

		$path = "data/$geojson_rel_path";
		$what_happened = ($new_wof_record) ? 'created' : 'updated';
		$filename = basename($path);
		$wof_name = $geojson_data['properties']['wof:name'];

		$args = array(
			'path' => $path,
			'message' => "Boundary Issues $what_happened $filename ($wof_name)",
			'content' => base64_encode($geojson)
		);

		// If the file exists, find its SHA hash
		$rsp = github_api_call('GET', "repos/$owner/$repo/contents/$path", $oauth_token);
		if ($rsp['ok']) {
			$args['sha'] = $rsp['rsp']['sha'];
		}

		$rsp = github_api_call('PUT', "repos/$owner/$repo/contents/$path", $oauth_token, $args);

		if (! $rsp['ok']) {
			api_output_error(500, "Unable to update GeoJSON file.");
		}

		// Pull the new changes from GitHub
		$data_dir = escapeshellarg($GLOBALS['cfg']['wof_data_dir']);
		exec("cd $data_dir && /usr/bin/git pull origin master &");

		error_reporting($reporting_level);

		$geojson_url = "/data/$geojson_rel_path";

		// It worked \o/
		return array(
			'ok' => 1,
			'id' => $geojson_data['id'],
			'geojson_url' => $geojson_url
		);
	}
