<?php

require_once("dbconnect.php.inc");
require_once("functions.php");

// let the browser know that we have an XML document
header("content-type: text/xml");

// begin the XML document
echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";


$dbh = dbconnect();

if (isset($_GET['station'])) {
	$stmt = "SELECT * FROM `raw_current_data` WHERE `station_id` = :station ORDER BY `processed_time` DESC LIMIT 1";
} else {
	echo "<conditions></conditions>";
	die();
}

// execute the query
$sth = $dbh->prepare($stmt);
$sth->bindParam(':station', $_GET['station'], PDO::PARAM_INT);
$sth->execute();

while ($row = $sth->fetch(PDO::FETCH_ASSOC)) {
	echo "<conditions>\n";
	echo "\t<station_id>".htmlspecialchars($row['station_id'], ENT_XML1)."</station_id>\n";
	echo "\t<time>".htmlspecialchars($row['processed_time'], ENT_XML1)."</time>\n";
	echo "\t<wind_direction>".htmlspecialchars(convertCardinal((float)$row['wind_direction']), ENT_XML1)."</wind_direction>\n";
	echo "\t<wind_speed>".htmlspecialchars($row['wind_speed'], ENT_XML1)."</wind_speed>\n";
	echo "\t<wind_gust>".htmlspecialchars($row['wind_gust'], ENT_XML1)."</wind_gust>\n";
	echo "\t<humidity>".htmlspecialchars($row['humidity'], ENT_XML1)."</humidity>\n";
	echo "\t<temperature>".htmlspecialchars($row['temperature'], ENT_XML1)."</temperature>\n";
	echo "\t<hi_temp>".htmlspecialchars($row['hi_temp'], ENT_XML1)."</hi_temp>\n";
	echo "\t<lo_temp>".htmlspecialchars($row['lo_temp'], ENT_XML1)."</lo_temp>\n";
	echo "\t<barometer>".htmlspecialchars($row['barometer'], ENT_XML1)."</barometer>\n";
	echo "\t<barotrend>".htmlspecialchars($row['barotrend'], ENT_XML1)."</barotrend>\n";
	echo "\t<rain_ytd_raw>".htmlspecialchars($row['rainytdraw'], ENT_XML1)."</rain_ytd_raw>\n";
	echo "\t<rain_ytd>".htmlspecialchars($row['rainytd'], ENT_XML1)."</rain_ytd>\n";
	echo "\t<evap>".htmlspecialchars($row['evap'], ENT_XML1)."</evap>\n";
	echo "\t<uv_index>".htmlspecialchars($row['uv_index'], ENT_XML1)."</uv_index>\n";
	echo "\t<solar_rad>".htmlspecialchars($row['solar_rad'], ENT_XML1)."</solar_rad>\n";
	echo "\t<wind_chill>".htmlspecialchars($row['wind_chill'], ENT_XML1)."</wind_chill>\n";
	echo "\t<heat_index>".htmlspecialchars($row['heat_index'], ENT_XML1)."</heat_index>\n";
	echo "\t<dew_point>".htmlspecialchars($row['dew_point'], ENT_XML1)."</dew_point>\n";
	echo "</conditions>\n";
}

?>
