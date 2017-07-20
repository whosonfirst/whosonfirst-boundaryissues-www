<?php

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

	function wof_repo_set_status($repo, $status, $debug = null) {

		$esc_repo = addslashes($repo);
		$esc_status = addslashes($status);
		$now = date('Y-m-d H:i:s');

		$values = array(
			'repo' => $esc_repo,
			'status' => $esc_status,
			'updated' => $now
		);

		if ($debug) {
			$values['debug'] = addslashes($debug);
		}

		return db_insert('boundaryissues_repo', $values);
	}

	########################################################################

	function wof_repo_search($args) {

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
