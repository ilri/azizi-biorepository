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
   private $config;
   
   public function __construct($Dbase) {
      include_once 'mod_wa_workflow.php';
      include_once 'mod_log.php';
      
      $this->Dbase = $Dbase;
      $this->config = Config::$config;
      $this->config['common_folder_path'] = OPTIONS_COMMON_FOLDER_PATH;
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
      else if (OPTIONS_REQUESTED_SUB_MODULE == "get_workflows"){
         $this->handleGetWorkflowsEndpoint();
      }
      else if (OPTIONS_REQUESTED_SUB_MODULE == "process_mysql_schema"){
         $this->handleProcessMysqlSchemaEndpoint();
      }
      else if (OPTIONS_REQUESTED_SUB_MODULE == "get_working_status"){
         $this->handleGetWorkingStatusEndpoint();
      }
      else if (OPTIONS_REQUESTED_SUB_MODULE == "get_workflow_schema"){
         $this->handleGetWorkflowSchemaEndpoint();
      }
      else if (OPTIONS_REQUESTED_SUB_MODULE == "alter_field") {
         $this->handleAlterFieldEndpoint();
      }
      else if (OPTIONS_REQUESTED_SUB_MODULE == "get_save_points") {
         $this->handleGetSavePointsEndpoint();
      }
      else {
         $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
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
               $workflow = new Workflow($this->config, $json['workflow_name'], $this->generateUserUUID($json['server'],$json['user']));

               //fetch the form data file from the client
               $workflow->addRawDataFile($json['data_file_url']);
               
               //clean up
               $workflow->cleanUp();
               
               //release resources
               $workflow->finalize();
               
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
            
            $this->returnResponse(Workflow::getUserWorkflows($this->config, $this->generateUserUUID($json['server'], $json['user'])));
            
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
    * This function handles the process_mysql_schema endpoint of the API.
    * The process_mysql_schema endpoint expects the following json object in the 
    * $_REQUEST['data'] variable
    * 
    * {
    *    server      :  "requesting server IP (Address should be ILRI DMZ subnet addresses)"
    *    user        :  "the user making the request"
    *    workflow_id :  "ID of the workflow"
    * }
    * 
    */
   private function handleProcessMysqlSchemaEndpoint() {
      if(isset($_REQUEST['data'])) {
         $json = json_decode($_REQUEST['data'], true);
         if(array_key_exists("server", $json)
                 && array_key_exists("user", $json)
                 && array_key_exists("workflow_id", $json)) {
            $workflow = new Workflow($this->config, null, $this->generateUserUUID($json['server'], $json['user']), $json['workflow_id']);
            
            $workflow->setIsProcessing(true);//set is processing to be true because workflow instance is going to be left processing after response sent to user
            $data = array(
                "status" => $workflow->getCurrentStatus()
            );
            $this->returnResponse($data);
            
            //call this function after sending response to client because it's goin to take some time
            $workflow->convertDataFilesToMySQL();
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
    * This function handles the get_working_status endpoint of the API.
    * The get_working_status endpoint expects the following json object in the 
    * $_REQUEST['data'] variable
    * 
    * {
    *    server      :  "requesting server IP (Address should be ILRI DMZ subnet addresses)"
    *    user        :  "the user making the request"
    *    workflow_id :  "ID of the workflow"
    * }
    * 
    */
   private function handleGetWorkingStatusEndpoint() {
      if(isset($_REQUEST['data'])) {
         $json = json_decode($_REQUEST['data'], true);
         if(array_key_exists("server", $json)
                 && array_key_exists("user", $json)
                 && array_key_exists("workflow_id", $json)) {
            $workflow = new Workflow($this->config, null, $this->generateUserUUID($json['server'], $json['user']), $json['workflow_id']);
            $data = array(
                "status" => $workflow->getCurrentStatus()
            );
            $this->returnResponse($data);
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
    * This function handles the get_workfow_schema endpoint of the API.
    * The get_workflow_schema endpoint returns schema details for all the data
    * storing tables for the provided workflow
    * $_REQUEST['data'] variable
    * 
    * {
    *    server      :  "requesting server IP (Address should be ILRI DMZ subnet addresses)"
    *    user        :  "the user making the request"
    *    workflow_id :  "ID of the workflow"
    * }
    */
   private function handleGetWorkflowSchemaEndpoint() {
      if(isset($_REQUEST['data'])) {
         $json = json_decode($_REQUEST['data'], true);
         if(array_key_exists("server", $json)
                 && array_key_exists("user", $json)
                 && array_key_exists("workflow_id", $json)) {
            $workflow = new Workflow($this->config, null, $this->generateUserUUID($json['server'], $json['user']), $json['workflow_id']);
            $schema = $workflow->getSchema();
            
            $data = array(
                "schema" => $schema,
                "status" => $workflow->getCurrentStatus()
            );
            
            $this->returnResponse($data);
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
    * This function handles the change_field_details endpoint of the API.
    * The change_field_details endpoint changes the schema values for the specified 
    * field.
    * 
    * $_REQUEST['data'] variable
    * {
    *    server      :  "requesting server IP (Address should be ILRI DMZ subnet addresses)"
    *    user        :  "the user making the request"
    *    workflow_id :  "Instance id for the workflow"
    *    sheet       :  "Name of the sheet containing the modified column"
    *    column      :  {"name", "delete", "type", "length", "nullable", "default", "key"}
    * }
    */
   private function handleAlterFieldEndpoint() {
      if(isset($_REQUEST['data'])) {
         $json = json_decode($_REQUEST['data'], true);
         if(array_key_exists("server", $json)
                 && array_key_exists("user", $json)
                 && array_key_exists("workflow_id", $json)
                 && array_key_exists("sheet", $json)
                 && array_key_exists("column", $json)
                 && array_key_exists("original_name", $json['column'])
                 && array_key_exists("name", $json['column'])
                 && array_key_exists("delete", $json['column'])
                 && array_key_exists("type", $json['column'])
                 && array_key_exists("length", $json['column'])
                 && array_key_exists("nullable", $json['column'])
                 && array_key_exists("default", $json['column'])
                 && array_key_exists("key", $json['column'])) {
            $workflow = new Workflow($this->config, null, $this->generateUserUUID($json['server'], $json['user']), $json['workflow_id']);
            $workflow->modifyColumn($json['sheet'], $json['column']);
            
            $data = array(
                "status" => $workflow->getCurrentStatus()
            );
            $this->returnResponse($data);
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
    * This function handles the show_save_points endpoint
    * 
    * $_REQUEST['data'] variable
    * {
    *    server      :  "requesting server IP (Address should be ILRI DMZ subnet addresses)"
    *    user        :  "the user making the request"
    *    workflow_id :  "Instance id for the workflow"
    * }
    */
   private function handleGetSavePointsEndpoint() {
      if(isset($_REQUEST['data'])) {
         $json = json_decode($_REQUEST['data'], true);
         if(array_key_exists("server", $json)
                 && array_key_exists("user", $json)
                 && array_key_exists("workflow_id", $json)) {
            $workflow = new Workflow($this->config, null, $this->generateUserUUID($json['server'], $json['user']), $json['workflow_id']);
            $savePoints = $workflow->getSavePoints();
            
            $data = array(
                "save_points" => $savePoints,
                "status" => $workflow->getCurrentStatus()
            );
            
            $this->returnResponse($data);
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
   
   public static function explodeUserUUID($userUUID) {
      $details = explode("_:_", $userUUID);
      if(count($details) == 2) {
         return array("server" => $details[0], "user" => $details[1]);
      }
      
      return null;
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

