<?php

	include("include/init.php");
	loadlib("wof_elasticsearch");

	$args = array();

	if ($page = get_int32("page")){
		$args['page'] = $page;
	}

	$rsp = wof_elasticsearch_facet('wof:placetype', $args);

	$pagination = $rsp['pagination'];
	$rows = $rsp['rows'];

	$GLOBALS['smarty']->assign_by_ref("pagination", $pagination);
	$GLOBALS['smarty']->assign_by_ref("placetypes", $rows);

	$pagination_url = $GLOBALS['cfg']['abs_root_url'] . "placetypes/";
	$GLOBALS['smarty']->assign("pagination_url", $pagination_url);

	$GLOBALS['smarty']->display('page_placetypes.txt');
	exit();
