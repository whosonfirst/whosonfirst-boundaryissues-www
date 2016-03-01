<?php
	loadlib('wof_utils');
	loadlib('artisanal_integers');

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

		$geojson_path = wof_utils_id2abspath(
			$GLOBALS['cfg']['wof_data_dir'],
			$geojson_data['properties']['wof:id']
		);

		$geojson_dir = dirname($geojson_path);

		# Because the following emit E_WARNINGS when things don't
		# work and that makes the JSON returned to the server sad
		# (20160226/thisisaaronland)

		$reporting_level = error_reporting();
		error_reporting(E_ERROR);

		// Create the directory structure, if it doesn't exist
		if (! file_exists($geojson_dir)){

			if (! mkdir($geojson_dir, 0775, true)){
				error_log("failed to mkdir {$geojson_dir}");
				api_output_error(500, "Argh! The server was unable to create your GeoJSON file");
			}		    
		}

		$is_update = file_exists($geojson_path);

		// Write the file

		if (! file_put_contents($geojson_path, $geojson)){
			api_output_error(500, "Argh! The server was unable to create your GeoJSON file");
		}

		error_reporting($reporting_level);

		$geojson_url = '/data/' . wof_utils_id2relpath($geojson_data['id']);

		// It worked \o/
		return array(
			'ok' => 1,
			'id' => $geojson_data['id'],
			'geojson_url' => $geojson_url
		);
	}
