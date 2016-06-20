<?php

	function wof_events_publish($summary, $details, $wof_ids = null, $user_id = null) {

		$enc_summary = addslashes($summary);
		$enc_details = addslashes(json_encode($details));
		$enc_created = addslashes(time());
		$enc_user_id = addslashes($user_id);

		$rsp = db_insert('boundaryissues_events', array(
			'summary' => $enc_summary,
			'details' => $enc_details,
			'created' => $enc_created,
			'user_id' => $enc_user_id
		));

		if (! $rsp['ok']) {
			return $rsp;
		}

		if ($wof_ids) {
			$event_id = addslashes($rsp['insert_id']);
			$rows = array();
			foreach ($wof_ids as $wof_id) {
				$enc_wof_id = addslashes($wof_id);
				$rows[] = array(
					'event_id' => $event_id,
					'wof_id' => $wof_id
				);
			}
			$rsp = db_insert_bulk('boundaryissues_events_wof', $rows);

			if (! $rsp['ok']) {
				return $rsp;
			}
		}

		return array(
			'ok' => 1
		);
	}

	########################################################################

	function wof_events_for_user($user_id) {
		$esc_user_id = addslashes($user_id);
		$rsp = db_fetch("
			SELECT summary, details
			FROM boundaryissues_events
			WHERE user_id = $esc_user_id
			   OR user_id = 0
			ORDER BY created DESC
			LIMIT 30
		");
		if ($rsp['ok']) {
			$events = array_map(function($row) {
				$row['details'] = json_decode($event['details'], 'as hash');
				return $row;
			}, $rsp['rows']);
			return $events;
		} else {
			return array();
		}
	}

	# the end
