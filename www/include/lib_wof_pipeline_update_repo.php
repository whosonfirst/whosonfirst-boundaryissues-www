<?php

	########################################################################

	function wof_pipeline_update_repo_defaults($meta) {
		$defaults = array();
		return array_merge($defaults, $meta);
	}

	########################################################################

	function wof_pipeline_update_repo_validate($meta) {

		if (! $meta['repo']) {
			return array(
				'ok' => 0,
				'error' => 'No repo specified.'
			);
		}

		return array(
			'ok' => 1
		);
	}

	########################################################################

	function wof_pipeline_update_repo($pipeline, $dry_run = false) {
		return array(
			'ok' => 1,
			'updated' => array()
		);
	}
