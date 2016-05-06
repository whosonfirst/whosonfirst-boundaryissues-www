<?php

	include("init_local.php");
	loadlib("wof_save");
	loadlib("offline_tasks");
	loadlib("uuid");
	loadlib("logstash");

	$task_id = uuid_v4();
	$now = offline_tasks_microtime();
	$event = array(
		'action' => 'schedule',
		'task_id' => $task_id,
		'task' => 'save_pending',
		'data' => array(),
		'rsp' => array(),
		'microtime' => $now
	);
	logstash_publish('offline_tasks', $event);

	$rsp = wof_save_pending();

	$now = offline_tasks_microtime();
	$event = array(
		'action' => 'execute',
		'task_id' => $task_id,
		'task' => 'save_pending',
		'data' => array(),
		'rsp' => $rsp,
		'microtime' => $now
	);
	logstash_publish('offline_tasks', $event);

	exit();
?>
