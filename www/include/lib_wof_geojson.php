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

	function wof_geojson_save($geojson, $branch = 'master') {

		$data = array(
			'geojson' => $geojson,
			'branch' => $branch
		);
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
		}

		return json_decode($rsp['body'], 'as hash');
	}

	########################################################################

	# the end
