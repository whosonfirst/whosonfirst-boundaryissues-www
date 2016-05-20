<?php

	include("include/init.php");
	loadlib("wof_elasticsearch");

	$args = array();

	$wofid = get_int64("id");

	if (! $wofid){
		error_404();
	}

	# something something something about looking up a parent
	# locally or remotely or from ES or... something something
	# (20160319/thisisaaronland)

	$ancestor = array(
		'wof:id' => $wofid,
	);

	if (! $ancestor){
		error_404();
	}

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

	$rsp = wof_elasticsearch_search($es_query, $args);

	$pagination = $rsp['pagination'];
	$results = $rsp['rows'];
	$categories = json_decode(file_get_contents('meta/categories.json'), 'as hash');

	$GLOBALS['smarty']->assign_by_ref("categories", $categories);
	$GLOBALS['smarty']->assign_by_ref("pagination", $pagination);
	$GLOBALS['smarty']->assign_by_ref("results", $results);

	$enc_wof = urlencode($wofid);

	$pagination_url = $GLOBALS['cfg']['abs_root_url'] . "belongsto/{$enc_wof}/";
	$GLOBALS['smarty']->assign("pagination_url", $pagination_url);
	$GLOBALS['smarty']->assign("per_page", $per_page);

	$GLOBALS['smarty']->assign_by_ref("ancestor", $ancestor);

	$crumb_save_batch = crumb_generate('api', 'wof.save_batch');
	$GLOBALS['smarty']->assign("crumb_save_batch", $crumb_save_batch);

	$GLOBALS['smarty']->display('page_belongs_to.txt');
	exit();
