<?php

	########################################################################

	function gearman_worker_connect($more=array()){

		$defaults = array(
			'host' => $GLOBALS['cfg']['gearman_host'],
			'port' => $GLOBALS['cfg']['gearman_port']
		);

		$more = array_merge($defaults, $more);

		$host = $more['host'];
		$port = $more['port'];

		$uri = "tcp://{$host}:{$port}";

		$worker = new GearmanWorker();
		$server_added = $worker->addServer($host, $port);
		$worker->addOptions(GEARMAN_WORKER_GRAB_UNIQ);
		if (! $server_added){
			return array('ok' => 0, 'error' => "Could not connect to Gearman server.");
		}

		return array('ok' => 1, 'worker' => $worker);
	}

	########################################################################

	function gearman_worker($more=array()){

		$rsp = gearman_worker_connect($more);

		if (! $rsp['ok']){
			return array(null, $rsp);
		}

		return array($rsp['worker'], null);
	}

	########################################################################

	# the end
