<?php

/* 
 * This class is the gateway to the ODK Workflow API
 */
class ODKWorkflowAPI extends Repository {
   public $Dbase;
   private $TAG = "odkworkflowapi";
   private static $STATUS_CODE_OK = "HTTP/1.1 200 OK";
   private static $STATUS_CODE_BAD_REQUEST = "HTTP/1.1 400 Bad Request";
   private static $HEADER_CTYPE_JSON = "Content-Type: application/json";
   private $lH;
   
   public function __construct($Dbase) {
      include_once 'mod_wa_workflow.php';
      include_once 'mod_log.php';
      
      $this->Dbase = $Dbase;
      $this->lH = new LogHandler("./");
      $this->lH->log(4, $this->TAG, "ODK Workflow API called");
   }
   
   /**
    * This function handles requests being handled by this class
    */
   public function trafficController(){
      if(OPTIONS_REQUESTED_SUB_MODULE == ""){//user does not know what to do. Return text
         $this->lH->log(2, $this->TAG, "Client called API without parameters. Setting status code to ".ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
         $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
      }
      else if (OPTIONS_REQUESTED_SUB_MODULE == "init_workflow"){
         $this->handleInitWorkflowEndpoint();
      }
      else if (OPTIONS_REQUESTED_SUB_MODULE == "getRawSheets"){
         
      }
      else if (OPTIONS_REQUESTED_SUB_MODULE == "get_workflows"){
         $this->handleGetWorkflowsEndpoint();
      }
   }
   
   /**
    * This function handles the initWorkflow endpoint of the API.
    * The initWorkflow endpoint expects the following json object in the 
    * $_REQUEST['data'] variable
    * 
    * {
    *    server         :  "requesting server IP (Address should be ILRI DMZ subnet addresses)"
    *    user           :  "The user in the server making the request"
    *    data_file_url  :  "URL to the data file that is resolvable from the DMZ"
    *    workflow_name  :  "The name to give the workflow instance"
    * }
    */
   private function handleInitWorkflowEndpoint() {
      if (isset($_REQUEST['data'])) {
         $json = json_decode($_REQUEST['data'], true);//decode as an associative array
         if($json === null) {
            $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
         }
         else {
            $jsonKeys = array_keys($json);

            //check if json has all the expected fields
            if(array_search("server", $jsonKeys) !== false
                    && array_search("user", $jsonKeys) !== false
                    && array_search("data_file_url", $jsonKeys) !== false
                    && array_search("workflow_name", $jsonKeys) !== false){

               //initialize a workflow object
               $workflow = new Workflow(Config::$config, $json['workflow_name'], $this->generateUserUUID($json['server'],$json['user']));

               //fetch the form data file from the client
               $workflow->addRawDataFile($json['data_file_url']);
               
               //clean up
               $workflow->cleanUp();
               
               //return details back to user
               $data = array(
                   "workflow_id" => $workflow->getInstanceId(),
                   "status" => $workflow->getCurrentStatus()
               );
               $this->returnResponse($data);

            }
            else {
               $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
            }
         }
      }
      else {
         $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
      }
   }
   
   /**
    * This function handles the get_workflow endpoint of the API.
    * The get_workflow endpoint expects the following json object in the 
    * $_REQUEST['data'] variable
    * 
    * {
    *    server      :  "requesting server IP (Address should be ILRI DMZ subnet addresses)"
    *    user        :  "the user making the request"
    * }
    * 
    */
   private function handleGetWorkflowsEndpoint() {
      if(isset($_REQUEST['data'])) {
         $json = json_decode($_REQUEST['data'], true);//decode json to associative array
         if(array_key_exists("server", $json)
                 && array_key_exists("user", $json)) {
            
            $this->returnResponse(Workflow::getUserWorkflows(Config::$config, $this->generateUserUUID($json['server'], $json['user'])));
            
         }
         else {
            $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
         }
      }
      else {
         $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
      }
   }
   
   /**
    * This function generates a UUID for the user by combining ith with the server address
    */
   private function generateUserUUID($server, $user) {
      return $server."_:_".$user;
   }
   
   private function setStatusCode($code) {
      $this->lH->log(3, $this->TAG, "Setting HTTP status code to '$code'");
      header($code);
   }
   
   /**
    * This function returns a response back to the user as a JSON string
    * 
    * @param type $data The data to be sent back to the client
    */
   private function returnResponse($data){
      $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_OK);
      header(ODKWorkflowAPI::$HEADER_CTYPE_JSON);
      echo json_encode($data);
   }
}

?>

