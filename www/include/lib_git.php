<?php

	loadlib('github_users');
	loadlib('github_api');

	# Aside from the general usefulness of a generic Git library we are using this
	# because the GitHub API is currently busted for WOF-sized repositories. See notes
	# in lib_github_api.php for details (20160502/thisisaaronland)

	########################################################################

	$GLOBALS['git_path'] = '/usr/bin/git';

	########################################################################

	function git_add($cwd, $path) {

		$args = "add $path";

		$rsp = git_execute($cwd, $args);
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

	function git_commit($cwd, $message, $author = null) {

		$esc_message = escapeshellarg($message);
		$args = "commit -m $esc_message";

		if ($author) {
			$esc_author = escapeshellarg($author);
			$args .= " --author=$esc_author";
		}

		$rsp = git_execute($cwd, $args);
		if (! $rsp['ok']) {
			return array(
				'ok' => 0,
				'error' => "Error from git commit: {$rsp['error']}"
			);
		}

		return $rsp;
	}

	########################################################################

	function git_pull($cwd, $remote = 'origin', $branch = null, $opts = '') {

		$rsp = git_curr_branch($cwd);
		if (! $rsp['ok']) {
			return $rsp;
		}

		$curr_branch = $rsp['branch'];
		if (! $branch) {
			$branch = $curr_branch;
		}

		$args = "pull $opts $remote $branch";
		$rsp = git_execute($cwd, $args);

		if (! $rsp['ok']) {
			return array(
				'ok' => 0,
				'error' => "Error from git pull: {$rsp['error']}"
			);
		}

		$git_pull_output = "{$rsp['error']}{$rsp['output']}";
		$success_regex = "/.{7}\.\..{7}\s+$branch -> $curr_branch/m";
		$no_changes_regex = "/Current branch $curr_branch is up to date./m";
		if (! preg_match($success_regex, $git_pull_output) &&
		    ! preg_match($no_changes_regex, $git_pull_output)) {
			return array(
				'ok' => 0,
				'error' => "Error from git pull: {$rsp['output']}"
			);
		}

		return $rsp;
	}

	########################################################################

	function git_push($cwd, $remote = 'origin', $branch = null, $opts = '') {

		if (! $branch) {
			$rsp = git_curr_branch($cwd);
			if (! $rsp['ok']) {
				return $rsp;
			}
			$branch = $rsp['branch'];
		}

		$args = "push $opts $remote $branch";

		$rsp = git_execute($cwd, $args);
		if (! $rsp['ok']) {
			return array(
				'ok' => 0,
				'error' => "Error from git push: {$rsp['error']}"
			);
		}

		return $rsp;
	}

	########################################################################

	function git_curr_branch($cwd) {
		$rsp = git_execute($cwd, 'branch');
		if (! $rsp['ok']) {
			return $rsp;
		}

		if (preg_match('/^\* (.+)$/m', $rsp['error'], $matches)) {
			return array(
				'ok' => 1,
				'branch' => $matches[1]
			);
		}

		return array(
			'ok' => 0,
			'error' => "Could not determine which branch $cwd is tracking."
		);

	}

	########################################################################

	function git_execute($cwd, $args) {

		$cmd = "{$GLOBALS['git_path']} $args";

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

		// Originally this would return 'ok' => 0 if it got back a non-
		// empty STDERR value. Then I noticed that `git hash-object` was
		// using STDERR to return the hash value. Plus, it seems that
		// STDOUT is used to convey info about a failed `git push` or
		// `git pull`, so now I pass both values back and expect the
		// caller to take the right action. (20160502/dphiffer)

		return array(
			'ok' => 1,
			'output' => trim($output),
			'error' => trim($error)
		);
	}
