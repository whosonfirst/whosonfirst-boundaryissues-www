<?php

	function wof_repo_init($repo, $status = 'active') {
		$esc_repo = addslashes($repo);
		$esc_status = addslashes($status);
		$now = date('Y-m-d H:i:s');
		return db_insert('boundaryissues_repo', array(
			'repo' => $esc_repo,
			'status' => $status,
			'updated' => $now
		));
	}

	########################################################################

	function wof_repo_is_active($repo) {
		$rsp = wof_repo_get_status($repo);
		return ($rsp['ok'] && $rsp['status'] == 'active');
	}

	########################################################################

	function wof_repo_get_status($repo) {
		$esc_repo = addslashes($repo);
		$rsp = db_fetch("
			SELECT *
			FROM boundaryissues_repo
			WHERE repo = '$esc_repo'
		");
		if (! $rsp['ok']) {
			return $rsp;
		}

		if (! $rsp['rows']) {
			return array(
				'ok' => 0,
				'error' => "No repo entry for '$esc_repo'"
			);
		}

		$rsp = array_merge(array('ok' => 1), $rsp['rows'][0]);
		return $rsp;
	}

	########################################################################

	function wof_repo_set_status($repo, $status, $debug = null) {

		$esc_repo = addslashes($repo);
		$esc_status = addslashes($status);
		$now = date('Y-m-d H:i:s');

		$values = array(
			'status' => $esc_status,
			'updated' => $now
		);
		$where = "repo = '$esc_repo'";

		if ($debug) {
			$values['debug'] = $debug;
		} else if ($status == 'active') {
			$values['debug'] = '';
		}

		return db_update('boundaryissues_repo', $values, $where);
	}

	# the end
