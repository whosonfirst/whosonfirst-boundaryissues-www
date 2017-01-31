<?php
	include('include/init.php');

	login_ensure_loggedin();

	// Make sure the user has accepted the TOS
	users_ensure_terms_accepted("upload/");

	if ($_FILES["upload_file"]) {
		loadlib('api_wof');
		api_wof_upload();
		exit;
	}

	$crumb_upload_fallback = crumb_generate('wof.upload');
	$GLOBALS['smarty']->assign("crumb_upload_fallback", $crumb_upload_fallback);

	$crumb_upload_feature = crumb_generate('api', 'wof.upload_feature');
	$GLOBALS['smarty']->assign("crumb_upload_feature", $crumb_upload_feature);

	$crumb_upload_collection = crumb_generate('api', 'wof.upload_collection');
	$GLOBALS['smarty']->assign("crumb_upload_collection", $crumb_upload_collection);

	$GLOBALS['smarty']->display('page_upload.txt');
	exit();
