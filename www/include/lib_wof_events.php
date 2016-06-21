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
			SELECT summary, details, user_id, created
			FROM boundaryissues_events
			WHERE user_id = $esc_user_id
			ORDER BY created DESC
			LIMIT 30
		");
		if ($rsp['ok']) {
			return array_map('wof_events_row', $rsp['rows']);
		} else {
			return array();
		}
	}

	########################################################################

	function wof_events_for_id($wof_id) {
		$esc_wof_id = addslashes($wof_id);
		$rsp = db_fetch("
			SELECT summary, details, user_id, created
			FROM boundaryissues_events,
			     boundaryissues_events_wof
			WHERE boundaryissues_events_wof.wof_id = $esc_wof_id
			  AND boundaryissues_events_wof.event_id = boundaryissues_events.id
			ORDER BY boundaryissues_events.created DESC
			LIMIT 30
		");
		if ($rsp['ok']) {
			return array_map('wof_events_row', $rsp['rows']);
		} else {
			return array();
		}
	}

	########################################################################

	function wof_events_row($row) {
		$row['details'] = json_decode($row['details'], 'as hash');
		if ($row['user_id']) {
			$user = users_get_by_id($row['user_id']);
			$row['user'] = array(
				'username' => $user['username'],
				'avatar' => users_get_gravatar($user['email'])
			);
		}
		return $row;
	}

	# the end
