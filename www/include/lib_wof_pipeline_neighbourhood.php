<?php

	########################################################################

	function wof_pipeline_neighbourhood_defaults($meta) {
		$defaults = array(
			'branch_merge' => true,
			'repo' => 'whosonfirst-data'
		);
		return array_merge($defaults, $meta);
	}

	########################################################################

	function wof_pipeline_neighbourhood_validate($pipeline) {

		// For now we will just make sure each of the filenames is .geojson.
		// Not a terribly high standard. (20171012/dphiffer)
		foreach ($pipeline['files'] as $file) {
			if (! preg_match('/\.geojson$/', $file)) {
				$esc_file = htmlspecialchars($file);
				return array(
					'ok' => 0,
					'error' => "$esc_file does not look like a GeoJSON file"
				);
			}
		}

		return array(
			'ok' => 1
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
