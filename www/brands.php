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
			'brands' => array(
				'terms' => array('field' => 'wof:brand_id', 'size' => 0)
			)
		)
	);

	$rsp = elasticsearch_search($es_query, $args);

	# sudo put me in a function or something?

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