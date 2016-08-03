<?php

	include("include/init.php");
	loadlib("audit_trail_search");

	# TO DO: check a config flag to see whether there are ACLs in place
	# (20160411/thisisaaronland)

	$args = array();
	$query = array();

	if ($page = get_int32("page")){
		$args['page'] = $page;
	}

	if ($id = get_str("pid")){
		$args['filter'] = array("pid" => $id);
		$query['pid'] = $id;
	}

	else if ($task = get_str("task")){
		$args['filter'] = array("task" => $task);
		$query['task'] = $task;
	}

	else {}

	$rsp = audit_trail_search_recent($args);

	if ($rsp['ok']){

		$pagination = $rsp['pagination'];
		$rows = $rsp['rows'];

		audit_trail_search_massage_resultset($rows);

		$GLOBALS['smarty']->assign_by_ref("pagination", $pagination);
		$GLOBALS['smarty']->assign_by_ref("rows", $rows);

		$query = http_build_query($query);
		$pagination_url = "{$GLOBALS['cfg']['abs_root_url']}audittrail/?{$query}";

		$GLOBALS['smarty']->assign("pagination_url", $pagination_url);
		$GLOBALS['smarty']->assign("pagination_page_as_queryarg", 1);
	}

	else {

		$GLOBALS['smarty']->assign("error_rsp", $rsp);
	}


	$GLOBALS['smarty']->display("page_audit_trail.txt");
	exit();

?>
