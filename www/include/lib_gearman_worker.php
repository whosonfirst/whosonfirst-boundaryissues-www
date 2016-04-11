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
		$worker->addServer($host, $port);

		return array('ok' => 1, 'worker' => $worker);
	}

	########################################################################

	function gearman_worker($more=array()){

		$rsp = gearman_worker($more);

		if (! $rsp['ok']){
			return array(null, $rsp);
		}

		return array($rsp['worker'], null);
	}

	########################################################################

	# the end