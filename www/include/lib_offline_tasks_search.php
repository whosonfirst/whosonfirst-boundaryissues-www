<?php

	loadlib("elasticsearch");

	# THIS IS NOT FINISHED YET. OR WORKING IN SOME CASES...
	# (20160411/thisisaaronland)

	########################################################################

	function offline_tasks_search_recent($args=array()){

		if (isset($args['filter'])){

			$filter = null;

			# This will not work as expected yet because it (task_id) needs to be
			# indexed as an un-analyzed string... (20160429/thisisaaronland)

			if ($args['filter']['task_id']){

				$filter = array(
					"match" => array("task_id" => $args['filter']['task_id'])
				);
			}

			else if ($args['filter']['task']){

				$filter = array(
					"match" => array("task" => $args['filter']['task'])
				);
			}

			else if ($args['filter']['action']){

				$filter = array(
					"match" => array("action" => $args['filter']['action'])
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

		return offline_tasks_search($es_query, $args);
	}

	########################################################################

	function offline_tasks_search($query, $more=array()){

		offline_tasks_search_append_defaults($more);

		return elasticsearch_search($query, $more);
	}

	########################################################################

	function offline_tasks_search_append_defaults(&$more){

		$more['index'] = $GLOBALS['cfg']['offline_tasks_elasticsearch_index'];
		$more['host'] = $GLOBALS['cfg']['offline_tasks_elasticsearch_host'];
		$more['port'] = $GLOBALS['cfg']['offline_tasks_elasticsearch_port'];

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
