<?php

	include("include/init.php");
	loadlib("elasticsearch");
	loadlib("wof_utils");

	$rsp = wof_utils_search_field_aggregation('wof:placetype');

	$body = $rsp['body'];
	$body = json_decode($body, 'as hash');

	$aggrs = $body['aggregations'];
	$placetypes = $aggrs['placetypes'];
	$placetypes = $placetypes['buckets'];

	$rsp = elasticsearch_paginate_aggregation_results($placetypes, $args);

	$pagination = $rsp['pagination'];
	$placetypes = $rsp['aggregations'];

	$GLOBALS['smarty']->assign_by_ref("pagination", $pagination);
	$GLOBALS['smarty']->assign_by_ref("placetypes", $placetypes);

	$pagination_url = $GLOBALS['cfg']['abs_root_url'] . "placetypes/";
	$GLOBALS['smarty']->assign("pagination_url", $pagination_url);

	$GLOBALS['smarty']->display('page_placetypes.txt');
	exit();
