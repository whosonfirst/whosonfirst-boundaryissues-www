<?php

	loadlib("whosonfirst_spatial");

	# loadlib("api_whosonfirst_output");
	loadlib("api_wof_utils");

	loadlib("geo_utils");

	########################################################################

	function api_whosonfirst_places_getWithin(){

 		list($sw_lat, $sw_lon, $ne_lat, $ne_lon) = api_whosonfirst_utils_ensure_bbox();
		
		$more = array(
			'wof:placetype_id' => 102312325,	# venues - PLEASE DO NOT HARDCODE ME
		);

		if ($cursor = request_int32("cursor")){
			$more['cursor'] = $cursor;
		}

		$rsp = whosonfirst_spatial_intersects($sw_lat, $sw_lon, $ne_lat, $ne_lon, $more);
		api_whosonfirst_places_foo($rsp);
	}

	########################################################################

	function api_whosonfirst_places_getClusters(){

		list($sw_lat, $sw_lon, $ne_lat, $ne_lon) = api_whosonfirst_utils_ensure_bbox();

		$map = array(
			"xxsm" => 1,
			"xsm" => 2,
			"sm" => 3,
			"md" => 4,
			"lg" => 5,
			"xlg" => 6,
			"xxlg" => 7,
			# "xxxlg" => 8,
		);

		$sz = request_str("size");

		if (! $sz){
			$sz = "md";
		}

		if (! isset($map[$sz])){
			api_output_error(500, "Invalid cluster size");
		}

		$rsp = whosonfirst_spatial_clusters($sw_lat, $sw_lon, $ne_lat, $ne_lon, $map[$sz]);

		api_output_ok($rsp);
	}

	########################################################################

	function api_wof_places_get_nearby(){

		$lat = null;
		$lon = null;

		api_utils_features_ensure_enabled(array('spatial'));

		if ($wofid = request_int64("id")){
		
			api_output_error(400, "This has not been implemented yet");
		}

		else {

			$lat = request_float("latitude");

			if (! $lat){
				api_output_error(400, "Missing latitude");
			}

			if (! geo_utils_is_valid_latitude($lat)){
				api_output_error(400, "Invalid latitude");
			}

			$lon = request_float("longitude");

			if (! $lon){
				api_output_error(400, "Missing longitude");
			}

			if (! geo_utils_is_valid_longitude($lon)){
				api_output_error(400, "Invalid longitude");
			}
		}

		if ($r = request_int32("radius")){

			if (($r < 0) || ($r > 100)){
				api_output_error(400, "Invalid radius");
			}
		}

		else {
			$r = 100;
		}

		$more = array(
			'wof:placetype_id' => 102312325,	# venues - PLEASE DO NOT HARDCODE ME
		);

		if ($cursor = request_int32("cursor")){
			$more['cursor'] = $cursor;
		}

		$rsp = whosonfirst_spatial_nearby_latlon($lat, $lon, $r, $more);
		api_whosonfirst_places_foo($rsp);
	}

	########################################################################

	function api_whosonfirst_places_foo(&$rsp){

		if (! $rsp['ok']){
			api_output_error(500, $rsp['error']);
		}
		
		# See this? It takes ~ 20-40 µs to fetch each name individually.
		# Which isn't very much even when added up. There are two considerations
		# here: 1) It's useful just to be able to append the name from the 
		# tile38 index itself 2) It might be just as fast to look up the
		# entire record from ES itself. Basically what I am trying to say is
		# that it's too soon so we're just going to do this for now...
		# (20160811/thisisaaronland)

		whosonfirst_spatial_append_names($rsp);

		$results = array();

		# please put me in a function somewhere (20160811/thisisaaronland)

		$fields = $rsp['fields'];
		$count_fields = count($fields);

		foreach ($rsp['objects'] as $row){

			$geom = $row['object'];
			$coords = $geom['coordinates'];

			$props = array();

			for ($i=0; $i < $count_fields; $i++){
				$props[ $fields[$i] ] = $row['fields'][$i];
			}

			list($id, $repo) = explode("#", $row['id']);
			
			$results[] = array(
				'wof:name' => $props['wof:name'],
				'wof:id' => $props['wof:id'],
				'wof:placetype' => "venue",	# PLEASE DO NOT HARDCODE ME
				'wof:parent_id' => -1,		# PLEASE FIX ME
				'wof:country' => "XY",		# PLEASE FIX ME
				'wof:repo' => $repo,
				'geom:latitude' => $coords[1],
				'geom:longitude' => $coords[0],
			);
		}

		# end of please put me in a function somewhere

		$out = array('results' => $results, 'cursor' => $rsp['cursor']);
		api_output_ok($out);
	}

	########################################################################

	# the end
