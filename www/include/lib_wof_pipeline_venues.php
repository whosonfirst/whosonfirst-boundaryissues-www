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
		// This is where we store the repo value, even though it should
		// probably be done from wof_pipeline_venue_repo().
		return wof_utils_id2repo($meta['parent_id']);
	}

	########################################################################

	function wof_pipeline_venues_validate($meta) {

		if (! $meta['parent_id']) {
			return array(
				'ok' => 0,
				'error' => 'Please include a parent_id.';
			);
		}

		return array(
			'ok' => 1
		);
	}

	########################################################################

	function wof_pipeline_venues($pipeline, $dry_run = false) {

		$pipeline_id = intval($pipeline['id']);
		$parent_id = $pipeline['meta']['parent_id'];

		$script = $GLOBALS['pipeline_venue_tool_path'];

		$args = "--verbose ";
		if ($dry_run) {
			$args .= ' --debug';
		}
		$args .= " $parent_id";

		$ret = wof_pipeline_run_script($pipeline, $script, $args);
		$ret['commit_all'] = true;

		return $ret;
	}

	# the end
