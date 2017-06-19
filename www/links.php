<?php

	include('include/init.php');
	loadlib('wof_schema');
	loadlib('wof_utils');
	loadlib('wof_pinboard');	

	login_ensure_loggedin();

	features_ensure_enabled("links");
	
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
	
	$feature = json_decode($geojson, 'as hash');
	$props = feature['properties'];
	
	$repo = $props['wof:repo'];

	if (! $repo) {
		$repo_path = wof_utils_id2repopath($wof_id);
		$repo = basename($repo_path);
	}
	
	$can_edit = users_acl_can_edit($GLOBALS['cfg']['user'], $repo);
	$GLOBALS['smarty']->assign('can_edit', $can_edit);
		
	$crumb_key = 'pinboard_link';
	$smarty->assign("crumb_key", $crumb_key);

	# as in: can edit, has url, has crumb, etc.

	$url = post_str("url");

	if ((post_isset('done') && (crumb_check($crumb_key)) && ($can_edit)){

		$url = post_str("url");
		$url = trim($url);

		if (! $url){
			error_500();
		}

		$wofid = $props["wof:id"];
		
		$tags = array(
			"mapzen:user={$GLOBALS['cfg']['user']['username']}",
			"mapzenplaces",				
		);

		$tags = implode(",", $tags);

		$more = array(
		      "tags" => $tags
		);
		
		$rsp = wof_pinboard_add_url($wofid, $url, $more);

		if (! $rsp["ok"]){

		}
	}

	$GLOBALS['smarty']->display('page_id_links.txt');
	exit();
