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

	function wof_pipeline_venues_repo($meta) {

		$root = wof_utils_id2repopath($meta['venues_parent']);
		$path = wof_utils_id2abspath($root, $meta['venues_parent']);
		$parent_json = file_get_contents($path);
		$parent = json_decode($parent_json, 'as hash');

		if (! $parent['properties']['wof:hierarchy']) {
			return array(
				'ok' => 0,
				'error' => 'Could not find parent hierarchy.'
			);
		}

		$feature = array(
			'properties' => array(
				'wof:placetype' => 'venue',
				'wof:hierarchy' => $parent['properties']['wof:hierarchy']
			)
		);

		return wof_utils_pickrepo($feature);
	}

	########################################################################

	function wof_pipeline_venues_validate($meta) {

		if (! $meta['venues_parent']) {
			return array(
				'ok' => 0,
				'error' => 'Please include a venues_parent.'
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
