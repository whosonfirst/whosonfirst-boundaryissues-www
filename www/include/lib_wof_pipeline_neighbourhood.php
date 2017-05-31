<?php

	function wof_pipeline_neighbourhood($pipeline, $dry_run = false) {
		wof_pipeline_log($pipeline['id'], "This is a mock pipeline; bailing out");
		return array('ok' => 0);
	}
