<?php

	loadlib("users_acl");
	loadlib("wof_pipeline");
	loadlib("wof_repo");

	########################################################################

	function api_wof_pipeline_create() {

		if (! users_acl_check_access($GLOBALS['cfg']['user'], 'can_upload_pipelines')) {
			api_output_error(403, "You don't have permission to update pipelines.");
		}

		$meta_json = post_str('meta_json');

		if (! $meta_json) {
			api_output_error(400, "Please include 'meta_json' arguments.");
		}

		$meta = json_decode($meta_json, 'as hash');
		$rsp = wof_pipeline_create($meta);
		api_output_ok($rsp);
	}

	########################################################################

	function api_wof_pipeline_update() {

		if (! users_acl_check_access($GLOBALS['cfg']['user'], 'can_upload_pipelines')) {
			api_output_error(403, "You don't have permission to update pipelines.");
		}

		$id = post_int64('id');
		$action = post_str('action');

		if (! $id || ! $action) {
			api_output_error(400, "Please include 'id' and 'action' arguments.");
		}

		$rsp = wof_pipeline_get($id);
		if (! $rsp) {
			api_output_error(404, "Could not find pipeline $id");
		}
		$pipeline = $rsp['pipeline'];

		if ($action == 'cancel') {
			wof_pipeline_cancel($pipeline);
		} else if ($pipeline['phase'] == 'error' && $action == 'retry') {
			if (! $pipeline['meta']['last_phase']) {
				api_output_error(400, "Could not determine the pipeline's last_phase.");
			}
			wof_pipeline_phase($pipeline, 'retry');
		} else if ($pipeline['phase'] == 'confirm' && $action == 'confirm') {
			wof_pipeline_phase($pipeline, 'confirmed');
		}

		// Reload the pipeline
		$rsp = wof_pipeline_get($id);
		api_output_ok($rsp);
	}

	# the end
