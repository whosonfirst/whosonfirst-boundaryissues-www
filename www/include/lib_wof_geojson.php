<?php

	loadlib("http");

	########################################################################

	function wof_geojson_encode($geojson) {

		// Use the GeoJSON pony to pretty-print the string
		$rsp = http_post('http://localhost:8181/encode', array(
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
		$rsp = http_post('http://localhost:8181/save', array(
			'geojson' => $geojson
		));

		if (! $rsp['ok']) {
			if ($rsp['body']) {
				$rsp['error'] = "Error from GeoJSON service: {$rsp['body']}";
			}
			return $rsp;
		}

		return array(
			'ok' => 1,
			'geojson' => $rsp['body']
		);
	}

	########################################################################

	# the end
