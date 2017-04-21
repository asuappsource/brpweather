<?php

@date_default_timezone_set('America/New_York');

//include_once('config.inc.php');
require_once("dbconnect.php.inc");

// Some global variables

$dbh; // database handler
$inotify; // inotify instance
$signal = 1; // stop signal
$cameras; // array of camera data
$watchMap = array(); // array of inotify watch descriptors

// Write the process ID to a file, so systemd can kill this process later
$myPid = getmypid();
$pidFile = fopen('/home/brpweather/img-ingestion/webcamProcess.pid', 'w+');
fwrite($pidFile, $myPid);

// set up signal handling
pcntl_signal(SIGUSR1, "sig_handler");

// date for the log
echo "===================================\n";
echo date(DATE_RFC822) . "\n\n";

// run, go, do
//execute($_host, $_user, $_pw, $_db);
execute();

function execute() {
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

    if ($result = $dbh->query('SELECT * FROM camera')) {
        while ($row = $result->fetch(PDO::FETCH_BOTH)) {
	        // cameras[id] = array('name' => name, 'dir' => data_fullpath)
	        $cameras[$row['id']] = array('name' => $row['name'], 'dir' => $row['imageDir'], 'webDir' => $row['webDir']);
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
        if (isset($camera['dir'])) {
            $d = $camera['dir'];

            //TODO: if a camera watch is no longer needed, remove only it.

            if (!in_array($d, $watchMap)) {
	      //echo "New camera found: $d\n";
            
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
                        $camera['watch'] = $watch;
                        $watchMap[$watch]['dir'] = $d;
                        $watchMap[$watch]['webDir'] = $camera['webDir'];
			            $watchMap[$watch]['id'] = $id;
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
    $file = $watchMap[$event['wd']]['dir'].$event['name'];
    $dir = $watchMap[$event['wd']]['dir'];
    $webDir = $watchMap[$event['wd']]['webDir'];
    $camId = $watchMap[$event['wd']]['id'];
    //echo "File changed: ".$file."\n";

    // make sure the subdirectories exist and are writable
    // NOTE: this is not efficient at all, consider relocating this code
    $webDir = '/var/www/html/brpcam/' . $webDir;

    if(!is_dir($webDir . "full/")) {
        //echo "Attempting to mkdir in $webDir" . "full/\n";
      mkdir($webDir . "full/", 0755,true);
    }
    if(!is_dir($webDir . "800px/")) {
      mkdir($webDir . "800px/", 0755,true);
    }
    if(!is_dir($webDir . "thumb/")) {
      mkdir($webDir . "thumb/", 0755,true);
    }
    if(!is_dir($webDir . "animation/")) {
      mkdir($webDir . "animation/", 0755,true);
    }

    // first, check to see if the file is empty
    // if it is, don't attempt any processing
    if(filesize($file) == 0)
      return;

    // take the image and make 3 folders: one for a full size copy,
    // one for an 800px wide copy, and one for a 260px thumbnail copy
    makeFull($webDir, $file);
    make800px($webDir, $file);
    makeThumb($webDir, $file);

    // handle the animation
    animationHandler($webDir, $file);

    // report that the camera has been updated
    updateDB($camId);
}

function animationHandler($dir, $source) {
  // copy the file into the appropriate directory
  if(!copy($source, $dir . "animation/image_" . time() . ".jpeg")) {
    printf("Error creating animation file in $dir/animation");
  }

  // flush the stat cache to prevent issues
  clearstatcache();

  // delete all files older than 24 hours
  if($handle = opendir($dir . "animation/")) {
    while ( false !== ($file = readdir($handle))) {
      // TODO if the directory is .. or ., ignore it
      $file_parts = pathinfo($dir. "animation/" . $file);
      switch($file_parts['extension']) {
      case "jpeg":
      case "jpg":
	if (( time() - filemtime($dir . "animation/" . $file)) > 60*60*24) {
	  unlink($dir . "animation/" . $file);
	}
	break;
      }
    }
  }

  // create the animation
}

//
function makeFull($dir, $source) {
  $image = new Imagick($source);

  // write out the image
  $image->writeImage($dir . "full/image.jpeg");

  // free up object and associated resources
  $image->clear();
  $image->destroy();
}

// helper function to create 800px wide image
function make800px($dir, $source) {
  $image = new Imagick($source);

  // resize image, 0 preserves aspect ratio
  $image->thumbnailImage(800,0);

  // write out the image
  $image->writeImage($dir . "800px/image.jpeg");

  // free up object and associated resources
  $image->clear();
  $image->destroy();
}

// helper function to create 260px wide thumbnail image
function makeThumb($dir, $source) {
  $image = new Imagick($source);

  // resize image, 0 preserves aspect ratio
  $image->thumbnailImage(260,0);

  // write out the image
  $image->writeImage($dir . "thumb/image.jpeg");

  // free up object and associated resources
  $image->clear();
  $image->destroy();
}

// puts an array of wxData into the raw_data_table
// uses a mysqli_stmt idiom, with mysqli_bind_param
// because the documentation said it was safer or something
function updateDB($camera_id) {
    global $dbh;

    // now update the time for the camera in the camera table
    $sth = $dbh->prepare("UPDATE `camera` SET `lastModified` = NOW() WHERE id = :id");
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
