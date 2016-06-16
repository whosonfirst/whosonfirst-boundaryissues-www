<?php

	include("init_local.php");
	loadlib("notifications");

	if (! $argv[1]) {
		die("Usage: php notify.php announcement [user id]");
	}

	$payload = array(
		'title' => 'Announcement',
		'body' => $argv[1]
	);

	if (count($argv) > 2) {
		$payload['title'] = 'Private message';
		$payload['user_ids'] = array(intval($argv[2]));
	}

	$rsp = notifications_publish($payload);
