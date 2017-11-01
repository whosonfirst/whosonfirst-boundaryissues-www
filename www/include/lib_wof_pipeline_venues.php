<?php

	loadlib('wof_utils');
	$GLOBALS['pipeline_venue_tool_path'] = '/usr/local/mapzen/whosonfirst-pipeline-tasks/tasks/venue-tool.py';

	########################################################################

	function wof_pipeline_venues_defaults($meta) {
		$defaults = array(
			'branch_merge' => true,
			'user_confirmation' => true,
			'generate_meta_files' => true
		);
		return array_merge($defaults, $meta);
	}

	########################################################################

	wof_pipeline_venues_repo($meta) {
		return wof_utils_id2repo($meta['venues_parent']);
	}

	########################################################################

	function wof_pipeline_venues_validate($meta) {

		if (! $meta['venues_parent']) {
			return array(
				'ok' => 0,
				'error' => 'Please include a venues_parent.';
			);
		}

		return array(
			'ok' => 1
		);
	}

	########################################################################

	function wof_pipeline_venues($pipeline, $dry_run = false) {

		$pipeline_id = intval($pipeline['id']);
		$venues_parent = $pipeline['meta']['venues_parent'];

		$script = $GLOBALS['pipeline_venue_tool_path'];

		$args = "--verbose ";
		if ($dry_run) {
			$args .= ' --debug';
		}
		$args .= " $venues_parent";

		$ret = wof_pipeline_run_script($pipeline, $script, $args);
		$ret['commit_all'] = true;

		return $ret;
	}

	# the end
