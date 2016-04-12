<?php

	# This is not a general purpose Logstash library. Or at least it isn't yet.
	# It is a thin wrapper to make publishing to Logstash using a Redis PubSub
	# channel a little more Flamework-like (20160408/thisissaronland)
	# Depends on https://github.com/whosonfirst/flamework-redis

	loadlib("redis");
	loadlib("uuid");

	########################################################################

	function logstash_publish($event, $data, $more=array()){

		$defaults = array(
			"logstash_redis_host" => $GLOBALS['cfg']['logstash_redis_host'],
			"logstash_redis_port" => $GLOBALS['cfg']['logstash_redis_port'],
			"logstash_redis_channel" => $GLOBALS['cfg']['logstash_redis_channel'],
		);

		$more = array_merge($defaults, $more);

		if (! is_array($data)){
			$data = array("data" => $data);
		}

		# Things I've learned: keys with a leading underbar like, say "_event"
		# make Elasticsearch sad... (20160411/thisisaaronland)

		$data[ "@event" ] = $event;

		# Other things I've learned is that ES will try to be clever about keys
		# like "@event_id" (but not @eventid) and assume they are integers...
		# (20160411/thisisaaronland)

		$event_id = uuid_v4();
		$data[ "@id" ] = $event_id;

		# to do: add call stack information here
		$msg = json_encode($data);

		$redis_more = array(
			"host" => $more["logstash_redis_host"],
			"port" => $more["logstash_redis_port"],
		);

		$rsp = redis_publish($more["logstash_redis_channel"], $msg, $redis_more);
		return $rsp;
	}

	########################################################################

	function omgwtf($data, $more=array()){

		return logstash_publish("omgwtf", $data, $more);
	}

	########################################################################

	# the end
