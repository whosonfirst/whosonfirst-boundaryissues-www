<?php

include 'init_local.php';
loadlib('wof_repo');

$rsp = db_fetch("
	SELECT repo
	FROM boundaryissues_repo
");

if (! $rsp['ok']) {
	echo "Could not load repos from db\n";
	exit;
}

$db_repos = array();
foreach ($rsp['rows'] as $row) {
	$db_repos[] = $row['repo'];
}

$dh = opendir('/usr/local/data');
while ($file = readdir($dh)) {
	if (preg_match('/^whosonfirst-data.*/', $file)) {
		if (! in_array($file, $db_repos)) {
			// The repo is checked out, but not in the db
			echo "$file: initialize db\n";
			wof_repo_init($file);
		} else {
			// Remove the repo from the queue to be cloned
			$index = array_search($repo, $db_repos);
			$db_repos = array_splice($db_repos, $index, 1);
		}
	}
}

// Remaining repos need to be cloned
foreach ($db_repos as $repo) {
	$rsp = offline_tasks_schedule_task('clone_repo', array(
		'repo' => $repo
	));
}
