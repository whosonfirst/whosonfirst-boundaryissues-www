<?php

	loadlib("elasticsearch");

	# https://github.com/whosonfirst/whosonfirst-www-boundaryissues/wiki/Updating-ES-schema

	########################################################################

	function wof_elasticsearch_search($query, $more=array()){

		// See also: the comment in config.php. Basically if the feature
		// flag is enabled, we require that a filter is configured
		// for this app. All incoming ES queries are filtered so that
		// only the subset of records we care about show up.
		// (20160525/dphiffer)

		if ($GLOBALS['cfg']['enable_feature_filter_search']) {
			if (empty($GLOBALS['cfg']['search_query_filter'])) {
				return array(
					'ok' => 0,
					'error' => 'No search query filter configured! Do that in your config_local_*.php.'
				);
			}
			$curr_query = $query['query'];
			$query['query'] = array(
				'filtered' => array(
					'query' => $curr_query,
					'filter' => $GLOBALS['cfg']['search_query_filter']
				)
			);
		}

		wof_elasticsearch_append_defaults($more);
		return elasticsearch_search($query, $more);
	}

	########################################################################

	function wof_elasticsearch_facet($field, $more=array()){

		wof_elasticsearch_append_defaults($more);
		return elasticsearch_facet($field, $more);
	}

	########################################################################

	function wof_elasticsearch_append_defaults(&$more){

		$more['index'] = $GLOBALS['cfg']['wof_elasticsearch_index'];
		$more['host'] = $GLOBALS['cfg']['wof_elasticsearch_host'];
		$more['port'] = $GLOBALS['cfg']['wof_elasticsearch_port'];

		# pass-by-ref
	}

	########################################################################

	function wof_elasticsearch_update_document($wof, $more=array()) {

		wof_elasticsearch_append_defaults($more);
		$props = $wof['properties'];
		$id = $props['wof:id'];
		$type = $props['wof:placetype'];
		$index = $more['index'];
		$more['type'] = $type;
		$more['id_field'] = 'wof:id';

		$existing_es_record = elasticsearch_get_index_record($id, $more);
		if ($existing_es_record) {
			$rsp = elasticsearch_update_document($index, $type, $id, $props, $more);
		} else {
			$docs = array(
				$props
			);
			$rsp = elasticsearch_add_documents($docs, $index, $type, $more);
		}

		return $rsp;
	}

	########################################################################

	# the end
