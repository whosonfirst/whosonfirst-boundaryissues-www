<?php

	include("include/init.php");
	loadlib("wof_elasticsearch");

	$args = array();

	if ($page = get_int32("page")){
		$args['page'] = $page;
	}

	$rsp = elasticsearch_facet('wof:tags', $args);

	$pagination = $rsp['pagination'];
	$rows = $rsp['rows'];

	$GLOBALS['smarty']->assign_by_ref("pagination", $pagination);
	$GLOBALS['smarty']->assign_by_ref("tags", $rows);

	$pagination_url = $GLOBALS['cfg']['abs_root_url'] . "tags/";
	$GLOBALS['smarty']->assign("pagination_url", $pagination_url);

	$GLOBALS['smarty']->display('page_tags.txt');
	exit();
