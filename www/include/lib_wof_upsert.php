<?php
	loadlib('wof_utils');
	loadlib('artisanal_integers');

	function wof_upsert($input_path){

		$filename = basename($input_path);
		$is_update = false;

		if (! file_exists($input_path)){
			return array(
				'ok' => 0,
				'error_msg' => "$filename not found."
			);
		}

		// This is a kludgy way to handle the incoming data. We will improve it.
		$geojson = file_get_contents($input_path);
		$geojson_data = json_decode($geojson, true);
		if (! $geojson_data){
			return array(
				'ok' => 0,
				'error_msg' => "Could not parse $filename; invalid GeoJSON."
			);
		}

		if (! $geojson_data['properties']){
			$geojson_data['properties'] = array();
		}

		if (! $geojson_data['properties']['wof:id'] ||
		    ! is_numeric($geojson_data['properties']['wof:id'])){
			$rsp = artisanal_integers_create();

			if (! $rsp['ok']){
				// Weird, this message doesn't seem to make it back to the AJAX requester
				$rsp['error_msg'] = 'Could not load artisanal integer.';
				return $rsp;
			}

			// Write WOF ID to the top-level 'id' and 'properties/wof:id'
			$geojson_data['id'] = intval($rsp['integer']);
			$geojson_data['properties']['wof:id'] = intval($rsp['integer']);

			// TODO: wrap this in a feature flag

			// Send GeoJSON to Python script to get prettied up
			$rsp = http_post('http://localhost:5000/geojson-encode', array(
				'geojson' => json_encode($geojson_data)
			));
			if (! $rsp['ok']) {
				$rsp['error_msg'] = 'Error encoding GeoJSON.';
				return $rsp;
			}
			$geojson = $rsp['body'];
		}

		// Figure out where we're going to put the incoming file
		$geojson_path = wof_utils_id2abspath(
			$GLOBALS['cfg']['wof_data_dir'],
			$geojson_data['properties']['wof:id']
		);
		if (file_exists($geojson_path)){
			$is_update = true;
		}
		$geojson_dir = dirname($geojson_path);

		// Create the directory structure, if it doesn't exist
		if (! file_exists($geojson_dir)){
			mkdir($geojson_dir, 0775, true);
		}

		// Write the file
		file_put_contents($geojson_path, $geojson);

		// Clean up the uploaded tmp file
		if (is_uploaded_file($input_path)) {
			unlink($input_path);
		}

		$geojson_url = '/data/' . wof_utils_id2relpath($geojson_data['id']);

		// It worked \o/
		return array(
			'ok' => 1,
			'id' => $geojson_data['id'],
			'is_update' => $is_update,
			'geojson_url' => $geojson_url
		);

	}
