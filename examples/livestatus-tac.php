<?php
/**
 * get overall hosts and services states (as known as tactical overview)
 * this introduce the concept of multisite. Each site is an entry in the array config["sites"]
 */
require_once("/var/www/frogx/conf/sites.inc.php");
require_once("/var/www/frogx/api/live.php");

$tac = new live($config["sites"],1024);
// get global states
print_r(json_decode($tac->getOverallStates()));
// get performances
print_r(json_decode($tac->getPerformances()));
?>
