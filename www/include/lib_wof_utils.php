<?php

	########################################################################

	function wof_utils_id2relpath($id, $more=array()){

		$fname = wof_utils_id2fname($id, $more);
		$tree = wof_utils_id2tree($id);

		return implode(DIRECTORY_SEPARATOR, array($tree, $fname));
	}

	########################################################################

	function wof_utils_id2abspath($root, $id, $more=array()){

		 $rel = wof_utils_id2relpath($id, $more);

		 return implode(DIRECTORY_SEPARATOR, array($root, $rel));
	}

	########################################################################

	function wof_utils_id2fname($id, $more=array()){

		 # PLEASE WRITE: all the alt/display name stuff

		 return "{$id}.geojson";
	}

	########################################################################

	function wof_utils_id2tree($id){

		$tree = array();
		$tmp = $id;

		while (strlen($tmp)){

			$slice = substr($tmp, 0, 3);
			array_push($tree, $slice);

			$tmp = substr($tmp, 3);
		}

		return implode(DIRECTORY_SEPARATOR, $tree);
	}

	########################################################################

	function wof_utils_encode($geojson) {

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

	function wof_utils_save($geojson) {

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
