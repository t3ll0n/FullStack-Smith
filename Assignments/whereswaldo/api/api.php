<?php
error_reporting(1);
//https://www.sitepoint.com/php-53-namespaces-basics/
//http://zetcode.com/db/mongodbphp/
//http://coreymaynard.com/blog/creating-a-restful-api-with-php/
//https://programmerblog.net/php-mongodb-tutorial/

require('../scripts/image_helper.php');

abstract class API
{
    /**
     * Property: method
     * The HTTP method this request was made in, either GET, POST, PUT or DELETE
     */
    protected $method = '';
    /**
     * Property: endpoint
     * The Model requested in the URI. eg: /files
     */
    protected $endpoint = '';
    /**
     * Property: verb
     * An optional additional descriptor about the endpoint, used for things that can
     * not be handled by the basic methods. eg: /files/process
     */
    protected $verb = '';
    /**
     * Property: args
     * Any additional URI components after the endpoint and verb have been removed, in our
     * case, an integer ID for the resource. eg: /<endpoint>/<verb>/<arg0>/<arg1>
     * or /<endpoint>/<arg0>
     */
    protected $args = array();
    /**
     * Property: file
     * Stores the input of the PUT request
     */
    protected $file = null;

    /**
     * Constructor: __construct
     * Allow for CORS, assemble and pre-process the data
     */
    public function __construct()
    {
        header("Access-Control-Allow-Orgin: *");
        header("Access-Control-Allow-Methods: *");
        header("Content-Type: application/json");
        
        $this->logger = new thelog();
        $this->logger->clear_log();

        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->request_uri = $_SERVER['REQUEST_URI'];
        
        $this->logger->do_log($this->method);
        
        $this->args = explode('/', rtrim($this->request_uri, '/'));
        
        $this->logger->do_log($this->args);
        
        while ($this->args[0] != 'api.php') {
            array_shift($this->args);
        }
        array_shift($this->args);
        
        $this->endpoint = array_shift($this->args);


        if (strpos($this->endpoint, '?')) {
            list($this->endpoint,$urlargs) = explode('?', $this->endpoint);
        }
        
        $this->logger->do_log($this->args, "args array:");
        $this->logger->do_log($this->endpoint, "endpoint:");
        

        switch ($this->method) {
            case 'POST':
                $this->request = $this->_cleanInputs($_POST);
                break;
            case 'DELETE':
            case 'GET':
                $this->request = $this->_cleanInputs($_GET);
                break;
            case 'PUT':
                $this->request = $this->_cleanInputs($_GET);
                $this->file = file_get_contents("php://input");
                break;
            default:
                $this->_response('Invalid Method', 405);
                break;
        }
        
        if ($urlargs) {
            $urlargs = explode('&', $urlargs);
            for ($i=0; $i<sizeof($urlargs); $i++) {
                list($k,$v) = explode('=', $urlargs[$i]);
                $this->request[$k] = $v;
            }
        }
    }
    
    public function processAPI()
    {
        $this->logger->do_log($this->endpoint);
        if (method_exists($this, $this->endpoint)) {
            return $this->_response($this->{$this->endpoint}($this->args));
        }
        return $this->_response("No Endpoint: $this->endpoint", 404);
    }

    private function _response($data, $status = 200)
    {
        header("HTTP/1.1 " . $status . " " . $this->_requestStatus($status));
        return json_encode($data);
    }

    private function _cleanInputs($data)
    {
        $clean_input = array();
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $clean_input[$k] = $this->_cleanInputs($v);
            }
        } else {
            $clean_input = trim(strip_tags($data));
        }
        return $clean_input;
    }

    private function _requestStatus($code)
    {
        $status = array(
            200 => 'OK',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            500 => 'Internal Server Error',
        );
        return ($status[$code])?$status[$code]:$status[500];
    }
}

class MyAPI extends API
{
    public function __construct()
    {
        parent::__construct();
        
        $this->mdb = 'waldogame';
        $this->mh = new mongoHelper($this->mdb);
        $this->mh->setDbcoll('users');
    }


    public function make_game_board(){
        $args = $this->request;

        $game_id = (string)time();
        $waldo_height = 32;
        $waldo_width = 16;

        // Create instance of our image helper
        $waldoGame = new ImageHelper();

        // example resizing a waldo image
        $waldoImg = $waldoGame->resize_waldo('waldo_walking_200x451.png', $waldo_width, $waldo_height);
    
        list($base_width,$base_height,$null1,$null2) = getimagesize('/var/www/html/waldo/images/crowd.jpg');
        
        $rx = rand(0,$base_width);
        $ry = rand(0,$base_height);

        $data = ['x'=>$rx,'y'=>$ry,'game_id'=>$game_id,'image_path'=>'/var/www/html/waldo/game_images','img_type'=>'png'];

        // put a single waldo on another image
        $waldoGame->place_waldo('/var/www/html/waldo/images/crowd.jpg', $waldoImg, $waldo_width, $waldo_height, $rx, $ry, $game_id.'.png', '/var/www/html/waldo/game_images');

        $this->mh->insert([$data]);

        return $data;

    }
	
	
	//Purpose: gets image path of image make_game_board created from the db/mongodbphp/
	public function get_waldoImage()
	{
		$newstuff = [];
		$result = $this->mh->query($newstuff);
		return $result;
    }
    

    //Purpose: gets waldo location based on matching gameboard
    public function get_waldoLocation()
    {
        $arr = [];
        $i = 0;
        if ($this->method == "GET")
        {
            $game_id = $this->request["game_id"];
            $userx= $this->request["x"];
            $usery = $this->request["y"];

            $result = $this->mh->query(["game_id"=>$game_id],[]);
            foreach ($result as $key => $array) { 
                foreach($array as $key => $value) {
                    $arr[$i] = $value; 
                    $i++;
               }
           }
           $waldox = $arr[1];
           $waldoy = $arr[2];

           //calculate the distance
           $distance = sqrt((pow($waldox - $userx,2)) + (pow($waldoy - $usery,2)));

           if($distance >= 0 && $distance <= 100)
           {
               $message = "found";
               return $message;
           }
           else{
                $message = "nope";
                return $message;
           }
        }
    }

    /////////////////////////////////////////////////////
    private function flatten_array($array){
        foreach($array as $key => $value){
            //If $value is an array.
            if(is_array($value)){
                //We need to loop through it.
                $this->flatten_array($value);
            } else{
                //It is not an array, so print it out.
                $this->temparray[$key] = $value;
            }
        }
    }

    private function addPrimaryKey($data, $coll, $key)
    {
        $max_id = $this->mh->get_max_id($this->mdb, $coll, $key);
        if ($this->has_string_keys($data)) {
            if (!array_key_exists($data, $key)) {
                $data[$key] = $max_id;
            }
        } else {
            foreach ($data as $row) {
                if (!array_key_exists($data, $key)) {
                    $data[$key] = $max_id;
                    $max_id++;
                }
            }
        }
        return $data;
    }
     
     
    private function isAssoc(array $arr)
    {
        if (array() === $arr) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
    
    private function has_string_keys(array $array)
    {
        return count(array_filter(array_keys($array), 'is_string')) > 0;
    }
}

$api = new MyAPI();
echo $api->processAPI();

exit;
