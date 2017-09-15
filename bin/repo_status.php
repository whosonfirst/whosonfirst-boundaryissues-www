<?php

	include('init_local.php');
	loadlib('wof_repo');

	$summary = (array_search('-s', $argv) !== false);

	$rsp = wof_repo_search();
	if (! $rsp['ok']) {
		echo $rsp['error'] . "\n";
		exit(1);
	}

	$oldest = array(
		'seconds_since_update' => 0
	);

	foreach ($rsp['rows'] as $repo) {
		if ($repo['status'] != 'ready') {
			$repo['seconds_since_update'] = time() - strtotime($repo['updated']) - date('Z');
			if ($repo['seconds_since_update'] > $oldest['seconds_since_update']) {
				$oldest = $repo;
			}
		}
	}

	if ($summary) {
		echo $oldest['seconds_since_update'] . "\n";
	} else if ($oldest['seconds_since_update'] > 0) {
		echo "{$oldest['seconds_since_update']} seconds since {$oldest['repo']} status became {$oldest['status']}\n";
	} else {
		echo "ready\n";
	}
