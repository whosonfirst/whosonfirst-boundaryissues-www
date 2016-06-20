<?php

	loadlib("redis");
	loadlib("emoji_alpha");
	loadlib("wof_events");
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
	//   JSON payload that gets blurted out and shown if the user is on a BI
	//   page.
	//
	// (20160603/dphiffer)

	########################################################################

	function notifications_publish($payload) {
		if ($payload['title']) {
			$payload['title'] = emoji_alpha_filter($payload['title']);
		}
		if ($payload['body']) {
			$payload['body'] = emoji_alpha_filter($payload['body']);
		}
		$payload_json = json_encode($payload);
		$rsp = redis_publish($GLOBALS['notifications_channel'], $payload_json);

		if (! empty($payload['user_ids'])) {
			$summary = $payload['title'];
			if (!empty($payload['summary'])) {
				$summary = $payload['summary'];
			}
			$wof_ids = null;
			if ($payload['wof_ids']) {
				$wof_ids = $payload['wof_ids'];
			}
			foreach ($payload['user_ids'] as $user_id) {
				wof_events_publish($summary, $payload, $wof_ids, $user_id);
			}
		}

		return $rsp;
	}

	# the end
