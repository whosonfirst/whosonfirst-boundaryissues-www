<?php

	loadlib("tile38");

	########################################################################

	function wof_spatial_index_feature(&$feature, $more=array()){

		$geom = $feature['geometry'];
		$props = $feature['properties'];

		$wofid = $props['wof:id'];
		$placetype = $props['wof:placetype'];

		$placetype_id = "FIX ME";	# I guess I finally need to finish this...
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

		$rsp = wof_spatial_do($cmd, $more);

		if (! $rsp['ok']){
			return $rsp;
		}

		$name = $props['wof:name'];
		$name_key = "{$wofid}:name";
		
		$cmd = array("SET", "__COLLECTION__", $name_key, "STRING", $name);
		$cmd = implode(" ", $cmd);

		$rsp2 = wof_spatial_do($cmd, $more);

		# See this? We're ignoring the return value of $rsp2... for now
		# (20160810/thisisaaronland)
		
		return $rsp;
	}

	########################################################################

	function wof_spatial_nearby_feature(&$feature, $more=array()){

		$lat = $feature['latitude'];
		$lon = $feature['longitude'];
		$r = 100;
	
		return wof_spatial_nearby_latlon($lat, $lon, $r, $more);
	}

	########################################################################

	function wof_spatial_nearby_latlon($lat, $lon, $r, $more=array()){

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

		$cmd = array(
			"NEARBY", "__COLLECTION__",
		);

		if (count($where)){
			$cmd = array_merge($cmd, implode(" ", $where));
		}

		$cmd = array_merge($cmd, array(
			"POINT", $lat, $lon, $r
		));

		$cmd = implode(" ", $cmd);

		return wof_spatial_do($cmd, $more);
	}

	########################################################################

	function wof_spatial_do($cmd, $more=array()){

		$defaults = array(
			'host' => $GLOBALS['cfg']['spatial_tile38_host'],
			'port' => $GLOBALS['cfg']['spatial_tile38_port'],
			'collection' => $GLOBALS['cfg']['spatial_tile38_collection'],
		);

		$more = array_merge($defaults, $more);

		$cmd = str_replace("__COLLECTION__", $more['collection'], $cmd);

		$rsp = tile38_do($cmd, $more);

		$rsp['command'] = $cmd;
		return $rsp;
	}

	########################################################################

	# the end
