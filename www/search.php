<?php

	include('include/init.php');
	loadlib("elasticsearch");

	$args = array(
		'index' => 'whosonfirst'
	);

	$query = get_str('q');
	$page = get_int32('page');
	$per_page = get_int32('per_page');

	if ($page){
		$args['page'] = $page;
	}

	if ($per_page && $per_page > 0 && $per_page <= 1000){
		$args['per_page'] = $per_page;
	} else {
		$per_page = $GLOBALS['cfg']['pagination_per_page'];
	}

	if ($query){

		$GLOBALS['smarty']->assign('query', $query);

		$es_query = array(
			'query' => array(
				// Search result relevance is determined by a combining multiple matches.
				// The overall score is based on combining each of the matches.
				'bool' => array(
					'should' => array(
						// #1: wof:name ('name_not_analyzed' field) is an exact match.
						array(
							'match' => array(
								'name_not_analyzed' => $query
							)
						),
						// #2: wof:name property has all the words.
						array(
							'match' => array(
								'wof:name' => array(
									'operator' => 'and',
									'query' => $query
								)
							)
						),
						// #3: any of the name:* properties (appended to 'name_all') have
						//     any of the words.
						array(
							'match' => array(
								'name_all' => $query
							)
						),
						// #4: boost records with mz:is_current set to 1
						array(
							'match' => array(
								'mz:is_current' => 1
							)
						)
					),
					'must' => array(
						// #4: any of the words appear anywhere in the WOF record.
						'match' => array(
							'_all' => $query
						)
					)
				)
			)
		);

		$rsp = elasticsearch_search($es_query, $args);

		if ($rsp['ok']){

			$pagination = $rsp['pagination'];
			$results = $rsp['rows'];
			$categories = json_decode(file_get_contents('meta/categories.json'), 'as hash');

			$GLOBALS['smarty']->assign_by_ref("categories", $categories);
			$GLOBALS['smarty']->assign_by_ref("pagination", $pagination);
			$GLOBALS['smarty']->assign_by_ref("results", $results);

			$pg_query = array('q' => $query);
			$pg_query = http_build_query($pg_query);

			$pagination_url = $GLOBALS['cfg']['abs_root_url'] . "search?" . $pg_query;

			$GLOBALS['smarty']->assign("pagination_url", $pagination_url);
			$GLOBALS['smarty']->assign("pagination_page_as_queryarg", 1);
		}

		else {
			$GLOBALS['smarty']->assign("error_rsp", $rsp);
		}
	}

	$crumb_save_batch = crumb_generate('api', 'wof.save_batch');
	$GLOBALS['smarty']->assign("crumb_save_batch", $crumb_save_batch);
	$GLOBALS['smarty']->assign("per_page", $per_page);

	$GLOBALS['smarty']->display('page_search.txt');
	exit();

?>
