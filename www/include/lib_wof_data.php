<?php

	function wof_data_getpath($wof_id){
		$id_parts = str_split($wof_id, 3);
		$data_subdir = implode($id_parts, '/');
		return $GLOBALS['cfg']['wof_data_dir'] . "/data/$data_subdir/$wof_id.geojson";
	}
