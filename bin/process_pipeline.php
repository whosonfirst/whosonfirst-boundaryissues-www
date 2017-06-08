<?php

	include("init_local.php");

	loadlib('git');
	loadlib('wof_pipeline');
	loadlib('wof_pipeline_utils');
	loadlib('wof_pipeline_neighbourhood');
	loadlib('wof_pipeline_remove_properties');

	$rsp = wof_pipeline_next();
	if (! $rsp['ok'] ||
	    ! $rsp['next']) {
		exit;
	}

	foreach ($rsp['next'] as $pipeline) {

		wof_pipeline_phase($pipeline, 'in_progress');
		wof_pipeline_log($pipeline['id'], "Processing as {$pipeline['type']} pipeline", $rsp);

		if (! preg_match('/[0-9a-zA-Z_-]+/', $pipeline['repo'])) {
			// Safety check: make sure the repo looks ok
			wof_pipeline_cleanup($pipeline);
			wof_pipeline_phase($pipeline, 'failed');
			continue;
		}

		$repo_path = wof_pipeline_repo_path($pipeline);
		$branch = "pipeline-{$pipeline['id']}";

		$rsp = git_execute($repo_path, "checkout -b $branch");
		wof_pipeline_log($pipeline['id'], "New {$pipeline['repo']} branch: $branch", $rsp);
		if (! $rsp['ok']) {
			wof_pipeline_finish($pipeline, 'failed');
			continue;
		}

		$rsp = wof_pipeline_download_files($pipeline);
		if (! $rsp['ok']) {
			wof_pipeline_finish($pipeline, 'failed');
			continue;
		}

		$pipeline['dir'] = $rsp['dir'];
		$handler = "wof_pipeline_{$pipeline['type']}";
		if (! function_exists($handler)) {
			wof_pipeline_finish($pipeline, 'failed');
			continue;
		}

		$rsp = $handler($pipeline, 'dry run');
		wof_pipeline_log($pipeline['id'], "Dry run: $handler", $rsp);
		if (! $rsp['ok']) {
			wof_pipeline_finish($pipeline, 'failed');
			continue;
		}

		$rsp = $handler($pipeline);
		wof_pipeline_log($pipeline['id'], "Execute: $handler", $rsp);
		if (! $rsp['ok']) {
			wof_pipeline_finish($pipeline, 'failed');
			continue;
		}

		$updated = $rsp['updated'];
		foreach ($updated as $path) {
			$rsp = git_add($repo_path, $path);
			wof_pipeline_log($pipeline['id'], "Add updated files to git index", $rsp);
			if (! $rsp['ok']) {
				wof_pipeline_finish($pipeline, 'failed');
				continue;
			}
		}

		$pipeline_id = intval($pipeline['id']);
		$emoji = ':horse:';
		$commit_msg = "$emoji pipeline $pipeline_id: {$pipeline['filename']} ({$pipeline['type']})";
		$rsp = git_commit($repo_path, $commit_msg);

		$how_many = count($updated);
		if ($how_many == 1) {
			$how_many .= ' file';
		} else {
			$how_many .= ' files';
		}
		wof_pipeline_log($pipeline['id'], "Commit changes to $how_many", $rsp);

		$rsp = git_push($repo_path);
		wof_pipeline_log($pipeline['id'], "Push commit to origin $branch", $rsp);
		if (! $rsp['ok']) {
			wof_pipeline_finish($pipeline, 'failed');
			continue;
		}

		$rsp = git_execute($repo_path, "checkout staging-work");
		wof_pipeline_log($pipeline['id'], "Checkout staging-work branch", $rsp);
		if (! $rsp['ok']) {
			wof_pipeline_finish($pipeline, 'failed');
			continue;
		}

		$rsp = git_pull($repo_path, 'origin', $branch);
		wof_pipeline_log($pipeline['id'], "Merge into staging-work branch", $rsp);
		if (! $rsp['ok']) {
			wof_pipeline_finish($pipeline, 'failed');
			continue;
		}

		$rsp = git_push($repo_path);
		wof_pipeline_log($pipeline['id'], "Push commit to origin staging-work", $rsp);
		if (! $rsp['ok']) {
			wof_pipeline_finish($pipeline, 'failed');
			continue;
		}

		$rsp = git_execute($repo_path, "checkout master");
		wof_pipeline_log($pipeline['id'], "Checkout master branch", $rsp);
		if (! $rsp['ok']) {
			wof_pipeline_finish($pipeline, 'failed');
			continue;
		}

		$rsp = git_pull($repo_path, 'origin', $branch);
		wof_pipeline_log($pipeline['id'], "Merge into master branch", $rsp);
		if (! $rsp['ok']) {
			wof_pipeline_finish($pipeline, 'failed');
			continue;
		}

		if ($pipeline['meta']['generate_meta_files']) {
			$rsp = wof_pipeline_generate_meta_files($pipeline);
			wof_pipeline_log($pipeline['id'], "Generate meta files", $rsp);
			if (! $rsp['ok']) {
				wof_pipeline_finish($pipeline, 'failed');
				continue;
			}

			$rsp = git_add($repo_path, "$repo_path/meta/*");
			wof_pipeline_log($pipeline['id'], "Add meta files to git index", $rsp);
			if (! $rsp['ok']) {
				wof_pipeline_finish($pipeline, 'failed');
				continue;
			}

			$emoji = ':horse:';
			$commit_msg = "$emoji pipeline $pipeline_id: generate meta files";
			$rsp = git_commit($repo_path, $commit_msg);
			wof_pipeline_log($pipeline['id'], "Commit meta files", $rsp);
			if (! $rsp['ok']) {
				wof_pipeline_finish($pipeline, 'failed');
				continue;
			}
		}

		$rsp = git_push($repo_path);
		wof_pipeline_log($pipeline['id'], "Push commit to origin master", $rsp);
		if (! $rsp['ok']) {
			wof_pipeline_finish($pipeline, 'failed');
			continue;
		}

		wof_pipeline_finish($pipeline, 'success');
	}
