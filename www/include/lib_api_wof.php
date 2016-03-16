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
		    ! isset($_POST['longitude']) ||
		    ! isset($_POST['placetype'])) {
			api_output_error(400, "Please include: 'latitude', 'longitude', and 'placetype'.");
		}

		$query = http_build_query(array(
			'latitude' => post_float('latitude'),
			'longitude' => post_float('longitude'),
			'placetype' => post_str('placetype')
		));

		$rsp = http_get("http://localhost:8181/pip?$query");
		if (! $rsp['ok']) {
			$error = $rsp['error'] ? $rsp['error'] : 'Error talking to the PIP service.';
			api_output_error(400, $error);
		}

		$results = json_decode($rsp['body'], true);
		api_output_ok($results);
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

	function api_wof_search() {

		$lat_min = post_float('lat_min');
		$lat_max = post_float('lat_max');
		$lng_min = post_float('lng_min');
		$lng_max = post_float('lng_max');

		if (! $lat_min || ! $lat_max ||
		    ! $lng_min || ! $lng_max) {
			api_output_error(400, "Please include: lat_min, lat_max, lng_min, lng_max");
		}

		$query = json_encode(array(
			'query' => array(
				'bool' => array(
					'must' => array(
						array(
							'range' => array(
								'geom:latitude' => array(
									'gte' => $lat_min,
									'lte' => $lat_max
								)
							)
						),
						array(
							'range' => array(
								'geom:longitude' => array(
									'gte' => $lng_min,
									'lte' => $lng_max
								)
							)
						)
					)
				)
			)
		));

		$url = "{$GLOBALS['cfg']['es_base_url']}_search";
		$rsp = http_post($url, $query);

		if (! $rsp['ok']) {
			api_output_error(400, $rsp['body']);
		}
		$body = json_decode($rsp['body'], true);

		api_output_ok(array(
			'results' => $body['hits']['hits']
		));
	}
