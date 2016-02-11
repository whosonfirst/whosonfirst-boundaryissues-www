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

	$crumb_venue = crumb_generate('api', 'wof.venue');
	$GLOBALS['smarty']->assign("crumb_venue", $crumb_venue);

	$GLOBALS['smarty']->assign('fields', wof_venue_fields());

	$GLOBALS['smarty']->display('page_venue.txt');
	exit();
