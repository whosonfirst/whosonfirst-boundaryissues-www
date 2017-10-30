<?php

	# see also: https://github.com/whosonfirst/go-whosonfirst-chatterbox
	# note that the "libphp-predis" package does not play nicely with the
	# built in pubsub server that is part of go-whosonfirst-chatterbox so
	# you'll need to make sure that you are routing requests through
	# actual-redis or equivalent (20171017/thisisaaronland)

	loadlib("redis");

	########################################################################

	function chatterbox_dispatch($msg, $more=array()){

		if (! features_is_enabled("chatterbox")){
			return array("ok" => 0, "error" => "chatterbox is disabled");
		}

		$hostname = gethostname();

		$defaults = array(
			"host" => $hostname,
			"application" => $GLOBALS["cfg"]["site_name"],
			"context" => $GLOBALS["cfg"]["environment"],
			"status" => "ok",
			"status_code" => 1,
		);

		$data = array_merge($defaults, $more);
		$data["details"] = $msg;

		$host = $GLOBALS["cfg"]["chatterbox_host"];
		$port = $GLOBALS["cfg"]["chatterbox_port"];
		$channel = $GLOBALS["cfg"]["chatterbox_channel"];
		$destination = $GLOBALS["cfg"]["chatterbox_destination"];

		$data["destination"] = $destination;

		$msg = json_encode($data);

		$redis_more = array(
			"host" => $host,
			"port" => $port,
		);

		try {
			$rsp = redis_publish($channel, $msg, $redis_more);
			return $rsp;
		}

		catch (Exception $e) {
			return array( "ok" => 0, "error" => $e->getMessage() );
		}
	}

	########################################################################

	# the end
