<?php
$config["commands"]=array(
        "ACKNOWLEDGE_HOST_PROBLEM"=>array("host_name","sticky","notify","persistent","author","comment"),
        "ACKNOWLEDGE_SVC_PROBLEM"=>array("host_name","service_description","sticky","notify","persistent","author","comment"),
        "SCHEDULE_HOST_DOWNTIME"=>array("host_name","start_time","end_time","fixed","trigger_id","duration","author","comment"),
        "SCHEDULE_SVC_DOWNTIME"=>array("host_name","service_description","start_time","end_time","fixed","trigger_id","duration","author","comment"),
        "SCHEDULE_HOST_SVC_DOWNTIME"=>array("host_name","start_time","end_time","fixed","trigger_id","duration","author","comment"),

        "DEL_HOST_DOWNTIME"=>array("downtime_id"),
        "DEL_SVC_DOWNTIME"=>array("downtime_id"),

        "DISABLE_HOST_SVC_CHECKS"=>array("host_name"),

        "DISABLE_HOST_CHECK"=>array("host_name"),
        "ENABLE_HOST_CHECK"=>array("host_name"),
        "DISABLE_PASSIVE_HOST_CHECKS"=>array("host_name"),
        "ENABLE_PASSIVE_HOST_CHECKS"=>array("host_name"),

        "DISABLE_SVC_CHECK"=>array("host_name","service_description"),
        "ENABLE_SVC_CHECK"=>array("host_name","service_description"),
        "DISABLE_PASSIVE_SVC_CHECKS"=>array("host_name","service_description"),
        "ENABLE_PASSIVE_SVC_CHECKS"=>array("host_name","service_description"),

        "ADD_HOST_COMMENT"=>array("host_name","persistent","author","comment"),
        "ADD_SVC_COMMENT"=>array("host_name","service_description","persistent","author","comment"),

        "SCHEDULE_SVC_CHECK"=>array("host_name","service_description","check_time"),
        "SCHEDULE_FORCED_SVC_CHECK"=>array("host_name","service_description","check_time"),
        "REMOVE_SVC_ACKNOWLEDGEMENT"=>array("host_name","service_description"),
        "DISABLE_SVC_NOTIFICATIONS"=>array("host_name","service_description"),
        "ENABLE_SVC_NOTIFICATIONS"=>array("host_name","service_description")
);
?>
