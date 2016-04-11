<?php

	$GLOBALS['gearman_clients'] = array();

	########################################################################

	function gearman_client($more=array()){

		$rsp = gearman_client($more);

		if (! $rsp['ok']){
			return array(null, $rsp);
		}

		return array($rsp['client'], null);
	}

	########################################################################

	function gearman_client_connect($more=array()){

		$defaults = array(
			'host' => $GLOBALS['cfg']['gearman_host'],
			'port' => $GLOBALS['cfg']['gearman_port']
		);

		$more = array_merge($defaults, $more);

		$host = $more['host'];
		$port = $more['port'];

		$uri = "tcp://{$host}:{$port}";

		if (! isset($GLOBALS['gearman_clients'][$uri])){

			$client = new GearmanClient();
			$client->addServer($host, $port);

			$GLOBALS['gearman_clients'][$uri] = $client;
		}

		return array('ok' => 1, 'client' => $GLOBALS['gearman_clients'][$uri]);
	}

	########################################################################

	function gearman_client_register_job($job, $dispatch){

		list($client, $err) = gearman_client($more);

		if ($err){
			return $err;
		}

		$client->register($job, $dispatch);
	}

	########################################################################

	function gearman_client_schedule_job($task, $data){

		list($client, $err) = gearman_client($more);

		if ($err){
			return $err;
		}

		$ok = $client->doBackground($task, $data);

		return array('ok' => $ok);
	}

	########################################################################

	# the end
