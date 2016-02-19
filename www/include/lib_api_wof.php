<?php
	loadlib('wof_upsert');

	function api_wof_upload() {

		if (! $_FILES["upload_file"]) {
			api_output_error(400, 'Please include an upload_file.');
		}

		$rsp = wof_upsert($_FILES["upload_file"]["tmp_name"]);
		if (! $rsp['ok'] ||
		    ! $rsp['geojson_url']) {
			$error = $rsp['error'] || 'Upload failed for some reason.';
			api_output_error(400, $error);
		}
		api_output_ok($rsp);
	}
	
	function api_wof_pip() {

		if (! isset($_POST['latitude']) ||
		    ! isset($_POST['longitude'])) {
			api_output_error(400, "Please include a 'latitude' and 'longitude'.");
		}

		$latitude = post_float('latitude');
		$longitude = post_float('longitude');

		$rsp = http_get("http://localhost:8080/?latitude=$latitude&longitude=$longitude");
		if (! $rsp['ok']) {
			$rsp['error_msg'] = 'Error finding point in polygon.';
			return $rsp;
		}
		$results = json_decode($rsp['body']);
		api_output_ok(array(
			'results' => $results
		));
	}
