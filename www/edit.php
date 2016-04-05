<?php
	include('include/init.php');
	loadlib('wof_schema');
	loadlib('wof_utils');

	# login_ensure_loggedin();

	$crumb_venue_fallback = crumb_generate('wof.save');
	$GLOBALS['smarty']->assign("crumb_save_fallback", $crumb_venue_fallback);

	$ref = 'https://whosonfirst.mapzen.com/schema/whosonfirst.schema#';
	$ignore_fields = array(
		'id',
		'bbox',
		'type',
		'geometry', // smells like yaks (20160216/dphiffer)
		'properties' => array(
			'wof:id',
			'wof:parent_id',
			'wof:hierarchy',
			'wof:belongsto',
			'wof:supersedes',
			'wof:superseded_by',
			'wof:breaches',
			'wof:country',
			'iso:country'
		)
	);

	$read_only = array(
		'properties' => array(
			'geom:hash' => true,
			'geom:area' => true,
			'geom:bbox' => true,
			'wof:lastmodified' => true,
			'wof:created' => true
		)
	);

	$wof_id = get_int64('id');

	$path = wof_utils_id2abspath(
		$GLOBALS['cfg']['wof_data_dir'],
		$wof_id
	);

	if (!file_exists($path)) {
		error_404();
	}
	$geojson = file_get_contents($path);
	$values = json_decode($geojson, true);

	$schema_fields = wof_schema_fields($ref, $ignore_fields, $values, $read_only);
	//dbug($schema_fields);

	$GLOBALS['smarty']->assign_by_ref("properties", $values['properties']);

	if ($GLOBALS['cfg']['user']){
		$crumb_save = crumb_generate('api', 'wof.save');
		$GLOBALS['smarty']->assign('crumb_save', $crumb_save);
	}

	$GLOBALS['smarty']->assign('wof_id', $wof_id);
	$GLOBALS['smarty']->assign('wof_name', $values['properties']['wof:name']);
	$GLOBALS['smarty']->assign('wof_parent_id', $values['properties']['wof:parent_id']);
	$GLOBALS['smarty']->assign('wof_hierarchy', json_encode($values['properties']['wof:hierarchy']));
	$GLOBALS['smarty']->assign('schema_fields', $schema_fields);

	$GLOBALS['smarty']->display('page_edit.txt');
	exit();
