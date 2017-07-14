<?php

	include("init_local.php");

	if (! $GLOBALS['cfg']['enable_feature_pipeline']) {
		exit;
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

	foreach ($rsp['next'] as $pipeline) {

		// Skip for now, if the repo is inactive
		if (! wof_repo_is_active($pipeline['repo'])) {
			continue;
		}

		if ($pipeline['phase'] == 'next') {
			$phase = 'prepare';
			wof_pipeline_phase($pipeline, 'prepare');
		} else if ($pipeline['phase'] == 'confirmed') {
			$phase = 'merge';
		} else if ($pipeline['phase'] == 'resume') {
			$phase = $pipeline['meta']['last_phase'];
		} else {
			$phase = $pipeline['phase'];
		}

		switch ($phase) {
			case 'prepare':
				if (! wof_pipeline_prepare($pipeline)) {
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

		wof_pipeline_finish($pipeline, 'success');
	}
