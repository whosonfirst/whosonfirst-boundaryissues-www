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

	$wofid = get_int64("id");

	if (! $wofid){
		error_404();
	}

	# this is a hack because I can't figure out how to extract a plain vanilla
	# WOF ID out of elasticsearch... because guh (20160319/thisisaaronland)

	$ancestor = array(
		'wof:id' => $wofid,
	);

	if (! $ancestor){
		error_404();
	}

	$es_query = array(
		'query' => array('filtered' => array(
			'query' => array(
				'match_all' => array()
			),
			'filter' => array(
				'term' => array('wof:belongsto' => $wofid)
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

	$enc_wof = urlencode($wofid);

	$pagination_url = $GLOBALS['cfg']['abs_root_url'] . "belongsto/{$enc_wof}/";
	$GLOBALS['smarty']->assign("pagination_url", $pagination_url);

	$GLOBALS['smarty']->assign_by_ref("ancestor", $ancestor);
	
	$GLOBALS['smarty']->display('page_belongs_to.txt');
	exit();