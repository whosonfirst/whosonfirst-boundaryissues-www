<?php

	########################################################################

	function wof_utils_id2relpath($id, $more=array()){

		$fname = wof_utils_id2fname($id, $more);
		$tree = wof_utils_id2tree($id);

		return implode(DIRECTORY_SEPARATOR, array($tree, $fname));
	}

	########################################################################

	// wof_utils_id2abspath returns an absolute path to the WOF ID,
	// regardless of whether the file exists. This, of course, is necessary
	// for the first time you write the file to disk.

	// See also: wof_utils_find_id

	function wof_utils_id2abspath($root, $id, $more=array()){

		$rel = wof_utils_id2relpath($id, $more);

		// Check $root for a trailing slash, so we don't get two slashes
		if (substr($root, -1, 1) == DIRECTORY_SEPARATOR) {
			$root = substr($root, 0, -1);
		}
		return implode(DIRECTORY_SEPARATOR, array($root, $rel));
	}

	########################################################################

	// wof_utils_find_id checks a sequence of possible root directories
	// until it finds an absolute path for the WOF record. Returns null
	// if no existing file was found.

	// See also: wof_utils_id2abspath

	function wof_utils_find_id($id, $more=array()){

		$root_dirs = array(
			"{$GLOBALS['cfg']['wof_pending_dir']}data/",
			$GLOBALS['cfg']['wof_data_dir']
		);
		if ($more['root_dirs']) {
			$root_dirs = $more['root_dirs'];
		}

		foreach ($root_dirs as $root) {
			$path = wof_utils_id2abspath($root, $id, $more);
			if (file_exists($path)) {
				return $path;
			}
		}
		return null; // Not found!
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

	# the end
