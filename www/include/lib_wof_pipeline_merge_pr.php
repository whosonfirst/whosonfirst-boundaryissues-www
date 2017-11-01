<?php

	loadlib('wof_utils');
	loadlib('wof_geojson');
	loadlib('wof_pipeline_utils');

	########################################################################

	function wof_pipeline_merge_pr_defaults($meta) {
		$defaults = array(
			'name' => "Merge {$meta['repo']} PR {$meta['pr_number']}",
			'branch_merge' => true,
			'user_confirmation' => true,
			'generate_meta_files' => true
		);
		return array_merge($defaults, $meta);


	########################################################################

	function wof_pipeline_merge_pr_validate($meta) {

		if (! $meta['repo']) {
			return array(
				'ok' => 0,
				'error' => 'No repo specified.'
			);
		}

		if (! $meta['pr_number']) {
			return array(
				'ok' => 0,
				'error' => 'No pr_number specified.'
			);
		}

		return array(
			'ok' => 1
		);
	}

	########################################################################

	function wof_pipeline_merge_pr($pipeline, $dry_run = false) {

		$verbose = $GLOBALS['cfg']['wof_pipeline_verbose'];
		$pipeline_id = $pipeline['id'];

		if ($verbose) {
			echo "[pipeline $pipeline_id] executing wof_pipeline_merge_pr";
			echo ($dry_run ? ' (dry run)' : '') . "\n";
		}

		$repo_data_path = wof_pipeline_repo_path($pipeline);
		$repo_path = dirname($repo_data_path);

		if ($GLOBALS['cfg']['github_token'] == 'READ-FROM-SECRETS') {
			return array(
				'ok' => 0,
				'error' => "Please configure 'github_token'"
			);
		}

		$owner = $GLOBALS['cfg']['wof_github_owner'];
		$repo = $pipeline['meta']['repo'];
		$number = $pipeline['meta']['pr_number'];
		$rsp = github_api_call('GET', "repos/$owner/$repo/pulls/$number", $GLOBALS['cfg']['github_token']);

		$branch = $rsp['rsp']['head']['ref'];

		if ($verbose) {
			echo "[pipeline $pipeline_id] merge PR $number branch $branch\n";
		}

		if (! $dry_run) {
			$rsp = git_pull($repo_path, 'origin', $branch);
			if (! $rsp['ok']) {
				return $rsp;
			}
		}

		return array(
			'ok' => 1,
			'updated' => array()
		);
	}
