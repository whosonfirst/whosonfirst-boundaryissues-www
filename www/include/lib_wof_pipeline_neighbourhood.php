<?php

	function wof_pipeline_neighbourhood($pipeline, $dry_run = false) {
		wof_pipeline_log($pipeline['id'], "Running wof_pipeline_neighbourhood", array(
			'dry_run' => $dry_run
		));
		$coin_toss = rand(0, 1); // Sometimes yes sometimes no
		if ($coin_toss) {
			wof_pipeline_log($pipeline['id'], "This is a mock pipeline; simulating failure");
			return array('ok' => 0);
		} else {
			wof_pipeline_log($pipeline['id'], "This is a mock pipeline; simulating success");
			return array('ok' => 1);
		}
	}
