<?php

	loadlib("offline_tasks_do");

	$GLOBALS['offline_tasks_hooks'] = array(
		'schedule' => null,
		'execute' => null,
	);

	########################################################################
	
	function uuid_v4(){
		import("random");
		return random_string(16);
	}

	########################################################################

	function offline_tasks_schedule_task($task, $data){

		$rsp = offline_tasks_hook('schedule');

		if (! $rsp['ok']){
			return $rsp;
		}

		$hook = $rsp['hook'];

		if (! offline_tasks_is_valid_task($task)){
			return array('ok' => 0, 'error' => 'invalid task');
		}

		$uuid = uuid_v4();
		$data['uuid'] = $uuid;

		$rsp = call_user_func($hook, $data);

		$event = array(
			'event' => 'schedule',
			'task' => $task,
			'id' => $uuid,
			'rsp' => $rsp,
		);

		logstash_publish('offline_tasks', $event);

		return $rsp;		
	}

	########################################################################

	function offline_tasks_execute_task($task, $data){

		$uuid = $data['uuid'];

		$event = array(
			'event' => 'execute',
			'task' => $task,
			'id' => $uuid,
			'rsp' => $rsp,
		);

		$hook = "offline_tasks_do_{$task}";

		if (! function_exists($hook)){

			$rsp = array("ok" => 0, "error" => "missing hook for {$task}");	
		}

		else {
			$rsp = call_user_func($hook, $data);
		}

		logstash_publish('offline_tasks', $event);
		return $rsp;		
	}

	########################################################################

	function offline_tasks_hook($hook){

		if (! $GLOBALS['offline_tasks_hooks'][$hook]){
			return array("ok" => 0, 'error' => "offline tasks are misconfigured - missing {$hook} hook");
		}

		$hook = $GLOBALS['offline_tasks_hooks'][$hook];

		if (! func_exists($hook)){
			return array("ok" => 0, "error" => "offline tasks are misconfigured - invalid {$hook} hook");
		}

		return array('ok' => 1, 'hook' => $hook);
	}

	########################################################################

	# the end