<?php
	loadlib('wof_save');

	function api_wof_upload() {

		if (! $_FILES["upload_file"]) {
			api_output_error(400, 'Please include an upload_file.');
		}

		$rsp = wof_save($_FILES["upload_file"]["tmp_name"]);
		if (! $rsp['ok'] ||
		    ! $rsp['geojson_url']) {
			$error = $rsp['error'] || 'Upload failed for some reason.';
			api_output_error(400, $error);
		}
		api_output_ok($rsp);
	}

	function api_wof_save() {

		$geojson = post_str('geojson');
		$step = post_str('step');
		$wof_id = post_int64('wof_id');
		$new_wof_record = post_bool('new_wof_record');

		if (! $geojson) {
			api_output_error(400, "Please include a 'geojson' parameter.");
		}

		if (! $step) {

			// If no step is specified, try to save in one shot
			$rsp = wof_save_string($geojson);

		} else if ($step == 'to_geojson') {

			// Start by generating IDs and encoding from the GeoJSON service
			$rsp = wof_save_to_geojson($geojson);

		} else if ($step == 'to_github') {

			// Save the GeoJSON string to GitHub
			if (! $wof_id) {
				api_output_error(400, "Please include a 'wof_id' parameter.");
			}
			$rsp = wof_save_to_github($wof_id, $geojson, $new_wof_record);

		} else if ($step == 'to_disk') {

			// Save the GeoJSON string to disk
			if (! $wof_id) {
				api_output_error(400, "Please include a 'wof_id' parameter.");
			}
			$rsp = wof_save_to_disk($wof_id, $geojson);

		}

		if (! $rsp['ok']) {
			$error = $rsp['error'] || 'Saving failed for some reason.';
			api_output_error(400, $error);
		}
		api_output_ok($rsp);
	}

	function api_wof_pip() {

		if (! isset($_POST['latitude']) ||
		    ! isset($_POST['longitude'])) {
			api_output_error(400, "Please include a 'latitude' and 'longitude'.");
		}

		$query = http_build_query(array(
			'latitude' => post_float('latitude'),
			'longitude' => post_float('longitude')
		));

		$rsp = http_get("http://localhost:8080/?$query");
		if (! $rsp['ok']) {
			api_output_error(400, 'Error talking to the PIP service.');
		}

		$results = json_decode($rsp['body']);
		api_output_ok(array(
			'results' => $results
		));
	}

	function api_wof_encode() {

		$geojson = post_str('geojson');
		if (! $geojson) {
			api_output_error(400, "Please include 'geojson' param.");
		}

		$rsp = wof_utils_encode($geojson);
		if (! $rsp['ok']) {
			$error = $rsp['error'] || 'Error talking to the GeoJSON service';
			api_output_error(400, $error);
		}

		api_output_ok(array(
			'encoded' => $rsp['encoded']
		));
	}
