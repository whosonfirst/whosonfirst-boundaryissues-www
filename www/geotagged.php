<?php
	include('include/init.php');

	login_ensure_loggedin();

	// Make sure the user has accepted the TOS
	users_ensure_terms_accepted("geotag/");

	$upload_formats = array();

	if ($GLOBALS['cfg']['enable_feature_geotagged_photos']) {
		$upload_formats[] = '.jpg';
		$upload_formats[] = '.jpeg';
	} else {
		error_404();
	}

	$upload_formats = implode(', ', $upload_formats);
	$GLOBALS['smarty']->assign('upload_formats', $upload_formats);

	$GLOBALS['smarty']->display('page_geotag.txt');
	exit();
