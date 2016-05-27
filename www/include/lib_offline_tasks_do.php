<?php

	loadlib('git');
	loadlib('github_users');
	loadlib('wof_elasticsearch');
	loadlib('wof_save');

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

		$rsp = wof_save_to_github($data['wof_id'], $oauth_token);
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

	$GLOBALS['offline_tasks_do_handlers']['update_s3'] = 'offline_tasks_do_update_s3';

	function offline_tasks_do_update_s3($data){

		$wof_id = $data['wof_id'];
		$rel = $GLOBALS['cfg']['wof_data_dir'];
		$path = wof_utils_id2relpath($wof_id);

		$rsp = wof_s3_put_file($rel, $path);
		return $rsp;
	}

	########################################################################

	$GLOBALS['offline_tasks_do_handlers']['omgwtf'] = 'offline_tasks_do_omgwtf';

	function offline_tasks_do_omgwtf($data){

		$rsp = omgwtf($data);
		return $rsp;
	}

	########################################################################

	# the end
