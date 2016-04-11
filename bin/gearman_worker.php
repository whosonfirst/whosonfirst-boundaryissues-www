<?php
	include(__DIR__ . '/../www/include/init.php');
	loadlib('github_users');
	loadlib('wof_save');
	loadlib('wof_elasticsearch');
	loadlib('gearman');

	/*
	Note: changes to this file, and any libraries it depends on, will
	require that you restart the worker process any time a change is made:

		sudo supervisorctl restart all

	(20160406/dphiffer)
	*/

	$worker = gearman_get_worker(array(
		'save_to_github' => 'gearman_save_to_github',
		'update_search_index' => 'gearman_update_search_index'
	));
	if (! $worker) {
		die("Unable to connect to Gearman server");
	}
	while ($worker->work());

	function gearman_save_to_github($job) {
		//dbug('gearman_worker: save_to_github');
		$details = $job->workload();
		$job_id = $job->unique();
		$details = unserialize($details);

		$github_user = github_users_get_by_user_id($details['user_id']);
		if (! $github_user) {
			gearman_log("error $job_id: couldn't find user_id '{$details['user_id']}'");
			return;
		}

		$oauth_token = $github_user['oauth_token'];
		$rsp = wof_save_to_github($details['geojson'], $details['geojson_data'], $oauth_token);

		if (! $rsp['ok']) {
			$details = trim(print_r($rsp, true));
			gearman_log("error $job_id: couldn't save to GitHub\n$details");
			return;
		}

		gearman_log("completed $job_id");
	}

	function gearman_update_search_index($job) {
		//dbug('gearman_worker: update_search_index');
		$details = $job->workload();
		$job_id = $job->unique();
		$details = unserialize($details);

		$rsp = wof_elasticsearch_update_document($details['geojson_data']);
		if (! $rsp['ok']) {
			$details = trim(print_r($rsp, true));
			gearman_log("error $job_id: couldn't update Elasticsearch\n$details");
			return;
		}

		gearman_log("completed $job_id");
	}

?>
