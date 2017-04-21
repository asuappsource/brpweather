<?php


function dbconnect() {
    require_once("/home/brpweather/data-ingestion/config.php.inc");

    // create the database handler object
    $dbh = new PDO("mysql:host=".$dbHost.";dbname=".$dbName,$dbUser,$dbPw);

    // sets up the connection to stop PHP is an error occurs
    // errors thrown as exceptions
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return $dbh;
}

?>
