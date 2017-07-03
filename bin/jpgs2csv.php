<?php

if (empty($argv[1]) ||
	! is_dir($argv[1])) {
	die("Usage: php jpgs2csv.php /path/to/folder\n");
}

$dir = $argv[1];
$jpgs = array();

if (! is_writable($dir)) {
	die("Error: cannot write to $dir");
}

// Normalize to trim trailing slash
if (substr($dir, -1, 1) == '/') {
	$dir = substr($dir, 0, -1);
}

$dh = opendir($dir);
while ($file = readdir($dh)) {
	if (preg_match('/\.jpe?g$/i', $file)) {
		$jpgs[] = $file;
	}
}

$out = basename($dir) . '.csv';
$csv = fopen("$dir/$out", 'w');
fputcsv($csv, array(
	'filename',
	'latitude',
	'longitude'
));

$count = 0;
foreach ($jpgs as $jpg) {
	$exif = exif_read_data("$dir/$jpg");
	if (empty($exif["GPSLongitude"]) ||
	    empty($exif['GPSLongitudeRef']) ||
	    empty($exif["GPSLatitude"]) ||
	    empty($exif['GPSLatitudeRef'])) {
		echo "$jpg: no geotag data found (skipping)\n";
		continue;
	}
	$lon = get_geo_exif($exif["GPSLongitude"], $exif['GPSLongitudeRef']);
	$lat = get_geo_exif($exif["GPSLatitude"], $exif['GPSLatitudeRef']);
	echo "$jpg: $lat, $lon\n";
	fputcsv($csv, array($jpg, $lat, $lon));
	$count++;
}

fclose($csv);
echo "Wrote $count rows to $dir/$out\n";


// Based on https://stackoverflow.com/a/2572991/937170
function get_geo_exif($coord, $hemi) {

	$degrees = count($coord) > 0 ? parse_coord($coord[0]) : 0;
	$minutes = count($coord) > 1 ? parse_coord($coord[1]) : 0;
	$seconds = count($coord) > 2 ? parse_coord($coord[2]) : 0;

	$flip = ($hemi == 'W' || $hemi == 'S') ? -1 : 1;

	return $flip * ($degrees + $minutes / 60 + $seconds / 3600);
}

function parse_coord($coord_part) {

	$parts = explode('/', $coord_part);

	if (count($parts) == 0) {
		return 0;
	}

	if (count($parts) == 1) {
		return $parts[0];
	}

	return floatval($parts[0]) / floatval($parts[1]);
}
