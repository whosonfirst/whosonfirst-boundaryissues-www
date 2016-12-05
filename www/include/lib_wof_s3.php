<?php

	loadlib("s3");

	########################################################################

	function wof_s3_put_file($wof_id, $args=array(), $more=array()) {

		$path = wof_utils_id2relpath($wof_id);
		$repo = wof_utils_id2repopath($wof_id);

		if (! file_exists("{$repo}{$path}")) {
			return array(
				'ok' => 0,
				'error' => "'{$repo}{$path}' not found."
			);
		}

		$data = file_get_contents("{$repo}{$path}");
		$path = "data/$path";

		$args['acl'] = rawurlencode('http://acs.amazonaws.com/groups/global/AllUsers');

		return wof_s3_put_data($data, $path, $args, $more);
	}

	########################################################################

	function wof_s3_put_data($data, $path, $args=array(), $more=array()) {

		$bucket = array(
			'id' => $GLOBALS['cfg']['aws']['s3_bucket'],
			'key' => $GLOBALS['cfg']['aws']['access_key'],
			'secret' => $GLOBALS['cfg']['aws']['access_secret'],
		);

		$args = array_merge(array(
			'id' => $path,
			'data' => $data
		), $args);

		$rsp = s3_put($bucket, $args, $more);
		return $rsp;
	}

	# the end
