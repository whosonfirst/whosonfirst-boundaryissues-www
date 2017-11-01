<?php

	$GLOBALS['pipeline_neighbourhood_tool_path'] = '/usr/local/mapzen/whosonfirst-pipeline-tasks/tasks/neighbourhood-tool.py';

	########################################################################

	function wof_pipeline_neighbourhood_defaults($meta) {
		$defaults = array(
			'branch_merge' => true,
			'user_confirmation' => true,
			'generate_meta_files' => true,
			'repo' => 'whosonfirst-data'
		);
		return array_merge($defaults, $meta);
	}

	########################################################################

	function wof_pipeline_neighbourhood_validate($meta) {

		// For now we will just make sure each of the filenames is .geojson.
		// Not a terribly high standard. (20171012/dphiffer)
		foreach ($meta['files'] as $file) {
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

		$pipeline_id = intval($pipeline['id']);
		$dir = "{$GLOBALS['cfg']['wof_pending_dir']}pipeline/$pipeline_id/";

		$script = $GLOBALS['pipeline_neighbourhood_tool_path'];

		$args = "--updates=$dir --verbose";
		if ($dry_run) {
			$args .= ' --debug';
		}

		$ret = wof_pipeline_run_script($pipeline, $script, $args);
		$ret['commit_all'] = true;

		return $ret;
	}

	# the end
