<?php
	include('include/init.php');
	loadlib('wof_venue');

	login_ensure_loggedin();

	if (post_isset('lat') &&
	    post_isset('lon')) {
		loadlib('api_wof');
		api_wof_venue();
		exit;
	}

	$crumb_venue_fallback = crumb_generate('wof.save');
	$GLOBALS['smarty']->assign("crumb_venue_fallback", $crumb_venue_fallback);

	$ref = 'https://whosonfirst.mapzen.com/schema/whosonfirst.schema#';
	$ignore_fields = array(
		'id',
		'bbox',
		'type',
		'geometry', // smells like yaks (20160216/dphiffer)
		'properties' => array(
			'wof:id',
			'wof:hierarchy',
			'wof:belongsto',
			'wof:supersedes',
			'wof:superseded_by',
			'wof:breaches',
			'wof:country',
			'iso:country'
		)
	);
	
	$path = wof_utils_id2abspath(
		$GLOBALS['cfg']['wof_data_dir'],
		get_int64('id')
	);
	if (!file_exists($path)) {
		// TODO: Do this the proper Flamework way
		http_response_code(404);
		echo "404 not found.";
		exit;
	}
	$geojson = file_get_contents($path);
	$values = json_decode($geojson, true);
	
	$schema_fields = wof_schema_fields($ref, $ignore_fields, $values);

	$crumb_venue = crumb_generate('api', 'wof.save');
	$GLOBALS['smarty']->assign('crumb_venue', $crumb_venue);
	$GLOBALS['smarty']->assign('schema_fields', $schema_fields);

	$GLOBALS['smarty']->display('page_edit.txt');
	exit();
