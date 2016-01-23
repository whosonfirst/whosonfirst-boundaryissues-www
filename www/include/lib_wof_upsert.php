<?php
	loadlib('wof_data');
	loadlib('artisanal_integers');

	function wof_upsert($input_path){

		$filename = basename($input_path);

		if (!file_exists($input_path)){
			return array(
				'ok' => 0,
				'error' => "$filename not found."
			);
		}

		// This is a kludgy way to handle the incoming data. We will improve it.
		$geojson = file_get_contents($input_path);
		$geojson_data = json_decode($geojson, true);
		if (!$geojson_data){
			return array(
				'ok' => 0,
				'error' => "Could not parse $filename; invalid GeoJSON."
			);
		}

		if (!$geojson_data['properties']){
			$geojson_data['properties'] = array();
		}

		if (!$geojson_data['properties']['wof:id']){
			$rsp = artisanal_integers_create();

			if (!$rsp['ok']){
				return $rsp;
			}

			$geojson_data['properties']['wof:id'] = $rsp['integer'];

			// This should use an API to validate & pretty print GeoJSON properties
			$geojson = json_encode($geojson_data);
		}

		// Figure out where we're going to put the incoming file
		$geojson_path = wof_data_getpath($geojson_data['properties']['wof:id']);
		$geojson_dir = dirname($geojson_path);

		// Create the directory structure, if it doesn't exist
		if (!file_exists($geojson_dir)){
			mkdir($geojson_dir, 0775, true);
		}

		// Write the file
		file_put_contents($geojson_path, $geojson);

		// Clean up the uploaded tmp file
		if (is_uploaded_file($input_path)) {
			unlink($input_path);
		}

		// It worked \o/
		return array(
			'ok' => 1,
			'path' => $geojson_path
		);

	}
