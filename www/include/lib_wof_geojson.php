<?php

	loadlib("http");

	########################################################################

	function wof_geojson_encode($geojson) {

		// Use the GeoJSON pony to pretty-print the string
		$rsp = http_post("{$GLOBALS['cfg']['geojson_base_url']}/encode", array(
			'geojson' => $geojson
		));

		if (! $rsp['ok']) {
			if ($rsp['body']) {
				$rsp['error'] = "Error from GeoJSON service: {$rsp['body']}";
			}
			return $rsp;
		}

		$rsp = json_decode($rsp['body'], true);
		if (! $rsp['ok']) {
			return $rsp;
		}

		return array(
			'ok' => 1,
			'encoded' => $rsp['encoded']
		);
	}

	########################################################################

	function wof_geojson_save($geojson, $branch = 'master') {

		// Save a GeoJSON file to disk
		$rsp = http_post("{$GLOBALS['cfg']['geojson_base_url']}/save", array(
			'geojson' => $geojson,
			'branch' => $branch
		));

		// Check for connection errors
		if (! $rsp['ok']) {
			$rsp['error'] = "Error connecting to GeoJSON service: {$rsp['body']}";
			return $rsp;
		}

		$rsp = json_decode($rsp['body'], true);
		if (! $rsp['ok']) {
			return $rsp;
		}

		return array(
			'ok' => 1,
			'geojson' => $rsp['geojson']
		);
	}

	########################################################################

	# the end
