<?php

	loadlib("elasticsearch");

	# https://github.com/whosonfirst/whosonfirst-www-boundaryissues/wiki/Updating-ES-schema

	########################################################################

	function wof_elasticsearch_search($query, $more=array()){

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

		$more['index'] = 'whosonfirst';

		# pass-by-ref
	}

	########################################################################

	function wof_elasticsearch_update_document($wof) {

		$props = $wof['properties'];
		$index = 'whosonfirst';
		$type = $props['wof:placetype'];
		$id = $props['wof:id'];
		$more = array(
			'id_field' => 'wof:id'
		);

		$existing_es_record = elasticsearch_get_index_record($id);
		if ($existing_es_record) {
			$rsp = elasticsearch_update_document($index, $type, $id, $props);
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
