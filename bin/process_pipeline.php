<?php

	include("init_local.php");

	if (! $GLOBALS['cfg']['enable_feature_pipeline']) {
		die("enable_feature_pipeline is disabled.\n");;
	}

	$lockfile = "{$GLOBALS['cfg']['wof_pending_dir']}PIPELINE_LOCKFILE";
	if (file_exists($lockfile)) {
		die("Looks like process_pipeline.php might already be running.\n");
	}

	loadlib('git');
	loadlib('wof_pipeline');
	loadlib('wof_pipeline_utils');
	loadlib('wof_pipeline_meta_files');
	loadlib('wof_pipeline_neighbourhood');
	loadlib('wof_pipeline_remove_properties');

	$rsp = wof_pipeline_next();
	if (! $rsp['ok'] ||
	    ! $rsp['next']) {
		exit;
	}

	// Set a lock file so we don't run more than one pipeline at a time
	touch($lockfile);

	foreach ($rsp['next'] as $pipeline) {

		if ($pipeline['phase'] == 'confirmed') {
			$phase = 'merge';
		} else if ($pipeline['phase'] == 'resume') {
			$phase = $pipeline['meta']['last_phase'];
		} else {
			$phase = 'prepare';
			wof_pipeline_phase($pipeline, 'prepare');
		}

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
