<?php

	include("include/init.php");
	loadlib("elasticsearch");
	loadlib("wof_utils");

	$rsp = wof_utils_search_field_aggregation('tags', 'wof:tags');

	$body = $rsp['body'];
	$body = json_decode($body, 'as hash');

	$aggrs = $body['aggregations'];
	$tags = $aggrs['tags'];
	$tags = $tags['buckets'];

	$rsp = elasticsearch_paginate_aggregation_results($tags, $args);

	$pagination = $rsp['pagination'];
	$tags = $rsp['aggregations'];

	$GLOBALS['smarty']->assign_by_ref("pagination", $pagination);
	$GLOBALS['smarty']->assign_by_ref("tags", $tags);

	$pagination_url = $GLOBALS['cfg']['abs_root_url'] . "tags/";
	$GLOBALS['smarty']->assign("pagination_url", $pagination_url);

	$GLOBALS['smarty']->display('page_tags.txt');
	exit();
