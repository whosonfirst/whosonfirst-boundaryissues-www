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

	// Load the JSON schema
	$schema_fields = wof_schema_fields($ref);

	// Next, the values from a particular record are merged into the schema.
	wof_render_insert_values($schema_fields, $values);

	// Remove the properties that aren't:
	// - required
	// - have a value
	// - are on the list of "default" properties
	wof_render_prune($schema_fields['properties']['properties']);

	// Sort the properties alphabetically
	ksort($schema_fields['properties']['properties']['properties']);

	$GLOBALS['smarty']->assign_by_ref("schema_fields", $schema_fields);
	$GLOBALS['smarty']->assign_by_ref("properties", $values['properties']);

	if ($GLOBALS['cfg']['user']){
		$crumb_save = crumb_generate('api', 'wof.save');
		$GLOBALS['smarty']->assign('crumb_save', $crumb_save);

		// Make sure the user has accepted the TOS
		users_ensure_terms_accepted("id/$wof_id/");
	}

	if ($rev) {
		$GLOBALS['smarty']->assign('rev', $rev);
	}

	$GLOBALS['smarty']->assign('wof_id', $wof_id);
	$GLOBALS['smarty']->assign('wof_name', $values['properties']['wof:name']);
	$GLOBALS['smarty']->assign('wof_parent_id', $values['properties']['wof:parent_id']);
	$GLOBALS['smarty']->assign('wof_hierarchy', json_encode($values['properties']['wof:hierarchy']));
	$GLOBALS['smarty']->assign('geometry', json_encode($values['geometry']));

	$GLOBALS['smarty']->assign('names', wof_render_names($values));

	$events = wof_events_for_id($wof_id);
	$GLOBALS['smarty']->assign_by_ref('events', $events);

	$repo = $values['properties']['wof:repo'];
	if (! $repo) {
		$repo_path = wof_utils_id2repopath($wof_id);
		$repo = basename($repo_path);
	}
	if (users_acl_can_edit($GLOBALS['cfg']['user'], $repo)) {
		$GLOBALS['smarty']->assign('user_can_edit', 'user-can-edit');
	}

	$GLOBALS['smarty']->display('page_id.txt');
	exit();
