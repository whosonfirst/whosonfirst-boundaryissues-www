<?php

	########################################################################

	function wof_pipeline_meta_files_defaults($meta) {
		$defaults = array(
			'branch_merge' => false
		);
		return array_merge($defaults, $meta);
	}

	########################################################################

	function wof_pipeline_meta_files_validate($meta) {

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

	function wof_pipeline_meta_files($pipeline, $dry_run = false) {

		$repo_data_path = wof_pipeline_repo_path($pipeline);
		$repo_path = dirname($repo_data_path);
		$bi_root = dirname(dirname(__DIR__));

		$cmd = "cd $repo_path && $bi_root/bin/wof-build-metafiles";
		$output = array();

		if (! $dry_run) {
			exec($cmd, $output);
		}

		$output = implode("\n", $output);
		$updated = glob("$repo_path/meta/*.csv");

		return array(
			'ok' => 1,
			'cmd' => $cmd,
			'output' => $output,
			'updated' => $updated
		);
	}
