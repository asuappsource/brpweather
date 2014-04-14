<?php

@date_default_timezone_set('America/New_York');

include_once('config.inc.php');
require_once("dbconnect.php");

// Some global variables

$dbh; // database handler
$inotify; // inotify instance
$signal = 1; // stop signal
$cameras; // array of camera data
$watchMap = array(); // array of inotify watch descriptors

// set up signal handling
pcntl_signal(SIGUSR1, "sig_handler");

// date for the log
echo "===================================\n";
echo date(DATE_RFC822);
echo "\n\n";

// run, go, do
execute($_host, $_user, $_pw, $_db);

// main
function execute($host, $user, $pw, $db) {
  global $cameras, $watchMap, $dbh, $inotify;

  // connect to the database
  $dbh = dbconnect();

  // obviously need inotify for this to work
  if (!extension_loaded('inotify')) {
    fprintf(STDERR, "Inotify extension not loaded !\n");
    exit(1);
  }

  // also need imagick for this to work
  if (!extension_loaded('imagick')) {
    fprintf(STDERR, "Imagick extension not loaded !\n");
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

// grabs new data from the camera db table, places into array with the camera
// id as the key and the name and directory as sub-array values
function updateCameras() {
  global $cameras, $dbh;
  //echo "Fetching new Station Data\n";

  if ($result = $dbh->query('SELECT * FROM `webcams`')) {
    while ($row = $result->fetch(PDO::FETCH_BOTH)) {
      // cameras[id] = array('name' => name, 'dir' => data_fullpath)
      $cameras[$row['id']] = array('name' => $row['name'], 'from_dir' => $row['from_dir'],
       'to_dir' => $row['to_dir'], 'to_url' => $row['to_url'], 'delay' => $row['delay'],
       'last_sent' => $row['last_sent'], 'top_crop' => $row['top_crop'], 'bottom_crop' => $row['bottom_crop'],
       'left_crop' => $row['left_crop'], 'right_crop' => $row['right_crop']);
    }
  }
  // keep track of when we last updated so we can refresh every so often
  $cameras['lastUpdate'] = time();

  initInotifyWatches();
}

// checks the updated camera table against current watch descriptors
// any that are missing are added to the inotify instance and to the watchMap array
function initInotifyWatches() {
  global $cameras, $watchMap, $inotify;
  //echo "Initializing Watches\n";

  foreach ($cameras as $id => $camera) {
    if (isset($camera['from_dir'])) {
      $d = $camera['from_dir'];

      //TODO: if a camera watch is no longer needed, remove only it.

      if (!in_array($d, $watchMap)) {
	//echo "New camera found: $d\n";

	// make sure the directory exists
       if (!file_exists($d) || ($fd = fopen($d, "r")) === false) {
	  // commented out for production use
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
           $camera['watch'] = $watch;
           $watchMap[$watch]['from_dir'] = $d;
           $watchMap[$watch]['id'] = $id;
           $watchMap[$watch]['to_url'] = $camera['to_url'];
           $watchMap[$watch]['to_dir'] = $camera['to_dir'];
           $watchMap[$watch]['delay'] = $camera['delay'];
           $watchMap[$watch]['last_sent'] = $camera['last_sent'];
           $watchMap[$watch]['top_crop'] = $camera['top_crop'];
           $watchMap[$watch]['bottom_crop'] = $camera['bottom_crop'];
           $watchMap[$watch]['left_crop'] = $camera['left_crop'];
           $watchMap[$watch]['right_crop'] = $camera['right_crop'];
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
// also updates the camera table if enough time has elapsed. 
function watchDirectories() {
  global $signal, $cameras, $dbh, $inotify;

  //wait for events
  while (($signal == 1) && (($events = inotify_read($inotify)) !== false)) {
    foreach ($events as $event) handleEvent($event);

    //TODO: allow configurable delay for camera updating
    if (time() >= 1800 + $cameras['lastUpdate']) updateCameras();
  }
}

// controls the parsing and inserting of parsed data into the database.
// mainly just figures out the camera id of the data file just parsed
// so it can be inserted properly
function handleEvent($event) {
  global $cameras, $watchMap, $dbh;

  // the wd field is the abs. path to the file and the name field is the filename
  // so append them to get the location
  $file = $watchMap[$event['wd']]['from_dir'].$event['name'];
  $dir = $watchMap[$event['wd']]['from_dir'];
  $camId = $watchMap[$event['wd']]['id'];
  $to_url = $watchMap[$event['wd']]['to_url'];
  $to_dir = $watchMap[$event['wd']]['to_dir'];
  $delay = $watchMap[$event['wd']]['delay'];
  $last_sent = $watchMap[$event['wd']]['last_sent'];
  $top_crop = $watchMap[$event['wd']]['top_crop'];
  $bottom_crop = $watchMap[$event['wd']]['bottom_crop'];
  $left_crop = $watchMap[$event['wd']]['left_crop'];
  $right_crop = $watchMap[$event['wd']]['right_crop'];
  //echo "File changed: ".$file."\n";

  // then, check to see if the file is empty
  // if it is, don't attempt any processing
  // TODO need to find a better way to accomplish this as
  // the file not being writable causes problems
  if(filesize($file) == 0) {
    echo "[" . date(DATE_RFC822) . "]:";
    printf("0-length file: %s\n", $file);
    return;
  }

  // if it has not been at least $delay minutes since the last time we sent an image, dont send one
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

  // Hook to perform image processing
  if(!processImg($file,$top_crop,$bottom_crop,$left_crop,$right_crop))
    return;

  // send the image
  $didSend = sendImg($file, $to_url, $to_dir);

  // get the newest last_sent time 
  if($didSend)
    $watchMap[$event['wd']]['last_sent'] = time();

  // report that the camera has been updated
  updateDB($camId, $didSend);
}

function processImg($source, $top_crop, $bottom_crop, $left_crop, $right_crop) {
  try {
    // hook for file processing
    // if modified be sure to save all image processing to the same source file
    $image = new Imagick($source);
    $imgGeo = $image->getImageGeometry();

    // crop the image down
    $image->cropImage($imgGeo['width']-$left_crop-$right_crop,$imgGeo['height']-$top_crop-$bottom_crop,$left_crop,$top_crop);

    // write the image back out
    $image->writeImage($source);
  } catch(ImagickException $e) {
    echo "[" . date(DATE_RFC822) . "]:";
    printf("Problem processing image %s\n", $source);
    printf("%s\n", $e->getMessage());
    return false;
  }
  return true;
}

function sendImg($source, $to_url, $to_dir) {
  // connect to the server via ssh
  $ssh = ssh2_connect($to_url);
  if($ssh) {
    if(ssh2_auth_pubkey_file($ssh, 'brpweather', '/home/brpweather/.ssh/id_rsa.pub', '/home/brpweather/.ssh/id_rsa')) {
      // if we authenticated, send the file
      $ret = ssh2_scp_send($ssh, $source, $to_dir . '/image.jpeg', 0644);
      ssh2_exec($ssh, 'exit');
      return $ret;
    }
  }
  return false;

}

// updates the 'last_update' field in the database
function updateDB($camera_id, $imgsent) {
  global $dbh;

  // now update the time for the camera in the camera table
  if($imgsent) 
    $sth = $dbh->prepare("UPDATE `webcams` SET `last_update` = NOW(), `last_sent` = NOW()  WHERE id = :id");
  else
    $sth = $dbh->prepare("UPDATE `webcams` SET `last_update` = NOW() WHERE id = :id");
  $sth->bindParam(":id", $camera_id);
  $sth->execute();

}

// handles the interrupt signal stuff
function sig_handler($signo) {
  echo ">> Signal received, exiting. <<\n";
  global $signal;
  $signal = 0;
}

?>
