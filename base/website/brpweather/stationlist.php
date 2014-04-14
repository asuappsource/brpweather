<?php

require_once("dbconnect.php.inc");
require_once("functions.php");

// list of columns to get
$_colList = "`id`,`name`,`city`,`state`,`zip`,`latitude`,`longitude`,`elevation`,last_update";

// let the browser know that we have an XML document
header("content-type: text/xml");

// begin the XML document
echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";

echo "<station_info>\n";

// setup the database connection
$dbh = dbconnect();

// check to see if the active flag is set and setup query accordingly
if(isset($_GET["active"])) {
    $active_query = ' WHERE isActive = 1';
} else {
    $active_query = '';
}
  $stmt = "SELECT $_colList FROM `station`".$active_query;

// execute the query
$sth = $dbh->prepare($stmt);
$sth->execute();

while ($row = $sth->fetch(PDO::FETCH_ASSOC)) {
  echo "\t<station>\n";
  foreach($row as $colid => $val) {
    echo "\t\t<".htmlspecialchars($colid, ENT_XML1).">".htmlspecialchars($val, ENT_XML1)."</".htmlspecialchars($colid, ENT_XML1).">\n";
  }
  echo "\t</station>\n";
}

echo "</station_info>\n";

$dbh = null;

?>
