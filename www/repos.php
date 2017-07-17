<?php

	include("include/init.php");
	loadlib("wof_repo");

	if (! users_acl_check_access($GLOBALS['cfg']['user'], 'can_view_repos')) {
		error_404();
	}

	$args = array();
	$query = array();

	if ($page = get_int32("page")){
		$args['page'] = $page;
	}

	if ($repo = get_str("repo")){
		$args['repo'] = $repo;
		$query['repo'] = $repo;
	}

	$rsp = wof_repo_search($args);

	if ($rsp['ok']){

		$pagination = $rsp['pagination'];
		$rows = $rsp['rows'];

		$GLOBALS['smarty']->assign_by_ref("pagination", $pagination);
		$GLOBALS['smarty']->assign_by_ref("rows", $rows);

		$query = http_build_query($query);
		$pagination_url = "{$GLOBALS['cfg']['abs_root_url']}repos/?{$query}";

		$GLOBALS['smarty']->assign("pagination_url", $pagination_url);
		$GLOBALS['smarty']->assign("pagination_page_as_queryarg", 1);
	}

	else {

		$GLOBALS['smarty']->assign("error_rsp", $rsp);
	}


	$GLOBALS['smarty']->display("page_repos.txt");
	exit();

?>
