<?php

// this script execute a simple livestatus query on table columns
// columns describe all of the others tables of livestatus

require_once("/var/www/frogx/api/live.php");

// live status query (each line is a row in the array)
$query = array("GET columns");

// create live object TCP with xinetd with default buffer size
$live = new live(array("host"=>"localhost", "port"=>"6557"));

// create live object with Unix Socket
//$live = new live(array("socket"=>"/opt/monitor/var/rw/live"),1024);

if(!$live){
    die("Error while connecting");
}else{
    //execute the query
    $json = $live->execQuery($query);
    if($live->responsecode != "200"){
        // error
        die($live->responsecode." : ".$live->responsemessage);
    }
    $response = json_decode($json);
    echo "<table>";
    foreach ($response as $line){
        // line
        echo "<tr>";
        // header
        foreach($line as $col){
            echo "<td style=\"border:1px solid black\">".$col."</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
}
?>
