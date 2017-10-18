<?php

include 'init_local.php';
loadlib('wof_repo');

if (count($argv) < 3) {
	die("Usage: php set_repo_status.php [repo] [status]\n");
}

$repo = $argv[1];
$status = $argv[2];
$rsp = wof_repo_set_status($repo, $status, 'status updated from command line');
if ($rsp['ok']) {
	echo "$repo: $status\n";
} else {
	var_export($rsp);
}
