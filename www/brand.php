<?php

	include("include/init.php");
	loadlib("wof_elasticsearch");

	$args = array();

	$page = get_int32('page');

	if ($page){
		$args['page'] = $page;
	}

	$id = get_int64("id");

	if (! $id){
		error_404();
	}

	# this is a hack because I can't figure out how to extract a plain vanilla
	# WOF ID out of elasticsearch... because guh (20160319/thisisaaronland)

	$brand = array(
		'wof:brand_id' => $id,
	);

	if (! $brand){
		error_404();
	}

	$es_query = array(
		'query' => array('filtered' => array(
			'query' => array(
				'match_all' => array()
			),
			'filter' => array(
				'term' => array('wof:brand_id' => $id)
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

	$enc_id = urlencode($id);

	$pagination_url = $GLOBALS['cfg']['abs_root_url'] . "brands/{$enc_id}/";
	$GLOBALS['smarty']->assign("pagination_url", $pagination_url);

	$GLOBALS['smarty']->assign_by_ref("brand", $brand);

	$crumb_save_batch = crumb_generate('api', 'wof.save_batch');
	$GLOBALS['smarty']->assign("crumb_save_batch", $crumb_save_batch);

	$GLOBALS['smarty']->display('page_brand.txt');
	exit();
