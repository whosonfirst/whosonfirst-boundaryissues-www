<?php
	include('include/init.php');
	loadlib('wof_schema');

	login_ensure_loggedin();

	$crumb_venue_fallback = crumb_generate('wof.save');
	$GLOBALS['smarty']->assign("crumb_save_fallback", $crumb_venue_fallback);

	$ref = 'https://whosonfirst.mapzen.com/schema/whosonfirst.schema#';

	// What is the minimum viable WOF document?
	$schema_fields = wof_schema_fields($ref);

	// Remove the properties that aren't required
	wof_render_remove_empty($schema_fields['properties']['properties']);

	$crumb_venue = crumb_generate('api', 'wof.save');
	$GLOBALS['smarty']->assign('crumb_save', $crumb_venue);
	$GLOBALS['smarty']->assign('schema_fields', $schema_fields);

	$GLOBALS['smarty']->display('page_add.txt');
	exit();
