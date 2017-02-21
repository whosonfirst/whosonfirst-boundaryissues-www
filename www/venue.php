<?php

	include('include/init.php');
	
	$id = get_int64('id');
	if ($id) {
		$GLOBALS['smarty']->assign('page_title', 'Edit venue');
	} else {
		$GLOBALS['smarty']->assign('page_title', 'Add venue');
	}
	
	$GLOBALS['smarty']->display('page_venue.txt');
