<?php

	loadlib("http");

	$GLOBALS['timings']['es_queries_count']	= 0;
	$GLOBALS['timings']['es_queries_time']	= 0;
	$GLOBALS['timing_keys']['es_queries'] = 'ElasticSearch Queries';

	########################################################################

	function elasticsearch_search($query, $more=array()){

		$defaults = array(
			'host' => $GLOBALS['cfg']['elasticsearch_host'],
			'port' => $GLOBALS['cfg']['elasticsearch_port'],
			'search_type' => 'query_then_fetch',
			'http_timeout' => $GLOBALS['cfg']['elasticsearch_http_timeout'],
			'per_page' => $GLOBALS['cfg']['pagination_per_page'],
			'page' => 1,
			'index' => null,
			'type' => null,
		);

		$more = array_merge($defaults, $more);

		# http://www.elasticsearchtutorial.com/basic-elasticsearch-concepts.html
		# The amount of time it took me to figure out that it's 'size' and 'from'
		# instead of 'rows' and 'start' or 'page' and 'per_page' was a bit
		# depressing really (20140206/straup)

		$page = max(1, $more['page']);
		$per_page = max(1, $more['per_page']);

		$from = ($page - 1) * $per_page;
		$size = $per_page;

		$get_args = array(
			'size' => $size,
			'from' => $from,
			'search_type' => $more['search_type'],
		);

		$get_args = http_build_query($get_args);

		$url = implode(":", array($more['host'], $more['port']));
		if($more['index']) {
			$url .= "/{$more['index']}";
		}
		if($more['type']) {
			$url .= "/{$more['type']}";
		}
		$url .= "/_search?{$get_args}";

		$body = json_encode($query);

		$headers = array(
			"Content-Type" => "application/x-www-form-urlencoded",
		);

		# dumper($url);
		# dumper($query);

		$http_more = array(
			'http_timeout' => $more['http_timeout'],
		);

		$start = microtime_ms();

		$rsp = http_post($url, $body, $headers, $http_more);

		$end = microtime_ms();

		$GLOBALS['timings']['es_queries_count']	+= 1;
		$GLOBALS['timings']['es_queries_time'] += $end-$start;

		if (! $rsp['ok']){
			return $rsp;
		}

		$data = json_decode($rsp['body'], 'as hash');

		if (! $data){
			return array('ok' => 0, 'error' => 'failed to decode JSON');
		}

		if($data['error']) {
			return array('ok' => 0, 'error' => $data['error']);
		}

		$rows = elasticsearch_rowinate_search_results($data);
		$pagination = elasticsearch_paginate_search_results($data, $page, $per_page);

		$rsp['rows'] = $rows;
		$rsp['pagination'] = $pagination;

		log_notice('elasticsearch', $url . ' ' . $body . " HTTP {$rsp['info']['http_code']}", $rsp['info']['total_time']);

		return $rsp;
	}

	########################################################################

	function elasticsearch_facet($field, $more=array()){

		$query_all = array('filtered' => array(
			'query' => array(
				'match_all' => array()
			),
		));

		$defaults = array(
			'query' => $query_all
		);

		$more = array_merge($defaults, $more);

		$es_query = array(
			'query' => $more['query'],
			'aggregations' => array(
				'facet' => array(
					'terms' => array('field' => $field, 'size' => 0)
				)
			)
		);

		$rsp = elasticsearch_search($es_query, $more);

		if (! $rsp['ok']){
			return $rsp;
		}

		$body = $rsp['body'];
		$body = json_decode($body, 'as hash');

		if (! $body){
			return array("ok" => 0, "error" => "failed to parse response");
		}

		$aggrs = $body['aggregations'];
		$facets = $aggrs['facet'];
		$facets = $facets['buckets'];

		$rsp = elasticsearch_paginate_aggregation_results($facets, $more);

		$pagination = $rsp['pagination'];
		$rows = $rsp['aggregations'];
		
		return array("ok" => 1, "rows" => $rows, "pagination" => $pagination);
	}

	########################################################################

	function elasticsearch_get_index_record($id, $more=array()) {

		$defaults = array(
			"host" => $GLOBALS['cfg']['elasticsearch_host'],
			"port" => $GLOBALS['cfg']['elasticsearch_port'],
			"index" => "collection",
			"type" => "objects"
		);

		$more = array_merge($defaults, $more);

		$url = implode(":", array($more['host'], $more['port']));
		if($more['index']) {
			$url .= "/{$more['index']}";
		}
		if($more['type']) {
			$url .= "/{$more['type']}";
		}
		if($id) {
			$url .= "/{$id}";
		}

		$start = microtime_ms();

		$rsp = http_get($url);

		$end = microtime_ms();

		$GLOBALS['timings']['es_queries_count']	+= 1;
		$GLOBALS['timings']['es_queries_time'] += $end-$start;

		log_notice('elasticsearch', $url . ' ' . $body . " HTTP {$rsp['info']['http_code']}", $rsp['info']['total_time']);

		$rsp = json_decode($rsp['body'], 'as array');
		return($rsp['_source']);
	}

	########################################################################

	function elasticsearch_rowinate_search_results(&$data){

		$rows = array();

		foreach ($data['hits']['hits'] as $h){
			$rows[] = $h['_source'];
		}

		return $rows;
	}

	########################################################################

	function elasticsearch_paginate_search_results(&$data, $page, $per_page){

		$total_count = $data['hits']['total'];
		$page_count = ceil($total_count / $per_page);
		$last_page_count = $total_count - (($page_count - 1) * $per_page);

		$pagination = array(
			'total_count' => $total_count,
			'page' => $page,
			'per_page' => $per_page,
			'page_count' => $page_count,
		);

		return $pagination;
	}

	########################################################################

	function elasticsearch_reset_index($index, $settings=null, $mappings=null, $more=array()) {
		$defaults = array(
			'host' => $GLOBALS['cfg']['elasticsearch_host'],
			'port' => $GLOBALS['cfg']['elasticsearch_port']
		);

		if (!$settings) {
			$settings = new stdClass;
		}

		if (!$mappings) {
			$mappings = new stdClass;
		}

		$more = array_merge($defaults, $more);

		$deleteRsp = elasticsearch_delete_index($index, $more);
		$createRsp = elasticsearch_create_index($index, $settings, $mappings, $more);

		$rsp = array(
			'delete' => $deleteRsp,
			'create' => $createRsp
		);

		return $rsp;
	}

	########################################################################

	function elasticsearch_create_index($index, $settings=null, $mappings=null, $more=array()) {
		$defaults = array(
			'host' => $GLOBALS['cfg']['elasticsearch_host'],
			'port' => $GLOBALS['cfg']['elasticsearch_port']
		);

		if (!$settings) {
			$settings = new stdClass;
		}

		if (!$mappings) {
			$mappings = new stdClass;
		}

		$more = array_merge($defaults, $more);

		$url = implode(":", array($more['host'], $more['port']));
		$url .= "/{$index}";

		$body = array(
			"settings" => $settings,
			"mappings" => $mappings
		);

		$body = json_encode($body);

		$start = microtime_ms();

		$rsp = http_post($url, $body);

		$end = microtime_ms();

		$GLOBALS['timings']['es_queries_count']	+= 1;
		$GLOBALS['timings']['es_queries_time'] += $end-$start;

		log_notice('elasticsearch', $url . ' ' . $body . " HTTP {$rsp['info']['http_code']}", $rsp['info']['total_time']);

		return $rsp;
	}	

	########################################################################

	function elasticsearch_delete_index($index, $more=array()) {
		$defaults = array(
			'host' => $GLOBALS['cfg']['elasticsearch_host'],
			'port' => $GLOBALS['cfg']['elasticsearch_port']
		);

		$more = array_merge($defaults, $more);

		$url = implode(":", array($more['host'], $more['port']));
		$url .= "/{$index}";
		
		$start = microtime_ms();

		$rsp = http_delete($url, null);

		$end = microtime_ms();

		$GLOBALS['timings']['es_queries_count']	+= 1;
		$GLOBALS['timings']['es_queries_time'] += $end-$start;

		log_notice('elasticsearch', $url . " HTTP {$rsp['info']['http_code']}", $rsp['info']['total_time']);

		return $rsp;
	}

	########################################################################

	function elasticsearch_update_document($index, $type, $id, $update=array(), $more=array()) {
		$defaults = array(
			'host' => $GLOBALS['cfg']['elasticsearch_host'],
			'port' => $GLOBALS['cfg']['elasticsearch_port']
		);

		$more = array_merge($defaults, $more);

		$url = implode(":", array($more['host'], $more['port']));
		$url .= "/{$index}/{$type}/{$id}/_update";

		$body = array("doc" => $update);
		$body = json_encode($body);

		$headers = array(
			"Content-Type" => "application/x-www-form-urlencoded",
		);

		$start = microtime_ms();

		$rsp = http_post($url, $body, $headers);

		$end = microtime_ms();

		$GLOBALS['timings']['es_queries_count']	+= 1;
		$GLOBALS['timings']['es_queries_time'] += $end-$start;

		log_notice('elasticsearch', $url . ' ' . $body . " HTTP {$rsp['info']['http_code']}", $rsp['info']['total_time']);

		return $rsp;
	}	

	########################################################################	

	function elasticsearch_add_documents($docs, $index, $type, $more=array()) {
		$defaults = array(
			'host' => $GLOBALS['cfg']['elasticsearch_host'],
			'port' => $GLOBALS['cfg']['elasticsearch_port'],
			'id_field' => 'id'
		);

		$more = array_merge($defaults, $more);

		$http_more = array(
			'http_timeout' => $GLOBALS['cfg']['elasticsearch_http_timeout'],
		);

		$data = array(
			"index" => array(
				"_id" => null
			)
		);

		$body = "";

		foreach ($docs as $doc) {
			$data["index"]["_id"] = $doc[$more['id_field']];

			/* 
			bulk api follows the format of instruction, new line, doc, new line, until complete.
			see http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/docs-bulk.html
			*/
			$doc_string = json_encode($data) . "\n" . json_encode($doc) . "\n";

			$body .= $doc_string;
		}

		$url = implode(":", array($more['host'], $more['port']));
		$url .= "/{$index}/{$type}/_bulk";

		$headers = array(
			"Content-Type" => "application/x-www-form-urlencoded",
		);

		$start = microtime_ms();

		$rsp = http_post($url, $body, $headers, $http_more);

		$end = microtime_ms();

		$GLOBALS['timings']['es_queries_count']	+= 1;
		$GLOBALS['timings']['es_queries_time'] += $end-$start;

		log_notice('elasticsearch', $url . ' ' . $body . " HTTP {$rsp['info']['http_code']}", $rsp['info']['total_time']);

		return $rsp;
	}

	########################################################################

	function elasticsearch_delete_documents($docs, $index, $type, $more=array()) {
		$defaults = array(
			'host' => $GLOBALS['cfg']['elasticsearch_host'],
			'port' => $GLOBALS['cfg']['elasticsearch_port'],
			'id_field' => 'id'
		);

		$more = array_merge($defaults, $more);
		
		$data = array(
			"delete" => array(
				"_id" => null
			)
		);

		$body = "";

		foreach($docs as $doc) {
			$data["delete"]["_id"] = $doc[$more['id_field']];
			$doc_string = json_encode($data) . "\n";

			$body .= $doc_string;
		}

		$url = implode(":", array($more['host'], $more['port']));
		$url .= "/{$index}/{$type}/_bulk";

		$headers = array(
			"Content-Type" => "application/x-www-form-urlencoded",
		);

		$start = microtime_ms();

		$rsp = http_post($url, $body, $headers);

		$end = microtime_ms();

		$GLOBALS['timings']['es_queries_count']	+= 1;
		$GLOBALS['timings']['es_queries_time'] += $end-$start;

		log_notice('elasticsearch', $url . ' ' . $body . " HTTP {$rsp['info']['http_code']}", $rsp['info']['total_time']);

		return $rsp;
	}

	########################################################################

	function elasticsearch_build_empty_query() {
		return array(
			"query" => array(
				"filtered" => array(
					"filter" => array(
						"and" => array()
					),
					"query" => array(
						"bool" => array(
							"must" => array(
							)
						)
					)
				)
			)
		);
	}

	########################################################################

	/*
		ensures the and filter and the bool->must query contain a match_all 
		if they are empty. they will always return 0 results otherwise.

		json_encode turns array() into [], and new stdClass into {}
		since match_all requires an empty object, {}, i use new stdClass below.
	*/

	function elasticsearch_ensure_complete_query(&$query) {
		//if the and filter is still empty, match all
		if(count($query['query']['filtered']['filter']['and']) == 0) {
			$query['query']['filtered']['filter']['and'][] = array(
				"match_all" => new stdClass
			);
		}

		//if the bool.must query is empty, match all
		if(count($query['query']['filtered']['query']['bool']['must']) == 0) {
			$query['query']['filtered']['query']['bool']['must'][] = array(
				"match_all" => new stdClass
			);
		}

		//note the pass by ref
	}

	########################################################################

	function elasticsearch_paginate_aggregation_results($results, $more=array()) {
		$page = isset($more['page']) ? max(1, $more['page']) : 1;
		$per_page = isset($more['per_page']) ? max(1, $more['per_page']) : $GLOBALS['cfg']['pagination_per_page'];

		$offset = ($page - 1) * $per_page;

		$total_count = count(array_keys($results));
		$page_count = ceil($total_count / $per_page);
		$last_page_count = $total_count - (($page_count - 1) * $per_page);

		$pagination = array(
			'total_count' => $total_count,
			'page' => $page,
			'per_page' => $per_page,
			'page_count' => $page_count,
		);

		$results = array_slice($results, $offset, $per_page, 'preserve keys');

		return array(
			'ok' => 1,
			'aggregations' => $results,
			'pagination' => $pagination,
		);		
	}

	########################################################################

	# the end
