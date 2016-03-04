<?php
	loadlib('wof_save');

	function api_wof_upload() {

		if (! $_FILES["upload_file"]) {
			api_output_error(400, 'Please include an upload_file.');
		}

		$rsp = wof_save($_FILES["upload_file"]["tmp_name"]);
		if (! $rsp['ok'] ||
		    ! $rsp['geojson_url']) {
			$error = $rsp['error'] ? $rsp['error'] : 'Upload failed for some reason.';
			api_output_error(400, $error);
		}
		api_output_ok($rsp);
	}

	function api_wof_save() {

		$geojson = post_str('geojson');
		if (! $geojson) {
			api_output_error(400, "Please include a 'geojson' parameter.");
		}

		$rsp = wof_save_string($geojson);

		if (! $rsp['ok']) {
			$error = $rsp['error'] ? $rsp['error'] : 'Error saving WOF record.';
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
			$error = $rsp['error'] ? $rsp['error'] : 'Error talking to the PIP service.';
			api_output_error(400, $error);
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
			$error = $rsp['error'] ? $rsp['error'] : 'Error talking to the GeoJSON service';
			api_output_error(400, $error);
		}

		api_output_ok(array(
			'encoded' => $rsp['encoded']
		));
	}
