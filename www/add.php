<?php
	include('include/init.php');
	loadlib('wof_schema');

	login_ensure_loggedin();

	$crumb_venue_fallback = crumb_generate('wof.save');
	$GLOBALS['smarty']->assign("crumb_save_fallback", $crumb_venue_fallback);

	$ref = 'https://whosonfirst.mapzen.com/schema/whosonfirst.schema#';
	$ignore_fields = array(
		'id',
		'bbox',
		'type',
		'geometry', // smells like yaks (20160216/dphiffer)
		'properties' => array(
			'wof:id',
			'wof:parent_id',
			'wof:hierarchy',
			'wof:belongsto',
			'wof:supersedes',
			'wof:superseded_by',
			'wof:breaches',
			'wof:country',
			'iso:country',
			'geom:area',
			'wof:geomhash',
			'mz:is_current',
			'wof:lastmodified'
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

	$crumb_venue = crumb_generate('api', 'wof.save');
	$GLOBALS['smarty']->assign('crumb_save', $crumb_venue);
	$GLOBALS['smarty']->assign('schema_fields', $schema_fields);

	$GLOBALS['smarty']->display('page_add.txt');
	exit();
