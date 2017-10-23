<?php

	$GLOBALS['pipeline_neighbourhood_tool_path'] = '/usr/local/mapzen/whosonfirst-pipeline-tasks/tasks/neighbourhood-tool.py';

	########################################################################

	function wof_pipeline_neighbourhood_defaults($meta) {
		$defaults = array(
			'branch_merge' => true,
			'user_confirmation' => true,
			'generate_meta_files' => true,
			'repo' => 'whosonfirst-data'
		);
		return array_merge($defaults, $meta);
	}

	########################################################################

	function wof_pipeline_neighbourhood_validate($pipeline) {

		// For now we will just make sure each of the filenames is .geojson.
		// Not a terribly high standard. (20171012/dphiffer)
		foreach ($pipeline['files'] as $file) {
			if (! preg_match('/\.geojson$/', $file)) {
				$esc_file = htmlspecialchars($file);
				return array(
					'ok' => 0,
					'error' => "$esc_file does not look like a GeoJSON file"
				);
			}
		}

		return array(
			'ok' => 1
		);
	}

	########################################################################

	function wof_pipeline_neighbourhood($pipeline, $dry_run = false) {

		$pipeline_id = intval($pipeline['id']);
		$dir = "{$GLOBALS['cfg']['wof_pending_dir']}pipeline/$pipeline_id/";
		$args = "--updates=$dir --verbose";

		if ($dry_run) {
			$args .= ' --debug';
		}

		$cmd = "{$GLOBALS['pipeline_neighbourhood_tool_path']} $args";

		$descriptor = array(
			0 => array('pipe', 'r'), // stdin
			1 => array('pipe', 'w'), // stdout
			2 => array('pipe', 'w')  // stderr
		);
		$pipes = array();
		$proc = proc_open($cmd, $descriptor, $pipes, $dir);

		if (! is_resource($proc)) {
			return array(
				'ok' => 0,
				'error' => "Couldn't talk to neighbourhood-tool.py. Sad face."
			);
		}

		fclose($pipes[0]);

		$stdout = stream_get_contents($pipes[1]);
		fclose($pipes[1]);

		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[2]);

		$exit_code = proc_close($proc);

		$rsp = array(
			'ok' => ($exit_code == 0) ? 1 : 0,
			'cwd' => $dir,
			'cmd' => $cmd,
			'stdout' => trim($stdout),
			'stderr' => trim($stderr)
		);
		if (! $rsp['ok']) {
			$sep = ($rsp['stdout'] && $rsp['stderr']) ? "\n" : '';
			$rsp['error'] = "{$rsp['stdout']}{$sep}{$rsp['stderr']}";
		}

		return $rsp;
	}
