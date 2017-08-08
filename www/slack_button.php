<?php

	include('include/init.php');
	loadlib('slack_bot');
	loadlib('wof_pipeline');

	$payload_json = post_str('payload');
	$payload = json_decode($payload_json, 'as hash');
	$response = 'Huh, something weird happened!'; // should get overridden

	$response_url = $payload['response_url'];
	$actions = $payload['actions'];
	$who = $payload['user']['name'];
	$callback_id = $payload['callback_id'];

	$slack_handle = $pipeline['meta']['slack_handle'];
	if (substr($slack_handle, 0, 1) == '@') {
		$slack_handle = substr($slack_handle, 1);
	}

	if (preg_match('/^pipeline_(\d+)$/', $callback_id, $matches)) {
		$pipeline_id = $matches[1];
		$pipeline = pipeline_get($pipeline_id);
	}

	if ($payload['token'] != $GLOBALS['cfg']['slack_bot_verification_token']) {
		$response = "You should configure 'slack_bot_verification_token.'";
	} else if (! $pipeline) {
		$response = "Could not find the pipeline '$callback_id.'";
	} else if (count($actions) != 1) {
		$response = "Hmm... the number of payload actions should be 1.";
	} else if ($who != $slack_handle) {
		$response = "Sorry, only $slack_handle can approve via Slack buttons (<{$pipeline['url']}|approve manually>).";
	} else if ($actions[0]['value'] == 'confirm') {
		$response = "$who confirmed the pipeline.";
		wof_pipeline_phase($pipeline, 'confirmed');
	} else if ($actions[0]['value'] == 'cancel') {
		$response = "$who cancelled the pipeline.";
		wof_pipeline_cancel($pipeline);
	}

	$text = $payload['original_message']['text'];

	slack_bot_msg($text, array(
		'attachments' => array(
			array(
				'fallback' => $response,
				'text' => $response,
				'color' => '#FF0081'
			)
		),
		'url' => $response_url
	));
