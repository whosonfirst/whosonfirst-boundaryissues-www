<?php

	include(__DIR__ . "/init_local.php");
	loadlib('github_users');
	loadlib('github_api');
	loadlib('git');

	// This script clones (or pulls in updates from) all the repos in the
	// whosonfirst-data org. It skips things that don't conform to the
	// pattern whosonfirst-data-venue-* and also skips duplicates like
	// whosonfirst-data-venue-us and whosonfirst-data-venue, where records
	// are available in more granular repos. (20160715/dphiffer)

	// We're not doing anything privileged with the token, so just grab one.
	$rsp = db_fetch("
		SELECT oauth_token
		FROM GithubUsers
		ORDER BY RAND()
		LIMIT 1
	");
	if (! $rsp['ok']) {
		print_r($rsp);
		exit;
	}
	$oauth_token = $rsp['rows'][0]['oauth_token'];

	$page = 1;
	$count = 0;
	$rsp = github_api_call('GET', "orgs/whosonfirst-data/repos?page=$page", $oauth_token);
	$repos = [];

	echo "Loading whosonfirst-data repos";

	while (! empty($rsp['rsp'])) {
		foreach ($rsp['rsp'] as $repo) {
			if (preg_match('/whosonfirst-data-venue-/', $repo['name'])) {
				if ($repo['name'] == 'whosonfirst-data-venue-us') {
					// Skip this since it's split into individual state repos
					continue;
				}
				//echo "{$repo['name']}\n";
				$repos[] = $repo['name'];
				$count++;
			}
		}
		echo ".";
		$page++;
		$rsp = github_api_call('GET', "orgs/whosonfirst-data/repos?page=$page", $oauth_token);
	}

	echo "\n";

	sort($repos);
	//echo "Total repos: $count\n";

	foreach ($repos as $repo) {
		$repo_dir = "/usr/local/data/$repo";
		if (! file_exists($repo_dir)) {
			$repo_url = "https://github.com/whosonfirst-data/$repo.git";
			echo "git clone $repo_url\n";
			git_clone('/usr/local/data', $repo_url);
		} else {
			echo "git pull $repo_dir\n";
			git_pull($repo_dir);
		}
		//index_dir("$repo_dir/data", $repo);
	}
