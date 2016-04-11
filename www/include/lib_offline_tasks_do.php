<?php

	$GLOBALS['offline_tasks_do'] = array();

	########################################################################

	$GLOBALS['offline_tasks_do']['commit'] = 'offline_tasks_do_commit';

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

	$GLOBALS['offline_tasks_do']['index'] = 'offline_tasks_do_index';

	function offline_tasks_do_index($data){

		$doc = $data['geojson_data'];

		$rsp = wof_elasticsearch_update_document($doc);
		return $rsp;
	}

	########################################################################

	# the end