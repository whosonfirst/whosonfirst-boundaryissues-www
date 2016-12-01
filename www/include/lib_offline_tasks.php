<?php

	loadlib("offline_tasks_do");
	loadlib("uuid");

	$GLOBALS['offline_tasks_hooks'] = array(
		'schedule' => null,
		'execute' => null,
	);

	########################################################################

	function offline_tasks_schedule_task($task, $data){

		if (! $GLOBALS['offline_tasks_hooks']['schedule']){
			return array("ok" => 0, 'error' => "offline tasks are misconfigured - missing 'schedule' hook");
		}

		$hook = $GLOBALS['offline_tasks_hooks']['schedule'];

		if (! function_exists($hook)){
			return array("ok" => 0, "error" => "offline tasks are misconfigured - invalid 'schedule' hook");
		}

		if (! offline_tasks_do_is_valid_task($task)){
			return array('ok' => 0, 'error' => 'invalid task: ' . $task);
		}

		$task_id = uuid_v4();

		list($usec, $sec) = explode(" ", microtime());
		$now = offline_tasks_microtime();

		$rsp = call_user_func($hook, $task, $data, $task_id);
		$data_json = json_encode($data);

		$event = array(
			'action' => 'schedule',
			'task_id' => $task_id,
			'task' => $task,
			'data' => $data_json,
			'rsp' => $rsp,
			'microtime' => $now,
		);

		logstash_publish('offline_tasks', $event);

		return $rsp;
	}

	########################################################################

	function offline_tasks_execute_task($task, $data, $task_id){

		$func = offline_tasks_do_function_name($task);
		$now = offline_tasks_microtime();

		if (! function_exists($func)){
			$rsp = array("ok" => 0, "error" => "missing handler for {$task}");
		}

		else {
			$rsp = call_user_func($func, $data);
		}

		$event = array(
			'action' => 'execute',
			'task' => $task,
			'task_id' => $task_id,
			'rsp' => $rsp,
			'microtime' => $now,
		);

		logstash_publish('offline_tasks', $event);
		return $rsp;
	}

	########################################################################

	function offline_tasks_microtime(){

		list($usec, $sec) = explode(" ", microtime());
		return (float)$sec + (float)$usec;
	}

	########################################################################

	# the end
