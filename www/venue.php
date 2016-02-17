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

	$crumb_venue_fallback = crumb_generate('wof.venue');
	$GLOBALS['smarty']->assign("crumb_venue_fallback", $crumb_venue_fallback);

	$ref = 'https://whosonfirst.mapzen.com/schema/whosonfirst.schema#';
	$ignore_fields = array(
		'id',
		'properties' => array(
			'wof:id'
		)
	);
	$schema_fields = wof_schema_fields($ref, $ignore_fields);

	$crumb_venue = crumb_generate('api', 'wof.venue');
	$GLOBALS['smarty']->assign('crumb_venue', $crumb_venue);
	$GLOBALS['smarty']->assign('schema_fields', $schema_fields);

	$GLOBALS['smarty']->display('page_venue.txt');
	exit();
