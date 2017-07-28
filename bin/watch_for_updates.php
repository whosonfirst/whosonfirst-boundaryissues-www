<?php

	include('init_local.php');
	loadlib('wof_pipeline');

	$data_root = 'https://whosonfirst.mapzen.com/data/';
	$endpoint = $GLOBALS['cfg']['pupssed-endpoint'];

	if (! $endpoint) {
		echo "Please configure 'pupssed-endpoint'\n";
		exit(1);
	}

	$bin = __DIR__;
	$cmd = "$bin/pubssed-client -endpoint $endpoint";

	$descriptor = array(
		1 => array('pipe', 'w'), // stdout
	);
	$pipes = array();
	$proc = proc_open($cmd, $descriptor, $pipes, $cwd);

	if (! is_resource($proc)) {
		echo "Couldn't talk to pupssed-client. Sad face.\n";
		exit(1);
	}

	while ($line = fgets($pipes[1])) {
		if (preg_match('/(\d+)\.geojson)/', $line, $matches)) {
			$path = wof_utils_find_id($wof_id);
			if ($path) {
				$geojson = file_get_contents($path);
			} else {
				$url = wof_utils_id2abspath($data_root, $wof_id);
				$rsp = http_get($url);
				if (! $rsp['ok']) {
					echo "Error loading $url: {$rsp['error']}\n";
					continue;
				}
				$geojson = $rsp['body'];
			}

			$feature = json_decode($geojson, 'as hash');
			if ($feature['properties']['wof:repo']) {
				$repo = $feature['properties']['wof:repo'];
				wof_pipeline_create(array(
					'type' => 'update_repo',
					'repo' => $repo
				));
			}
		}
	}
	fclose($pipes[1]);
	$exit_status = proc_close($proc);
