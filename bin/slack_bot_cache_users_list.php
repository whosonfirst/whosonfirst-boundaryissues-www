<?php

	include("init_local.php");
	loadlib('slack_bot');

	$rsp = slack_bot_load_users_list();
	if (! $rsp['ok']){
		print_r($rsp);
		exit(1);
	}

	$users = $rsp['users_list'];
	$json = json_encode($users, JSON_PRETTY_PRINT);
	$cache_file = $GLOBALS['cfg']['slack_bot_users_list'];
	file_put_contents($cache_file, $json);
