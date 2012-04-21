<?php

// this script send an external command to livestatus
$base = "/var/www/frogx";
include_once ($base."/api/live.php");
include_once ($base."/conf/commands.inc.php");

// command definition 
$command = array(
    "DISABLE_HOST_SVC_CHECKS",
    array("host_name"=>"localhost")
);

// this is a single site command call

// create live object with Unix Socket
$live = new live(array("socket"=>"/opt/monitor/var/rw/live"),1024);

//load authorised external command list
$live->getCommands($config["commands"]);

if(!$live){
    die("Error while connecting");
}else{
    //execute the command
    if(!$live->sendExternalCommand($command)){
        echo "[ERROR ".$live->responsecode."] ".$live->responsemessage;
    }else{
        echo "[SUCCESS]";
    }
}

// 

?>
