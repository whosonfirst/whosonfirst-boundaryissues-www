<?php

	loadlib("http");

	########################################################################

	function wof_geojson_encode($geojson) {

		$data = array(
			'geojson' => $geojson
		);
		return wof_geojson_request("/encode", $data);
	}

	########################################################################

	function wof_geojson_pip($geojson) {

		$data = array(
			'geojson' => $geojson
		);
		return wof_geojson_request("/pip", $data);
	}

	########################################################################

	function wof_geojson_save($geojson, $branch = 'master', $properties = null) {

		// THIS IS PART 5 (five) OF THE #datavoyage
		// Search the codebase for #datavoyage to follow along at home.
		// (20171121/dphiffer)
		$data = array(
			'geojson' => $geojson,
			'branch' => $branch
		);
		if ($properties) {
			$data['properties'] = implode(',', $properties);
		}

		// Our #datavoyage is heading to wof-geojson-server.py next...
		return wof_geojson_request("/save", $data);
	}

	########################################################################

	function wof_geojson_request($path, $data, $headers=array(), $more=array()) {

		$url = "{$GLOBALS['cfg']['geojson_base_url']}{$path}";
		$defaults = array(
			'http_timeout' => 60
		);
		$more = array_merge($defaults, $more);

		$rsp = http_post($url, $data, $headers, $more);

		if (! $rsp['body']) {
			return array(
				'ok' => 0,
				'error' => 'No response from GeoJSON service',
				'rsp' => $rsp
			);
		} else if ($rsp['code'] &&
		           ($rsp['code'] < 200 || $rsp['code'] > 299)) {
			$esc_code = intval($rsp['code']);
			return array(
				'ok' => 0,
				'error' => "HTTP $esc_code response from GeoJSON service",
				'rsp' => $rsp
			);
		}

		return json_decode($rsp['body'], 'as hash');
	}

	########################################################################

	# the end
