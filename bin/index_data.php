<?php

	include(__DIR__ . "/init_local.php");

	// This script generates a very simple MySQL index of all the WOF
	// records in a given repo (or subdirectory). I'm not sure whether
	// this is something we should actually do in Boundary Issues proper,
	// so consider it exploratory at this point. (20160715/dphiffer)

	// Usage: php index_data.php /path/to/repo [repo name] [comma-separated IDs to skip (i.e., NZ)]

	if (substr($argv[0], -14, 14) == 'index_data.php' &&
	    ! empty($argv[1])) {
		$dir = $argv[1];
		if (substr($dir, 0, 1) != '/') {
			$dir = __DIR__ . "/$dir";
		}
		if (! file_exists($dir)) {
			die("Directory $dir doesn't exist!\n");
		}
		if (file_exists("$dir/data")) {
			$repo = basename($dir);
			$dir = "$dir/data";
		}
		if (! empty($argv[2])) {
			$repo = $argv[2];
		} else if (empty($repo)) {
			die("Please pass a second repo argument.\n");
		}
		if (! empty($argv[3])) {
			$skip = explode(',', $argv[3]);
		}
		index_dir($dir, $repo, $skip);
	}

	function index_dir($dir, $repo, $skip = null) {
		$dh = opendir($dir);
		$subdirs = array();
		while (($file = readdir($dh)) !== false) {
			if (preg_match('/^\d+$/', $file)) {
				$subdirs[] = $file;
			} else if (preg_match('/^(\d+)\.geojson$/', $file, $matches)) {
				if (! empty($skip) &&
				    in_array($matches[1], $skip)) {
					continue;
				}
				$rsp = index_file("$dir/$file", $repo);
				if (! $rsp['ok']) {
					exit;
				}
			}
		}
		closedir($dh);
		sort($subdirs, SORT_NUMERIC);
		foreach ($subdirs as $subdir) {
			index_dir("$dir/$subdir", $repo, $skip);
		}
	}

	function index_file($path, $repo) {
		echo "Indexing $path\n";
		$json = file_get_contents($path);
		$feature = json_decode($json, 'as hash');
		$props = $feature['properties'];
		$hash = array(
			'id' => addslashes($props['wof:id']),
			'name' => addslashes($props['wof:name']),
			'repo' => addslashes($repo)
		);
		if ($props['lbl:latitude']) {
			$hash['latitude'] = addslashes($props['lbl:latitude']);
		} else if ($props['geom:latitude']) {
			$hash['latitude'] = addslashes($props['geom:latitude']);
		}
		if ($props['lbl:longitude']) {
			$hash['longitude'] = addslashes($props['lbl:longitude']);
		} else if ($props['geom:longitude']) {
			$hash['longitude'] = addslashes($props['geom:longitude']);
		}
		if ($props['geom:bbox']) {
			$bbox = explode(',', $props['geom:bbox']);
			$hash['bbox0'] = addslashes($bbox[0]);
			$hash['bbox1'] = addslashes($bbox[1]);
			$hash['bbox2'] = addslashes($bbox[2]);
			$hash['bbox3'] = addslashes($bbox[3]);
		}
		$where = array(
			'id' => addslashes($props['wof:id'])
		);
		$rsp = db_insert_dupe('boundaryissues_wof', $hash, $where);
		return $rsp;
	}
