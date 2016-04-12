<?php

	include("init_local.php");
	loadlib('gearman_worker');
	loadlib('offline_tasks');

	list($worker, $err) = gearman_worker();
	if ($err) {
		die("Problem setting up worker.");
	}

	function handler($job){

		$task = $job->functionName();
		$data = $job->workload();
		$data = unserialize($data);
		$task_id = $job->unique();

		$rsp = offline_tasks_execute_task($task, $data, $task_id);

		return $rsp['ok'];
	}

	foreach ($GLOBALS['offline_tasks_do_handlers'] as $task => $ignore){
		$worker->addFunction($task, 'handler');
	}

	while ($worker->work()){

	}

	exit();
?>
