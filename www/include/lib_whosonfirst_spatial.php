<?php

	loadlib("tile38");

	########################################################################

	function whosonfirst_spatial_index_feature(&$feature, $more=array()){

		$geom = $feature['geometry'];
		$props = $feature['properties'];

		$wofid = $props['wof:id'];
		$placetype = $props['wof:placetype'];

		$placetype_id = 102312325;	# I guess I finally need to finish this...
						# (20160810/thisisaaronland)
						
		$str_geom = json_encode($geom);

		# Should we set flags for things being deprecated, superseded, current, etc?
		
		$cmd = array(
			"SET", "__COLLECTION__", $wofid,
			"FIELD", "wof:id", $wofid,
			"FIELD", "wof:placetype_id", $placetype_id,
			"OBJECT", $str_geom
		);

		$cmd = implode(" ", $cmd);

		$rsp = whosonfirst_spatial_do($cmd, $more);

		if (! $rsp['ok']){
			return $rsp;
		}

		$name = $props['wof:name'];
		$name_key = "{$wofid}:name";
		
		$cmd = array("SET", "__COLLECTION__", $name_key, "STRING", $name);
		$cmd = implode(" ", $cmd);

		$rsp2 = whosonfirst_spatial_do($cmd, $more);

		# See this? We're ignoring the return value of $rsp2... for now
		# (20160810/thisisaaronland)
		
		return $rsp;
	}

	########################################################################

	function whosonfirst_spatial_nearby_feature(&$feature, $more=array()){

		$props = $feature['properties'];
		
		# sudo make me a function to pick the best coordinate for
		# nearby-iness (20160811/thisisaaronland)

		$lat = $props['geom:latitude'];
		$lon = $props['geom:longitude'];

		$r = 100;
	
		return whosonfirst_spatial_nearby_latlon($lat, $lon, $r, $more);
	}

	########################################################################

	function whosonfirst_spatial_nearby_latlon($lat, $lon, $r, $more=array()){

		$defaults = array(
			'cursor' => 0
		);

		$more = array_merge($defaults, $more);

		$where = whosonfirst_spatial_generate_where($more);

		$cmd = array(
			"NEARBY", "__COLLECTION__",
		);

		if ($cursor = $more['cursor']){

			$cmd = array_merge($cmd, array(
				"CURSOR", $cursor
			));
		}

		if (count($where)){
			$cmd[] = implode(" ", $where);
		}

		$cmd = array_merge($cmd, array(
			"POINT", $lat, $lon, $r
		));

		$cmd = implode(" ", $cmd);

		return whosonfirst_spatial_do($cmd, $more);
	}

	########################################################################

	function whosonfirst_spatial_intersects($sw_lat, $sw_lon, $ne_lat, $ne_lon, $more=array()){

		$defaults = array(
			'cursor' => 0
		);

		$more = array_merge($defaults, $more);

		$where = whosonfirst_spatial_generate_where($more);

		$cmd = array(
			"INTERSECTS", "__COLLECTION__",
		);

		if ($cursor = $more['cursor']){
			$cmd = array_merge($cmd, array(
				"CURSOR", $cursor
			));
		}

		if (count($where)){
			$cmd[] = implode(" ", $where);
		}

		$cmd = array_merge($cmd, array(
			"BOUNDS", $sw_lat, $sw_lon, $ne_lat, $ne_lon
		));

		$cmd = implode(" ", $cmd);

		return whosonfirst_spatial_do($cmd, $more);
	}

	########################################################################

	function whosonfirst_spatial_clusters($sw_lat, $sw_lon, $ne_lat, $ne_lon, $sz, $more=array()){

		$defaults = array(
			'cursor' => 0
		);

		$more = array_merge($defaults, $more);

		$where = whosonfirst_spatial_generate_where($more);

		$cmd = array(
			"INTERSECTS", "__COLLECTION__",
			"SPARSE", $sz,
		);

		if (count($where)){
			$cmd[] = implode(" ", $where);
		}

		$cmd = array_merge($cmd, array(
			"BOUNDS", $sw_lat, $sw_lon, $ne_lat, $ne_lon
		));

		$cmd = implode(" ", $cmd);

		return whosonfirst_spatial_do($cmd, $more);
	}

	########################################################################

	function whosonfirst_spatial_generate_where($more=array()){

		$where = array();

		$possible = array(
			"wof:id",
			"wof:placetype_id",
		);

		foreach ($possible as $key){

			if ((! isset($more[$key])) || (! $more[$key])){
				continue;
			}

			$id = $more[$key];

			$where[] = "WHERE {$key} {$id} {$id}";
		}

		return $where;
	}

	########################################################################

	function whosonfirst_spatial_append_names(&$rsp, $more=array()){

		$fields = $rsp['fields'];
		$fields[] = "wof:name";

		# What follows is written in a way that should make it easy to
		# support a 'tile38_do_multi' command once it's been written
		# (20160811/thisisaaronland)

		$cmds = array();
		$rsps = array();

		$count_objects = count($rsp['objects']);

		for ($i=0; $i < $count_objects; $i++){

			$row = $rsp['objects'][$i];
			list($id, $ignore) = explode("#", $row['id']);

			$key = "{$id}:name";
			$cmd = "GET __COLLECTION__ {$key}";

			$cmds[] = $cmd;
		}

		foreach ($cmds as $cmd){

			# Note the lack of error checking...
			
			$rsp2 = whosonfirst_spatial_do($cmd, $more);
			$rsps[] = $rsp2;
		}

		for ($i=0; $i < $count_objects; $i++){

			$rsp['objects'][$i]['fields'][] = $rsps[$i]['object'];
		}
	
		$rsp['fields'] = $fields;

		# pass-by-ref
	}

	########################################################################

	function whosonfirst_spatial_do($cmd, $more=array()){

		$defaults = array(
			'host' => $GLOBALS['cfg']['whosonfirst_spatial_tile38_host'],
			'port' => $GLOBALS['cfg']['whosonfirst_spatial_tile38_port'],
			'collection' => $GLOBALS['cfg']['whosonfirst_spatial_tile38_collection'],
		);

		$more = array_merge($defaults, $more);

		$cmd = str_replace("__COLLECTION__", $more['collection'], $cmd);

		$rsp = tile38_do($cmd, $more);

		$rsp['command'] = $cmd;
		return $rsp;
	}

	########################################################################

	# the end
