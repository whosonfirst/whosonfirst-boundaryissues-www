<?php

	loadlib("http");

	# http://docs.aws.amazon.com/AmazonS3/latest/API/APIRest.html

	########################################################################

	function s3_get_bucket_url($bucket){

		$url = "http://{$bucket['id']}.s3.amazonaws.com/";
		return $url;
	}

	########################################################################
	
	function s3_get_bucket_exists($bucket) {
	   
		$url = s3_signed_object_url($bucket, '');
		$rsp = http_get($url);

		return s3_parse_response($rsp);		
	}


	# list contents of a bucket
	#   params support added for marker and prefix
	#
	
	function s3_get_bucket($bucket, $more) {
		$url = s3_signed_object_url($bucket, '', array('params' => $more));
		$rsp = http_get($url);
		return s3_parse_response($rsp);
	}

	########################################################################

	# FIX ME: allow for optionally signed requests, etc.

	function s3_get($bucket, $object_id, $args=array()){

		$query = array();

		# Note: it is your responsibility to urlencode parameters
		# because AWS is too fussy to accept things like acl=1 so
		# we can't use http_build_query (20120716/straup)

		if (isset($args['acl'])){
			$query[] = urlencode('acl');
		}

		if (count($query)){
			$query = implode("&", $query);
		}

		$date = gmdate('D, d M Y H:i:s T');
		$path = "/{$bucket['id']}/{$object_id}";

		if ($query){
			$path .= "?{$query}";
		}

		$parts = array(
			'GET',
			'',
			'',
			$date,
			$path
		);

		$raw = implode("\n", $parts);

		$sig = s3_sign_auth_string($bucket, $raw);
		$sig = base64_encode($sig);

		$auth = "AWS {$bucket['key']}:{$sig}";

		$headers = array(
			'Date' => $date,
			'Authorization' => $auth,
		);

		$bucket_url = s3_get_bucket_url($bucket);
		$object_url = $bucket_url . $object_id;

		if ($query){
			$object_url .= "?{$query}";
		}

		$rsp = http_get($object_url, $headers);
		return s3_parse_response($rsp);
	}
	
	########################################################################

	function s3_get_acl($bucket, $object_id){

		$args = array(
			'acl' => 1
		);

		$rsp = s3_get($bucket, $object_id, $args);

		if (! $rsp['ok']){
			return $rsp;
		}

		# I mean this works but still it makes me want to
		# be sad... (20120716/straup)

		$xml = new SimpleXMLElement($rsp['body']);
		$json = json_encode($xml);
		$json = json_decode($json, 'as hash');

		return array(
			'ok' => 1,
			'acl' => $json
		);

	}

	########################################################################

	function s3_put($bucket, $args, $more=array()){

		$default_args = array(
			'acl' => 'private',
		);

		$default_more = array(
			'http_timeout' => 60,
			# See this? It's important. AWS freaks out at the mere presence
			# of the 'Transfer-Encoding' header. Thanks, Roy...
			'donotsend_transfer_encoding' => 1,
		);

		$args = array_merge($default_args, $args);
		$more = array_merge($default_more, $more);

		# TO DO: account for PUT-ing of a file and
		# not just bits (aka $args['data'])

		$bytes_hashed = md5($args['data'], true);
		$bytes_enc = base64_encode($bytes_hashed);

		$date = gmdate('D, d M Y H:i:s T');
		$path = "/{$bucket['id']}/{$args['id']}";

		$parts = array();

		$parts[] = 'PUT';
		$parts[] = $bytes_enc;
		$parts[] = $args['content_type'];
		$parts[] = $date;
		$parts[] = "x-amz-acl:{$args['acl']}";
		
		if ($args['meta']){

			ksort($args['meta']);

			foreach ($args['meta'] as $k => $v){
				$parts[] = "x-amz-meta-$k:$v";
			}
		}
		
		$parts[] = $path;
		
		$raw = implode("\n", $parts);

		$sig = s3_sign_auth_string($bucket, $raw);
		$sig = base64_encode($sig);

		$auth = "AWS {$bucket['key']}:{$sig}";

		$headers = array(
			'Date' => $date,
			'X-Amz-Acl' => $args['acl'],
			'Content-Type' => $args['content_type'],
			'Content-MD5' => $bytes_enc,
			'Content-Length' => strlen($args['data']),
			'Authorization' => $auth,
		);

		if ($args['meta']){
			foreach ($args['meta'] as $k => $v){
				$headers["X-Amz-Meta-$k"] = $v;
			}
		}
		
		$bucket_url = s3_get_bucket_url($bucket);

		# enurl-ify ?
		$object_url = $bucket_url . $args['id'];

		$rsp = http_put($object_url, $args['data'], $headers, $more);
		return s3_parse_response($rsp);
	}

	########################################################################

	function s3_delete($bucket, $object_id){

		$date = gmdate('D, d M Y H:i:s T');
		$path = "/{$bucket['id']}/{$object_id}";

		$parts = array(
			"DELETE",
			'',
			'text/plain',
			$date,
			$path
		);

		$raw = implode("\n", $parts);

		$sig = s3_sign_auth_string($bucket, $raw);
		$sig = base64_encode($sig);

		$auth = "AWS {$bucket['key']}:{$sig}";

		$headers = array(
			'Date' => $date,
			'Authorization' => $auth,
			'Content-Type' => 'text/plain',
			'Content-Length' => 0
		);

		# See this? It's important. AWS freaks out at the mere presence
		# of the 'Transfer-Encoding' header. Thanks, Roy...

		$more = array(
			'donotsend_transfer_encoding' => 1,
		);

		$bucket_url = s3_get_bucket_url($bucket);
		$object_url = $bucket_url . $object_id;

		$rsp = http_delete($object_url, '', $headers, $more);
		return s3_parse_response($rsp);
	}

	########################################################################

	# http://docs.aws.amazon.com/AmazonS3/latest/API/RESTObjectCOPY.html
	# https://doc.s3.amazonaws.com/proposals/copy.html#Requesting_a_Copy_with_REST
	# http://www.bucketexplorer.com/documentation/amazon-s3--copy-s3-objects-in-a-single-operation-put-object-copy.html

	function s3_copy($bucket, $src, $dest, $args, $more=array()){

		$default_args = array(
			'acl' => 'private',
		);

		$default_more = array(
			'http_timeout' => 30,
			# See this? It's important. AWS freaks out at the mere presence
			# of the 'Transfer-Encoding' header. Thanks, Roy...
			'donotsend_transfer_encoding' => 1,
		);

		# See this? See the part where we're prefixing the copy source
		# with the bucket ID. Yeah, that part is important. See also:
		# https://github.com/cooperhewitt/parallel-tms/issues/225
		# (20140520/straup)

		$src = $bucket['id'] . '/' . $src;

		$args = array_merge($default_args, $args);
		$more = array_merge($default_more, $more);

		$date = gmdate('D, d M Y H:i:s T');
		$path = "/{$bucket['id']}/{$dest}";

		# Okay. This is ... a thing. A couple things to note:
		# The message body is empty. And not MD5-ed. Just empty.
		# So is the content type. Because... well, who knows
		# really. Also we have to include both the copy source
		# and the ACL. Most everything else is the same except
		# for all the headers that we don't bother setting below.
		# (20140314/straup)

		$bytes_enc = '';
		$content_type = '';

		$parts = array();

		$parts[] = 'PUT';
		$parts[] = $bytes_enc;
		$parts[] = $content_type;
		$parts[] = $date;
		$parts[] = "x-amz-acl:{$args['acl']}";
		$parts[] = "x-amz-copy-source:{$src}";
		$parts[] = $path;

		$raw = implode("\n", $parts);

		$sig = s3_sign_auth_string($bucket, $raw);
		$sig = base64_encode($sig);

		$auth = "AWS {$bucket['key']}:{$sig}";

		$headers = array(
			'Content-Type' => $content_type,
			'X-Amz-Acl' => $args['acl'],
			'x-amz-copy-source' => $src,
			'Authorization' => $auth,
			'Date' => $date,
		);

		$bucket_url = s3_get_bucket_url($bucket);

		# enurl-ify ?
		$object_url = $bucket_url . $dest;

		$rsp = http_put($object_url, '', $headers, $more);
		return s3_parse_response($rsp);
	}

	########################################################################

	# TO DO: Update to use s3_copy() instead of GET-ing and PUT-ing
	# objects (20140314/straup)

	function s3_rename($bucket, $old_object_id, $new_object_id, $args=array()){

		$rsp = array(
			'ok' => 0,
			'get' => null,
			'put' => null,
			'delete' => null,
			'old_object_id' => $old_object_id,
			'new_object_id' => $new_object_id,
		);

		$get_rsp = s3_get($bucket, $old_object_id);
		$rsp['get'] = $get_rsp;

		if (! $get_rsp['ok']){
			return $rsp;
		}

		# FIX ME: get ACL (if not specified in $args)

		$put_args = array(
			'id' => $new_object_id,
			'content_type' => $get_rsp['headers']['content_type'],
			'data' => $get_rsp['body'],
		);

		# note the order of precedence
		$put_args = array_merge($args, $put_args);

		$put_rsp = s3_put($bucket, $put_args);
		$rsp['put'] = $put_rsp;

		if (! $put_rsp['ok']){
			return s3_parse_response($rsp);			
		}

		$del_rsp = s3_delete($bucket, $old_object_id);
		$rsp['delete'] = $del_rsp;

		if (! $del_rsp['ok']){
			return s3_parse_response($rsp);
		}

		$rsp['ok'] = 1;
		return s3_parse_response($rsp);
	}

	########################################################################

	# see also: https://doc.s3.amazonaws.com/proposals/post.html

	function s3_signed_post_params($bucket, $args=array()){

		$defaults = array(
			'expires' => time() + 300,
			'acl' => 'private',
			'dirname' => '',
			'filename' => "\${filename}",
		);

		$args = array_merge($defaults, $args);

		if ($args['dirname']){
			$args['dirname'] = ltrim($args['dirname'], '/');
		}

		$key = $args['dirname'] . $args['filename'];

		$conditions = array(
			array('bucket' => $bucket['id']),
			array('acl' => $args['acl']),
			array('starts-with', '$key', $args['dirname']),
			array('redirect' => $args['redirect'])
		);

		if (isset($args['content_type'])){
			$conditions[] = array('starts-with', '$Content-Type', $args['content_type']);
		}

		if (is_array($args['amz_headers'])){

			foreach ($args['amz_headers'] as $k => $v){
				$conditions[] = array( "x-amz-meta-{$k}" => $v );
			}
		}

		$ymd = gmdate('Y-m-d', $args['expires']);
		$hmd = gmdate('H:i:s', $args['expires']);

		$policy = array(
			'expiration' => "{$ymd}T{$hmd}Z",
			'conditions' => $conditions,
		);

		$policy = json_encode($policy);
		$policy = base64_encode($policy);

		$sig = s3_sign_auth_string($bucket, $policy);
		$sig = base64_encode($sig);

		$params = array(
			'policy' => $policy,
			'signature' => $sig,
			'acl' => $args['acl'],
			'key' => $key,
			'redirect' => $args['redirect'],
			'AWSAccessKeyId' => $bucket['key'],
		);

		if (isset($args['content_type'])){
			$params['content-type'] = $args['content_type'];
		}

		if (is_array($args['amz_headers'])){

			foreach ($args['amz_headers'] as $k => $v){
				$params["x-amz-meta-{$k}"] = $v;
			}
		}

		return $params;
	}

	########################################################################

	function s3_verify_etag($bucket, $object_id, $etag){

		$more = array(
			'expires' => time() + 300,
			'method' => 'HEAD',
		);
		
		$rsp = s3_head($bucket, $object_id, $more);
		
		if (! $rsp['ok']){
			return $rsp;
		}

		$ok = ($rsp['headers']['etag'] == $etag) ? 1 : 0;

		return array(
			'ok' => $ok,
		);
	}

	########################################################################

	# http://docs.aws.amazon.com/AmazonS3/latest/API/RESTObjectHEAD.html

	function s3_head($bucket, $object_id, $args=array()) {

		$query = array();

		# Note: it is your responsibility to urlencode parameters
		# because AWS is too fussy to accept things like acl=1 so
		# we can't use http_build_query (20120716/straup)

		if (isset($args['acl'])){
			$query[] = urlencode('acl');
		}

		if (count($query)){
			$query = implode("&", $query);
		}

		$date = gmdate('D, d M Y H:i:s T');
		$path = "/{$bucket['id']}/{$object_id}";

		if ($query){
			$path .= "?{$query}";
		}

		$parts = array(
			'HEAD',
			'',
			'',
			$date,
			$path
		);

		$raw = implode("\n", $parts);

		$sig = s3_sign_auth_string($bucket, $raw);
		$sig = base64_encode($sig);

		$auth = "AWS {$bucket['key']}:{$sig}";

		$headers = array(
			'Date' => $date,
			'Authorization' => $auth,
		);

		$bucket_url = s3_get_bucket_url($bucket);
		$object_url = $bucket_url . $object_id;

		if ($query){
			$object_url .= "?{$query}";
		}

		return http_head($object_url, $headers);
	}
	
	########################################################################
		
	function s3_unsigned_object_url($bucket, $object_id){

		$bucket_url = s3_get_bucket_url($bucket);
		$object_id = s3_enurlify_object_id($object_id);

		$object_url = $bucket_url . $object_id;
		return $object_url;
	}

	########################################################################

	function s3_signed_object_url($bucket, $id, $more=array()){

		$defaults = array(
			'method' => 'GET',
			'expires' => time() + 300,
		);

		$args = array_merge($defaults, $more);

		$id = s3_enurlify_object_id($id);
		$path = "/{$bucket['id']}/{$id}";

		$parts = array(
			$args['method'],
			null,
			null,
			$args['expires'],
			$path,
		);

		$raw = implode("\n", $parts);

		$sig = s3_sign_auth_string($bucket, $raw);
		$sig = base64_encode($sig);

		$query = array(
			'Signature' => $sig,
			'AWSAccessKeyId' => $bucket['key'],
			'Expires' => $args['expires'],
		);

		if ($args['params']) {
		    $query = array_merge($query, $args['params']);
		}

		$query = http_build_query($query);

		$url = s3_unsigned_object_url($bucket, $id);

		return $url . "?" . $query;
	}

	########################################################################

	function s3_sign_auth_string(&$bucket, $raw){

		return hash_hmac('sha1', $raw, $bucket['secret'], true);
	}

	########################################################################

	function s3_enurlify_object_id($object_id){

		$object_id = rawurlencode($object_id);
		$object_id = str_replace('%2F', '/', $object_id);
		$object_id = str_replace('+', '%20', $object_id);
		return $object_id;
	}

	########################################################################

	function s3_parse_response(&$rsp){

		if (! $rsp['ok']){
			s3_append_error($rsp);			
		}

		return $rsp;
	}

	########################################################################

	function s3_append_error(&$rsp){

		try {
			$p = xml_parser_create();
			xml_parse_into_struct($p, $rsp['body'], $vals, $index);
			xml_parser_free($p);

		}

		catch (Exception $e){
			return;
		}


		$wtf = array();

		foreach ($vals as $idx => $details){
			$key = strtolower($details['tag']);
			$value = $details['value'];
			$wtf[$key] = $value;
		}

		$rsp['error'] = "{$rsp['error']}, because {$wtf['code']}: {$wtf['message']}";
		$rsp['details'] = $wtf;

		# pass-by-ref
	}

	########################################################################

	# the end
