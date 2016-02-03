<?php
	include('include/init.php');

	login_ensure_loggedin();

	if (post_isset('lat') &&
	    post_isset('lon')) {
		loadlib('api_wof');
		api_wof_venue();
		exit;
	}

	$crumb_venue_fallback = crumb_generate('wof.venue');
	$GLOBALS['smarty']->assign("crumb_venue_fallback", $crumb_venue_fallback);

	$crumb_venue = crumb_generate('api', 'wof.venue');
	$GLOBALS['smarty']->assign("crumb_venue", $crumb_venue);

	$GLOBALS['smarty']->assign('fields', array(
		//'wof:id' => 'number',
		'wof:parent_id' => array(
			'type' => 'number', 'default' => '-1'
		),
		'wof:name' => array(
			'type' => 'text'
		),
		'wof:placetype' => array(
			'type' => 'text'
		),
		'wof:country' => array(
			'type' => 'text'
		),
		'wof:concordances' => array(
			'type' => 'dictionary'
		),
		'wof:hierarchy' => array(
			'type' => 'list'
		),
		'wof:belongsto' => array(
			'type' => 'list'
		),
		'wof:supersedes' => array(
			'type' => 'list'
		),
		'wof:superseded_by' => array(
			'type' => 'list'
		),
		'wof:breaches' => array(
			'type' => 'list'
		),
		'wof:tags' => array(
			'type' => 'list'
		),
		'iso:country' => array(
			'type' => 'text'
		),
		'src:geom' => array(
			'type' => 'text'
		),
		'edtf:inception' => array(
			'type' => 'text', 'default' => 'uuuu'
		),
		'edtf:cessation' => array(
			'type' => 'text', 'default' => 'uuuu'
		)
	));

	$GLOBALS['smarty']->display('page_venue.txt');
	exit();
