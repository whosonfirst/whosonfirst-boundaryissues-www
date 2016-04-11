<?php

	include("init_local.php");

	$worker = gearman_worker();

	function handler($job){

		$task = $job->task();
		$data = $job->workload();
		$data = unserialize($data);

		$rsp = offline_tasks_execute_task($task, $data);

		return $rsp['ok'];
	}

	foreach ($GLOBALS['offline_tasks_handlers'] as $task => ignore){
		$worker->addFunction($task, 'handler');
	}

	while ($worker->work()){

	}

	exit();
?>
