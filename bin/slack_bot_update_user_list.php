<?php

	include("init_local.php");

	loadlib('slack_bot');

	$rsp = http_post('https://slack.com/api/users.list', array(
		'token' => $GLOBALS['cfg']['slack_bot_access_token'],
		'presence' => false
	));
	$list = json_decode($rsp['body'], 'as hash');
	$users = array();
	foreach ($list['members'] as $user) {
		$name = $user['name'];
		$users[$name] = $user['id'];
	}

	$json = json_encode($users);
	file_put_contents(dirname(__DIR__) . '/schema/slack_users.json', $json);
