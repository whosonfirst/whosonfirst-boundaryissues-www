<?php

	include('include/init.php');
	loadlib('users_settings');

	$csv_id = get_str('csv');
	$page = get_int32('page');

	if ($csv_id) {

		login_ensure_loggedin("csv/$csv_id");

		$GLOBALS['smarty']->assign('page_title', 'Import from CSV');

		$user = $GLOBALS['cfg']['user'];
		$settings_json = users_settings_get_single($user, "csv_$csv_id");
		$settings = json_decode($settings_json, 'as hash');

		$GLOBALS['smarty']->assign('csv_id', $csv_id);
		$GLOBALS['smarty']->assign('csv_filename', $settings['orig_filename']);
		$GLOBALS['smarty']->assign('csv_row', $page);
		$GLOBALS['smarty']->assign('csv_row_count', $settings['row_count']);

		$path = $GLOBALS['cfg']['wof_pending_dir'] . 'csv/' . $settings['filename'];
		$column_properties = explode(',', $settings['column_properties']);

		$csv_file_handle = fopen($path, 'r');

		# TODO: make the heading row optional
		$heading = fgetcsv($csv_file_handle);

		if (! $page) {
			$page = 1;
		}

		for ($i = 0; $i < $page; $i++) {
			$row = fgetcsv($csv_file_handle);
		}
		fclose($csv_file_handle);

		$assignments = array();
		foreach ($row as $index => $value) {
			$prop = $column_properties[$index];
			$assignments[$prop] = $value;
		}
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

	$GLOBALS['smarty']->display('page_venue.txt');
