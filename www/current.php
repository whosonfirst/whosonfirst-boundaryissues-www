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

	# This doesn't work yet (20160319/thisisaaronland)

	# https://www.elastic.co/guide/en/elasticsearch/guide/current/_finding_exact_values.html
	# https://github.com/whosonfirst/es-whosonfirst-schema/issues/1

	$es_query = array(
		'query' => array('filtered' => array(
			'filter' => array(
				'term' => array('mz:is_current' => '1')
			)
		)),
		'sort' => array(
			array('wof:lastmodified' => 'desc'),
			array('wof:id' => 'desc'),		# absent wof:created...
		)
	);

	$rsp = elasticsearch_search($es_query, $args);

	$pagination = $rsp['pagination'];
	$results = $rsp['rows'];

	$GLOBALS['smarty']->assign_by_ref("pagination", $pagination);
	$GLOBALS['smarty']->assign_by_ref("results", $results);

	$pagination_url = $GLOBALS['cfg']['abs_root_url'] . "current/";
	$GLOBALS['smarty']->assign("pagination_url", $pagination_url);
	
	$GLOBALS['smarty']->display('page_current.txt');
	exit();