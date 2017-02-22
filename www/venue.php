<?php

	include('include/init.php');

	login_ensure_loggedin('venue/');

	$id = get_int64('id');
	if ($id) {
		$GLOBALS['smarty']->assign('page_title', 'Edit venue');
	} else {
		$GLOBALS['smarty']->assign('page_title', 'Add venue');
	}

	$crumb_save = crumb_generate('api', 'wof.save');
	$GLOBALS['smarty']->assign('crumb_save', $crumb_save);

	$GLOBALS['smarty']->display('page_venue.txt');
