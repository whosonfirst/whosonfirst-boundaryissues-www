<?php

	include('include/init.php');
	loadlib('wof_utils');

	$id = get_int64('id');
	$download = get_int64('download');
	$path = wof_utils_id2abspath(
		array($GLOBALS['cfg']['wof_pending_dir'],
		      $GLOBALS['cfg']['wof_data_dir']),
		$id
	);

	if (! $id || ! file_exists($path)) {
		error_404();
	}

	if ($download) {
		$filename = basename($path);
		header("Content-Type: application/octet-stream");
		header("Content-Transfer-Encoding: Binary");
		header("Content-disposition: attachment; filename=\"$filename\"");
	} else {
		header('Content-Type: application/json');
	}
	echo file_get_contents($path);
