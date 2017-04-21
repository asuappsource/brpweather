<?php

require_once("dbconnect.php.inc");
require_once("functions.php");
global $xml;

if (isset($_GET['date'])  && ($t = strtotime($_GET['date']))) {
    $starttime = date('Y-m-d H:i:s', $t);
    if (isset($_GET['seconds'])) {
        $seconds = min($_GET['seconds'], 2628000); // hard limit of 1 month worth of data
        $endtime = date('Y-m-d H:i:s', $t - $seconds);
        $multiple = true;
    } else {
        $endtime = date('Y-m-d H:i:s', $t - 300);
        $multiple = false;
    }
} else {
    $starttime = date('Y-m-d H:i:s');
    if (isset($_GET['seconds'])) {
        $seconds = min($_GET['seconds'], 2628000); // hard limit of 1 month worth of data
        $endtime = date('Y-m-d H:i:s', strtotime("-$seconds seconds"));
        $multiple = true;
    } else {
        $endtime = date('Y-m-d H:i:s', strtotime('-5 minutes'));
        $multiple = false;
    }
}

if (isset($_GET['station'])) {
    header('content-type: text/xml');
    $xml = new XmlWriter();
    $xml->openMemory();
    $xml->startDocument('1.0', 'UTF-8');
    $xml->startElement('weather');
    $xml->writeElement('stations', $_GET['station']);
} else die('No station specified.');

$xml->writeElement('start', $starttime);
$xml->writeElement('end', $endtime);

$stationIds = explode(',', $_GET['station']);

$dbh = dbconnect();

foreach ($stationIds as $station) {
    if ($multiple)
        $stmt_limit = '';
    else
        $stmt_limit = ' LIMIT 1';

    //TODO: intelligently know which table to look in for older historical data
    $stmt = 'SELECT * FROM `raw_current_data` WHERE `station_id`=:station AND `processed_time` BETWEEN :end AND :start ORDER BY `processed_time` DESC'.$stmt_limit;

    $sth = $dbh->prepare($stmt);
    $sth->bindParam(':station', $station, PDO::PARAM_INT);
    $sth->bindParam(':end', $endtime);
    $sth->bindParam(':start', $starttime);
    $sth->execute();
    
    $calibs = getCalibrations($dbh, $station);

    $dailyRain = getDailyRain($dbh, $station, $starttime);
    $monthlyRain = getMonthlyRain($dbh, $station, $starttime);

    while ($row = $sth->fetch(PDO::FETCH_ASSOC)) {
        addRowToOutput($row, $calibs, $dailyRain, $monthlyRain);
    }
}

printWeatherData();

$dbh = null;

// HELPER FUNCTIONS

function getCalibrations($dbh, $station) {
    $stmt = 'SELECT * FROM `rain_calibration` WHERE `station`=:station';
    $sth = $dbh->prepare($stmt);
    $sth->bindParam(':station', $station, PDO::PARAM_INT);
    $sth->execute();
    return $sth->fetchAll();
}

function getDailyRain($dbh, $station, $starttime) {
    $dailyStmt = 'SELECT * FROM `daily_data` WHERE `station`=:station AND `date_hour_start` = :start ORDER BY `date_hour_end` DESC LIMIT 1';
    // subtract a day from the start time for the daily data query
    // $dailyEndTime = date('Y-m-d H:i:s', strtotime($starttime . ' -1 day'));
    $dailyStartTime = date('Y-m-d 00:00:00');
    $sth = $dbh->prepare($dailyStmt);
    $sth->bindParam(':station', $station, PDO::PARAM_INT);
    // $sth->bindParam(':end', $dailyEndTime);
    $sth->bindParam(':start', $dailyStartTime);

    $sth->execute();

    $row = $sth->fetch(PDO::FETCH_ASSOC);
    return $row['rain_raw'];
}

function getMonthlyRain($dbh, $station, $starttime) {
    $dailyStmt = 'SELECT * FROM `monthly_data` WHERE `station`=:station AND `date_hour_start` = :start ORDER BY `date_hour_end` DESC LIMIT 1';
    // subtract a day from the start time for the daily data query
    // $monthlyEndTime = date('Y-m-d H:i:s', strtotime($starttime . ' -1 month'));
    $monthlyStartTime = date('Y-m-01 00:00:00');
    $sth = $dbh->prepare($dailyStmt);
    $sth->bindParam(':station', $station, PDO::PARAM_INT);
    // $sth->bindParam(':end', $monthlyEndTime);
    $sth->bindParam(':start', $monthlyStartTime);

    $sth->execute();

    $row = $sth->fetch(PDO::FETCH_ASSOC);
    if($row['rain_raw'])
        return $row['rain_raw'];
    else
        return 0;
}

function printWeatherData() {
    global $xml;
    $xml->endElement();
    echo $xml->outputMemory();
    return;
}

function addRowToOutput($row, $calibs, $dailyRain, $monthlyRain) {
    global $xml;
    
    foreach ($calibs as $calib) {
        if ($calib['station'] == $row['station_id']) {
            //TODO might need to use strtotime to compare dates
            if ($calib['BeginDateTime'] <= $row['processed_time']) {
                if ($calib['EndDateTime'] >= $row['processed_time']) {
                    $row['rainytd'] += $calib['amount'];
                }
            }
        }
    }

    $xml->startElement('conditions');
    
    $xml->writeElement('station_id', $row['station_id']);
    $date = new DateTime($row['processed_time']);
    $xml->writeElement('time', $date->format(DateTime::ATOM));
    $xml->writeElement('wind_direction', convertCardinal((float) $row['wind_direction']));
    $xml->writeElement('wind_speed', $row['wind_speed']);
    $xml->writeElement('wind_gust', $row['wind_gust']);
    $xml->writeElement('humidity', $row['humidity']);
    $xml->writeElement('temperature', $row['temperature']);
    $xml->writeElement('hi_temp', $row['hi_temp']);
    $xml->writeElement('lo_temp', $row['lo_temp']);
    $xml->writeElement('barometer', $row['barometer']);
    $xml->writeElement('barotrend', $row['barotrend']);
    $xml->writeElement('rain_ytd_raw', $row['rainytdraw']);
    $xml->writeElement('rain_ytd', $row['rainytd']);

    $xml->writeElement('rain_day', $dailyRain);
    $xml->writeElement('rain_month', $monthlyRain);

    $xml->writeElement('evap', $row['evap']);
    $xml->writeElement('uv_index', $row['uv_index']);
    $xml->writeElement('solar_rad', $row['solar_rad']);
    $xml->writeElement('wind_chill', $row['wind_chill']);
    $xml->writeElement('heat_index', $row['heat_index']);
    
    $xml->writeElement('dew_point', $row['dew_point']);

    $xml->endElement();
    return;
}

?>
