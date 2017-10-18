<?php

	########################################################################

	function wof_repo_list() {
		$rsp = db_fetch("
			SELECT repo
			FROM boundaryissues_repo
			GROUP BY repo
		");
		if (! $rsp['ok']) {
			return $rsp;
		}
		$repos = array();
		foreach ($rsp['rows'] as $row) {
			$repos[] = $row['repo'];
		}
		return array(
			'ok' => 1,
			'repos' => $repos
		);
	}

	########################################################################

	function wof_repo_init($repo, $status = 'ready') {
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

	function wof_repo_is_ready($repo) {
		$rsp = wof_repo_get_status($repo);
		return ($rsp['ok'] && $rsp['status'] == 'ready');
	}

	########################################################################

	function wof_repo_get_status($repo) {
		$esc_repo = addslashes($repo);
		$rsp = db_fetch("
			SELECT *
			FROM boundaryissues_repo
			WHERE repo = '$esc_repo'
			ORDER BY id DESC
			LIMIT 1
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

	function wof_repo_set_status($repo, $status, $notes = null) {

		$esc_repo = addslashes($repo);
		$esc_status = addslashes($status);
		$now = date('Y-m-d H:i:s');

		$values = array(
			'repo' => $esc_repo,
			'status' => $esc_status,
			'updated' => $now
		);

		if ($notes) {
			$values['notes'] = addslashes($notes);
		}

		return db_insert('boundaryissues_repo', $values);
	}

	########################################################################

	function wof_repo_search($args = null) {

		if (! $args) {
			$args = array();
		}

		if ($args['repo']) {
			$esc_repo = addslashes($args['repo']);
			$sql = "SELECT * FROM boundaryissues_repo WHERE repo = '$esc_repo' ORDER BY id DESC";
			return db_fetch_paginated($sql, $args);
		} else {
			$sql = "SELECT cur.* FROM boundaryissues_repo cur LEFT JOIN boundaryissues_repo next ON cur.repo = next.repo AND cur.id < next.id WHERE next.id IS NULL ORDER BY id DESC";
			return db_fetch($sql, $args);
		}

	}

	# the end
