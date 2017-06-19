<?php

	########################################################################
	
	loadlib("http");

	$GLOBALS['wof_pinboard_endpoint'] = 'http://localhost:8080';

	########################################################################

	function wof_pinboard_add_url($wofid, $url, $more=array()){

		$defaults = array(
			"tags" => "",
			"endpoint" => $GLOBALS['wof_pinboard_endpoint'],
		);
		
		$query = array(
			"wof_id" => $wofid,
			"url" => $url,
			"tags" => $defaults['tags'];
		);

		$query = http_build_query($query);
		$url = $defaults['endpoint'] . "?" . $query;

		return http_get($url);
	}

	########################################################################
	# the end
