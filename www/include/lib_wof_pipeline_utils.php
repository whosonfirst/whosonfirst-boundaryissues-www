<?php

	########################################################################

	function wof_pipeline_generate_meta_files($pipeline) {

		$repo_data_path = wof_pipeline_repo_path($pipeline);
		$repo_path = dirname($repo_data_path);

		$cmd = "cd $repo_path && /usr/local/bin/wof-build-metafiles";
		$output = array();
		exec($cmd, $output);

		$output = implode("\n", $output);

		return array(
			'ok' => 1,
			'cmd' => $cmd,
			'output' => $output
		);
	}

	########################################################################

	function wof_pipeline_repo_path($pipeline) {

		if (! $GLOBALS['cfg']['enable_feature_multi_repo']) {
			return $GLOBALS['cfg']['wof_data_dir'];
		}

		$repo = $pipeline['repo'];
		$path_template = $GLOBALS['cfg']['wof_data_dir'];
		$repo_path = str_replace('__REPO__', $repo, $path_template);

		return $repo_path;
	}

	# the end
