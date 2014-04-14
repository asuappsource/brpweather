<?php

require_once "dbconnect.php.inc";

// check to make sure we have a range to do and an ID for the camera
if((isset($_POST['range']) || isset($_GET['range'])) && (isset($_POST['id']) || isset($_GET['id']))) {
    $range = isset($_POST['range']) ? $_POST['range'] : $_GET['range'];
    $id = isset($_POST['id']) ? $_POST['id'] : $_GET['id'];
    // connect to the database
    $dbh = dbconnect();

    // query to get directory for camera
    $qry = "SELECT * FROM `camera` WHERE `id` = :id";

    // prepare and execute the query
    $sth = $dbh->prepare($qry);
    $sth->bindParam(":id", $id);
    $sth->execute();

    $dir = "";
    // fetch the results
    if($row = $sth->fetch(PDO::FETCH_BOTH)) {
        $dir = $row['webDir'];
    }

    // close the connection to the database
    $dbh = NULL;

    // determine how far back to get images
    $timeBack = $range;

    // the array of images
    $imgs = array();

    // open the directory
    if($handle = opendir($dir . "animation/")) {
        while( false !== ($file = readdir($handle))) {
            // get the info about the file
            $file_parts = pathinfo($dir."animation/".$file);
            // make sure the file is a jpeg
            switch($file_parts['extension']) {
                case "jpeg":
                case "jpg":
                    // if the image is within the range
                    if(( time() - filemtime($dir . "animation/" . $file)) < $timeBack) {
                        // add the image to the list
                        $imgs[] = $dir . "animation/" . $file;
                    }
                    break;
            }
        }
    }
    // sort the images from oldest to newest
    sort($imgs);
    // return as JSON
    echo json_encode($imgs);
}
else {
    echo "bad input";
}

?>
