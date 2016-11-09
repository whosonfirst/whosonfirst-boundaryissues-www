<?php

	loadlib('git');
	loadlib('github_users');
	loadlib('wof_elasticsearch');
	loadlib('wof_save');
	loadlib('wof_s3');
	loadlib('wof_photos');
	loadlib('http');

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

		$doc = $data['feature'];

		$rsp = wof_elasticsearch_update_document($doc);
		if (! $rsp['ok']) {
			return $rsp;
		}

		if ($GLOBALS['cfg']['enable_feature_index_spelunker']) {
			// Update the Spelunker ES index
			$rsp = wof_elasticsearch_update_document($doc, array(
				'es_settings_prefix' => 'spelunker'
			));
		}
		return $rsp;
	}

	########################################################################

	$GLOBALS['offline_tasks_do_handlers']['update_s3'] = 'offline_tasks_do_update_s3';

	function offline_tasks_do_update_s3($data){

		$wof_id = $data['wof_id'];
		$rsp = wof_s3_put_file($wof_id);
		return $rsp;
	}

	########################################################################

	$GLOBALS['offline_tasks_do_handlers']['process_feature_collection'] = 'offline_tasks_do_process_feature_collection';

	function offline_tasks_do_process_feature_collection($data){

		$path = $data['upload_path'];
		$geometry = $data['geometry'];
		$properties = $data['properties'];
		$collection_uuid = $data['collection_uuid'];
		$user_id = $data['user_id'];

		$rsp = wof_save_feature_collection($path, $geometry, $properties, $collection_uuid, $user_id);
		return $rsp;
	}

	########################################################################

	$GLOBALS['offline_tasks_do_handlers']['process_feature'] = 'offline_tasks_do_process_feature';

	function offline_tasks_do_process_feature($data){

		$geojson = $data['geojson'];
		$geometry = $data['geometry'];
		$properties = $data['properties'];
		$collection_uuid = $data['collection_uuid'];
		$user_id = $data['user_id'];

		$rsp = wof_save_feature($geojson, $geometry, $properties, $collection_uuid, $user_id);
		return $rsp;
	}

	########################################################################

	$GLOBALS['offline_tasks_do_handlers']['setup_index'] = 'offline_tasks_do_setup_index';

	function offline_tasks_do_setup_index($data){
		if (wof_elasticsearch_index_exists($data['index'])) {
			return array(
				'ok' => 0,
				'error' => 'Index already exists.'
			);
		}

		if (! preg_match('/^[a-zA-Z0-9-_]+$/', $data['index'])) {
			return array(
				'ok' => 0,
				'error' => "Invalid index: {$data['index']}"
			);
		}

		$more = array();
		wof_elasticsearch_append_defaults($more);

		$server = "http://{$more['host']}:{$more['port']}";
		$source = "$server/{$more['index']}";
		$target = "$server/{$data['index']}";

		// Copy mappings from existing index
		$rsp = http_get("$source/_mappings");
		if (! $rsp) {
			return $rsp;
		}
		$body = json_decode($rsp['body'], 'as hash');

		foreach ($body as $top_level => $mappings) {
			// There should only be one item, but the index name is
			// not predictable
			$mappings = json_encode($mappings);
			break;
		}
		$rsp = http_put($target, $mappings);

		// stream2es is kind of a large-ish binary. We might want to
		// reference it from the es-whosonfirst-schema repo, but it
		// isn't currently set up by default. (20160627/dphiffer)
		$stream2es = dirname(dirname(FLAMEWORK_INCLUDE_DIR)) . '/bin/stream2es';

		$output = array();
		$source = escapeshellarg($source);
		$target = escapeshellarg($target);
		exec("$stream2es es --source $source --target $target", $output);

		// do something with the output?

		return array(
			'ok' => 1,
			'index' => $data['index']
		);
	}

	########################################################################

	$GLOBALS['offline_tasks_do_handlers']['save_photo'] = 'offline_tasks_do_save_photo';

	function offline_tasks_do_save_photo($data){

		$wof_id = $data['wof_id'];
		$type = $data['type'];
		$info_json = $data['info_json'];
		$user_id = $data['user_id'];

		$rsp = wof_photos_save($wof_id, $type, $info_json, $user_id);
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
