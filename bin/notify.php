<?php

	include("init_local.php");
	loadlib("notifications");

	if (! $argv[1]) {
		die("Usage: php notify.php announcement [user id]");
	}

	$title = 'Announcement';
	$body = $argv[1];
	$details = array();

	if (count($argv) > 2) {
		$title = 'Private message';
		$details['user_ids'] = array(intval($argv[2]));
	}

	$rsp = notifications_publish($title, $body, $details);
