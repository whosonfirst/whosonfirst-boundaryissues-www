<?php

	# This is not a general purpose Logstash library. Or at least it isn't yet.
	# It is a thin wrapper to make publishing to Logstash using a Redis PubSub
	# channel a little more Flamework-like (20160408/thisissaronland)
	# Depends on https://github.com/whosonfirst/flamework-redis

	loadlib("redis");

	########################################################################

	function logstash_publish($channel, $data, $more=array()){

		$defaults = array(
			"logstash_redis_host" => $GLOBALS['cfg']['logstash_redis_host'],
			"logstash_redis_port" => $GLOBALS['cfg']['logstash_redist_port'],
			"logstash_redis_channel" => $GLOBALS['cfg']['logstash_redis_channel'],
		);

		$more = array_merge($defaults, $more);

		if (! is_array($data)){
			$data = array("data" => $data);
		}

		$data['channel'] = $channel;

		# to do: add call stack information here

		$msg = json_encode($data);

		$rsp = redis_publish($more["logstash_redis_channel"], $msg, $more);
		return $rsp;
	}

	########################################################################

	function omgwtf($data, $more=array()){

		return logstash_publish("omgwtf", $data, $more);
	}

	########################################################################
	
	# the end