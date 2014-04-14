<?php

require_once("dbconnect.php.inc");
require_once("functions.php");

global $xml;
$_colList = "`id`,`name`,`city`,`state`,`zip`,`latitude`,`longitude`,`elevation`,`last_update`,`isActive`";

if (isset($_GET['json'])) {
    header('content-type: application/json');
    $json = array();
} else {
    header('content-type: text/xml');
    $xml = new XmlWriter();
    $xml->openMemory();
    $xml->startDocument('1.0', 'UTF-8');
    $xml->startElement('stations');
    //$xml->writeElement('param-stations', $_GET['station']);
}

$dbh = dbconnect();

$stmt = "SELECT $_colList FROM `station`";

if (isset($_GET['station'])) {
    $stmt .= ' WHERE `id`=:station';
    $sth = $dbh->prepare($stmt);
    $sth->bindParam(':station', $_GET['station'], PDO::PARAM_INT);
} else {
    $sth = $dbh->prepare($stmt);
}

$sth->execute();

while ($row = $sth->fetch(PDO::FETCH_ASSOC)) {
    if ($row['isActive'] && strtotime($row['last_update']) > 0) {
        
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
    
    $xml->startElement('station');
    
    foreach ($row as $id=>$value)
        $xml->writeElement($id, $value);

    $xml->endElement();
    return;
}

?>
