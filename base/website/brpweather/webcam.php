<?php

require_once("dbconnect.php.inc");
require_once("functions.php");

global $xml;
$_colList = '`camera`.`id`, `webDir`, `name`, `active`, `lastModified`, `city`, `state`, `lat`, `long`, `milemarker`';

if (isset($_GET['json'])) {
    header('content-type: application/json');
    // for JSONP
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
    $json = array();
} else {
    header('content-type: text/xml');
    $xml = new XmlWriter();
    $xml->openMemory();
    $xml->startDocument('1.0', 'UTF-8');
    $xml->startElement('webcams');
    //$xml->writeElement('param-webcams', $_GET['webcam']);
}

$dbh = dbconnect();

$stmt = "SELECT $_colList FROM `camera`, `camera_info` WHERE `camera`.`id` = `camera_info`.`id`";

if (isset($_GET['webcam'])) {
    $stmt .= ' AND `camera`.`id`=:webcam';
    $sth = $dbh->prepare($stmt);
    $sth->bindParam(':webcam', $_GET['webcam'], PDO::PARAM_INT);
} else {
    $sth = $dbh->prepare($stmt);
}

$sth->execute();

while ($row = $sth->fetch(PDO::FETCH_ASSOC)) {
    if ($row['active']) {

        $row['webcamURL'] = 'http://brpwebcams.org/cam/'.$row['id'];
        $web_path = str_replace('/var/www', '', $row['webDir']);
        $row['thumbURL'] = 'http://appsourcevideo.cs.appstate.edu'.$web_path.'thumb/image.jpeg';
        $row['dir'] = end(explode('/', trim($web_path, '/')));
        unset($row['webDir']);
        unset($row['active']);
        
        if (isset($_GET['json']))
            array_push($json, json_encode($row));
        else
            addRowToOutput($row);
    }
}

if (isset($_GET['json']))
    echo '['.implode(', ', $json).']';
else
    printWebcamData();

$dbh = null;

// HELPER FUNCTIONS

function printWebcamData() {
    global $xml;
    $xml->endElement();
    echo $xml->outputMemory();
    return;
}

function addRowToOutput($row) {
    global $xml;
    
    $xml->startElement('webcam');
    
    foreach ($row as $id=>$value)
        $xml->writeElement($id, $value);

    $xml->endElement();
    return;
}

?>
