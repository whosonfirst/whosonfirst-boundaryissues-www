<?php

	include("include/init.php");
	loadlib("wof_elasticsearch");

	$rsp = wof_elasticsearch_facet('wof:placetype');

	$pagination = $rsp['pagination'];
	$rows = $rsp['rows'];

	$GLOBALS['smarty']->assign_by_ref("pagination", $pagination);
	$GLOBALS['smarty']->assign_by_ref("placetypes", $rows);

	$pagination_url = $GLOBALS['cfg']['abs_root_url'] . "placetypes/";
	$GLOBALS['smarty']->assign("pagination_url", $pagination_url);

	$GLOBALS['smarty']->display('page_placetypes.txt');
	exit();
