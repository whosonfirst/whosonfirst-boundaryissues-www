<?php
	include(__DIR__ . '/../www/include/init.php');
	loadlib('github_users');
	loadlib('wof_save');
	loadlib('wof_elasticsearch');

	$worker = new GearmanWorker();
	$worker->addServer();
	$worker->addFunction('save_to_github', 'gearman_save_to_github');
	$worker->addFunction('update_search_index', 'gearman_update_search_index');
	while ($worker->work());

	function gearman_save_to_github($job) {
		$details = $job->workload();
		$details = unserialize($details);

		$github_user = github_users_get_by_user_id($details['user_id']);
		if (! $github_user) {
			// No user found
			return;
		}

		$oauth_token = $github_user['oauth_token'];
		wof_save_to_github($details['geojson'], $details['geojson_data'], $oauth_token);
	}

	function gearman_update_search_index($job) {
		$details = $job->workload();
		$details = unserialize($details);

		wof_elasticsearch_update_document($details['geojson_data']);
	}

?>
