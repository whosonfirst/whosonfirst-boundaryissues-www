<?php

	# This is not a general purpose Logstash library. Or at least it isn't yet.
	# It is a thin wrapper to make publishing to Logstash using a Redis PubSub
	# channel a little more Flamework-like (20160408/thisissaronland)
	# Depends on https://github.com/whosonfirst/flamework-redis

	loadlib("redis");

	########################################################################

	function logstash_publish($channel, $data, $more=array()){

		$defaults = array(
			'logstash_redis_host',
			'logstash_redis_port',
		);

		$more = array_merge($defaults, $more);

		$msg = json_encode($data);

		$rsp = redis_publish($channel, $msg, $more);
		return $rsp;
	}

	########################################################################

	function omgwtf($data, $more=array()){
		return logstash_publish("omgwtf", $data, $more);
	}

	########################################################################
	
	# the end