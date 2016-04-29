<?php

	loadlib('github_users');
	loadlib('github_api');

	########################################################################

	$GLOBALS['git_path'] = '/usr/bin/git';

	########################################################################

	function git_add($path) {

		$args = "add $path";

		$rsp = git_execute($args);
		if (! $rsp['ok']) {
			return array(
				'ok' => 0,
				'error' => "Error from git add: {$rsp['error']}"
			);
		}

		return array(
			'ok' => 1,
			'added' => $path
		);
	}

	########################################################################

	function git_commit($message, $author = null) {

		$esc_message = escapeshellarg($message);
		$args = "commit -m $esc_message";

		if ($author) {
			$esc_author = escapeshellarg($author);
			$args .= " --author=$esc_author";
		}

		$rsp = git_execute($args);
		if (! $rsp['ok']) {
			return array(
				'ok' => 0,
				'error' => "Error from git commit: {$rsp['error']}"
			);
		}

		return $rsp;
	}

	########################################################################

	function git_push($remote = 'origin', $branch = 'master') {

		$args = "push $remote $branch";

		$rsp = git_execute($args);
		if (! $rsp['ok']) {
			return array(
				'ok' => 0,
				'error' => "Error from git push: {$rsp['error']}"
			);
		}

		return $rsp;
	}

	########################################################################

	function git_execute($args) {

		$cmd = "{$GLOBALS['git_path']} $args";
		$cwd = $GLOBALS['cfg']['wof_data_dir'];

		$descriptor = array(
			1 => array('pipe', 'w'), // stdout
			2 => array('pipe', 'w')  // stderr
		);
		$pipes = array();
		$proc = proc_open($cmd, $descriptor, $pipes, $cwd);

		if (! is_resource($proc)) {
			return array(
				'ok' => 0,
				'error' => "Couldn't talk to git. Sad face."
			);
		}

		$error = stream_get_contents($pipes[1]);
		$output = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		proc_close($proc);

		if ($error) {
			return array(
				'ok' => 0,
				'error' => $error
			);
		}

		return array(
			'ok' => 1,
			'output' => $output
		);
	}
