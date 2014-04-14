<?php

@date_default_timezone_set('America/New_York');

include_once('config.inc.php');
require_once("dbconnect.php");

// Some global variables

$dbh; // database handler
$inotify; // inotify instance
$signal = 1; // stop signal
$stations; // array of station data
$watchMap = array(); // array of inotify watch descriptors

// set up signal handling
pcntl_signal(SIGUSR1, "sig_handler");

// date for the log
echo "===================================\n";
echo date(DATE_RFC822) . "\n\n";

// run, go, do
execute($_host, $_user, $_pw, $_db);

function execute($host, $user, $pw, $db) {
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

    updateCameras();

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
function updateCameras() {
    global $stations, $dbh;
    //echo "Fetching new Station Data\n";

    if ($result = $dbh->query('SELECT * FROM `wxdata`')) {
        while ($row = $result->fetch(PDO::FETCH_BOTH)) {
            // stations[id] = array('name' => name, 'dir' => data_fullpath)
            $stations[$row['id']] = array('name' => $row['name'], 'from_dir' => $row['from_dir'], 'to_dir' => $row['to_dir'], 'to_url' => $row['to_url'], 'delay' => $row['delay'], 'last_sent' => $row['last_sent']);
        }
    }
    // keep track of when we last updated so we can refresh every so often
    $stations['lastUpdate'] = time();

    initInotifyWatches();
}

// checks the updated station table against current watch descriptors
// any that are missing are added to the inotify instance and to the watchMap array
function initInotifyWatches() {
    global $stations, $watchMap, $inotify;
    //echo "Initializing Watches\n";

    foreach ($stations as $id => $station) {
        if (isset($station['from_dir'])) {
            $d = $station['from_dir'];

            //TODO: if a station watch is no longer needed, remove only it.

            if (!in_array($d, $watchMap)) {
                //echo "New station found: $d\n";

                // make sure the directory exists
                if (!file_exists($d) || ($fd = fopen($d, "r")) === false) {
                    //fprintf(STDERR, "File '%s' does not exists or is not readable\n", $d);
                } else {
                    // something about putting the file pointer at the beginning or end,
                    // not totally sure about this one.
                    fseek($fd, 0, SEEK_END);

                    // add the watch to our inotify instance.
                    // the IN_CLOSE_WRITE mask seems the most pertinent.
                    $watch = inotify_add_watch($inotify, $d, IN_CLOSE_WRITE);

                    if ($watch === false) {
                        fprintf(STDERR, "Failed to watch directory '%s'\n", $d);
                    } else {
                        $station['watch'] = $watch;
                        $watchMap[$watch]['from_dir'] = $d;
                        $watchMap[$watch]['id'] = $id;
                        $watchMap[$watch]['to_url'] = $station['to_url'];;
                        $watchMap[$watch]['to_dir'] = $station['to_dir'];;
                        $watchMap[$watch]['delay'] = $station['delay'];;
                        $watchMap[$watch]['last_sent'] = $station['last_sent'];;
                    }
                    fclose($fd);
                }
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
    global $signal, $stations, $dbh, $inotify;

    //wait for events
    while (($signal == 1) && (($events = inotify_read($inotify)) !== false)) {
        foreach ($events as $event) handleEvent($event);

        //TODO: allow configurable delay for station updating
        if (time() >= 1800 + $stations['lastUpdate']) updateCameras();
    }
}

// controls the parsing and inserting of parsed data into the database.
// mainly just figures out the station id of the data file just parsed
// so it can be inserted properly
function handleEvent($event) {
    global $stations, $watchMap, $dbh;

    // the wd field is the abs. path to the file and the name field is the filename
    // so append them to get the location
    $file = $watchMap[$event['wd']]['from_dir'].$event['name'];
    $dir = $watchMap[$event['wd']]['from_dir'];
    $camId = $watchMap[$event['wd']]['id'];
    $to_url = $watchMap[$event['wd']]['to_url'];
    $to_dir = $watchMap[$event['wd']]['to_dir'];
    $delay = $watchMap[$event['wd']]['delay'];
    $last_sent = $watchMap[$event['wd']]['last_sent'];
    //echo "File changed: ".$file."\n";

    // first, check to see if the file is empty
    // if it is, don't attempt any processing
    // TODO change this, does not work if file is not writeable
    //    if(filesize($file) == 0)
    //      return;

    // if it has not been at least $delay seconds since the last time we sent a file, dont send one
    if(!is_numeric($last_sent)) {
        $checkTime = strtotime($last_sent) + $delay*60 + 15; // 15 seconds for possible delay in receiving image
        if(time() < $checkTime) {
            updateDB($camId, false);
            return;
        }
    }
    else {
        $checkTime = $last_sent + $delay*60 + 15; // 15 seconds for possible delay in receiving image
        if(time() < $checkTime) {
            updateDB($camId, false);
            return;
        }
    }

    // send the file
    $didSend = sendData($file, $to_url, $to_dir);

    // report that the station has been updated
    updateDB($camId, $didSend);
}

function sendData($source, $to_url, $to_dir) {
    // connect to the server via ssh
    $ssh = ssh2_connect($to_url);

    if($ssh) {
        // authenticate with the server
        if(ssh2_auth_pubkey_file($ssh, 'brpweather', '/home/brpweather/.ssh/id_rsa.pub', '/home/brpweather/.ssh/id_rsa')) {
            // if we authenticated, send the file
            ssh2_scp_send($ssh, $source, $to_dir . '/data.txt', 0644);
            return true;
        }
        return false;
    }
}

// updates the 'last_update' field in the database
function updateDB($station_id, $imgsent) {
    global $dbh;

    // now update the time for the station in the station table
    if($imgsent)
        $sth = $dbh->prepare("UPDATE `wxdata` SET `last_update` = NOW(), `last_sent` = NOW()  WHERE id = :id");
    else
        $sth = $dbh->prepare("UPDATE `wxdata` SET `last_update` = NOW() WHERE id = :id");
    $sth->bindParam(":id", $station_id);
    $sth->execute();

}

// handles the interrupt signal stuff
function sig_handler($signo) {
    echo ">> Signal received, exiting. <<\n";
    global $signal;
    $signal = 0;
}

?>
