<?php

	loadlib("elasticsearch");

	########################################################################

	function offline_tasks_search_recent($args=array()){

		$must = array(
			"term" => array( "@event" => "offline_tasks" )
		);

		$query = array(
			"bool" => array(
				"must" => $must
			)
		);

		$sort = array(
			array( "@timestamp" => array( "order" => "desc" ) )
		);

		$es_query = array(
			'query' => $query,
			'sort' => $sort,
		);

		return offline_tasks_search($es_query, $more);
	}

	########################################################################

	function offline_tasks_search($query, $more=array()){

		offline_tasks_search_append_defaults($more);

		return elasticsearch_search($query, $more);
	}

	########################################################################

	function offline_tasks_search_append_defaults(&$more){

		# TO DO: offline task specific host/port information
		# derived from $GLOBALS['cfg']

		$more['index'] = "offline-tasks";	# sudo make me a config thing
		
		# pass-by-ref
	}

	########################################################################

	function offline_tasks_search_massage_resultset(&$rows){

		foreach ($rows as &$row){
			offline_tasks_search_massage_results($row);
		}

		# pass-by-ref
	}

	########################################################################

	function offline_tasks_search_massage_results(&$row){

		foreach ($row as $k => $v){

			if (preg_match("/^\@(.*)/", $k, $m)){
				$row[ "_{$m[1]}" ] = $v;
			}
		}

		# pass-by-ref
	}

	########################################################################

	# the end