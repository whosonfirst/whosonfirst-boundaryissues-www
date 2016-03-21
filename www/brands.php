<?php

	include("include/init.php");
	loadlib("elasticsearch");
	loadlib("wof_utils");

	$rsp = wof_utils_search_field_aggregation('wof:brand_id');

	$body = $rsp['body'];
	$body = json_decode($body, 'as hash');

	$aggrs = $body['aggregations'];
	$brands = $aggrs['brands'];
	$brands = $brands['buckets'];

	$rsp = elasticsearch_paginate_aggregation_results($brands, $args);

	$pagination = $rsp['pagination'];
	$brands = $rsp['aggregations'];

	$GLOBALS['smarty']->assign_by_ref("pagination", $pagination);
	$GLOBALS['smarty']->assign_by_ref("brands", $brands);

	$pagination_url = $GLOBALS['cfg']['abs_root_url'] . "brands/";
	$GLOBALS['smarty']->assign("pagination_url", $pagination_url);

	$GLOBALS['smarty']->display('page_brands.txt');
	exit();
