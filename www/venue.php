<?php

	include('include/init.php');

	login_ensure_loggedin('venue/');

	$id = get_int64('id');
	if ($id) {
		$GLOBALS['smarty']->assign('page_title', 'Edit venue');
	} else {
		$GLOBALS['smarty']->assign('page_title', 'Add venue');
	}

	$bbox = users_settings_get_single($GLOBALS['cfg']['user'], 'default_bbox');
	if ($bbox){
		$GLOBALS['smarty']->assign('default_bbox', $bbox);
	}

	$crumb_save = crumb_generate('api', 'wof.save');
	$GLOBALS['smarty']->assign('crumb_save', $crumb_save);
	$GLOBALS['smarty']->assign('mapzen_api_key', $GLOBALS['cfg']['mazpen_api_key']);

	$GLOBALS['smarty']->display('page_venue.txt');
