<?php

include('include/init.php');

$query = get_str('q');

if ($query) {
	$GLOBALS['smarty']->assign('query', $query);
	$url = "{$GLOBALS['cfg']['es_base_url']}_search";
	$query = json_encode(array(
		'query' => array(
			'match' => array(
				'_all' => array(
					'operator' => 'and',
					'query' => $query
				),
			)
		),
		'sort' => array(
			//array('wof:megacity' => array('mode' => 'max', 'order' => 'desc')),
			//array('gn:population' => array('mode' => 'max', 'order' => 'desc')),
			array('wof:name' => array('order' => 'asc'))
			//array('wof:scale' => array('mode' => 'max', 'order' => 'desc')),
			//array('geom:area' => array('mode' => 'max', 'order' => 'desc'))
		)
	));
	$rsp = http_post($url, $query);
	if ($rsp['ok']) {
		$results = array();
		$body = json_decode($rsp['body'], true);
		foreach ($body['hits']['hits'] as $hit) {
			$results[] = array(
				'id' => $hit['_source']['wof:id'],
				'name' => $hit['_source']['wof:name'],
				'placetype' => $hit['_source']['wof:placetype']
			);
		}
		$GLOBALS['smarty']->assign('results', $results);
	}
}

$GLOBALS['smarty']->display('page_search.txt');
exit();
