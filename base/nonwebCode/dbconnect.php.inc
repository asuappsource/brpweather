<?php

function dbconnect() {

    require_once("config.php.inc");

    // create the database handler object
    $dbh = new PDO("mysql:host=".$dbHost.";dbname=".$dbName, $dbUser, $dbPw);

    // Handle db errors by throwing an exception
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return $dbh;
}

?>
