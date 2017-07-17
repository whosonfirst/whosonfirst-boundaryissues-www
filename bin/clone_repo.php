<?php

include('init_local.php');
loadlib('offline_tasks_gearman');
loadlib('offline_tasks');

if (! $argv[1] ||
    ! preg_match('/^whosonfirst-data*/', $argv[1])) {
	die("Usage: php bin/clone_repo.php [repo]\n");
}

if (file_exists("/usr/local/data/{$argv[1]}/")) {
	die("/usr/local/data/{$argv[1]}/ already exists\n");
}

$rsp = offline_tasks_schedule_task('clone_repo', $argv[1]);
var_export($rsp);
echo "\n";
