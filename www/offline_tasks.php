<?php

	include("include/init.php");
	loadlib("offline_tasks_search");

	# TO DO: check a config flag to see whether there are ACLs in place
	# (20160411/thisisaaronland)

	$args = array();

	if ($page = get_int32("page")){
		$args['page'] = $page;
	}

	if ($id = get_str("task_id")){
		$args['filter'] = array("task_id" => $id);
	}

	else if ($task = get_str("task")){
		$args['filter'] = array("task" => $task);
	}

	else if ($type = get_str("type")){
		$args['filter'] = array("type" => $type);
	}

	else {}

	$rsp = offline_tasks_search_recent($args);

	$pagination = $rsp['pagination'];
	$rows = $rsp['rows'];

	offline_tasks_search_massage_resultset($rows);

	$GLOBALS['smarty']->assign_by_ref("pagination", $pagination);
	$GLOBALS['smarty']->assign_by_ref("rows", $rows);

	$GLOBALS['smarty']->display("page_offline_tasks.txt");
	exit();

?>
