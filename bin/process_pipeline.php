<?php

	include("init_local.php");

	loadlib('wof_pipeline');
	loadlib('wof_pipeline_neighbourhood');

	$rsp = wof_pipeline_next();
	if (! $rsp['ok'] ||
	    ! $rsp['next']) {
		exit;
	}

	foreach ($rsp['next'] as $pipeline) {

		wof_pipeline_phase($pipeline, 'in_progress');
		wof_pipeline_log($pipeline['id'], "Processing as {$pipeline['type']} pipeline", $rsp);

		$rsp = wof_pipeline_download_files($pipeline);
		if (! $rsp['ok']) {
			wof_pipeline_phase($pipeline, 'failed');
			wof_pipeline_cleanup($pipeline);
			exit;
		}

		$pipeline['dir'] = $rsp['dir'];
		$handler = "wof_pipeline_{$pipeline['type']}";
		if (! function_exists($handler)) {
			wof_pipeline_phase($pipeline, 'failed');
			wof_pipeline_cleanup($pipeline);
			exit;
		}

		$rsp = $handler($pipeline, 'dry run');
		if (! $rsp['ok']) {
			wof_pipeline_phase($pipeline, 'failed');
			wof_pipeline_cleanup($pipeline);
			exit;
		}

		$rsp = $handler($pipeline);
		if (! $rsp['ok']) {
			wof_pipeline_phase($pipeline, 'failed');
			wof_pipeline_cleanup($pipeline);
			exit;
		}

		wof_pipeline_phase($pipeline, 'success');
		wof_pipeline_cleanup($pipeline);
	}
