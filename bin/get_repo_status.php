<?php

include 'init_local.php';
loadlib('wof_repo');

if (count($argv) < 2) {
	die("Usage: php get_repo_status.php [repo]\n");
}

$repo = $argv[1];
$rsp = wof_repo_get_status($repo);

if ($rsp['ok'] && $rsp['status']) {
	echo "$repo: {$rsp['status']}\n";
	echo "Updated: {$rsp['updated']}\n";
	if ($rsp['debug']) {
		echo "Debug:\n{$rsp['debug']}\n";
	}
} else {
	var_export($rsp);
}
