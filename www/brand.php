<?php

	include("include/init.php");
	loadlib("wof_elasticsearch");

	$args = array();

	$page = get_int32('page');

	if ($page){
		$args['page'] = $page;
	}

	$per_page = get_int32('per_page');

	if ($per_page && $per_page > 0 && $per_page <= 1000){
		$args['per_page'] = $per_page;
	} else {
		$per_page = $GLOBALS['cfg']['pagination_per_page'];
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
	$categories = json_decode(file_get_contents('meta/categories.json'), 'as hash');

	$GLOBALS['smarty']->assign_by_ref("categories", $categories);
	$GLOBALS['smarty']->assign_by_ref("pagination", $pagination);
	$GLOBALS['smarty']->assign_by_ref("results", $results);

	$enc_id = urlencode($id);

	$pagination_url = $GLOBALS['cfg']['abs_root_url'] . "brands/{$enc_id}/";
	$GLOBALS['smarty']->assign("pagination_url", $pagination_url);
	$GLOBALS['smarty']->assign("per_page", $per_page);

	$GLOBALS['smarty']->assign_by_ref("brand", $brand);

	$crumb_save_batch = crumb_generate('api', 'wof.save_batch');
	$GLOBALS['smarty']->assign("crumb_save_batch", $crumb_save_batch);
	$crumb_download_batch = crumb_generate('api', 'wof.download_batch');
	$GLOBALS['smarty']->assign("crumb_download_batch", $crumb_download_batch);

	$GLOBALS['smarty']->display('page_brand.txt');
	exit();
