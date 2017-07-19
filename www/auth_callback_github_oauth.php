<?php

	include("include/init.php");

	loadlib("users_acl");
	loadlib("github_api");

	// This does not actually "sign you in." This is a way to get an OAuth
	// token so we can do fun things with the GitHub API. Basically we call
	// this once per instance and drop the result into a config file. It's
	// very bare bones, since only admins will ever see this.
	// (20170719/dphiffer)

	if (! users_acl_check_access($GLOBALS['cfg']['user'], 'can_github_oauth')) {
		error_404();
	}

	if ($GLOBALS['cfg']['github_oauth_key'] == 'READ-FROM-SECRETS' ||
	    $GLOBALS['cfg']['github_oauth_secret'] == 'READ-FROM-SECRETS') {
		die("Oh hey, you should set the github_oauth_key & github_oauth_secret configs.");
	}

	$code = get_str("code");

	if (! $code){
		$url = github_api_get_auth_url();
		header("Location: $url");
		exit;
	}

	$rsp = github_api_get_auth_token($code);

	if (! $rsp['ok']){
		dumper($rsp);
	}

	echo "
<pre>// Put this into your config file
\$GLOBALS['cfg']['github_token'] = '{$rsp['oauth_token']}';
</pre>
";
