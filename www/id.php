<?php

	include('include/init.php');
	loadlib('wof_schema');
	loadlib('wof_utils');
	loadlib('wof_render');
	loadlib('wof_events');

	$crumb_venue_fallback = crumb_generate('wof.save');
	$GLOBALS['smarty']->assign("crumb_save_fallback", $crumb_venue_fallback);

	$ref = 'https://whosonfirst.mapzen.com/schema/whosonfirst.schema#';
	$wof_id = get_int64('id');
	$rev = get_str('rev');

	if ($rev) {
		$path = wof_utils_find_revision($rev);
	} else {
		$path = wof_utils_find_id($wof_id);
	}

	if (! $path){
		error_404();
	}

	$geojson = file_get_contents($path);
	$values = json_decode($geojson, 'as hash');

	// We start from a pretty generic JSON schema representation of WOF
	// minimum viable document.
	$schema_fields = wof_schema_fields($ref);

	// Next, the values from a particular record are merged into the schema.
	$schema_fields = wof_render_insert_values($schema_fields, $values);

	$GLOBALS['smarty']->assign_by_ref("schema_fields", $schema_fields);
	$GLOBALS['smarty']->assign_by_ref("properties", $values['properties']);

	if ($GLOBALS['cfg']['user']){
		$crumb_save = crumb_generate('api', 'wof.save');
		$GLOBALS['smarty']->assign('crumb_save', $crumb_save);
	}

	if ($rev) {
		$GLOBALS['smarty']->assign('rev', $rev);
	}

	$GLOBALS['smarty']->assign('wof_id', $wof_id);
	$GLOBALS['smarty']->assign('wof_name', $values['properties']['wof:name']);
	$GLOBALS['smarty']->assign('wof_parent_id', $values['properties']['wof:parent_id']);
	$GLOBALS['smarty']->assign('wof_hierarchy', json_encode($values['properties']['wof:hierarchy']));
	$GLOBALS['smarty']->assign('geometry', json_encode($values['geometry']));

	$events = wof_events_for_id($wof_id);
	$GLOBALS['smarty']->assign_by_ref('events', $events);

	$GLOBALS['smarty']->display('page_id.txt');
	exit();
