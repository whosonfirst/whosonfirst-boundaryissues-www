<?php

	loadlib('wof_utils');
	loadlib('wof_save');
	loadlib('uuid');
	loadlib('users_settings');

	########################################################################

	function api_wof_upload_feature() {

		if (! $_FILES["upload_file"]) {
			api_output_error(400, 'Please include an upload_file.');
		}

		$geojson = file_get_contents($_FILES["upload_file"]["tmp_name"]);
		$geometry = post_bool('geometry');
		$properties = api_wof_property_list();
		$rsp = wof_save_feature($geojson, $geometry, $properties);

		if (! $rsp['ok'] ||
		    ! $rsp['feature']) {
			$error = $rsp['error'] ? $rsp['error'] : 'Upload failed for some reason.';
			api_output_error(400, $error);
		} else {
			api_output_ok(array(
				'ok' => 1,
				'feature' => $rsp['feature']
			));
		}
	}

	########################################################################

	function api_wof_upload_collection() {

		if (! $_FILES["upload_file"]) {
			api_output_error(400, 'Please include an upload_file.');
		}

		$timestamp = time();
		$user_id = $GLOBALS['cfg']['user']['id'];
		$uuid = uuid_v4();
		$filename = "$timestamp-$user_id-$uuid.geojson";

		$upload_file = $_FILES["upload_file"]["tmp_name"];
		$upload_dir = wof_utils_pending_dir('upload');
		$upload_path = "$upload_dir$filename";

		if (! file_exists($upload_dir)) {
			mkdir($upload_dir, 0775, true);
		}

		move_uploaded_file($upload_file, $upload_path);

		if (! file_exists($upload_path)) {
			return array(
				'ok' => 0,
				'error' => 'Could not save pending uploaded file.'
			);
		}

		$rsp = offline_tasks_schedule_task('process_feature_collection', array(
			'upload_path' => $upload_path,
			'geometry' => post_bool('geometry'),
			'properties' => api_wof_property_list(),
			'collection_uuid' => $uuid,
			'user_id' => $user_id
		));

		if (! $rsp['ok']) {
			api_output_error(400, 'Could not schedule a task to process the feature collection.');
		}

		api_output_ok(array(
			'ok' => 1,
			'collection_uuid' => $uuid
		));
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

		// Returns a list of properties selected from the WOF upload
		// page, in the format:
		//
		//   array(
		//      'source_key' => 'target_key'
		//   )
		//
		// The source_key is where we find the *value* within the file
		// upload. The target_key is where we'll merge it in the
		// existing WOF record. The source_key can sometimes be modified
		// when the other tools don't like colons in the property names.
		//
		// See also: wof_save_merged()
		// (20160613/dphiffer)

		$properties = array();
		if ($_POST['properties']) {
			$aliases = $_POST['property_aliases'];
			if (! $aliases) {
				$aliases = array();
			}
			foreach ($_POST['properties'] as $property) {
				$key = sanitize($property, 'str');
				$value = $key;
				if ($aliases[$key]) {
					$value = sanitize($aliases[$key], 'str');
				}
				$properties[$key] = $value;
			}
		}
		return $properties;
	}

	########################################################################

	function api_wof_checkout_branch() {

		$user = $GLOBALS['cfg']['user'];
		if (! $user) {
			api_output_error(400, 'You must be logged in to checkout a branch.');
		}

		$branch = post_str('branch');
		if (! $branch) {
			api_output_error(400, 'Please select a branch.');
		}

		if (! preg_match('/^[a-z0-9-_]+$/', $branch)) {
			api_output_error(400, 'Invalid branch name. Please stick to alphanumerics, dashes, and underbars.');
		}

		users_settings_set($user, 'branch', $branch);

		if (! file_exists("{$GLOBALS['cfg']['wof_pending_dir']}$branch")) {
			mkdir("{$GLOBALS['cfg']['wof_pending_dir']}$branch", 0775, true);
		}

		$rsp = array(
			'branch' => $branch
		);

		$index = "{$GLOBALS['cfg']['wof_elasticsearch_index']}_$branch";
		if (! wof_elasticsearch_index_exists($index) &&
		      $branch != 'master') {
			offline_tasks_schedule_task('setup_index', array(
				'index' => $index
			));
			$rsp['scheduled_index_setup'] = $index;
		}

		api_output_ok($rsp);
	}

	# the end
