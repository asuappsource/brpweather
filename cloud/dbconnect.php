<?php

require_once("config.inc.php");

function dbconnect() {
  global $_host, $_user, $_pw, $_db;
  // create the database handler object
  $dbh = new PDO("mysql:host=".$_host.";dbname=".$_db,$_user,$_pw);
  // sets up the connection to stop PHP is an error occurs
  // errors thrown as exceptions
  $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  return $dbh;
}

?>