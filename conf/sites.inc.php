<?php
/**
 * This config file is used to define the nagios/shinken collectors
 * 
 * @global array $GLOBALS['config']
 * @name $config
 */
$config["sites"]=array(
    "host-unix"=>array(
        "type"=>"UNIX",
        "socket"=>"/opt/monitor/var/rw/live"
    )
//    ,
//    "host-tcp"=>array(
//        "type"=>"TCP",
//        "host"=>"localhost",
//        "port"=>"6557"
//    )  
//      
);

?>
