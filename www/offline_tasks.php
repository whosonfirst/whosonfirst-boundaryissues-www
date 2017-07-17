<?php

	include("include/init.php");
	loadlib("offline_tasks_search");

	if (! users_acl_check_access($GLOBALS['cfg']['user'], 'can_view_offline_tasks')) {
		error_404();
	}

	$args = array();
	$query = array();

	if ($page = get_int32("page")){
		$args['page'] = $page;
	}

	if ($id = get_str("task_id")){
		$args['filter'] = array("task_id" => $id);
		$query['task_id'] = $id;
	}

	else if ($task = get_str("task")){
		$args['filter'] = array("task" => $task);
		$query['task'] = $task;
	}

	else if ($type = get_str("type")){
		$args['filter'] = array("type" => $type);
		$query['type'] = $type;
	}

	else if ($action = get_str("action")){
		$args['filter'] = array("action" => $action);
		$query['action'] = $action;
	}

	else {}

	$rsp = offline_tasks_search_recent($args);

	if ($rsp['ok']){

		$pagination = $rsp['pagination'];
		$rows = $rsp['rows'];

		offline_tasks_search_massage_resultset($rows);

		$GLOBALS['smarty']->assign_by_ref("pagination", $pagination);
		$GLOBALS['smarty']->assign_by_ref("rows", $rows);

		$query = http_build_query($query);
		$pagination_url = "{$GLOBALS['cfg']['abs_root_url']}offlinetasks/?{$query}";

		$GLOBALS['smarty']->assign("pagination_url", $pagination_url);
		$GLOBALS['smarty']->assign("pagination_page_as_queryarg", 1);
	}

	else {

		$GLOBALS['smarty']->assign("error_rsp", $rsp);
	}


	$GLOBALS['smarty']->display("page_offline_tasks.txt");
	exit();

?>
