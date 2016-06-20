<?php

	function wof_events_publish($type, $details, $wof_ids = null, $user_id = null) {

		$enc_type = addslashes($type);
		$enc_details = addslashes(json_encode($details));
		$enc_created = addslashes(time());
		$enc_user_id = addslashes($user_id);

		$rsp = db_insert('events_users', array(
			'event_type' => $enc_type,
			'event_details' => $enc_details,
			'created' => $enc_created,
			'user_id' => $enc_user_id
		));

		if (! $rsp['ok']) {
			return $rsp;
		}

		if ($wof_ids) {
			$events_id = addslashes($rsp['insert_id']);
			$rows = array();
			foreach ($wof_ids as $wof_id) {
				$enc_wof_id = addslashes($wof_id);
				$rows[] = array(
					'events_id' => $events_id,
					'wof_id' => $wof_id
				);
			}
			$rsp = db_insert_bulk('events_wof', $rows);

			if (! $rsp['ok']) {
				return $rsp;
			}
		}

		return array(
			'ok' => 1
		);
	}

	# the end
