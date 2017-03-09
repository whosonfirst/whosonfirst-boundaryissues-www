<?php

	include('include/init.php');
	loadlib('users_settings');
	loadlib('wof_utils');

	$csv_id = get_str('id');
	$download = get_bool('download');

	login_ensure_loggedin("csv/$csv_id/");

	$user = $GLOBALS['cfg']['user'];
	$settings_json = users_settings_get_single($user, "csv_$csv_id");
	if (! $settings_json) {
		error_404();
	}
	$settings = json_decode($settings_json, 'as hash');

	if ($download) {

		$filename = $settings['orig_filename'];
		header('Content-Type: text/plain; charset=utf-8');
		header("Content-disposition: attachment; filename=\"wof-{$settings['orig_filename']}\"");

		$path = $GLOBALS['cfg']['wof_pending_dir'] . "/csv/{$settings['filename']}";
		$csv_fh = fopen($path, 'r');
		$output = fopen('php://output', 'w');

		$headings = fgetcsv($csv_fh);
		array_unshift($headings, 'wof_id');
		fputcsv($output, $headings);

		// Ok, just to be super clear about this, we are starting our
		// count at 0 even though the 'page' value in the URL starts
		// with 1. (20170309/dphiffer)
		$row_index = 0;
		while ($row = fgetcsv($csv_fh)) {
			if ($settings['wof_ids'] &&
			    $settings['wof_ids'][$row_index]) {
				array_unshift($row, $settings['wof_ids'][$row_index]);
			} else {
				array_unshift($row, -1);
			}
			fputcsv($output, $row);
			$row_index++;
		}

		fclose($csv_fh);
		fclose($output);

	} else {

		$GLOBALS['smarty']->assign('page_title', 'Download imported CSV');

		$GLOBALS['smarty']->assign('csv_id', $csv_id);
		$GLOBALS['smarty']->assign('csv_filename', 'wof-' . $settings['orig_filename']);

		$GLOBALS['smarty']->display('page_csv.txt');

	}
