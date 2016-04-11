<?php

	loadlib("elasticsearch");

	########################################################################

	function offline_tasks_search_recent($args=array()){

		# These are ES 1.7 -isms...

		$match = array("@event" => "offline_tasks");
		$query = array("match" => $match);

		$es_query = array("filtered" => array(
			"query" => $query, 
		));

		# These are ES 2.x -isms... because computers?

		# $must = array(
		# 	"term" => array( "@event" => "offline_tasks" )
		# );
		# 
		# $query = array(
		# 	"bool" => array(
		# 		"must" => $must
		# 	)
		# );

		if (isset($args['filter'])){

			$filter = null;

			if ($args['filter']['id']){

				$filter = array(
					"term" => array("id" => $args['filter']['id'])
				);
			}

			# What doesn't this work... (20160411/thisissaaronland)

			if ($filter){
				$es_query["filtered"]["filter"] = array(
					"bool" => array( "must" => $filter )
				);
			}
		}

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