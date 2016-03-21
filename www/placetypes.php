<?php

	include("include/init.php");
	loadlib("elasticsearch");

	$args = array(
		'index' => 'whosonfirst'
	);

	$page = get_int32('page');

	if ($page){
		$args['page'] = $page;
	}

	$es_query = array(
		'query' => array('filtered' => array(
			'query' => array(
				'match_all' => array()
			),
		)),
		'aggregations' => array(
			'placetypes' => array(
				'terms' => array('field' => 'wof:placetype', 'size' => 0)
			)
		)
	);

	$rsp = elasticsearch_search($es_query, $args);

	# sudo put me in a function or something?

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
