<?php

	loadlib("http");

	# Basically this doesn't work as of 20160502 because the following.
	# Use lib_git.php instead... (20160502/thisisaaronland)

	# From dphiffer to GitHub (20160428)
	#
	# I'm a developer on the Mapzen Boundary Issues project [1]. I recently discovered that our API calls to
	# commit via the repos/contents method have stopped working. It seems related to the large size (in terms
	# of file count) of the repo I'm working with. The same method works just fine on a "small" repo.
	# Here is a minimal demonstration of the issue using curl commands:
	# https://gist.github.com/dphiffer/0ab9b7a69510b04f1a189e5df9b43ae3

	# From GitHub (support@ / Ivan Žužak) to dphiffer (20160502)
	#
	# Just wanted to update you on this. The team identified a possible optimization that should help in cases
	# like these. We hope to include that optimization in a future version of libgit2 (the library we use for
	# Git operations), in which case all libgit2 users -- including users of the GitHub API -- could benefit
	# from it. I can't promise when that will happen, though. In the meantime, as I already mentioned -- you
	# should be able to work around these limitations by adding and committing changes in a local copy of the
	# repository and sending them to us with git push. And we'll followup with you again when these changes are
	# made in libgit2 and land in GitHub.

	#################################################################

	$GLOBALS['github_api_endpoint'] = 'https://api.github.com/';
	$GLOBALS['github_oauth_endpoint'] = 'https://github.com/login/oauth/';

	#################################################################

	function github_api_get_auth_url($redir=''){

		$callback = $GLOBALS['cfg']['abs_root_url'] . $GLOBALS['cfg']['github_oauth_callback'];

		if ($redir){
			$enc_redir = urlencode($redir);
			$callback .= "?redir={$enc_redir}";
		}

		$oauth_key = $GLOBALS['cfg']['github_oauth_key'];
		$oauth_redir = urlencode($callback);
		$github_scope = $GLOBALS['cfg']['github_api_scope'];
		$state = crumb_generate('github_auth');

		$url = "{$GLOBALS['github_oauth_endpoint']}authorize?client_id={$oauth_key}&redirect_uri={$oauth_redir}&scope={$github_scope}&state={$state}";
		return $url;
	}

	#################################################################

	function github_api_get_auth_token($code, $redir=""){

		$callback = $GLOBALS['cfg']['abs_root_url'] . $GLOBALS['cfg']['github_oauth_callback'];

		if ($redir){
			$enc_redir = urlencode($redir);
			$callback .= "?redir={$enc_redir}";
		}

		$state = crumb_generate('github_auth');

		$args = array(
			'client_id' => $GLOBALS['cfg']['github_oauth_key'],
			'client_secret' => $GLOBALS['cfg']['github_oauth_secret'],
			'code' => $code,
			'redirect_uri' => $callback,
			'state' => $state
		);

		$query = http_build_query($args);

		$url = "{$GLOBALS['github_oauth_endpoint']}access_token?{$query}";

		$headers = array();

		$more = array(
			'http_timeout' => 10
		);

		$rsp = http_get($url, $headers, $more);

		if (! $rsp['ok']){
			return $rsp;
		}

		$data = array();
		parse_str($rsp['body'], $data);

		if ((! $data) || (! $data['access_token'])){

			return array(
				'ok' => 0,
				'error' => 'failed to parse response'
			);
		}

		return array(
			'ok' => 1,
			'oauth_token' => $data['access_token']
		);
	}

	#################################################################

	function github_api_call($method, $path, $oauth_token, $args = null) {
		$more = array(
			// See: https://developer.github.com/v3/#user-agent-required
			'user_agent' => "{$GLOBALS['cfg']['site_name']} GitHub API client",
			'donotsend_transfer_encoding' => 1,
			'http_timeout' => 20
		);

		$headers = array(
			'Authorization' => "token $oauth_token",
			'Accept' => 'application/vnd.github.v3+json'
		);

		if ($method != 'GET') {
			$data = ($args) ? json_encode($args) : null;
			$headers['Content-Type'] = 'application/json';
			//$headers['Content-Length'] = mb_strlen($data);
		}

		if ($method == 'GET') {
			if ($args) {
				$query = '?' . http_build_query($args);
			}
			$rsp = http_get("{$GLOBALS['github_api_endpoint']}$path$query", $headers, $more);
		} else if ($method == 'POST') {
			$rsp = http_post("{$GLOBALS['github_api_endpoint']}$path", $data, $headers, $more);
		} else if ($method == 'PUT') {
			$rsp = http_put("{$GLOBALS['github_api_endpoint']}$path", $data, $headers, $more);
		} else if ($method == 'DELETE') {
			$rsp = http_delete("{$GLOBALS['github_api_endpoint']}$path", $data, $headers, $more);
		}

		if (! $rsp['ok']) {
			$rsp['error'] = "{$rsp['error']} {$rsp['body']}";
			return $rsp;
		} else {
			return array(
				'ok' => 1,
				'rsp' => json_decode($rsp['body'], true),
				'headers' => $rsp['headers']
			);
		}
	}

	#################################################################
