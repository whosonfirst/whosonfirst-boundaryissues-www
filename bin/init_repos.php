<?php

include 'init_local.php';
loadlib('wof_repo');

$dh = opendir('/usr/local/data');
while ($file = readdir($dh)) {
	if (preg_match('/^whosonfirst-data.*/', $file)) {
		echo "$file\n";
		wof_repo_init($file);
	}
}
