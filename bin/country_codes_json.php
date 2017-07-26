<?php

$country_csv = "/usr/local/data/whosonfirst-data/meta/wof-country-latest.csv";

if (! file_exists($country_csv)) {
	die("Could not find $country_csv\n");
}

$fh = fopen($country_csv, 'r');
$headers = fgetcsv($fh);

$wof_id_index = array_search('country_id', $headers);
$wof_name_index = array_search('name', $headers);
$iso_country_index = array_search('iso_country', $headers);
$wof_country_index = array_search('wof_country', $headers);

$countries = array();

while ($row = fgetcsv($fh)) {
	$wof_id = intval($row[$wof_id_index]);
	$countries[$wof_id] = array(
		'wof:id' => $wof_id,
		'wof:name' => $row[$wof_name_index],
		'iso:country' => $row[$iso_country_index],
		'wof:country' => $row[$wof_country_index]
	);
}

echo json_encode($countries, JSON_PRETTY_PRINT);
