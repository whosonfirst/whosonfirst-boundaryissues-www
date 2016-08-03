<?php

	loadlib("elasticsearch");

	# THIS IS NOT FINISHED YET. OR WORKING IN SOME CASES...
	# (20160411/thisisaaronland)

	########################################################################

	function audit_trail_search_recent($args=array()){

		if (isset($args['filter'])){

			$filter = null;

			if ($args['filter']['pid']){

				$filter = array(
					"match" => array("pid" => $args['filter']['pid'])
				);
			}

			else if ($args['filter']['task']){

				$filter = array(
					"match" => array("task" => $args['filter']['task'])
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

		return audit_trail_search($es_query, $args);
	}

	########################################################################

	function audit_trail_search($query, $more=array()){

		audit_trail_search_append_defaults($more);

		return elasticsearch_search($query, $more);
	}

	########################################################################

	function audit_trail_search_append_defaults(&$more){

		$more['index'] = $GLOBALS['cfg']['audit_trail_elasticsearch_index'];
		$more['host'] = $GLOBALS['cfg']['audit_trail_elasticsearch_host'];
		$more['port'] = $GLOBALS['cfg']['audit_trail_elasticsearch_port'];

		# pass-by-ref
	}

	########################################################################

	function audit_trail_search_massage_resultset(&$rows){

		foreach ($rows as &$row){
			audit_trail_search_massage_results($row);
		}

		# pass-by-ref
	}

	########################################################################

	function audit_trail_search_massage_results(&$row){

		foreach ($row as $k => $v){

			if (preg_match("/^\@(.*)/", $k, $m)){
				$row[ "_{$m[1]}" ] = $v;
			}
		}

		# pass-by-ref
	}

	########################################################################

	# the end
