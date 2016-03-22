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
	
	# the end