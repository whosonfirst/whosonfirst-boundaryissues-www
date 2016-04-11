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
	
	foreach ($GLOBALS['offline_tasks_do'] as $task => ignore){
		$worker->register($task, 'handler');
	}
	
	while ($worker->work()){

	}

	exit();
?>