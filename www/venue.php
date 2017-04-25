<?php

	include('include/init.php');
	loadlib('users_settings');
	loadlib('wof_utils');

	$csv_id = get_str('csv');
	$page = get_int32('page');

	$GLOBALS['smarty']->assign('button_label', 'Add venue');

	if ($csv_id) {

		if (! $GLOBALS['cfg']['enable_feature_csv_upload']){
			error_404();
		}

		login_ensure_loggedin("csv/$csv_id/$page/");

		$GLOBALS['smarty']->assign('page_title', 'Import from CSV');

		$user = $GLOBALS['cfg']['user'];
		$settings_json = users_settings_get_single($user, "csv_$csv_id");
		if (! $settings_json) {
			error_404();
		}
		$settings = json_decode($settings_json, 'as hash');

		$GLOBALS['smarty']->assign('csv_id', $csv_id);
		$GLOBALS['smarty']->assign('csv_filename', $settings['orig_filename']);
		$GLOBALS['smarty']->assign('csv_row', $page);
		$GLOBALS['smarty']->assign('csv_row_count', $settings['row_count']);

		if ($page < $settings['row_count']) {
			$next_url = $GLOBALS['cfg']['abs_root_url'] . "csv/$csv_id/" . ($page + 1) . '/';
			$GLOBALS['smarty']->assign('next_url', $next_url);
		}
		if ($page > 1) {
			$prev_url = $GLOBALS['cfg']['abs_root_url'] . "csv/$csv_id/" . ($page - 1) . '/';
			$GLOBALS['smarty']->assign('prev_url', $prev_url);
		}

		$assignments = array(
			'wof:tags' => array()
		);

		if ($settings['wof_ids'] &&
		    $settings['wof_ids'][$page - 1] &&
		    $settings['wof_ids'][$page - 1] != -1) {
			$wof_id = $settings['wof_ids'][$page - 1];
			$GLOBALS['smarty']->assign('wof_id', $wof_id);
			$path = wof_utils_find_id($wof_id);
			if (file_exists($path)) {
				$geojson = file_get_contents($path);
				$feature = json_decode($geojson, 'as hash');
				$props = $feature['properties'];

				$GLOBALS['smarty']->assign('button_label', 'Save venue');

				$assignments['wof:name'] = $props['wof:name'];
				$assignments['addr:full'] = $props['addr:full'];
				$assignments['wof:tags'] = $props['wof:tags'];

				// For some reason tags are getting encoded as a plain
				// string, not an array of strings. (20170405/dphiffer)
				if (is_scalar($assignments['wof:tags'])) {
					$assignments['wof:tags'] = array($assignments['wof:tags']);
				}
			} else {
				$GLOBALS['smarty']->assign('error_wof_not_found', 1);
			}
		}

		$path = $GLOBALS['cfg']['wof_pending_dir'] . 'csv/' . $settings['filename'];
		$column_properties = explode(',', $settings['column_properties']);

		if ($settings['geom_source']) {
			$assignments['src:geom'] = $settings['geom_source'];
		}

		$csv_file_handle = fopen($path, 'r');

		if ($settings['has_headers'] ||
		    ! isset($settings['has_headers'])) {
			$heading = fgetcsv($csv_file_handle);
		}

		if (! $page) {
			$page = 1;
		}

		for ($i = 0; $i < $page; $i++) {
			$row = fgetcsv($csv_file_handle);
		}
		fclose($csv_file_handle);

		foreach ($row as $index => $value) {
			$prop = $column_properties[$index];
			if ($prop == 'wof:tags') {
				if ($value) {
					$assignments['wof:tags'][] = $value;
				}
			} else if ($prop == 'addr:housenumber addr:street') {
				if (preg_match('/\s*(\S+)\s+(.+)/', $value, $matches)) {
					$assignments['addr:housenumber'] = $matches[1];
					$assignments['addr:street'] = $matches[2];
				}
			} else {
				$assignments[$prop] = $value;
			}
		}
		$assignments['wof:tags'] = implode(', ', $assignments['wof:tags']);

		$GLOBALS['smarty']->assign_by_ref('assignments', $assignments);
		$GLOBALS['smarty']->assign('venue_name', $assignments['wof:name']);
		$GLOBALS['smarty']->assign('venue_address', $assignments['addr:full']);
		$GLOBALS['smarty']->assign('venue_tags', $assignments['wof:tags']);

	} else {
		login_ensure_loggedin('venue/');
		$GLOBALS['smarty']->assign('page_title', 'Add venue');
	}

	$bbox = users_settings_get_single($GLOBALS['cfg']['user'], 'default_bbox');
	if ($bbox){
		$GLOBALS['smarty']->assign('default_bbox', $bbox);
	}

	$crumb_save = crumb_generate('api', 'wof.save');
	$GLOBALS['smarty']->assign('crumb_save', $crumb_save);
	$GLOBALS['smarty']->assign('mapzen_api_key', $GLOBALS['cfg']['mazpen_api_key']);

	$crumb_update_csv = crumb_generate('api', 'wof.update_csv');
	$GLOBALS['smarty']->assign('crumb_update_csv', $crumb_update_csv);

	$GLOBALS['smarty']->display('page_venue.txt');
