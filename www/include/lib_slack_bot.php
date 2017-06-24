<?php

	loadlib('http');

	$GLOBALS['cfg']['slack_bot_users'] = null;

	########################################################################

	function slack_bot_msg($msg){
		if (! $GLOBALS['cfg']['enable_feature_slack_bot']){
			return array(
				'ok' => 0,
				'error' => 'enable_feature_slack_bot not enabled'
			);
		}

		$msg = preg_replace_callback('/@([a-zA-Z0-9_]+)/i', function($matches) {
			$id = slack_bot_user_id($matches[1]);
			if ($id) {
				return "<@$id>";
			} else {
				return $matches[0];
			}
		}, $msg);

		$url = $GLOBALS['cfg']['slack_bot_webhook_url'];
		$postfields = json_encode(array(
			'text' => $msg
		));
		$headers = array(
			'Content-Type: application/json'
		);
		$rsp = http_post($url, $postfields, $headers);

		return $rsp;
	}

	########################################################################

	function slack_bot_user_id($username) {
		if (! $GLOBALS['cfg']['slack_bot_users']) {
			$root = dirname(dirname(__DIR__));
			$json = file_get_contents("$root/schema/slack_users.json");
			$users = json_decode($json, 'as hash');
			$GLOBALS['cfg']['slack_bot_users'] = $users;
		} else {
			$users = $GLOBALS['cfg']['slack_bot_users'];
		}
		if ($users[$username]) {
			return $users[$username];
		} else {
			return null;
		}
	}

	# the end
