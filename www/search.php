<?php

include('include/init.php');

$query = get_str('q');

if ($query) {
	$GLOBALS['smarty']->assign('query', $query);
	$url = "{$GLOBALS['cfg']['es_base_url']}_search?size=25";
	$query = json_encode(array(
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
	));
	$rsp = http_post($url, $query);
	if ($rsp['ok']) {
		$results = array();
		$body = json_decode($rsp['body'], true);
		foreach ($body['hits']['hits'] as $hit) {
			$results[] = array(
				'id' => $hit['_source']['wof:id'],
				'name' => $hit['_source']['wof:name'],
				'placetype' => $hit['_source']['wof:placetype'],
				'lat' => $hit['_source']['geom:latitude'],
				'lng' => $hit['_source']['geom:longitude']
			);
		}
		$GLOBALS['smarty']->assign('results', $results);
	}
}

$GLOBALS['smarty']->display('page_search.txt');
exit();
