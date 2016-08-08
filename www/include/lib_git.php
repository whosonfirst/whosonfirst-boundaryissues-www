<?php

	loadlib('github_users');
	loadlib('github_api');

	# Aside from the general usefulness of a generic Git library we are using this
	# because the GitHub API is currently busted for WOF-sized repositories. See notes
	# in lib_github_api.php for details (20160502/thisisaaronland)

	########################################################################

	$GLOBALS['git_path'] = `which git`;
	if (! file_exists($GLOBALS['git_path'])) {
		if (file_exists('/usr/local/bin/git')) {
			$GLOBALS['git_path'] = '/usr/local/bin/git';
		} else if (file_exists('/usr/bin/git')) {
			$GLOBALS['git_path'] = '/usr/bin/git';
		} else {
			die('OMGWTF WHERE IS GIT??');
		}
	}

	########################################################################

	function git_clone($cwd, $url) {

		$args = "clone $url";

		$rsp = git_execute($cwd, $args);
		if (! $rsp['ok']) {
			return array(
				'ok' => 0,
				'error' => "Error from git clone: {$rsp['error']}"
			);
		}

		// Should this handle GitHub redirects?

		return array(
			'ok' => 1,
			'cloned' => $url
		);
	}

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

	function git_commit($cwd, $message, $args = '') {

		$esc_message = escapeshellarg($message);
		$args = "commit --message=$esc_message $args";

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
		$git_pull_output = "{$rsp['output']}\n{$rsp['error']}";

		if (! $rsp['ok']) {
			return array(
				'ok' => 0,
				'error' => "Error from git pull: $git_pull_output"
			);
		}

		$success_regex = "/(.{7}\.\..{7})\s+$curr_branch\s+->\s+$remote\/$branch/m";
		$no_changes_regex = "/Current branch $curr_branch is up to date./m";
		if (! preg_match($success_regex, $git_pull_output, $success_match) &&
		    ! preg_match($no_changes_regex, $git_pull_output)) {
			return array(
				'ok' => 0,
				'error' => "Error from git pull: $git_pull_output"
			);
		}

		if ($success_match) {
			$rsp['commit_hashes'] = $success_match[1];
		}

		return $rsp;
	}

	########################################################################

	function git_push($cwd, $remote = 'origin', $branch = null, $opts = '') {

		$rsp = git_curr_branch($cwd);
		if (! $rsp['ok']) {
			return $rsp;
		}

		$curr_branch = $rsp['branch'];
		if (! $branch) {
			$branch = $curr_branch;
		}

		$args = "push $opts $remote $branch";
		$rsp = git_execute($cwd, $args);
		$git_push_output = "{$rsp['error']}{$rsp['output']}";

		if (! $rsp['ok']) {
			return array(
				'ok' => 0,
				'error' => "Error from git push: $git_push_output"
			);
		}

		$update_regex = "/.{7}\.\..{7}\s+$curr_branch -> $branch/m";
		$new_repo_regex = "/\[new branch\]\s+$curr_branch -> $branch/m";
		if (! preg_match($update_regex, $git_push_output) &&
		    ! preg_match($new_repo_regex, $git_push_output)) {
			return array(
				'ok' => 0,
				'error' => "Error from git push: $git_push_output"
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

		if (preg_match('/^\* (.+)$/m', $rsp['rsp'], $matches)) {
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

	function git_branches($cwd) {
		$rsp = git_execute($cwd, 'branch');
		if (! $rsp['ok']) {
			return $rsp;
		}

		$branches = array();
		preg_match_all('/^(\*)?\s*([a-zA-Z0-9_-]+)$/m', $rsp['rsp'], $matches);

		$rsp = array(
			'ok' => 1,
			'branches' => $matches[2]
		);

		foreach ($matches[1] as $index => $selected) {
			if ($selected == '*') {
				$rsp['selected'] = $matches[2][$index];
			}
		}

		return $rsp;
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

		$rsp = array(
			'ok' => 1,
			'output' => trim($output),
			'error' => trim($error),
			'rsp' => trim($error) . trim($output)
		);
		if (function_exists('audit_trail')) {
			// Audit all the git commands!
			audit_trail('git_execute', $rsp, array(
				'cwd' => $cwd,
				'cmd' => "git $args"
			));
		}

		// Originally this would return 'ok' => 0 if it got back a non-
		// empty STDERR value. Then I noticed that `git hash-object` was
		// using STDERR to return the hash value. Plus, it seems that
		// STDOUT is used to convey info about a failed `git push` or
		// `git pull`, so now I pass both values back and expect the
		// caller to take the right action. (20160502/dphiffer)

		return $rsp;
	}
