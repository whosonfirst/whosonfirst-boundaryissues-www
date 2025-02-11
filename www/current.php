<?php

	include("include/init.php");
	loadlib("wof_elasticsearch");

	$args = array();

	if ($page = get_int32('page')){
		$args['page'] = $page;
	}

	$per_page = get_int32('per_page');

	if ($per_page && $per_page > 0 && $per_page <= 1000){
		$args['per_page'] = $per_page;
	} else {
		$per_page = $GLOBALS['cfg']['pagination_per_page'];
	}

	# https://www.elastic.co/guide/en/elasticsearch/guide/current/_finding_exact_values.html

	$es_query = array(
		'query' => array('filtered' => array(
			'filter' => array(
				'term' => array('mz:is_current' => 1)
			)
		)),
		'sort' => array(
			array('wof:lastmodified' => 'desc'),
			array('wof:id' => 'desc'),		# absent wof:created...
		)
	);

	$rsp = wof_elasticsearch_search($es_query, $args);

	$pagination = $rsp['pagination'];
	$results = $rsp['rows'];
	$categories = json_decode(file_get_contents('meta/categories.json'), 'as hash');

	$GLOBALS['smarty']->assign_by_ref("categories", $categories);
	$GLOBALS['smarty']->assign_by_ref("pagination", $pagination);
	$GLOBALS['smarty']->assign_by_ref("results", $results);

	$pagination_url = $GLOBALS['cfg']['abs_root_url'] . "current/";
	$GLOBALS['smarty']->assign("pagination_url", $pagination_url);
	$GLOBALS['smarty']->assign("per_page", $per_page);

	$crumb_save_batch = crumb_generate('api', 'wof.save_batch');
	$GLOBALS['smarty']->assign("crumb_save_batch", $crumb_save_batch);

	$GLOBALS['smarty']->display('page_current.txt');
	exit();
