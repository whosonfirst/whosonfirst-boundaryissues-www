<?php

	########################################################################

	function api_whosonfirst_utils_ensure_bbox(){

		$sw_lat = request_float("sw_latitude");

		if (! $sw_lat){
			api_output_error(400, "Missing SW latitude");
		}

		if (! geo_utils_is_valid_latitude($sw_lat)){
			api_output_error(400, "Invalid SW latitude");
		}

		$sw_lon = request_float("sw_longitude");

		if (! $sw_lon){
			api_output_error(400, "Missing SW longitude");
		}

		if (! geo_utils_is_valid_longitude($sw_lon)){
			api_output_error(400, "Invalid SW longitude");
		}

		$ne_lat = request_float("ne_latitude");

		if (! $ne_lat){
			api_output_error(400, "Missing NE latitude");
		}

		if (! geo_utils_is_valid_latitude($ne_lat)){
			api_output_error(400, "Invalid NE latitude");
		}

		$ne_lon = request_float("ne_longitude");

		if (! $ne_lon){
			api_output_error(400, "Missing NE longitude");
		}

		if (! geo_utils_is_valid_longitude($ne_lon)){
			api_output_error(400, "Invalid NE longitude");
		}

		return array($sw_lat, $sw_lon, $ne_lat, $ne_lon);
	}

	########################################################################

	function api_wof_utils_save_feature_csv($feature, $csv_id, $csv_row) {
		$user = $GLOBALS['cfg']['user'];
		$settings_json = users_settings_get_single($user, "csv_$csv_id");
		$settings = json_decode($settings_json, 'as hash');

		if (! $settings['wof_ids']) {
			$settings['wof_ids'] = array();
		}

		$settings['wof_ids'][$csv_row - 1] = intval($feature['properties']['wof:id']);
		$settings_json = json_encode($settings);
		$rsp = users_settings_set($user, "csv_$csv_id", $settings_json);
	}

	########################################################################

	function api_wof_utils_passthrough($method, $more=array()){

		$url = $GLOBALS['cfg']['wof_api_base_url'];
		unset($_POST['access_token']);
		$_POST['method'] = $method;
		$_POST['api_key'] = $GLOBALS['cfg']['mazpen_api_key'];
		$query = http_build_query($_POST);
		$url = "$url?$query";
		$headers = array();

		$rsp = http_get($url, $headers, $more);
		$out = json_decode($rsp['body'], 'as hash');

		if ($rsp['ok']){
			api_output_ok($out);
		} else {
			$error = $rsp['error'];
			if ($out['error']['message']){
				$error = $out['error']['message'];
			}
			api_output_error($rsp['code'], $error);
		}
	}

	########################################################################

	function api_wof_utils_validate_zip($upload) {

		$names = array();
		$fh = zip_open($upload['tmp_name']);
		$basename = basename($upload['name'], '.zip');
		$err = array();

		while ($entry = zip_read($fh)) {
			$name = zip_entry_name($entry);
			if ($name == "$basename/meta.json") {
				$json = zip_entry_read($entry);
				$meta = json_decode($json, 'as hash');
			} else if (preg_match("/^$basename\/([^\/]+\.geojson)\$/", $name, $matches)) {
				$names[] = $matches[1];
			}
		}

		if (! $meta) {
			$err[] = 'No meta.json file found';
		} else {
			if (! $meta['type']) {
				$err[] = "meta.json has no 'type' property";
			} else {
				$fn = "api_wof_utils_validate_{$meta['type']}_zip";
				if (function_exists($fn)) {
					$rsp = $fn($meta, $names);
					$err = array_merge($err, $rsp);
				}
			}
		}

		if ($err) {
			return array('ok' => 0, 'errors' => $err);
		} else {
			return array('ok' => 1);
		}
	}

	########################################################################

	function api_wof_utils_validate_neighbourhood_zip($meta, $names) {
		$err = array();
		if (! $meta['parent_id']) {
			$err[] = "No 'parent_id' property found in meta.json";
		}
		return $err;
	}

	# the end
