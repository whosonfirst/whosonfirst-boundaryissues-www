<?php

	include('include/init.php');
	loadlib('wof_utils');

	$id = get_int64('id');
	$ids = post_str('ids');
	$download = get_int64('download');
	$path = wof_utils_find_id($id);

	if ($ids) {
		$download = true;
		$ids = explode(',', $ids);
		$features = array();
		foreach ($ids as $id) {
			if (! is_numeric($id)) {
				continue;
			}
			$id = intval($id);
			$path = wof_utils_find_id($id);
			$geojson = file_get_contents($path);
			$features[] = json_decode($geojson, 'as hash');
		}
		$collection = array(
			'type' => 'FeatureCollection',
			'features' => $features
		);
		$count = count($features);
		$when = date('Ymd_His');
		$filename = "wof_{$count}_{$when}.geojson";
		$geojson = json_encode($collection);
	} else if (! $id || ! file_exists($path)) {
		error_404();
	} else {
		$filename = basename($path);
		$geojson = file_get_contents($path);
	}

	if ($download) {
		header("Content-Type: application/octet-stream");
		header("Content-Transfer-Encoding: Binary");
		header("Content-disposition: attachment; filename=\"$filename\"");
	} else {
		header('Content-Type: application/json');
	}
	echo $geojson;
