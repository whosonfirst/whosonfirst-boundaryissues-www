<?php

	loadlib('wof_repo');

	########################################################################

	function api_wof_repo_get_status() {

		if (! users_acl_check_access($GLOBALS['cfg']['user'], 'can_get_repo_status')) {
			api_output_error(403, "You don't have permission to get repo status.");
		}

		$repo = get_str('repo');
		if (! $repo) {
			api_output_error(400, "Please include a 'repo' argument.");
		}

		$rsp = wof_repo_get_status($repo);
		if (! $rsp['ok']) {
			api_output_error(400, $rsp['error']);
		}

		api_output_ok($rsp);
	}

	########################################################################

	function api_wof_repo_set_status() {

		if (! users_acl_check_access($GLOBALS['cfg']['user'], 'can_set_repo_status')) {
			api_output_error(403, "You don't have permission to set repo status.");
		}

		$repo = post_str('repo');
		$status = post_str('status');
		if (! $repo || ! $status) {
			api_output_error(400, "Please include 'repo' and 'status' arguments.");
		}

		$debug = post_str('debug');

		$rsp = wof_repo_set_status($repo, $status, $debug);
		if (! $rsp['ok']) {
			api_output_error(400, $rsp['error']);
		}

		$rsp = wof_repo_get_status($repo);

		api_output_ok($rsp);
	}
