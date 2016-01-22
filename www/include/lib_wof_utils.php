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

	# the end
