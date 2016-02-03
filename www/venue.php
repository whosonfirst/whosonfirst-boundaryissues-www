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
		'wof:parent_id' => 'number',
		'wof:name' => 'text',
		'wof:placetype' => 'text',
		'wof:country' => 'text',
		'wof:concordances' => 'dictionary',
		'wof:hierarchy' => 'list',
		'wof:belongsto' => 'list',
		'wof:supersedes' => 'list',
		'wof:superseded_by' => 'list',
		'wof:breaches' => 'list',
		'wof:tags' => 'list',
		'iso:country' => 'text',
		'src:geom' => 'text',
		'edtf:inception' => 'text',
		'edtf:cessation' => 'text',
	));

	$GLOBALS['smarty']->assign("javascript_lib", glob('javascript/lib/*.js'));
	$GLOBALS['smarty']->display('page_venue.txt');
	exit();
