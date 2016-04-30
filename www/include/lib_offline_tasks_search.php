<?php

	loadlib("elasticsearch");

	# THIS IS NOT FINISHED YET. OR WORKING IN SOME CASES...
	# (20160411/thisisaaronland)

	########################################################################

	function offline_tasks_search_recent($args=array()){

		# These are ES 1.7 -isms...
		# https://www.elastic.co/guide/en/elasticsearch/reference/1.7/query-dsl-filtered-query.html

		$match = array("@event" => "offline_tasks");
		$query = array("match" => $match);

		$es_query = array("filtered" => array(
			"query" => $query,
		));

		# What... why did this work for 1.7.2 (I think...) and not 1.7.5 ???
		# The following is a patch to just getting something to show up in
		# advance of actually figuring out what's going on...
		# (20160429/thisisaaronland)

		$es_query = array();

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

			if ($args['filter']['task_id']){

				$filter = array(
					"match" => array("task_id" => $args['filter']['task_id'])
				);
			}

			# What doesn't this work... (20160411/thisissaaronland)

			if ($filter){
				$es_query = array(
					"query" => $filter
				);
			}
		}

		$es_query['sort'] = array(
			array( "@timestamp" => array( "order" => "desc" ) )
		);

		$more = array(
			'offline-tasks',
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
