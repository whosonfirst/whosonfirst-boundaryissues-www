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

		if (! $_POST['geojson']) {
			api_output_error(400, "Please include a 'geojson' parameter.");
		}

		$rsp = wof_save_string($_POST['geojson']);
		if (! $rsp['ok'] ||
				! $rsp['geojson_url']) {
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
			$rsp['error_msg'] = 'Error finding point in polygon.';
			return $rsp;
		}
		$results = json_decode($rsp['body']);
		api_output_ok(array(
			'results' => $results
		));
	}
