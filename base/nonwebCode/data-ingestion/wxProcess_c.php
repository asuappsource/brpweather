<?php

@date_default_timezone_set('America/New_York');

require_once("dbconnect.php.inc");

// Some global variables

$dbh; // database handler
$inotify; // inotify instance
$signal = 1; // stop signal
$stations; // array of station data
$watchMap = array(); // array of inotify watch descriptors

// Write the process ID to a file, so systemd can kill this process later
$myPid = getmypid();
$pidFile = fopen('/home/brpweather/data-ingestion/wxProcess.pid', 'w+');
fwrite($pidFile, $myPid);

// set up signal handling
pcntl_signal(SIGUSR1, "sig_handler");

// date for the log
echo "===================================\n";
echo date(DATE_RFC822) . "\n\n";

// run, go, do
execute();

function execute() {
    global $stations, $watchMap, $dbh, $inotify;
    
    // connect to the database
    $dbh = dbconnect();
    
    // obviously need inotify for this to work
    if (!extension_loaded('inotify')) {
        fprintf(STDERR, "Inotify extension not loaded !\n");
        exit(1);
    }
    
    // obtain instance of inotify service
    $inotify = inotify_init();
    if ($inotify === false) {
        fprintf(STDERR, "Failed to obtain an inotify instance\n");
        exit(2);
    }

    updateStations();
    
    watchDirectories();
    
    // clean up
    // some of this is probably unnecessary... but i feel better doing it
    echo "Removing inotify watches...\n";
    foreach ($watchMap as $watch => $dir) {
        inotify_rm_watch($inotify, $watch);
    }
    
    echo "Closing inotify...\n";
    fclose($inotify);
    
    echo "Closing database connection...\n";
    $dbh = NULL;

    echo "Goodbye!\n";
}

// grabs new data from the station db table, places into array with the station
// id as the key and the name and directory as sub-array values
function updateStations() {
    global $stations, $dbh;
    //echo "Fetching new Station Data\n";

    if ($result = $dbh->query('SELECT * FROM station')) {
      while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $stations[$row['id']] = array('name' => $row['name'], 'dir' => $row['dir']);
        }
    }

    // keep track of when we last updated so we can refresh every so often
    //$stations['lastUpdate'] = time();
    global $lastUpdateTime;
    $lastUpdateTime = time();

    initInotifyWatches();
}

// checks the updated station table against current watch descriptors
// any that are missing are added to the inotify instance and to the watchMap array
function initInotifyWatches() {
    global $stations, $watchMap, $inotify;
    //echo "Initializing Watches\n";

    foreach ($stations as $id => $station) {

        if (!isset($station['dir'])) {
            echo "No directory set for station id $id}\n";
            continue;
        }

        $d = $station['dir'];

        //TODO: if a station watch is no longer needed, remove only it.

        if (!in_array($d, $watchMap)) {
            //echo "New station found: $d\n";
        
            // make sure the directory exists
            if (!file_exists($d) || ($fd = fopen($d, "r")) === false) {
                fprintf(STDERR, "File '%s' does not exists or is not readable\n", $d);
            } else {
                // something about putting the file pointer at the beginning or end,
                // not totally sure about this one.
                fseek($fd, 0, SEEK_END);
                
                // add the watch to our inotify instance.
                // the IN_CLOSE_WRITE mask seems the most pertinent.
                //$watch = inotify_add_watch($inotify, $d, IN_CLOSE_WRITE);
                $watch = inotify_add_watch($inotify, $d, IN_MOVED_TO);
    
                if ($watch === false) {
                    fprintf(STDERR, "Failed to watch directory '%s'\n", $d);
                } else {
                    $station['watch'] = $watch;
                    $watchMap[$watch] = $d;
                }
                fclose($fd);
            }
        }
    }
}

// main blocking loop. the inotify documentation has a more technical description,
// but the call to inotify_read() returns a queue of events or blocks until there
// is another event to return. thus, the signal is implemented to allow early exiting
// of this function.
//
// also updates the station table if enough time has elapsed. 
function watchDirectories() {
    global $signal, $stations, $dbh, $inotify, $lastUpdateTime;
    
    //wait for events
    while (($signal == 1) && (($events = inotify_read($inotify)) !== false)) {
        foreach ($events as $event) handleEvent($event);
        
        //TODO: allow configurable delay for station updating
        if (time() >= 1800 + $lastUpdateTime) updateStations($dbh);
    }
}

// controls the parsing and inserting of parsed data into the database.
// mainly just figures out the station id of the data file just parsed
// so it can be inserted properly
function handleEvent($event) {
    global $stations, $watchMap, $dbh;

    // the wd field is the abs. path to the file and the name field is the filename
    // so append them to get the location
    $file = $watchMap[$event['wd']].$event['name'];
    //echo "File changed: ".$file."\n";


    // Ignore events from files that start with a '.' for hidden files
    //echo "Name: {$event['name']} ";
    //echo "Wd: {$event['wd']} ";
    //var_dump($event);
    if($event['name'] === '' || substr($event['name'], 0, 1) === '!' || substr($event['name'], 0, 1) === '.'){
        //echo " Skipping this file..\n";
        return;
    }else{
        //echo " Using file.\n";
    }

    $data = parseFile($file);

    // iterate over the station table to find the correct ID.
    // there is probably a much easier way to do this, in retrospect,
    // but it cant know which station it is from the id or path because multiple stations
    // might use the same path for all i know.
    // UPDATE DEC 2012, now comparing the watch directory to the directory in the station array
    //$currName = str_replace(' ', '', $data['stat']);
    //echo 'attempting to match '.$watchMap[$event['wd']]."\n";
    foreach ($stations as $id => $station) {
        if(strcasecmp($id, 'lastUpdate') == 0){
            continue;
        }

        //$testName = str_replace(' ', '', $station['name']);
        //if (strcasecmp($currName, $testName) == 0) {
        //echo 'comparing to '.$station['dir']."\n";
	    if (strcasecmp($watchMap[$event['wd']], $station['dir']) == 0) {
	        //echo "Station match: $id, ".$station['name']."\n";
	        insertData($id, $data);

	        break; // found the correct station, no need to keep searching
        }
    }
}

// puts the data from the ftp'd file into a neater array
// and adds a field for processed time
//
// this is obviously dependent of formatting
function parseFile($filename) {
    $data = file($filename) or die("Could not read file: $filename!");
    
    foreach ($data as $line) {
        //$tokens = explode(' ', trim($line), 2);
        //$wxData[$tokens[0]] = $tokens[1];
        if (preg_match("/(?P<type>[a-z]+)\s(?P<value>.+)$/i", $line, $matches)) {
            $wxData[$matches['type']] = trim($matches['value']);
        }
    }

    $wxData['reported_time'] = date('Y-m-d H:i:s', strtotime($wxData['date'].' '.$wxData['time']));
    unset($wxData['date']);
    unset($wxData['time']);

    //$wxData['processed_time'] = date('c');

    $wxData['wdir'] = convertCardinal($wxData['wdir']);

    return $wxData;
}

// puts an array of wxData into the raw_data_table
// uses a mysqli_stmt idiom, with mysqli_bind_param
// because the documentation said it was safer or something
function insertData($station_id, $data) {
    global $dbh;

    // set transaction processing
    $dbh->beginTransaction();

    // list of values for the query
    $_vals = '(`station_id`, `processed_time`, `reported_time`, `wind_direction`, `wind_speed`, `wind_gust`, `humidity`, `temperature`, `hi_temp`, `lo_temp`, `barometer`, `barotrend`, `rainytdraw`, `rainytd`, `evap`, `uv_index`, `solar_rad`, `wind_chill`, `heat_index`, `dew_point`, `cloud_base`)';

    $qstr = "INSERT INTO raw_current_data $_vals VALUES (:id, NOW(), :rtime, :wdir, :wspe, :wgus, :humi, :temp, :tdhi, :tdlo, :baro, :btrn, :rnyr, :rnyrr, :evap, :uvin, :srad, :wchi, :htin, :dewp, :cbas)";
    
    if ($stmt = $dbh->prepare($qstr)) {
      $stmt->bindParam(':id',$station_id);
      //$stmt->bindParam(':ptime',$data['processed_time']); // Removed in favor of using NOW() function, because date('c') is the wrong format
      $stmt->bindParam(':rtime',$data['reported_time']);
      $stmt->bindParam(':wdir',$data['wdir']);
      $stmt->bindParam(':wspe',$data['wspe']);
      $stmt->bindParam(':wgus',$data['wgus']);
      $stmt->bindParam(':humi',$data['humi']);
      $stmt->bindParam(':temp',$data['temp']);
      $stmt->bindParam(':tdhi',$data['tdhi']);
      $stmt->bindParam(':tdlo',$data['tdlo']);
      $stmt->bindParam(':baro',$data['baro']);
      $stmt->bindParam(':btrn',$data['btrn']);
      $stmt->bindParam(':rnyr',$data['rnyr']);
      $stmt->bindParam(':rnyrr',$data['rnyr']);
      $stmt->bindParam(':evap',$data['evap']);
      $stmt->bindParam(':uvin',$data['uvin']);
      $stmt->bindParam(':srad',$data['srad']);
      $stmt->bindParam(':wchi',$data['wchi']);
      $stmt->bindParam(':htin',$data['htin']);
      $stmt->bindParam(':dewp',$data['dewp']);
      $stmt->bindParam(':cbas',$data['cbas']);

      $stmt->execute();

      // now update the time for the station in the station table
      $sth = $dbh->prepare("UPDATE `station` SET `last_update` = NOW() WHERE id = :id");
      $sth->bindParam(":id", $station_id);
      $sth->execute();

      // commit the transaction
      $dbh->commit();
    }
}

// helper function that can convert a cardinal direction string to a degree value
// or a degree value to the closest cardinal direction string.
// detects input automatically based on is_string or is_numeric
function convertCardinal($dir) {
    static $map = array(
            'N'    =>      0,
            'NbE'  =>  11.25,
            'NNE'  =>  22.50,
            'NEbN' =>  33.75,
            'NE'   =>  45.00,
            'NEbE' =>  56.25,
            'ENE'  =>  67.50,
            'EbN'  =>  78.75,
            'E'    =>  90.00,
            'EbS'  => 101.25,
            'ESE'  => 112.50,
            'SEbE' => 123.75,
            'SE'   => 135.00,
            'SEbS' => 146.25,
            'SSE'  => 157.50,
            'SbE'  => 168.75,
            'S'    => 180.00,
            'SbW'  => 191.00,
            'SSW'  => 202.50,
            'SWbS' => 213.75,
            'SW'   => 225.00,
            'SWbW' => 236.25,
            'WSW'  => 247.50,
            'WbSW' => 258.75,
            'W'    => 270.00,
            'WbN'  => 281.25,
            'WNW'  => 292.50,
            'NWbW' => 303.75,
            'NW'   => 315.00,
            'NWbN' => 326.25,
            'NNW'  => 337.50,
            'NbW'  => 348.75);

    // just hardcode this, would be faster to have as static
    // $mapStrings = array_keys($map);
    static $mapStrings = array(
            'N',
            'NbE',
            'NNE',
            'NEbN',
            'NE',
            'NEbE',
            'ENE',
            'EbN',
            'E',
            'EbS',
            'ESE',
            'SEbE',
            'SE',
            'SEbS',
            'SSE',
            'SbE',
            'S',
            'SbW',
            'SSW',
            'SWbS',
            'SW',
            'SWbW',
            'WSW',
            'WbSW',
            'W',
            'WbN',
            'WNW',
            'NWbW',
            'NW',
            'NWbN',
            'NNW',
            'NbW');
    if (is_string($dir)) {
        $dir = trim($dir);
        if (isset($map[$dir])) {
            return $map[$dir];
        }
    } else if (is_int($dir) || is_double($dir)) {
        $dir = floor((($dir/11.25) + 1.5) < 33 ? (($dir/11.25) + 1.5) : (($dir/11.25) + 1.5) - 32);
        if (isset($mapStrings[$dir-1])) {
            return $mapStrings[$dir-1];
        }
    }
    return -1;
}

// handles the interrupt signal stuff
function sig_handler($signo) {
    echo ">> Signal received, exiting. <<\n";
    global $signal;
    $signal = 0;
}

?>
