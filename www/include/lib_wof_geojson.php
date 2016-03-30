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

		$encoded = $rsp['body'];

		// For new entries that haven't been saved yet, remove empty top-level id param
		$encoded = str_replace("  \"id\": \"\",\n", '', $encoded);

		return array(
			'ok' => 1,
			'encoded' => $encoded
		);
	}

	########################################################################

	function wof_geojson_save($geojson) {

		// Save a GeoJSON file to disk
		$rsp = http_post("{$GLOBALS['cfg']['geojson_base_url']}/save", array(
			'geojson' => $geojson
		));

		// Check for connection errors
		if (! $rsp['ok']) {
			$rsp['error'] = "Error connecting to GeoJSON service: {$rsp['body']}";
			return;
		}

		$rsp = json_decode($rsp['body'], true);
		if (! $rsp['ok']) {
			$rsp['error'] = "Error with saving via GeoJSON service: {$rsp['error']}";
			return $rsp;
		}

		return array(
			'ok' => 1,
			'geojson' => $rsp['geojson']
		);
	}

	########################################################################

	# the end
