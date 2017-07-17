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
		$rsp = wof_s3_put_id($wof_id);
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

	$GLOBALS['offline_tasks_do_handlers']['clone_repo'] = 'offline_tasks_do_clone_repo';

	function offline_tasks_do_clone_repo($data){

		if (! $GLOBALS['cfg']['enable_feature_multi_repo']) {
			return array(
				'ok' => 0,
				'error' => 'Cannot clone repo if enable_feature_multi_repo is turned off'
			);
		}

		$repo = $data['repo'];
		if (! preg_match('/^[a-z-]+$/i', $repo)) {
			return array(
				'ok' => 0,
				'error' => 'Repo name can only have letters and hyphens'
			);
		}

		$data_dir = str_replace('__REPO__', $repo, $GLOBALS['cfg']['wof_data_dir']);
		$cwd = preg_replace('/data\/?$/', '', $data_dir);

		$org = $GLOBALS['cfg']['wof_github_owner'];
		$url = "git@github.com:$org/$repo.git";

		$output = array(
			'repo' => $repo,
			'cwd' => $cwd,
			'url' => $url
		);

		wof_repo_set_status($repo, 'cloning');
		$rsp = git_clone($cwd, $url);
		if (! $rsp['ok']) {
			return $rsp;
		}
		$output['git_clone'] = $rsp;
		wof_repo_set_status($repo, 'cloned', $rsp['output']);

		offline_tasks_schedule_task('setup_repo_lfs', array(
			'repo' => $repo
		));

		$output['ok'] = 1;
		return $output;
	}

	########################################################################

	$GLOBALS['offline_tasks_do_handlers']['setup_repo_lfs'] = 'offline_tasks_do_setup_repo_lfs';

	function offline_tasks_do_setup_repo_lfs($data){

		if (! $GLOBALS['cfg']['enable_feature_multi_repo']) {
			return array(
				'ok' => 0,
				'error' => 'Cannot clone repo if enable_feature_multi_repo is turned off'
			);
		}

		$repo = $data['repo'];
		if (! preg_match('/^[a-z-]+$/i', $repo)) {
			return array(
				'ok' => 0,
				'error' => 'Repo name can only have letters and hyphens'
			);
		}

		$data_dir = str_replace('__REPO__', $repo, $GLOBALS['cfg']['wof_data_dir']);
		$cwd = preg_replace('/data\/?$/', '', $data_dir);

		$fh = fopen("$cwd/.git/config", 'a');
		$lfs_config = <<<END
[filter "lfs"]
	clean = git-lfs clean -- %f
	smudge = git-lfs smudge -- %f
	process = git-lfs filter-process
	required = true
END;
		fwrite($fh, $lfs_config);
		fclose($fh);

		$output = array();

		$rsp = git_execute($cwd, "lfs pull");
		if (! $rsp['ok']) {
			return $rsp;
		}
		$output['lfs_pull'] = $rsp;

		$rsp = git_execute($cwd, "lfs fetch");
		if (! $rsp['ok']) {
			return $rsp;
		}
		$output['lfs_fetch'] = $rsp;

		$rsp = git_execute($cwd, "lfs checkout");
		if (! $rsp['ok']) {
			return $rsp;
		}
		$output['lfs_checkout'] = $rsp;

		$debug = "git lfs pull\n{$output['lfs_pull']['output']}\n";
		$debug = "git lfs fetch\n{$output['lfs_fetch']['output']}\n";
		$debug = "git lfs checkout\n{$output['lfs_checkout']['output']}";

		wof_repo_set_status($repo, 'setup_lfs', $debug);

		offline_tasks_schedule_task('index_repo', array(
			'repo' => $repo
		));

	}

	########################################################################

	$GLOBALS['offline_tasks_do_handlers']['index_repo'] = 'offline_tasks_do_index_repo';

	function offline_tasks_do_index_repo($data){

		if (! $GLOBALS['cfg']['enable_feature_multi_repo']) {
			return array(
				'ok' => 0,
				'error' => 'Cannot clone repo if enable_feature_multi_repo is turned off'
			);
		}

		$repo = $data['repo'];
		if (! preg_match('/^[a-z-]+$/i', $repo)) {
			return array(
				'ok' => 0,
				'error' => 'Repo name can only have letters and hyphens'
			);
		}

		$data_dir = str_replace('__REPO__', $repo, $GLOBALS['cfg']['wof_data_dir']);
		$cwd = preg_replace('/data\/?$/', '', $data_dir);

		$output = '';
		$wof_es_index = '/usr/local/bin/wof-es-index';
		$index = $GLOBALS['cfg']['wof_elasticsearch_index'];
		$host = $GLOBALS['cfg']['wof_elasticsearch_host'];
		$port = $GLOBALS['cfg']['wof_elasticsearch_port'];

		$cmd = "$wof_es_index -s $cwd --index=$index --host=$host --port=$port";
		exec($cmd, $output);

		$debug = "$cmd\n$output";
		wof_repo_set_status($repo, 'index', $debug);
		wof_repo_set_status($repo, 'ready');

		return array(
			'ok' => 1,
			'cmd' => $cmd,
			'output' => $output
		);
	}

	########################################################################

	$GLOBALS['offline_tasks_do_handlers']['omgwtf'] = 'offline_tasks_do_omgwtf';

	function offline_tasks_do_omgwtf($data){

		$rsp = omgwtf($data);
		return $rsp;
	}

	########################################################################

	# the end
