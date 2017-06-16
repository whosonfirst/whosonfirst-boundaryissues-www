<?php

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
