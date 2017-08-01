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
		2 => array('pipe', 'w'), // stderr
	);
	$pipes = array();
	$proc = proc_open($cmd, $descriptor, $pipes, $cwd);

	if (! is_resource($proc)) {
		echo "Couldn't talk to pupssed-client. Sad face.\n";
		exit(1);
	}

	while ($line = fgets($pipes[2])) {
		if (preg_match('/(\d+)\.geojson/', $line, $matches)) {
			$wof_id = $matches[1];
			$path = wof_utils_find_id($wof_id);
			if ($path) {
				echo "found $path\n";
				$geojson = file_get_contents($path);
			} else {
				echo "loading $url ...";
				$url = wof_utils_id2abspath($data_root, $wof_id);
				$rsp = http_get($url);
				if (! $rsp['ok']) {
					echo "error: {$rsp['error']}\n";
					continue;
				}
				echo "ok\n";
				$geojson = $rsp['body'];
			}

			$feature = json_decode($geojson, 'as hash');
			if ($feature['properties']['wof:repo']) {
				$repo = $feature['properties']['wof:repo'];
				echo "update_repo $repo\n";
				wof_pipeline_create(array(
					'type' => 'update_repo',
					'repo' => $repo
				));
			}
		}
	}
	fclose($pipes[2]);
	$exit_status = proc_close($proc);
