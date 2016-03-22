<?php

	include("include/init.php");
	loadlib("wof_elasticsearch");

	$args = array();

	$page = get_int32('page');

	if ($page){
		$args['page'] = $page;
	}

	$tag = get_str("tag");
	$tag = filter_strict($tag);

	if (! $tag){
		error_404();
	}

	$es_query = array(
		'query' => array('filtered' => array(
			'query' => array(
				'match_all' => array()
			),
			'filter' => array(
				'term' => array('wof:tags' => $tag)
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

	$GLOBALS['smarty']->assign_by_ref("pagination", $pagination);
	$GLOBALS['smarty']->assign_by_ref("results", $results);

	$enc_tag = urlencode($tag);

	$pagination_url = $GLOBALS['cfg']['abs_root_url'] . "tags/{$enc_tag}/";
	$GLOBALS['smarty']->assign("pagination_url", $pagination_url);

	$GLOBALS['smarty']->assign_by_ref("tag", $tag);
	
	$GLOBALS['smarty']->display('page_tag.txt');
	exit();