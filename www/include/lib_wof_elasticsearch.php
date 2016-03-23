<?php

	loadlib("elasticsearch");

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
		$index = 'whosonfirst';
		$type = $wof['properties']['wof:placetype'];
		$id = $wof['properties']['wof:id'];
		elasticsearch_update_document($index, $type, $id, $wof);
	}

	########################################################################

	# the end
