<?php

	loadlib("elasticsearch");
	loadlib("users_settings");
	loadlib("http");

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

		# This is kind of a big hack. But it should scope the current
		# user's queries within a wof:belongsto WOF ID, assuming there
		# is one set for $GLOBALS['cfg']['users_search_scope'] in
		# config.php. Eventually this will be exposed as some user-
		# configuration UI (20170214/dphiffer)

		if ($GLOBALS['cfg']['user']){
			$user_id = $GLOBALS['cfg']['user']['id'];
			if ($GLOBALS['cfg']['users_search_scope'][$user_id]){
				$scope_wof_id = $GLOBALS['cfg']['users_search_scope'][$user_id];
				$curr_query = $query['query'];
				$query['query'] = array(
					'filtered' => array(
						'query' => $curr_query,
						'filter' => array(
							"bool" => array(
								"must" => array(
									array("term" => array(
										"wof:belongsto" => $scope_wof_id
									))
								)
							)
						)
					)
				);
			}
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

		$es_settings_prefix = 'wof';
		if (isset($more['es_settings_prefix'])) {
			$es_settings_prefix = $more['es_settings_prefix'];
		}
		$more['index'] = $GLOBALS['cfg']["{$es_settings_prefix}_elasticsearch_index"];
		$more['host'] = $GLOBALS['cfg']["{$es_settings_prefix}_elasticsearch_host"];
		$more['port'] = $GLOBALS['cfg']["{$es_settings_prefix}_elasticsearch_port"];

		if ($GLOBAL['cfg']['user']) {
			$branch = users_settings_get_single($GLOBAL['cfg']['user'], 'branch');
			if ($branch != 'master') {
				$more['index'] = "{$more['index']}_$branch";
			}
		}

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

	function wof_elasticsearch_index_exists($index, $more=array()) {
		wof_elasticsearch_append_defaults($more);
		$url = "http://{$more['host']}:{$more['port']}/$index";
		$rsp = http_head($url);
		$index_exists = ($rsp['info']['http_code'] == 200);
		return $index_exists;
	}

	########################################################################

	function wof_elasticsearch_get($wof_id) {
		wof_elasticsearch_append_defaults($more);
		$url = "http://{$more['host']}:{$more['port']}/{$more['index']}/wof/$wof_id";
		$rsp = http_get($url);
		if (! $rsp['ok']) {
			return null;
		}
		$results = json_decode($rsp['body'], 'as hash');
		return $results['_source'];
	}

	# the end
