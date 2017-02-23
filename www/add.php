<?php
	include('include/init.php');
	loadlib('wof_schema');

	login_ensure_loggedin();

	// Make sure the user has accepted the TOS
	users_ensure_terms_accepted("add/");

	$crumb_venue_fallback = crumb_generate('wof.save');
	$GLOBALS['smarty']->assign("crumb_save_fallback", $crumb_venue_fallback);

	$ref = 'https://whosonfirst.mapzen.com/schema/whosonfirst.schema#';

	// What is the minimum viable WOF document?
	$schema_fields = wof_schema_fields($ref);

	// Sort the properties alphabetically
	ksort($schema_fields['properties']['properties']['properties']);

	// Remove the properties that aren't required
	wof_render_remove_empty($schema_fields['properties']['properties']);

	$crumb_venue = crumb_generate('api', 'wof.save');
	$GLOBALS['smarty']->assign('crumb_save', $crumb_venue);
	$GLOBALS['smarty']->assign('schema_fields', $schema_fields);
	$GLOBALS['smarty']->assign('user_can_edit', 'user-can-edit');

	$bbox = users_settings_get_single($GLOBALS['cfg']['user'], 'default_bbox');
	if ($bbox){
		$GLOBALS['smarty']->assign('default_bbox', $bbox);
	}

	$GLOBALS['smarty']->display('page_add.txt');
	exit();
