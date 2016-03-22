<?php

	include("include/init.php");
	loadlib("wof_elasticsearch");

	$args = array();

	if ($page = get_int32("page")){
		$args['page'] = $page;
	}

	$rsp = wof_elasticsearch_facet('wof:brand_id', $args);

	$pagination = $rsp['pagination'];
	$rows = $rsp['rows'];

	$GLOBALS['smarty']->assign_by_ref("pagination", $pagination);
	$GLOBALS['smarty']->assign_by_ref("brands", $rows);

	$pagination_url = $GLOBALS['cfg']['abs_root_url'] . "brands/";
	$GLOBALS['smarty']->assign("pagination_url", $pagination_url);

	$GLOBALS['smarty']->display('page_brands.txt');
	exit();
