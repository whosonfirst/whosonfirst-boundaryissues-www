<?php

	loadlib("redis");
	loadlib("emoji_alpha");
	$GLOBALS['notifications_channel'] = 'notifications';

	// This is a work in progress. There are a few issues that I'm not even
	// trying to address yet.
	//
	// TL;DR: this is not a fully-fledged notification system; it just
	// works for a very narrow use case.
	//
	// * Securely/privately sending user-specific notifications. Currently
	//   there is a user_ids array to specify who should receive a given
	//   message. The filtering happens in JS.
	// * Storing notifications for future discovery. Currently if you happen
	//   to not be online when the notification is emited, you just don't
	//   receive it.
	// * Keeping a read/unread status. Currently a notification is just a
	//   title/body/details JSON payload that gets blurted out and shown
	//   if the user is on a BI page.
	//
	// (20160603/dphiffer)

	########################################################################

	function notifications_publish($title, $body, $details = null) {
		$payload = array(
			'title' => emoji_alpha_filter($title),
			'body' => emoji_alpha_filter($body)
		);
		if ($details) {
			$payload['details'] = $details;
		}
		$payload = json_encode($payload);
		$rsp = redis_publish($GLOBALS['notifications_channel'], $payload);

		return $rsp;
	}

	# the end
