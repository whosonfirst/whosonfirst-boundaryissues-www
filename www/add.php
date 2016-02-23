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
	$defaults = array(
		'properties' => array(
			'wof:parent_id' => -1,
			'wof:placetype' => 'venue',
			'src:geom' => 'mapzen',
			'edtf:inception' => 'uuuu',
			'edtf:cessation' => 'uuuu'
		)
	);
	$schema_fields = wof_schema_fields($ref, $ignore_fields, $defaults);

	$crumb_venue = crumb_generate('api', 'wof.venue.create');
	$GLOBALS['smarty']->assign('crumb_venue', $crumb_venue);
	$GLOBALS['smarty']->assign('schema_fields', $schema_fields);

	$GLOBALS['smarty']->display('page_add.txt');
	exit();
