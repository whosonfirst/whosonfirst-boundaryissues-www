<?php

	loadlib('wof_save');

	########################################################################

	function api_wof_upload_feature() {

		if (! $_FILES["upload_file"]) {
			api_output_error(400, 'Please include an upload_file.');
		}

		$geojson = file_get_contents($_FILES["upload_file"]["tmp_name"]);
		$geometry = post_bool('geometry');
		$properties = api_wof_property_list();
		$rsp = wof_save_feature($geojson, $geometry, $properties);

		if (! $rsp['ok']) {
			$error = $rsp['error'] ? $rsp['error'] : 'Upload failed for some reason.';
			api_output_error(400, $error);
		} else {
			$id = intval($rsp['geojson']['properties']['wof:id']);
			$name = $rsp['geojson']['properties']['wof:name'];
			api_output_ok(array(
				'ok' => 1,
				'saved_wof' => array(
					$id => $name
				)
			));
		}
	}

	########################################################################

	function api_wof_upload_collection() {

		if (! $_FILES["upload_file"]) {
			api_output_error(400, 'Please include an upload_file.');
		}

		$geojson = file_get_contents($_FILES["upload_file"]["tmp_name"]);
		$collection = json_decode($geojson, 'as hash');

		$geometry = post_bool('geometry');
		$properties = api_wof_property_list();

		$errors = array();
		$saved_wof = array();

		foreach ($collection['features'] as $index => $feature) {
			$geojson = json_encode($feature);
			$rsp = wof_save_feature($geojson, $geometry, $properties);
			if (! $rsp['ok']) {
				$errors[] = "Feature $index: {$rsp['error']}";
			} else {
				$id = intval($rsp['geojson']['properties']['wof:id']);
				$name = $rsp['geojson']['properties']['wof:name'];
				$saved_wof[$id] = $name;
			}
		}

		if ($errors) {
			$error = implode(', ', $errors);
			api_output_error(400, $error);
		} else {
			api_output_ok(array(
				'ok' => 1,
				'saved_wof' => $saved_wof
			));
		}
	}

	########################################################################

	function api_wof_save() {

		$geojson = post_str('geojson');
		if (! $geojson) {
			api_output_error(400, "Please include a 'geojson' parameter.");
		}

		$rsp = wof_save_feature($geojson);

		if (! $rsp['ok']) {
			$error = $rsp['error'] ? $rsp['error'] : 'Error saving WOF record.';
			api_output_error(400, $error);
		}
		api_output_ok($rsp);
	}

	########################################################################

	function api_wof_save_batch() {

		$ids = post_str('ids');
		$ids = explode(',', $ids);
		if (empty($ids)) {
			api_output_error(400, "Please include an 'ids' parameter.");
		}

		foreach ($ids as $id) {
			$id = trim($id);
			if (! is_numeric($id)) {
				api_output_error(400, "Invalid ID value: '$id'");
			}
		}

		$properties = post_str('properties');
		$properties = json_decode($properties, true);
		if (! $properties) {
			api_output_error(400, "Please include a 'properties' parameter.");
		}

		$rsp = wof_save_batch($ids, $properties);

		if (! $rsp['ok']) {
			$error = $rsp['error'] ? $rsp['error'] : 'Error batch-saving WOF records.';
			api_output_error(400, $error);
		}
		api_output_ok($rsp);
	}

	########################################################################

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

		# Note the absence of the trailing slash - this is relevant because Python
		# (20160429/thisisaaronland)

		$url = "http://{$GLOBALS['cfg']['wof_geojson_server_host']}:{$GLOBALS['cfg']['wof_geojson_server_port']}/pip";
		# error_log("{$url}?{$query}");

		$rsp = http_get("{$url}?$query");

		if (! $rsp['ok']) {
			$error = $rsp['error'] ? $rsp['error'] : 'Error talking to the PIP service.';
			api_output_error(400, $error);
		}

		$results = json_decode($rsp['body'], true);
		api_output_ok($results);
	}

	########################################################################

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

	########################################################################

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

		$es_base_url = $GLOBALS['cfg']['wof_elasticsearch_host'] . ':' .
		               $GLOBALS['cfg']['wof_elasticsearch_port'];
		$url = "$es_base_url/_search";
		$rsp = http_post($url, $query);

		if (! $rsp['ok']) {
			api_output_error(400, $rsp['body']);
		}
		$body = json_decode($rsp['body'], true);

		api_output_ok(array(
			'results' => $body['hits']['hits']
		));
	}

	########################################################################

	function api_wof_property_list() {

		// Returns a list of properties selected on the WOF upload
		// page.

		$properties = array();
		if ($_POST['properties']) {
			foreach ($_POST['properties'] as $property) {
				$properties[] = sanitize($property, 'str');
			}
		}
		return $properties;
	}

	# the end
