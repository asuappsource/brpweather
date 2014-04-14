<?php

require_once("../dbconnect.php.inc");
require_once("../functions.php");

// let browser know that we have an XML document
header("content-type: text/xml");

// begin the XML document
echo '<?xml version="1.0" encoding="utf-8"?>' . "\n";

$dbh = dbconnect();

if (isset($_GET['interval'])) {
    $interval = $_GET['interval'];
    if (isset($_GET['start'])) {
        $start = strtotime($_GET['start']);
        $end = '';
        if (isset($_GET['end'])) {
            $end   = strtotime($_GET['end']);
        } else {  // end not set
            $end = strtotime('now');
        }
        // TODO Station select stuff error handling
        $stations = array();
        $stations[0] = 1;
        displayConditions($interval,getConditions($dbh,$interval,$start,$end,$stations));
    } else { // start not set
        echo "<$interval></$interval>";
        die();
    }
} else {
    echo '<conditions></conditions>';
    die();
}

// This function accesses the database to get the requested conditions.
// $dbh - the database handle (PDO object)
// $interval - hourly, daily, or monthly
// $start - the begin time for the interval to retrieve
// $end - the end time for the interval to retrieve
// $stations - the stations to get data for (an array)
function getConditions($dbh,$interval,$start,$end,$stations) {
    $query = 'SELECT * FROM `' . $interval . '_data` WHERE `date_hour_end` > '.
                $start . ' AND `date_hour_start` < ' . $end . ' AND `station` IN SET(' .
                $stations[0] . ')';
    echo "<query>".htmlspecialchars($query,ENT_XML1)."</query>";
}

// This function displays the conditions retrieved by getConditions
// $interval - the interval: hourly, daily, or monthly
// $rows - the database rows retrieved
function displayConditions($interval, $rows) {
}

?>
