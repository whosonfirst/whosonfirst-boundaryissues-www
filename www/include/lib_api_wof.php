<?php

	loadlib('api_wof_utils');
	loadlib('wof_utils');
	loadlib('wof_photos');
	loadlib('wof_save');
	loadlib('users_settings');
	loadlib('uuid');

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

	function api_wof_upload_csv() {

		if (! $_FILES["upload_file"]) {
			api_output_error(400, 'Please include an upload_file.');
		}

		$column_properties = post_str('column_properties');
		if (! $column_properties) {
			api_output_error(400, 'Please include a column_properties list.');
		}

		$row_count = post_int32('row_count');
		if (! $row_count) {
			api_output_error(400, 'Please include a row_count list.');
		}

		$has_headers = post_bool('has_headers');
		$geom_source = post_str('geom_source');
		$common_tags = post_str('common_tags');

		$user_id = $GLOBALS['cfg']['user']['id'];
		$csv_id = random_string('10');
		$timestamp = time();
		$filename = "$timestamp-$user_id-$csv_id.csv";

		$dir = $GLOBALS['cfg']['wof_pending_dir'] . 'csv';
		if (! file_exists($dir)) {
			mkdir($dir, 0755, true);
		}

		$path = "$dir/$filename";
		move_uploaded_file($_FILES["upload_file"]["tmp_name"], $path);

		$wof_ids = array();
		$props = explode(',', $column_properties);
		$wof_id_index = array_search('wof:id', $props);
		if ($wof_id_index !== false) {
			$fh = fopen($path, 'r');
			$headers = fgetcsv($fh);
			$index = 0;
			while ($row = fgetcsv($fh)) {
				$wof_id = $row[$wof_id_index];
				if ($wof_id != -1) {
					$wof_ids[$index] = $wof_id;
				}
				$index++;
			}
		}

		$csv_settings = array(
			'column_properties' => $column_properties,
			'orig_filename' => $_FILES['upload_file']['name'],
			'filename' => $filename,
			'row_count' => $row_count,
			'wof_ids' => $wof_ids,
			'has_headers' => $has_headers,
			'geom_source' => $geom_source,
			'common_tags' => $common_tags
		);
		$csv_settings = json_encode($csv_settings);
		users_settings_set($GLOBALS['cfg']['user'], "csv_$csv_id", $csv_settings);

		api_output_ok(array(
			'ok' => 1,
			'csv_id' => $csv_id
		));
	}

	########################################################################

	function api_wof_update_csv() {

		$csv_id = post_str('csv_id');
		$settings = users_settings_get_single($GLOBALS['cfg']['user'], "csv_$csv_id");

		if (! $settings) {
			api_output_error(400, 'Could not find that CSV ID.');
		}

		$settings = json_decode($settings, 'as hash');
		$updated = array();

		$csv_row = post_int32('csv_row');
		$wof_id = post_str('wof_id');
		$wof_id = intval($wof_id);

		if ($csv_row && $wof_id) {
			$index = $csv_row - 1;
			$wof_ids = $settings['wof_ids'];
			$wof_ids[$index] = $wof_id;
			$settings['wof_ids'] = $wof_ids;
			$updated[] = 'wof_ids';
		}

		$settings = json_encode($settings);
		users_settings_set($GLOBALS['cfg']['user'], "csv_$csv_id", $settings);

		api_output_ok(array(
			'ok' => 1,
			'csv_id' => $csv_id,
			'updated' => $updated
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
			$error = 'Error saving WOF record';
			if ($rsp['error']) {
				$error .= ": {$rsp['error']}";
			}
			api_output_error(400, $error);
		}

		$csv_id = post_str('csv_id');
		$csv_row = post_int32('csv_row');
		api_wof_utils_save_feature_csv($rsp['feature'], $csv_id, $csv_row);

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

		$vars = array(
			'latitude' => post_float('latitude'),
			'longitude' => post_float('longitude'),
			'placetype' => post_str('placetype')
		);
		if (isset($_POST['wof_id'])) {
			$vars['wof_id'] = post_int32('wof_id');
		}
		$query = http_build_query($vars);

		# Note the absence of the trailing slash - this is relevant because Python
		# (20160429/thisisaaronland)

		$url = "http://{$GLOBALS['cfg']['wof_geojson_server_host']}:{$GLOBALS['cfg']['wof_geojson_server_port']}/pip";
		# error_log("{$url}?{$query}");

		$headers = array();
		$more = array(
			'http_timeout' => 10 // let this run for up to 10s
		);
		dbug("{$url}?$query");
		$rsp = http_get("{$url}?$query", $headers, $more);

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

		$query = array(
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
		);

		$rsp = wof_elasticsearch_search($query);

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

	########################################################################

	function api_wof_users_settings_set() {

		$user = $GLOBALS['cfg']['user'];
		if (! $user) {
			api_output_error(400, 'You must be logged in to set a users setting.');
		}

		$name = post_str('name');
		$value = post_str('value');
		$rsp = users_settings_set($user, $name, $value);

		api_output_ok($rsp);

	}

	########################################################################

	function api_wof_photos_get(){

		api_utils_features_ensure_enabled(array('photos'));

		$wof_id = post_int32('wof_id');
		$rsp = wof_photos_get($wof_id);

		api_output_ok($rsp);
	}

	########################################################################

	function api_wof_assign_flickr_photo(){

		$user = $GLOBALS['cfg']['user'];
		if (! $user) {
			api_output_error(400, 'You must be logged in assign a Flickr photo.');
		}

		$wof_id = post_int32('wof_id');
		$flickr_id = post_int32('flickr_id');
		$rsp = wof_photos_assign_flickr_photo($wof_id, $flickr_id);

		api_output_ok($rsp);
	}

	########################################################################

	function api_wof_address_lookup(){

		// This stuff should probably live in its own dedicated library.
		// For now it's going to live here. (20160916/dphiffer)

		$query = request_str('query');
		$props = array(
			'addr:full' => str_replace("\r\n", ', ', $query)
		);

		// Translation lookup from libpostal labels to WOF properties
		// See: https://github.com/whosonfirst/whosonfirst-properties/blob/master/properties/addr.md
		$libpostal_translation = array(
			'house_number' => 'addr:housenumber',
			'road' => 'addr:street',
			'postcode' => 'addr:postcode'
		);

		if ($GLOBALS['cfg']['wof_libpostal_host']) {
			$query = http_build_query(array(
				'address' => $query
			));
			$host = $GLOBALS['cfg']['wof_libpostal_host'];
			$rsp = http_get("http://$host/parse?$query");
			if (! $rsp['ok']){
				api_output_error(400, 'Error loading results from libpostal.');
			}
			$results = json_decode($rsp['body'], 'as hash');
		} else {
			// Placeholders for testing
			$results = array(
				array(
					"label" => "house_number",
					"value" => "475"
				),
				array(
					"label" => "road",
					"value" => "sansome st"
				),
				array(
					"label" => "city",
					"value" => "san francisco"
				),
				array(
					"label" => "state",
					"value" => "ca"
				)
			);
		}

		foreach ($results as $result) {
			$label = $result['label'];
			if ($libpostal_translation[$label]) {
				$key = $libpostal_translation[$label];
				$props[$key] = $result['value'];
			}
		}

		if (! $props['addr:postcode'] &&
		    $GLOBALS['cfg']['wof_postcode_pip_host']) {

			// Look up the postcode using lat/lng if we don't know it

			$query = http_build_query(array(
				'latitude' => request_float('latitude'),
				'longitude' => request_float('longitude')
			));
			$host = $GLOBALS['cfg']['wof_postcode_pip_host'];
			$rsp = http_get("http://$host/?$query");
			if ($rsp['ok']) {
				$pip_results = json_decode($rsp['body'], 'as hash');
				foreach ($pip_results as $result) {
					if ($result['Placetype'] == 'postalcode' &&
					    ! $result['Deprecated'] &&
					    ! $result['Superseded']) {
						$props['addr:postcode'] = $result['Name'];
					}
				}
			}
		}

		api_output_ok(array(
			'properties' => $props,
			'libpostal_results' => $results
		));
	}

	# the end
