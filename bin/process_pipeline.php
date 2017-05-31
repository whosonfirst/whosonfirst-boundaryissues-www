<?php

	include("init_local.php");

	loadlib('wof_pipeline');
	loadlib('wof_pipeline_neighbourhood');

	$rsp = wof_pipeline_next();
	if (! $rsp['ok'] ||
	    ! $rsp['pipeline']) {
		exit;
	}

	$pipeline = $rsp['pipeline'];
	wof_pipeline_phase($pipeline['id'], 'in_progress');
	wof_pipeline_log($pipeline['id'], "Processing as {$pipeline['type']} pipeline", $rsp);

	$rsp = wof_pipeline_download_files($pipeline);
	wof_pipeline_log($pipeline['id'], "Downloaded files", $rsp);
	if (! $rsp['ok']) {
		wof_pipeline_log($pipeline['id'], "Error: could not download files", $rsp);
		wof_pipeline_phase($pipeline['id'], 'aborted');
		exit;
	}

	$pipeline['dir'] = $rsp['dir'];
	$handler = "wof_pipeline_{$pipeline['type']}";
	if (! function_exists($handler)) {
		wof_pipeline_log($pipeline['id'], "Error: could not find handler $handler");
		wof_pipeline_phase($pipeline['id'], 'failed');
		wof_pipeline_cleanup($pipeline);
		exit;
	}

	$rsp = $handler($pipeline, 'dry run');
	if (! $rsp['ok']) {
		wof_pipeline_log($pipeline['id'], "Dry run failed; bailing out", $rsp);
		wof_pipeline_phase($pipeline['id'], 'failed');
		wof_pipeline_cleanup($pipeline);
		exit;
	}

	$rsp = $handler($pipeline);
	if (! $rsp['ok']) {
		wof_pipeline_log($pipeline['id'], "Pipeline failed", $rsp);
		wof_pipeline_phase($pipeline['id'], 'failed');
		wof_pipeline_cleanup($pipeline);
		exit;
	}

	wof_pipeline_phase($pipeline['id'], 'success');
	wof_pipeline_cleanup($pipeline);
