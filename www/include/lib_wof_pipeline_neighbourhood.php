<?php

	########################################################################

	function wof_pipeline_neighbourhood_validate($pipeline) {
		// Disabled for now.
		return array(
			'ok' => 0,
			'error' => "This pipeline isn't working yet."
		);
	}

	########################################################################

	function wof_pipeline_neighbourhood($pipeline, $dry_run = false) {

		$coin_toss = rand(0, 1); // Sometimes succeed sometimes failure
		$sleep_time = rand(10, 120); // Wait some amount of time between 10s and 2m

		sleep($sleep_time);

		if ($coin_toss) {
			wof_pipeline_log($pipeline['id'], "This is a mock pipeline; simulating failure");
			return array('ok' => 0);
		} else {
			wof_pipeline_log($pipeline['id'], "This is a mock pipeline; simulating success");
			return array('ok' => 1);
		}
	}
