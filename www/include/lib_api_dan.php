<?php
	loadlib('dan');
	function api_dan_hello(){
		 $rsp = dan();
		 $out = array(
		      'foo' => $rsp
		 );
		 api_output_ok($out);
	}