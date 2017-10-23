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

		$args = "clone $url $cwd";

		mkdir($cwd, 0755, true);
		if (! file_exists($cwd)) {
			return array(
				'ok' => 0,
				'error' => "Could not create '$cwd' directory"
			);
		}

		$rsp = git_execute($cwd, $args);
		if (! $rsp['ok']) {
			return $rsp;
		}

		$rsp['fetch'] = git_execute($cwd, "fetch");
		if (! $rsp['fetch']['ok']) {
			return $rsp['fetch'];
		}

		// Should (does) this handle GitHub redirects?

		$rsp['cloned'] = $url;
		return $rsp;
	}

	########################################################################

	function git_add($cwd, $path) {

		$args = "add $path";

		$rsp = git_execute($cwd, $args);
		if (! $rsp['ok']) {
			$rsp;
		}

		$rsp['added'] = $path;
		return $rsp;
	}

	########################################################################

	function git_commit($cwd, $message, $args = '') {

		$esc_message = escapeshellarg($message);
		$args = "commit --message=$esc_message $args";

		return git_execute($cwd, $args);
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
			return $rsp;
		}

		$hashes_regex = "/[0-9a-f]+\.\.[0-9a-f]+/m";
		if (preg_match($hashes_regex, $rsp['stdout'], $hashes_match)) {
			$rsp['commit_hashes'] = $hashes_match[0];
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
		if (! $rsp['ok']) {
			return $rsp;
		}

		$hashes_regex = "/[0-9a-f]+\.\.[0-9a-f]+/m";
		if (preg_match($hashes_regex, $rsp['stdout'], $hashes_match)) {
			$rsp['commit_hashes'] = $hashes_match[0];
		}

		return $rsp;
	}

	########################################################################

	function git_curr_branch($cwd) {
		$rsp = git_execute($cwd, 'branch');
		if (! $rsp['ok']) {
			return $rsp;
		}

		if (preg_match('/^\* (.+)$/m', $rsp['stderr'], $matches)) {
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

		print_r($rsp);

		$branches = array();
		preg_match_all('/^(\*)?\s*([a-zA-Z0-9_-]+)$/m', $rsp['stderr'], $matches);

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

		$stdout = stream_get_contents($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		$exit_code = proc_close($proc);

		$rsp = array(
			'ok' => ($exit_code == 0) ? 1 : 0,
			'cwd' => $cwd,
			'cmd' => $cmd,
			'stdout' => trim($stdout),
			'stderr' => trim($stderr)
		);
		if (! $rsp['ok']) {
			$sep = ($rsp['stdout'] && $rsp['stderr']) ? "\n" : '';
			$rsp['error'] = "{$rsp['stdout']}{$sep}{$rsp['stderr']}";
		}
		if (function_exists('audit_trail')) {
			// Audit all the git commands!
			audit_trail('git_execute', $rsp, array(
				'cwd' => $cwd,
				'cmd' => "git $args"
			));
		}

		return $rsp;
	}
