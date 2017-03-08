<?php

function dbug() {
	if ($GLOBALS['cfg']['environment'] != 'dev') {
		return;
	}

	if (! $GLOBALS['dbug_fh']) {
		$root = dirname(dirname(__DIR__));
		$GLOBALS['dbug_fh'] = @fopen($GLOBALS['cfg']['dbug_log'], 'a');
	}

	if (! is_resource($GLOBALS['dbug_fh'])) {
		error_log("Could not write dbug() message to {$GLOBALS['cfg']['dbug_log']}");
		return;
	}

	$args = func_get_args();
	foreach ($args as $arg) {
		if (! is_scalar($arg)) {
			$arg = print_r($arg, true);
		}
		$arg = trim($arg);
		$prefix = date('[Y-m-d H:i:s] ');
		fwrite($GLOBALS['dbug_fh'], "$prefix $arg\n");
	}
}
