<?php

	include("init_local.php");

	if (! $GLOBALS['cfg']['enable_feature_pipeline']) {
		die("enable_feature_pipeline is disabled.\n");;
	}

	$verbose = (array_search('--verbose', $argv) != false);

	$lockfile = "/tmp/boundaryissues.pipeline.lock";
	if (file_exists($lockfile)) {
		die("Looks like process_pipeline.php might already be running.\nRemove $lockfile to retry.\n");
	}

	loadlib('git');
	loadlib('wof_pipeline');
	loadlib('wof_pipeline_utils');
	loadlib('wof_pipeline_meta_files');
	loadlib('wof_pipeline_neighbourhood');
	loadlib('wof_pipeline_remove_properties');

	// Since we are running parallel to other pipeline scripts, each
	// synchronized to some degree, wait for a random amount of time so
	// everyone isn't jumping in all at once. This probably could be done
	// in a more elegant way, but this is dumb and simple.
	// (20170817/dphiffer)
	$random = rand(0, 5000000); // 0-5 seconds
	if ($verbose) {
		$seconds = number_format($random / 1000000, 1);
		echo "Waiting $seconds seconds for the sake of desynchronization\n";
	}
	usleep($random);

	$rsp = wof_pipeline_next($verbose);

	if ($verbose) {
		$GLOBALS['cfg']['wof_pipeline_verbose'] = true;
	}

	if (! $rsp['ok'] ||
	    ! $rsp['next']) {
		exit;
	}

	// Set a lock file so we don't run more than one pipeline at a time
	touch($lockfile);

	foreach ($rsp['next'] as $pipeline) {

		if ($pipeline['phase'] == 'confirmed') {
			$phase = 'merge';
		} else if ($pipeline['phase'] == 'retry') {
			$phase = $pipeline['meta']['last_phase'];
		} else {
			$phase = 'prepare';
		}
		wof_pipeline_phase($pipeline, $phase);

		switch ($phase) {
			case 'prepare':
				if (! wof_pipeline_prepare($pipeline)) {
					continue 2;
				}
			case 'branch':
				if (! wof_pipeline_branch($pipeline)) {
					continue 2;
				}
			case 'execute':
				if (! wof_pipeline_execute($pipeline)) {
					continue 2;
				}
			case 'commit':
				if (! wof_pipeline_commit($pipeline)) {
					continue 2;
				}
			case 'push':
				if (! wof_pipeline_push($pipeline)) {
					continue 2;
				}
			case 'merge':
				if (! wof_pipeline_merge($pipeline)) {
					continue 2;
				}
		}
	}

	// Delete the lock file; all done!
	unlink($lockfile);
