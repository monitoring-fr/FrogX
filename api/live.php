<?php
/**
 * base class to use live status implementation in shinken and nagios
 *
 * @package     livestatusapi
 * @author      David GUENAULT (dguenault@monitoring-fr.org)
 * @copyright   (c) 2010 Monitoring-fr Team / Shinken Team (http://monitoring-fr.org / http://www.shinken-monitoring.org)
 * @license     Afero GPL
 * @version     0.1
 */
class live {
	/**
	 * 
	 * this is an array containing the location of each livestatus sites parameters 
	 * @var array $sites List of sites paramaters (see sites.inc.php) 
	 */
	protected $sites = null;
	/**
	* the socket used to communicate 
    * @var ressource
	*/
    protected	$socket         = null;
    /**
    * path to the livestatus unix socket 
    * @var string 
    */
    protected       $socketpath     = null;
    /**
    * host name or ip address of the host that serve livestatus queries
    * @var string 
    */
	protected   	$host           = null;
    /**
    * TCP port of the remote host that serve livestatus queries
    * @var int
    */
    protected   	$port           = null;
    /**
    * the socket buffer read length
    * @var int
    */
    protected   	$buffer         = 1024;
    /**
    * the cahracter used to define newlines (livestatus use double newline character end of query definition)
    * @var char
    */
    protected   	$newline        = "\n";
    /**
    * this is the version of livestatus it is automaticaly filed by the getLivestatusVersion() method
    * @var string
    */
	protected   	$livever	= null;	
    /**
    * default headersize (in bytes) returned after a livestatus query (always 16)
    * @var int
    */
    protected   	$headersize     = 16;
    /**
    *
    * @commands array list all authorized commands
    */
    protected       $commands = null;
    /**
    * this define query options that will be added to each query (default options)
    * @var array
    */
    
    protected		$resultArray = array();
    
    public	$defaults = array(
							"ColumnHeaders: on",
                        	"ResponseHeader: fixed16",
                            //  "KeepAlive: on",
                        	"OutputFormat: json"
                        	);
	/**
    * used to make difference between pre 1.1.3 version of livestatus return code and post 1.1.3
    * @var array
    */
	protected $messages = array(
		"versions" => array(
		"1.0.19" => "old",
		"1.0.20" => "old",
		"1.0.21" => "old",
		"1.0.22" => "old",
		"1.0.23" => "old",
		"1.0.24" => "old",
		"1.0.25" => "old",
		"1.0.26" => "old",
		"1.0.27" => "old",
		"1.0.28" => "old",
		"1.0.29" => "old",
		"1.0.30" => "old",
		"1.0.31" => "old",
		"1.0.32" => "old",
		"1.0.33" => "old",
		"1.0.34" => "old",
		"1.0.35" => "old",
		"1.0.36" => "old",
		"1.0.37" => "old",
		"1.0.38" => "old",
		"1.0.39" => "old",
		"1.1.0" => "old",
		"1.1.1" => "old",
		"1.1.2" => "old",
		"1.1.3" => "new",
		"1.1.4" => "new",
		"1.1.5i0" => "new",
		"1.1.5i1" => "new",
		"1.1.5i2" => "new",
		"1.1.5i3" => "new",
		"1.1.6b2" => "new",
		"1.1.6b3" => "new",
		"1.1.6rc1" => "new",
		"1.1.6rc2" => "new",
		"1.1.6rc3" => "new",
		"1.1.6p1" => "new",
		"1.1.7i1" => "new",
		"1.1.7i2" => "new"
		),
		"old" => array(
			"200"=>"OK. Reponse contains the queried data.",
			"401"=>"The request contains an invalid header.",
			"402"=>"The request is completely invalid.",
			"403"=>"The request is incomplete",
			"404"=>"The target of the GET has not been found (e.g. the table).",
			"405"=>"A non-existing column was being referred to"
			),
		"new" => array(
			"200"=>"OK. Reponse contains the queried data.",
			"400"=>"The request contains an invalid header.",
			"403"=>"The user is not authorized (see AuthHeader)",
			"404"=>"The target of the GET has not been found (e.g. the table).",
			"450"=>"A non-existing column was being referred to",
			"451"=>"The request is incomplete.",
			"452"=>"The request is completely invalid."
			)
	);

	/**
    * json response of the query
    * @var string
    */
    public	$queryresponse  = null;
    
    /**
    * response code returned after query
    * @var int
    */
	public	$responsecode = null;
    
    /**
    * response message returned after query
    */
	public  $responsemessage = null; 

	/**
    * Class Constructor
    * @param array params array of parameters (if socket is in the keys then the connection is UNIX SOCKET else it is TCP SOCKET)
    * @param int buffer used to read query response
    */
	public function __construct($params=null,$buffer=1024){
    	// fucking php limitation on declaring multiple constructors !
        if(isset($params["socket"])){
        	$this->socketpath = $params["socket"];
            $this->buffer = $buffer;
            $this->getLivestatusVersion();
		}elseif(isset($params["host"]) && isset($params["port"]) ){
        	$this->host   = $params["host"];
            $this->port   = $params["port"];
            $this->buffer = $buffer;
            $this->getLivestatusVersion();
		}elseif(isset($params["multisite"])){
        	// multi site rule !
            array_shift($params);
            $this->sites = $params;
            $this->buffer = $buffer;
		}else{
			return false;
		}
		return true;
	}
			
	/**
    * Class destructor
    */
    public function  __destruct() {
    	$this->disconnect();
        $this->queryresponse = null;
	}

	/**
	 * 
	 * Execute a multisite query
	 * @param $query the query to be executed
	 */
	public function execQueryMultisite($query){
		// response array
		$responses=array();
		// reset connection data
		$this->resetConnection();
		//iterate through each site and execute the query
		foreach (array_keys($this->sites) as $site){
			// set connection data
			$params = $this->sites[$site];
			if(isset($params["socket"])){
        		$this->socketpath = $params["socket"];
            	$this->getLivestatusVersion();
			}elseif(isset($params["host"]) && isset($params["port"]) ){
        		$this->host   = $params["host"];
            	$this->port   = $params["port"];
            	$this->getLivestatusVersion();
			}
			// execute query for current site;
			$response = json_decode($this->execQuery($query));
			// record result			
			if($response){
				$this->agregateResult($response,$site);
			}
			$response = ""; 		
			$this->resetConnection();	
		}
		return json_encode($this->resultArray);
	}
	
    /**
     * This method get the overall status of all services and hosts. if $sites is an array of sites names it will only retrieve the tac for the specified sites.
     * @param array sites an array off all of the monitoring hosts (see conf/sites.inc.php) if null get tac for all sites
     * @return string   the json encoded tac values
     */
    public function getOverallStates($sites=null){
    	// TODO : introduce a possibility to get TAC for a filtered list .....
    	
    	if(!is_null($sites)){
    		$this->sites = $sites;
    	}
    	
        // get overall services states  
        $queryservices = array(
			"GET services",
        	"Filter: last_check > 0",		// not pending ?
        	"Stats: last_hard_state = 0",
			"Stats: last_hard_state = 1",
			"Stats: last_hard_state = 2",
			"Stats: last_hard_state = 3",  
        );
        
        // pending services
        $querypendingservices = array(
			"GET services",
        	"Stats: last_check = 0",
        );
        
        // get overall hosts states 
        $queryhosts = array(
			"GET hosts",
        	"Filter: last_check > 0",		// not pending ?
			"Stats: last_hard_state = 0",
			"Stats: last_hard_state = 1",
			"Stats: last_hard_state = 2"
        );

        // pending services
        $querypendinghosts = array(
			"GET hosts",
        	"Stats: last_check = 0",
        );
        
        $failedservices = array();
        $failedhosts = array();
        
        $hosts=array(
            "UP"=>0,
            "DOWN"=>0,
            "UNREACHABLE"=>0,
            "ALL PROBLEMS"=>0,
            "ALL TYPES"=>0,
        	"PENDING"=>0      
        );        
        
        $services=array(
            "OK"=>0,
            "WARNING"=>0,
            "CRITICAL"=>0,
            "UNKNOWN"=>0,
            "ALL PROBLEMS"=>0,
            "ALL TYPES"=>0,
        	"PENDING"=>0  
        );        
        
        foreach($this->sites as $site){
        	switch ($site["type"]){
        		case "UNIX":
        			$this->socketpath = $site["socket"];
        			$this->host=null;
        			$this->port=null;
        			$this->getLivestatusVersion();
        			break;
        		case "TCP":
        			$this->socketpath = null;
        			$this->host=$site["host"];
        			$this->port=$site["port"];
        			$this->getLivestatusVersion();
        			break;
        		default:
        			return false;
        	}
        	if($this->socket) { $this->resetConnection();}
        	if($this->connect()){
        		$result=$this->execQuery($queryservices);
	            if($result == false){
	                // one ore more sites failed to execute the query
	                // we keep a trace
	                $failedservices[]=array("site"=>$site,"code"=>$this->responsecode,"message"=>$this->responsemessage);
	                return false;
	            }else{
	                $states=json_decode($this->queryresponse);
	                
	                // query pending services
	                $result = $this->execQuery($querypendingservices);
	                $pending = json_decode($this->queryresponse);
	                
	                // PENDING
	                $services["PENDING"] += $pending[0][0];
	                // OK
	                $services["OK"] += $states[0][0];
	                // WARNING
	                $services["WARNING"] += $states[0][1];
	                // CRITICAL
	                $services["CRITICAL"] += $states[0][2];
	                // UNKNOWN
	                $services["UNKNOWN"] += $states[0][3];
	                // ALL TYPES
	                $services["ALL TYPES"] += $services["OK"]+$services["WARNING"]+$services["CRITICAL"]+$services["UNKNOWN"]+$services["PENDING"];
	                // ALL PROBLEMS
	                $services["ALL PROBLEMS"] += $services["WARNING"]+$services["CRITICAL"]+$services["UNKNOWN"];
	            }
	            $result=$this->execQuery($queryhosts);
	            if(!$result){
	                // one ore more sites failed to execute the query
	                // we keep a trace
	                $failedhosts[]=array("site"=>$site,"code"=>$this->responsecode,"message"=>$this->responsemessage);
	            }else{
	                $states=json_decode($this->queryresponse);
	                
	                // query pending hosts
	                $result = $this->execQuery($querypendinghosts);
	                $pending = json_decode($this->queryresponse);
	                
	                // PENDING
	                $hosts["PENDING"] += $pending[0][0];
	                // UP
	                $hosts["UP"] += $states[0][0];
	                // DOWN
	                $hosts["DOWN"] += $states[0][1];
	                // UNREACHABLE
                	$hosts["UNREACHABLE"] += $states[0][2];
	                // ALL TYPES
	                $hosts["ALL TYPES"] = $hosts["UP"]+$hosts["DOWN"]+$hosts["UNREACHABLE"]+$hosts["PENDING"];
	                // ALL PROBLEMS
	                $hosts["ALL PROBLEMS"] = $hosts["DOWN"]+$hosts["UNREACHABLE"];
	            }
        	}else{
	            // one ore more sites failed to connect 
	            // we keep a trace
	            $failedhosts[]=array("site"=>$site,"code"=>$this->responsecode,"message"=>$this->responsemessage);
        	}
        }
        
		return json_encode(array(
			"hosts"=>(array)$hosts,
		    "services"=>(array)$services
		)); 	            
    }	
	
    
    /**
     * 
     * Enter description here ...
     */
    public function getPerformances($sites=null){
    	if(!is_null($sites)){
    		$this->sites = $sites;
    	}

        $ts = strtotime("now");
        $ts1min = strtotime("-1 minute");
        $ts5min = strtotime("-5 minute");
		$ts15min = strtotime("-15 minute");
        $ts1hour = strtotime("-1 hour");    	
    	
        // get active services stats
        $queryactiveservicestats = array(
			"GET services",
        	"Stats: min latency",
        	"Stats: max latency",
        	"Stats: avg latency",
        	"Stats: min execution_time",
        	"Stats: max execution_time",
        	"Stats: avg execution_time",
        	"Stats: min percent_state_change",
        	"Stats: max percent_state_change",
        	"Stats: avg percent_state_change",
        	"Stats: last_check >= ".$ts1min,
        	"Stats: last_check >= ".$ts5min,
        	"Stats: last_check >= ".$ts15min,
        	"Stats: last_check >= ".$ts1hour,
        	"Stats: last_check > 0",
			"Filter: has_been_checked = 1",
        	"Filter: check_type = 0"        	
        );

        // get passive services stats
        $querypassiveservicestats = array(
			"GET services",
        	"Stats: min percent_state_change",
        	"Stats: max percent_state_change",
        	"Stats: avg percent_state_change",
        	"Stats: last_check >= ".$ts1min,
        	"Stats: last_check >= ".$ts5min,
        	"Stats: last_check >= ".$ts15min,
        	"Stats: last_check >= ".$ts1hour,
        	"Stats: last_check > 0",
			"Filter: has_been_checked = 1",
        	"Filter: check_type = 1"        	
        );        
        
        $queryactivehoststats = array(
			"GET hosts",
        	"Stats: min latency",
        	"Stats: max latency",
        	"Stats: avg latency",
        	"Stats: min execution_time",
        	"Stats: max execution_time",
        	"Stats: avg execution_time",
        	"Stats: min percent_state_change",
        	"Stats: max percent_state_change",
        	"Stats: avg percent_state_change",
        	"Stats: last_check >= ".$ts1min,
        	"Stats: last_check >= ".$ts5min,
        	"Stats: last_check >= ".$ts15min,
        	"Stats: last_check >= ".$ts1hour,
        	"Stats: last_check > 0",
			"Filter: has_been_checked = 1",
        	"Filter: check_type = 0"        	
        );

        // get passive hosts stats
        $querypassivehoststats = array(
			"GET hosts",
        	"Stats: min percent_state_change",
        	"Stats: max percent_state_change",
        	"Stats: avg percent_state_change",
        	"Stats: last_check >= ".$ts1min,
        	"Stats: last_check >= ".$ts5min,
        	"Stats: last_check >= ".$ts15min,
        	"Stats: last_check >= ".$ts1hour,
        	"Stats: last_check > 0",
			"Filter: has_been_checked = 1",
        	"Filter: check_type = 1"        	
        );                
        
        foreach(array_keys($this->sites) as $key){
        	$site = $this->sites[$key];
        	switch ($site["type"]){
        		case "UNIX":
        			$this->socketpath = $site["socket"];
        			$this->host=null;
        			$this->port=null;
        			$this->getLivestatusVersion();
        			break;
        		case "TCP":
        			$this->socketpath = null;
        			$this->host=$site["host"];
        			$this->port=$site["port"];
        			$this->getLivestatusVersion();
        			break;
        		default:
        			return false;
        	}
        	if($this->socket) { $this->resetConnection();}
        	if($this->connect()){
        		
        		$this->execQuery($queryactiveservicestats );
        		$activeservicesstats = json_decode($this->queryresponse);
        		$activeservicesstats = $activeservicesstats[0];

        		$this->execQuery($querypassiveservicestats );
        		$passiveservicesstats = json_decode($this->queryresponse);
        		$passiveservicesstats = $passiveservicesstats[0];
        		
        		$this->execQuery($queryactivehoststats );
        		$activehostsstats = json_decode($this->queryresponse);
        		$activehostsstats = $activehostsstats[0];

        		$this->execQuery($querypassivehoststats );
        		$passivehostsstats = json_decode($this->queryresponse);
        		$passivehostsstats = $passivehostsstats[0];				
        		
				$performances[]=array(
					"site"=>$key,
					"services"=>array(
						"active"=>array(
							"execStats"=>array(
								"lte1min"=>$activeservicesstats[9],
								"lte5min"=>$activeservicesstats[10],
								"lte15min"=>$activeservicesstats[11],
								"lte1hour"=>$activeservicesstats[12],
								"sinceStart"=>$activeservicesstats[13],
								
							),
							"latency"=>array(
								"min"=>$activeservicesstats[0],
								"max"=>$activeservicesstats[1],
								"average"=>$activeservicesstats[2]
							),
							"executiontime"=>array(
								"min"=>$activeservicesstats[3],
								"max"=>$activeservicesstats[4],
								"average"=>$activeservicesstats[5]
							),
							"percentstatechange"=>array(
								"min"=>$activeservicesstats[6],
								"max"=>$activeservicesstats[7],
								"average"=>$activeservicesstats[8]
							)
						),
						"passive"=>array(
							"execStats"=>array(
								"lte1min"=>$passiveservicesstats[3],
								"lte5min"=>$passiveservicesstats[4],
								"lte15min"=>$passiveservicesstats[5],
								"lte1hour"=>$passiveservicesstats[6],
								"sinceStart"=>$passiveservicesstats[7]
							),						
							"percentstatechange"=>array(
								"min"=>$passiveservicesstats[0],
								"max"=>$passiveservicesstats[1],
								"average"=>$passiveservicesstats[2]
							)
						)
					),
					"hosts"=>array(
						"active"=>array(
							"execStats"=>array(
								"lte1min"=>$activehostsstats[9],
								"lte5min"=>$activehostsstats[10],
								"lte15min"=>$activehostsstats[11],
								"lte1hour"=>$activehostsstats[12],
								"sinceStart"=>$activehostsstats[13],
								
							),
							"latency"=>array(
								"min"=>$activehostsstats[0],
								"max"=>$activehostsstats[1],
								"average"=>$activehostsstats[2]
							),
							"executiontime"=>array(
								"min"=>$activehostsstats[3],
								"max"=>$activehostsstats[4],
								"average"=>$activehostsstats[5]
							),
							"percentstatechange"=>array(
								"min"=>$activehostsstats[6],
								"max"=>$activehostsstats[7],
								"average"=>$activehostsstats[8]
							)
						),
						"passive"=>array(
							"execStats"=>array(
								"lte1min"=>$passivehostsstats[3],
								"lte5min"=>$passivehostsstats[4],
								"lte15min"=>$passivehostsstats[5],
								"lte1hour"=>$passivehostsstats[6],
								"sinceStart"=>$passivehostsstats[7]
							),						
							"percentstatechange"=>array(
								"min"=>$passivehostsstats[0],
								"max"=>$passivehostsstats[1],
								"average"=>$passivehostsstats[2]
							)
						)
					)
				);
				
				
        	}
        }
		return json_encode($performances);
    }

    public function getHealth($sites){
    	
    }
    
    public function getFeatures($sites){
    	
    }
    
	/**
	 * 
	 * Enter description here ...
	 * @param unknown_type $source
	 * @param unknown_type $site
	 */
	private function agregateresult($source,$site){
		$headers = array_shift($source);
		$headers[]="site";
		foreach($source as $row){
			array_push($row,$site);
			$this->resultArray[] = array_combine($headers,$row);
		}
	}    
    
	/**
	 * 
	 * specific to multisite query (see execQueryMultisite)
	 */
	private function resetConnection(){
		$this->socketpath = null;
		$this->host = null;
		$this->port = null;
		if($this->socket){
			$this->disconnect();
		}
		return true;
	}

        /**
         * execute a livestatus query and return result as json
         * @param array $query elements of the querry (ex: array("GET services","Filter: state = 1","Filter: state = 2", "Or: 2"))
         * @return string the json encoded result of the query
         */
        private function execQuery($query){
            if($this->socket){
                $this->disconnect();
            }
            if(!$this->connect()){
                $this->responsecode = "501";
                $this->responsemessage="[SOCKET] ".$this->getSocketError();
                return false;
            }else{
                if(!$this->query($query)){
                    $this->responsecode = "501";
                    $this->responsemessage="[SOCKET] ".$this->getSocketError();
                    $this->disconnect();
                    return false;
                }else{
                    if(!$this->readresponse()){
                        $this->disconnect();
                        return false;
                    }else{
                        $this->disconnect();
                        return $this->queryresponse;
                    }
                }
            }
        }

        /**
         * This method submit an external command to nagios through livestatus socket.
         * @param array $command an array describing the command array("COMMANDNAME",array("paramname"=>"value","paramname"=>"value",...)
         * @return bool true if success false il failed
         */
        public function sendExternalCommand($command){
            if(!$this->parseCommand($command)){
                return false;
            }else{
                if(!$this->submitExternalCommand($command)){
                    return false;
                }else{
                    return true;
                }
            }
        }

        /**
         * load commands defined in commands.inc.php
         */
        public function getCommands($commands){
            $this->commands = $commands;
        }


/**
 * PRIVATE METHODS
 */

        /**
         * Abstract method that choose wich connection method we should use.....
         */
        protected function connect(){
            if(is_null($this->socketpath)){
                return $this->connectTCP();
            }else{
                return $this->connectUNIX();
            }
	}
        /**
         * connect to livestatus through TCP.
         * @return bool true if success false if fail
         */
        private function connectTCP(){
            $this->socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            if( $this->socket == false){
                $this->socket = false;
                return false;
            }
            $result = @socket_connect($this->socket, $this->host,$this->port);
            if ($result == false){
                $this->socket = null;
                return false;
            }
            return true;
        }

        private function connectUNIX(){
            $this->socket = @socket_create(AF_UNIX, SOCK_STREAM, SOL_SOCKET);
            if( $this->socket == false){
                $this->socket = false;
                return false;
            }
            $result = @socket_connect($this->socket, $this->socketpath);
            if ($result == false){
                $this->socket = null;
                return false;
            }
            return true;
        }

        private function connectSSH(){
            die("Not implemented");
        }

	/**
	 * 
	 * Enter description here ...
	 */
	private function disconnect(){
		if ( ! is_null($this->socket)){
        	// disconnect gracefully
            socket_shutdown($this->socket,2);
            socket_close($this->socket);
            $this->socket = null;
            return true;
		}else{
        	return false;
		}
	}
	
	/**
	 * get livestatus version and put it in livever class property
	 */
	protected function getLivestatusVersion(){
		$query = array(
			"GET status",
			"Columns: livestatus_version",
		);
		
		$this->execQuery($query);
		$result = json_decode($this->queryresponse);

		$this->livever = $result[1][0];
                $this->responsecode=null;
                $this->responsemessage=null;
                $this->queryresponse=null;
		}

		
	/**
	 * 
	 * Enter description here ...
	 * @param unknown_type $elements
	 * @param unknown_type $default
	 */
	private function query($elements,$default="json") {
    	$query = $this->preparequery($elements,$default);
        foreach($query as $element){
        	if(($this->socketwrite($element.$this->newline)) == false){
            	return false;
			}
		}
		// finalize query
        if(($this->socketwrite($this->newline)) == false){
        	return false;
		}
		return true;
	}

	/**
	 * 
	 * Enter description here ...
	 */
	private function readresponse(){
		$this->queryresponse="";
		if ( ! is_null( $this->socket ) ){
        	$headers = $this->getHeaders();
            $code = $headers["statuscode"];
            $size = $headers["contentlength"];
			$this->responsecode = $code;
			$this->responsemessage = $this->code2message($code);
			if($code != "200"){
				return false;
			}
            $this->queryresponse = $this->socketread($size);
            return true;
		}else{
        	return false;
		}
	}

	/**
	 * 
	 * Enter description here ...
	 * @param unknown_type $code
	 */
	private function code2message($code){
		if ( ! isset($this->messages["versions"][$this->livever])){
			// assume new
			$type = "new";
		}else{
			$type = $this->messages["versions"][$this->livever];
		}
		$message = $this->messages[$type][$code];
		return $message;
	}

	/**
	 * 
	 * Enter description here ...
	 * @param unknown_type $size
	 */
	private function socketread($size){
    	if ( ! is_null( $this->socket ) ){
        	$buffer = $this->buffer;
            $socketData = "";
            if($size <= $buffer){
            	$socketData = @socket_read($this->socket,$size);
			}else{
            	while($read = @socket_read($this->socket, $buffer)){
					$socketData .= $read;
				}
			}
			return $socketData;
		}else{
			return false;
		}
	}

	/**
	 * 
	 * Enter description here ...
	 * @param unknown_type $data
	 */
	private function socketwrite($data){
		if ( ! is_null( $this->socket ) ){
        	if (socket_write($this->socket, $data) === false){
            	return false;
			}else{
				return true;
			}
		}else{
			return false;
		}
		return true;
	}

	/**
	 * 
	 * Enter description here ...
	 */
    public function getSocketError(){
		$errorcode = socket_last_error();
		$errormsg = socket_strerror($errorcode);
		return $errormsg;
	}

        private function getHeaders(){
            if ( ! is_null( $this->socket ) ){
                $rawHeaders = @socket_read($this->socket, $this->headersize);
                return array(
                    "statuscode" => substr($rawHeaders, 0, 3),
                    "contentlength" => intval(trim(substr($rawHeaders, 4, 11)))
                );
            }else{
                return false;
            }
        }

        private function preparequery($elements){
            $query=$this->defaults;
            return array_merge((array)$elements,(array)$query);
        }


        /**
         * This function submit an external command to the livestatus enabled host
         * @param array $command an array defining the command array("commandname",array("argname"=>"value","argname"=>"value",...))
         * @return bool true if success, false if failed
         */
        private function submitExternalCommand($command){
            $arguments = "";
            foreach(array_keys($command[1]) as $key){
                
                $arguments .= $command[1][$key].";";
            }
            $arguments = substr($arguments, 0, strlen($arguments)-1);
            $commandline = "COMMAND [".time()."] ".$command[0].";".$arguments;
            if($this->socket){
                $this->disconnect();
            }
            if(!$this->connect()){
                $this->responsecode = "501";
                $this->responsemessage="[SOCKET] ".$this->getSocketError();
                return false;
            }else{
                if(!$this->query($commandline)){
                    $this->responsecode = "501";
                    $this->responsemessage="[SOCKET] ".$this->getSocketError();
                    $this->disconnect();
                    return false;
                }else{
                    $this->responsecode = null;
                    $this->responsemessage = null;
                    return true;
                }
            }
        } 
        /**
         * This function is used to parse and validate commands before submit them.
         * @param array $command an array defining the command array("commandname",array("argname"=>"value","argname"=>"value",...))
         * @return bool true id ok false if not. the raison is stored in responsecode and response message class properties.
         */
        private function parseCommand($command){

            // check if there is 2 elements in the array
            if(count($command) != 2){
                $this->responsecode = "602";
                $this->responsemessage = "Invalid message definition (wrong number of entries in \$command)";
            }else{
                // check if first element exist as a key in commands definition
                if(!array_key_exists($command[0], $this->commands)){
                    $this->responsecode = "602";
                    $this->responsemessage = "Invalid message definition (command ".$command[0]." not found)";
                }else{
                    // check number of arguments against command definition
                    if(count($this->commands[$command[0]]) != count($command[1])){
                        $this->responsecode = "602";
                        $this->responsemessage = "Invalid number of arguments (required : ".count($this->commands[$command[0]]).", provided : ".count($command[1]).")";
                    }else{
                        // check argument's names
                        $defined_keys = $this->commands[$command[0]];
                        $provided_keys = array_keys($command[1]);
                        $diff_keys = array_diff($defined_keys, $provided_keys);
                        if ( count($diff_keys) > 0 ){
                            $this->responsecode = "602";
                            $this->responsemessage = "The arguments provided doesn't match the required arguments (".implode(", ", $diff_keys).")";
                        }else{
                            $this->responsecode = null;
                            $this->responsemessage = null;
                            return true;
                        }
                    }
                }
            }
            return false;
        }
}
