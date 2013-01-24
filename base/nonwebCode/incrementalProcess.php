<?php

@date_default_timezone_set('America/New_York');

include_once('config.inc.php');

$link;
$rawData;
$resultData;

$startStr;
$endStr;

execute($_host, $_user, $_pw, $_db);

// glorified main method
// checks for a mysqli connectability
// checks for command line options:
// -h for hourly processing
// -d for daily processing
// -m for monthly processing
// (defaults to hourly)
function execute($host, $user, $pw, $db) {
    global $link, $startStr, $endStr;

    $link = mysqli_connect($host, $user, $pw, $db);

    if (mysqli_connect_errno()) {
        printf("Connection failed: %s\n", mysqli_connect_error());
        exit();
    }

    $argv = getopt('hdm');

    if (isset($argv['m'])) {
        $table = '`monthly_data`';
        $endStr = date('Y-m-01 00:00:00');
        $startStr = date('Y-m-01 00:00:00', strtotime('-1 month'));
    } else if (isset($argv['d'])) {
        $table = '`daily_data`';
        $endStr = date('Y-m-d 00:00:00');
        $startStr = date('Y-m-d 00:00:00', time() - 86400);
    } else {
        $table = '`hourly_data`';
        $endStr = date('Y-m-d H:00:00');
        $startStr = date('Y-m-d H:00:00', time() - 3600);
    }

    //echo "data from $startStr to $endStr\n";

    getStations();

    getRawData($startStr, $endStr);

    processData();

    insertData($table);
    
    mysqli_close($link);
}

// set up the raw data array by making a subarray for each station id in the db table
// does a shallow copy of this into the resultData array
function getStations() {
    global $link, $rawData, $resultData;

    $resultData = array();
    if ($result = mysqli_query($link, 'SELECT id FROM `station`')) {
        while ($row = mysqli_fetch_row($result)) {
            $rawData[$row[0]] = array();
        }
        mysqli_free_result($result);
    }
    
    $resultData = $rawData;
}    

// set up the rawData array with all relevant entries from database
function getRawData() {
    global $link, $rawData, $resultData, $startStr, $endStr;

    $query =  "SELECT * FROM `raw_current_data` WHERE processed_time BETWEEN '$startStr' AND '$endStr'";

    if ($result = mysqli_query($link, $query)) {
        while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
            //array_push($rawData[$row['station_id']], $row);
            $rawData[$row['station_id']][$row['sample_id']] = $row;
        }
        mysqli_free_result($result);
    }
    // this is commented out because for monthly data the log alone would be like 40,000 lines.
    //TODO: come to think of it, i really should test it on that much data and see how long it takes
    //echo "raw data:\n";
    //print_r($rawData);
}

// does the math
function processData() {
    global $rawData, $resultData, $startStr, $endStr;
    
    foreach ($rawData as $id => $stationData) {
      if(empty($stationData)) {
	//echo "Array is empty for station $id\n";
	$resultData[$id] = array();
	continue;
      }
        //print_r($stationData);
        $resultData[$id]['date_hour_start'] = $startStr;
        $resultData[$id]['date_hour_end'] = $endStr;
    
        //get first element from the station data array
        $sample0 = array_shift($stationData);

        //time
        $time = $sample0['reported_time'];

        //wind
        $w_hi = $w_lo = $sample0['wind_speed'];
        $w_hi_t = $w_lo_t = $time;
        $u_tot = 0;
        $v_tot = 0;

        //wind gust
        $wg_hi = $wg_lo = $sample0['wind_gust'];
        $wg_hi_t = $wg_lo_t = $time;

        //humidity
        $h_hi = $h_lo = $sample0['humidity'];
        $h_hi_t = $h_lo_t = $time;
        $h_tot = 0;

        //temperature
        //TODO: use temp_hi or temp_lo from input data???
        $t_hi = $t_lo = $sample0['temperature'];
        $t_hi_t = $t_lo_t = $time;
        $t_tot = 0;

        //barometer
        $b_hi = $b_lo = $sample0['barometer'];
        $b_hi_t = $b_lo_t = $time;
        $b_tot = 0;

        //rain
        $r_start = $sample0['rainytdraw'];
        $r_tot = 0;
        
        //wind chill
        $wc_hi = $wc_lo = $sample0['wind_chill'];
        $wc_hi_t = $wc_lo_t = $time;
        
        //heat index
        $hi_hi = $hi_lo = $sample0['heat_index'];
        $hi_hi_t = $hi_lo_t = $time;

        //dew point
        $dp_hi = $dp_lo = $sample0['dew_point'];
        $dp_hi_t = $dp_lo_t = $time;

        $count = 0;
        //add first element back
        array_unshift($stationData, $sample0);
        foreach ($stationData as $s_id => $sample) {
            $time = $sample['reported_time'];
            
            //wind speed
            $wspd = $sample['wind_speed'];
            //TODO: in the case of ties, should it be the first or last? >= vs > / <= vs <
            if ($wspd > $w_hi) {
                $w_hi = $wspd;
                $w_hi_t = $time;
            }
            if ($wspd < $w_lo) {
                $w_lo = $wspd;
                $w_lo_t = $time;
            }
            
            //prevailing winds
            $wdir = $sample['wind_direction'];
            $u_tot += (-1 * $wspd) * sin($wdir);
            $v_tot += (-1 * $wspd) * cos($wdir);

            //wind gust
            $wgst = $sample['wind_gust'];
            if ($wgst > $wg_hi) {
                $wg_hi = $wgst;
                $wg_hi_t = $time;
            }
            if ($wgst < $wg_lo) {
                $wg_lo = $wgst;
                $wg_lo_t = $time;
            }

            //humidity
            $humi = $sample['humidity'];
            $h_tot += $humi;
            if ($humi > $h_hi) {
                $h_hi = $humi;
                $h_hi_t = $time;
            }
            if ($humi < $h_lo) {
                $h_lo = $humi;
                $h_lo_t = $time;
            }

            //temperature
            $temp = $sample['temperature'];
            $t_tot += $temp;
            if ($temp > $t_hi) {
                $t_hi = $temp;
                $t_hi_t = $time;
            }
            if ($temp < $t_lo) {
                $t_lo = $temp;
                $t_lo_t = $time;
            }

            //barometer
            $baro = $sample['barometer'];
            $b_tot += $baro;
            if ($baro > $b_hi) {
                $b_hi = $baro;
                $b_hi_t = $time;
            }
            if ($baro < $b_lo) {
                $b_lo = $baro;
                $b_lo_t = $time;
            }
            
            //rain
            $rain = $sample['rainytdraw'] - $r_start;
            $r_tot = $rain;
            
            //wind chill
            $wchi = $sample['wind_chill'];
            if ($wchi > $wc_hi) {
                $wc_hi = $wchi;
                $wc_hi_t = $time;
            }
            if ($wchi < $wc_lo) {
                $wc_lo = $wchi;
                $wc_lo_t = $time;
            }
           
            //heat index
            $hind = $sample['heat_index'];
            if ($hind > $hi_hi) {
                $hi_hi = $hind;
                $hi_hi_t = $time;
            }
            if ($hind < $hi_lo) {
                $hi_lo = $hind;
                $hi_lo_t = $time;
            }
            
            //dew point
            $dewp = $sample['dew_point'];
            if ($dewp > $dp_hi) {
                $dp_hi = $dewp;
                $dp_hi_t = $time;
            }
            if ($dewp < $dp_lo) {
                $dp_lo = $dewp;
                $dp_lo_t = $time;
            }
            
            $count++;
        }
        
        //calculate u and v components of predominate wind direction
        $u_avg = $u_tot / $count;
        $v_avg = $v_tot / $count;
        
        //add wind data to result table
        $resultData[$id]['predom_wind_direction'] = (atan2($u_avg, $v_avg) / 0.01745329251994) + 180; // 4.0 * (atan(1.0) / 180) = 0.01745329251994
        $resultData[$id]['wind_speed_avg'] = sqrt(pow($u_avg,2) + pow($v_avg,2));
        $resultData[$id]['wind_speed_high'] = $w_hi;
        $resultData[$id]['wind_speed_high_time'] = $w_hi_t;
        $resultData[$id]['wind_speed_low'] = $w_lo;
        $resultData[$id]['wind_speed_low_time'] = $w_lo_t;

        //add wind gust data
        $resultData[$id]['wind_gust_high'] = $wg_hi;
        $resultData[$id]['wind_gust_high_time'] = $wg_hi_t;
        $resultData[$id]['wind_gust_low'] = $wg_lo;
        $resultData[$id]['wind_gust_low_time'] = $wg_lo_t;

        //add humidity data
        $resultData[$id]['humidity_avg'] = $h_tot / $count;
        $resultData[$id]['humidity_high'] = $h_hi;
        $resultData[$id]['humidity_high_time'] = $h_hi_t;
        $resultData[$id]['humidity_low'] = $h_lo;
        $resultData[$id]['humidity_low_time'] = $h_lo_t;
        
        //add temperature data
        $resultData[$id]['temp_avg'] = $t_tot / $count;
        $resultData[$id]['temp_high'] = $t_hi;
        $resultData[$id]['temp_high_time'] = $t_hi_t;
        $resultData[$id]['temp_low'] = $t_lo;
        $resultData[$id]['temp_low_time'] = $t_lo_t;

        //add barometric data
        $resultData[$id]['baro_avg'] = $b_tot / $count;
        $resultData[$id]['baro_high'] = $b_hi;
        $resultData[$id]['baro_high_time'] = $b_hi_t;
        $resultData[$id]['baro_low'] = $b_lo;
        $resultData[$id]['baro_low_time'] = $b_lo_t;

        //add rain data
        $resultData[$id]['rain_raw'] = $r_tot;

        //add wind chill data
        $resultData[$id]['wind_chill_high'] = $wc_hi;
        $resultData[$id]['wind_chill_high_time'] = $wc_hi_t;
        $resultData[$id]['wind_chill_low'] = $wc_lo;
        $resultData[$id]['wind_chill_low_time'] = $wc_lo_t;

        //add heat index data
        $resultData[$id]['heat_index_high'] = $hi_hi;
        $resultData[$id]['heat_index_high_time'] = $hi_hi_t;
        $resultData[$id]['heat_index_low'] = $hi_lo;
        $resultData[$id]['heat_index_low_time'] = $hi_lo_t;

        //add dew point data
        $resultData[$id]['dew_point_high'] = $dp_hi;
        $resultData[$id]['dew_point_high_time'] = $dp_hi_t;
        $resultData[$id]['dew_point_low'] = $dp_lo;
        $resultData[$id]['dew_point_low_time'] = $dp_lo_t;

    }
    
    echo "result data:\n";
    print_r($resultData);

}

// insert resultant data into the database
function insertData($table) {
    global $link, $resultData;

    foreach ($resultData as $id => $result) {
      if(empty($result)) {
	//echo "Empty array for station $id\n";
	continue;
      }
        $values = '\''.$id.'\', \''.implode('\', \'', $result).'\'';
        $query = "REPLACE INTO $table VALUES ($values)";

        if (!mysqli_query($link, $query))
            printf("Error: %s\n", mysqli_error($link));
    }

}
    

?>
