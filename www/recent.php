<?php

	include("include/init.php");
	loadlib("wof_elasticsearch");

	$args = array();

	if ($page = get_int32('page')){
		$args['page'] = $page;
	}

	$created = get_isset('created');
	$per_page = get_int32('per_page');

	if ($per_page && $per_page > 0 && $per_page <= 1000){
		$args['per_page'] = $per_page;
	} else {
		$per_page = $GLOBALS['cfg']['pagination_per_page'];
	}

	$es_query = array(
		'query' => array(
			'match_all' => array(),
		),
		'sort' => array(
			array('wof:lastmodified' => 'desc')
		)
	);

	if ($created){
		$es_query['sort'][0] = array('wof:id' => 'desc');
	}

	$rsp = wof_elasticsearch_search($es_query, $args);

	$pagination = $rsp['pagination'];
	$results = $rsp['rows'];
	$categories = json_decode(file_get_contents('meta/categories.json'), 'as hash');

	$GLOBALS['smarty']->assign_by_ref("categories", $categories);
	$GLOBALS['smarty']->assign_by_ref("pagination", $pagination);
	$GLOBALS['smarty']->assign_by_ref("results", $results);
	$GLOBALS['smarty']->assign("per_page", $per_page);

	$pagination_url = $GLOBALS['cfg']['abs_root_url'] . "recent/";

	if ($created){
		$pagination_url .= "created/";
	}

	$GLOBALS['smarty']->assign("created", $created);
	$GLOBALS['smarty']->assign("pagination_url", $pagination_url);

	$crumb_save_batch = crumb_generate('api', 'wof.save_batch');
	$GLOBALS['smarty']->assign("crumb_save_batch", $crumb_save_batch);

	$GLOBALS['smarty']->display('page_recent.txt');
	exit();
