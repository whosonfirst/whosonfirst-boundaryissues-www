<?php

	loadlib('github_users');
	loadlib('wof_elasticsearch');

	$GLOBALS['offline_tasks_do_handlers'] = array();

	########################################################################

	function offline_tasks_do_is_valid_task($task){

		if (! isset($GLOBALS['offline_tasks_do_handlers'][$task])){
			return 0;
		}

		$func = offline_tasks_do_function_name($task);

		if (! function_exists($func)){
			return 0;
		}

		return 1;
	}

	########################################################################

	function offline_tasks_do_function_name($task){

		$func = "offline_tasks_do_{$task}";
		return $func;
	}

	########################################################################

	# Given that we are being explicit about function names in lib_offline_tasks
	# (offline_tasks_function_name) it's not clear why or what benefit spelling
	# them out here gets us. But today, we do... (20160411/thisisaaronland)

	########################################################################

	$GLOBALS['offline_tasks_do_handlers']['commit'] = 'offline_tasks_do_commit';

	function offline_tasks_do_commit($data){

		$github_user = github_users_get_by_user_id($data['user_id']);

		if (! $github_user) {
			return array("ok" => 0, "error" => "unvalid user ID");
		}

		$oauth_token = $github_user['oauth_token'];

		$rsp = wof_save_to_github($data['geojson'], $data['geojson_data'], $oauth_token);
		return $rsp;
	}

	########################################################################

	$GLOBALS['offline_tasks_do_handlers']['index'] = 'offline_tasks_do_index';

	function offline_tasks_do_index($data){

		$doc = $data['geojson_data'];

		$rsp = wof_elasticsearch_update_document($doc);
		return $rsp;
	}

	########################################################################

	# the end
