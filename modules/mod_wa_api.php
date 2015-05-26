<?php

/* 
 * This class is the gateway to the ODK Workflow API
 */
class ODKWorkflowAPI extends Repository {
   public $Dbase;
   private $TAG = "odkworkflowapi";
   private static $STATUS_CODE_OK = "HTTP/1.1 200 OK";
   private static $STATUS_CODE_BAD_REQUEST = "HTTP/1.1 400 Bad Request";
   private static $STATUS_CODE_FORBIDDEN = "HTTP/1.1 403 Forbidden";
   private static $HEADER_CTYPE_JSON = "Content-Type: application/json";
   private $lH;
   private $config;
   private $userUUID;
   
   public function __construct() {
      include_once 'mod_wa_workflow.php';
      include_once 'mod_log.php';
      include_once 'mod_wa_database.php';
      include_once 'mod_wa_exception.php';
      
      $this->config = Config::$config;
      $this->config['common_folder_path'] = OPTIONS_COMMON_FOLDER_PATH;
      //$this->config['timeout'] = Config::$timeout;//TODO: uncomment when deploying
      $this->config['timeout'] = 100000000;
      include_once OPTIONS_COMMON_FOLDER_PATH."authmodules/mod_security_v0.1.php";
      $this->lH = new LogHandler("./");
      $this->Dbase = new DBase("mysql");
      $this->Dbase->InitializeConnection($this->config);
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
      else if (OPTIONS_REQUESTED_SUB_MODULE == "auth"){
         if(isset($_REQUEST['token'])) {
            $authJson = $this->getData($_REQUEST['token']);
            if(array_key_exists("server", $authJson)
                    && array_key_exists("user", $authJson)
                    && array_key_exists("secret", $authJson)
                    && array_key_exists("auth_mode", $authJson)
                    && ($authJson['auth_mode'] == "local" || $authJson['auth_mode'] == "ldap")) {
               try {
                  $sessionId = $this->authUser($this->generateUserUUID($authJson['server'], $authJson['user']), $authJson['auth_mode'], $authJson['secret']);
                  $data = array(
                      "session" => $sessionId,
                      "status" => array("healthy" => true, "errors" => array())
                  );
                  
                  $this->returnResponse($data);
               } catch (WAException $ex) {
                  $data = array(
                      "session" => null,
                      "status" => array("healthy" => FALSE, "errors" =>array(Workflow::getErrorMessage($ex)))
                  );
                  
                  $this->returnResponse($data);
               }
            }
            else {
               $this->lH->log(2, $this->TAG, "Either server, secret or user not set in data provided to auth endpoint '".$_REQUEST['token']."'");
               $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
            }
         }
         else {
            $this->lH->log(2, $this->TAG, "Token variable not set in data provided to auth endpoint");
            $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
         }
      }
      else {
         //check if client provided token
         if(isset($_REQUEST['token'])) {
            $authJson = $this->getData($_REQUEST['token']);
            if(array_key_exists("server", $authJson)
                    && array_key_exists("user", $authJson)
                    && array_key_exists("session", $authJson)) {
               //check if session id is still valid
               try {
                  $this->userUUID = $this->generateUserUUID($authJson['server'], $authJson['user']);
                  if($this->isSessionValid($this->userUUID, $authJson['session'])) {//session valid
                     if (OPTIONS_REQUESTED_SUB_MODULE == "init_workflow"){
                        $this->handleInitWorkflowEndpoint();
                     }
                     else if(OPTIONS_REQUESTED_SUB_MODULE == "register") {
                        $this->handleRegisterEndpoint();
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
                     else if (OPTIONS_REQUESTED_SUB_MODULE == "alter_sheet") {
                        $this->handleAlterSheetEndpoint();
                     }
                     else if (OPTIONS_REQUESTED_SUB_MODULE == "get_save_points") {
                        $this->handleGetSavePointsEndpoint();
                     }
                     else if (OPTIONS_REQUESTED_SUB_MODULE == "restore_save_point") {
                        $this->handleRestoreSavePointEndpoint();
                     }
                     else if (OPTIONS_REQUESTED_SUB_MODULE == "delete_workflow") {
                        $this->handleDeleteWorkflowEndpoint();
                     }
                     else if (OPTIONS_REQUESTED_SUB_MODULE == "add_foreign_key") {
                        $this->handleAddForeignKeyEndpoint();
                     }
                     else if (OPTIONS_REQUESTED_SUB_MODULE == "get_foreign_keys") {
                        $this->handleGetForeignKeyEndpoint();
                     }
                     else if (OPTIONS_REQUESTED_SUB_MODULE == "get_sheet_data") {
                        $this->handleGetSheetDataEndpoint();
                     }
                     else if(OPTIONS_REQUESTED_SUB_MODULE == "dump_data") {
                        $this->handleDumpDataEndpoint();
                     }
                     else if(OPTIONS_REQUESTED_SUB_MODULE == "alter_name") {
                        $this->handleAlterNameEndpoint();
                     }
                     else if(OPTIONS_REQUESTED_SUB_MODULE == "get_db_credentials") {
                        $this->handleGetDbCredentialsEndpoint();
                     }
                     else if(OPTIONS_REQUESTED_SUB_MODULE == "get_schema_diff") {
                        $this->handleGetSchemaDiffEndpoint();
                     }
                     else if(OPTIONS_REQUESTED_SUB_MODULE == "resolve_trivial_diff") {
                        $this->handleResolveTrivialDiffEndpoint();
                     }
                     else if(OPTIONS_REQUESTED_SUB_MODULE == "get_data") {
                        $this->handleGetDataEndpoint();
                     }
                     else {
                        $this->lH->log(2, $this->TAG, "No recognised endpoint specified in data provided to API");
                        $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
                     }
                  }
                  else {
                     $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_FORBIDDEN);
                  }
               } catch (WAException $ex) {
                  $data = array(
                      "status" => array("healthy" => FALSE, "errors" =>array(Workflow::getErrorMessage($ex)))
                  );
                  
                  $this->returnResponse($data);
               }
            }
            else {
               $this->lH->log(2, $this->TAG, "Either server, secret or user not set in data provided to auth endpoint");
               $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
            }
         }
         else {
            $this->lH->log(2, $this->TAG, "Token variable not set in data provided to API");
            $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
         }
      }
      
   }
   
   private function getData($variable) {
      $array = null;
      if(gettype($variable) == "string") {
         $array = json_decode($variable, true);
      }
      else if(gettype($variable) == "array") {
         $array = $variable;
      }
      
      return $array;
   }
   
   /**
    * This functioin hanles the register endpoint of the API.
    * The register endpoint registers a new user.
    * 
    * $_REQUEST['data'] variable
    * {
    *    server   :  "The IP address from where this user is going to access the API"
    *    user     :  "The username to be used for authenticating. Should be unique for each server"
    *    secret   :  "If type of authentication is local, the secret to be used for authetication"
    *    auth_mode:  "Can either be local or ldap"
    * }
    */
   private function handleRegisterEndpoint() {
      if(isset($_REQUEST['data'])) {
         $authJson = $this->getData($_REQUEST['data']);
         if(array_key_exists("server", $authJson)
                 && array_key_exists("user", $authJson)
                 && array_key_exists("secret", $authJson)
                 && array_key_exists("auth_mode", $authJson)
                 && ($authJson['auth_mode'] == "local" || $authJson['auth_mode'] == "ldap")) {
            try {
               $this->lH->log(4, $this->TAG, "Token json looks like this ".print_r($authJson, true));
               $result = $this->addClient($this->generateUserUUID($authJson['server'], $authJson['user']), $authJson['auth_mode'], $authJson['secret']);

               $data = array(
                   "created" => $result,
                   "status" => array("healthy" => true, "errors" => array())
               );

               $this->returnResponse($data);
            }
            catch (WAException $ex) {
               $data = array(
                   "created" => false,
                   "status" => array("healthy" => false, "errors" => array(Workflow::getErrorMessage($ex)))
               );

               $this->returnResponse($data);
            }
         }
         else {
            $this->lH->log(2, $this->TAG, "Either server, secret or user not set in data provided to register endpoint");
            $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
         }
      }
      else {
         $this->lH->log(2, $this->TAG, "Data variable not set in data provided to register endpoint");
         $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
      }
   }
   
   /**
    * This function handles the initWorkflow endpoint of the API.
    * The initWorkflow endpoint expects the following json object in the 
    * $_REQUEST['data'] variable
    * 
    * {
    *    data_file_url  :  "URL to the data file that is resolvable from the DMZ"
    *    workflow_name  :  "The name to give the workflow instance"
    * }
    */
   private function handleInitWorkflowEndpoint() {
      if (isset($_REQUEST['data'])) {
         $json = $this->getData($_REQUEST['data']);//decode as an associative array
         if($json === null) {
            $this->lH->log(2, $this->TAG, "Unable to parse JSON provided to init_workflow endpoint");
            $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
         }
         else {
            $jsonKeys = array_keys($json);

            //check if json has all the expected fields
            if(array_search("data_file_url", $jsonKeys) !== false
                    && array_search("workflow_name", $jsonKeys) !== false){

               //initialize a workflow object
               $workflow = new Workflow($this->config, $json['workflow_name'], $this->userUUID);

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
               $this->lH->log(2, $this->TAG, "Either data_file_url or workflow_name not set in data provided to init_workflow endpoint");
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
    */
   private function handleGetWorkflowsEndpoint() {
      $this->returnResponse(Workflow::getUserWorkflows($this->config, $this->userUUID));
   }
   
   /**
    * This function handles the process_mysql_schema endpoint of the API.
    * The process_mysql_schema endpoint expects the following json object in the 
    * $_REQUEST['data'] variable
    * 
    * {
    *    workflow_id :  "ID of the workflow"
    * }
    * 
    */
   private function handleProcessMysqlSchemaEndpoint() {
      if(isset($_REQUEST['data'])) {
         $json = $this->getData($_REQUEST['data']);
         if(array_key_exists("workflow_id", $json)
                 && array_key_exists("link_sheets", $json)) {
            $workflow = new Workflow($this->config, null, $this->userUUID, $json['workflow_id']);
            
            $workflow->setIsProcessing(true);//set is processing to be true because workflow instance is going to be left processing after response sent to user
            //call this function after sending response to client because it's goin to take some time
            $workflow->convertDataFilesToMySQL($json['link_sheets']);
            $data = array(
                "status" => $workflow->getCurrentStatus()
            );
            $this->returnResponse($data);
         }
         else {
            $this->lH->log(2, $this->TAG, "workflow_id variable not set in data provided to process_mysql_schema endpoint");
            $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
         }
      }
      else {
         $this->lH->log(2, $this->TAG, "data variable not set in data provided to process_mysql_schema endpoint");
         $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
      }
   }
   
   /**
    * This function handles the get_working_status endpoint of the API.
    * The get_working_status endpoint expects the following json object in the 
    * $_REQUEST['data'] variable
    * 
    * {
    *    workflow_id :  "ID of the workflow"
    * }
    * 
    */
   private function handleGetWorkingStatusEndpoint() {
      if(isset($_REQUEST['data'])) {
         $json = $this->getData($_REQUEST['data']);
         if(array_key_exists("workflow_id", $json)) {
            $workflow = new Workflow($this->config, null, $this->userUUID, $json['workflow_id']);
            $data = array(
                "status" => $workflow->getCurrentStatus()
            );
            $this->returnResponse($data);
         }
         else {
            $this->lH->log(2, $this->TAG, "workflow_id not provided in data provided to get_working_status endpoint");
            $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
         }
      }
      else {
         $this->lH->log(2, $this->TAG, "data variable not set in data provided to get_working_status endpoint");
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
    *    workflow_id :  "ID of the workflow"
    * }
    */
   private function handleGetWorkflowSchemaEndpoint() {
      if(isset($_REQUEST['data'])) {
         $json = $this->getData($_REQUEST['data']);
         if(array_key_exists("workflow_id", $json)) {
            $workflow = new Workflow($this->config, null, $this->userUUID, $json['workflow_id']);
            $schema = $workflow->getSchema();
            
            $data = array(
                "schema" => $schema,
                "status" => $workflow->getCurrentStatus()
            );
            
            $this->returnResponse($data);
         }
         else {
            $this->lH->log(2, $this->TAG, "workflow_id not set in data provieded to get_workflow_schema endpoint");
            $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
         }
      }
      else {
         $this->lH->log(2, $this->TAG, "data variable not set in data provided to get_workflow_schema endpoint");
         $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
      }
   }
   
   /**
    * This function handles the alter_field endpoint of the API.
    * The alter_field endpoint changes the schema values for the specified 
    * field.
    * 
    * $_REQUEST['data'] variable
    * {
    *    workflow_id :  "Instance id for the workflow"
    *    sheet       :  "Name of the sheet containing the modified column"
    *    column      :  {"original_name", "name", "delete", "type", "length", "nullable", "default", "key"}
    * }
    */
   private function handleAlterFieldEndpoint() {
      if(isset($_REQUEST['data'])) {
         $json = $this->getData($_REQUEST['data']);
         if(array_key_exists("workflow_id", $json)
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
            $workflow = new Workflow($this->config, null, $this->userUUID, $json['workflow_id']);
            $savePoint = $workflow->modifyColumn($json['sheet'], $json['column']);
            
            $data = array(
                "save_point" => $savePoint,
                "status" => $workflow->getCurrentStatus()
            );
            $this->returnResponse($data);
         }
         else {
            $this->lH->log(2, $this->TAG, "One of the fields in the data variable not set for the data provided to alter_field endpoint");
            $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
         }
      }
      else {
         $this->lH->log(2, $this->TAG, "data variable not set in data provided to alter_field endpoint");
         $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
      }
   }
   
   /**
    * This function handles the alter_name endpoint of the API.
    * The alter_name endpoint renames the workflow field.
    * 
    * $_REQUEST['data'] variable
    * {
    *    workflow_id :  "Instance id for the workflow"
    *    name        :  "New name for the workflow"
    * }
    */
   private function handleAlterNameEndpoint() {
      if(isset($_REQUEST['data'])) {
         $json = $this->getData($_REQUEST['data']);
         if(array_key_exists("workflow_id", $json)
                 && array_key_exists("name", $json)) {
            $workflow = new Workflow($this->config, null, $this->userUUID, $json['workflow_id']);
            $savePoint = $workflow->modifyName($json['name']);
            
            $data = array(
                "save_point" => $savePoint,
                "status" => $workflow->getCurrentStatus()
            );
            $this->returnResponse($data);
         }
         else {
            $this->lH->log(2, $this->TAG, "One of the fields in the data variable not set for the data provided to alter_field endpoint");
            $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
         }
      }
      else {
         $this->lH->log(2, $this->TAG, "data variable not set in data provided to alter_field endpoint");
         $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
      }
   }
   
   /**
    * This function handles the alter_sheet endpoint of the API.
    * The alter_sheet endpoint changes the schema values for the specified 
    * field.
    * 
    * $_REQUEST['data'] variable
    * {
    *    workflow_id :  "Instance id for the workflow"
    *    sheet       :  {"original_name", "name", "delete"}
    * }
    */
   private function handleAlterSheetEndpoint() {
      if(isset($_REQUEST['data'])) {
         $json = $this->getData($_REQUEST['data']);
         if(array_key_exists("workflow_id", $json)
                 && array_key_exists("sheet", $json)
                 && array_key_exists("original_name", $json['sheet'])
                 && array_key_exists("name", $json['sheet'])
                 && array_key_exists("delete", $json['sheet'])) {
            $workflow = new Workflow($this->config, null, $this->userUUID, $json['workflow_id']);
            $savePoint = $workflow->modifySheet($json['sheet']);
            
            $data = array(
                "save_point" => $savePoint,
                "status" => $workflow->getCurrentStatus()
            );
            $this->returnResponse($data);
         }
         else {
            $this->lH->log(2, $this->TAG, "One of the fields in the data variable not set for the data provided to alter_sheet endpoint");
            $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
         }
      }
      else {
         $this->lH->log(2, $this->TAG, "data variable not set in data provided to alter_sheet endpoint");
         $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
      }
   }
   
   /**
    * This function handles the show_save_points endpoint
    * 
    * $_REQUEST['data'] variable
    * {
    *    workflow_id :  "Instance id for the workflow"
    * }
    */
   private function handleGetSavePointsEndpoint() {
      if(isset($_REQUEST['data'])) {
         $json = $this->getData($_REQUEST['data']);
         if(array_key_exists("workflow_id", $json)) {
            $data = Workflow::getSavePoints($this->config, $json['workflow_id']);
            
            $this->returnResponse($data);
         }
         else {
            $this->lH->log(2, $this->TAG, "workflow_id not set in data provided to get_savepoints endpoint");
            $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
         }
      }
      else {
         $this->lH->log(2, $this->TAG, "data variable not set in data provided to get_savepoints endpoint");
         $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
      }
   }
   
   /**
    * This function handles the restore_save_point endpoint
    * 
    * $_REQUEST['data'] variable
    * {
    *    workflow_id :  "Instance id for the workflow"
    *    save_point  :  "Filename of the save point to restore to"
    * }
    */
   private function handleRestoreSavePointEndpoint() {
      if(isset($_REQUEST['data'])) {
         $json = $this->getData($_REQUEST['data']);
         if(array_key_exists("workflow_id", $json)
                 && array_key_exists("save_point", $json)) {
            $status = Workflow::restore($this->config, $json['workflow_id'], $json['save_point']);
            
            $data = array(
                "status" => $status
            );
            
            $this->returnResponse($data);
         }
         else {
            $this->lH->log(2, $this->TAG, "either workflow_id or save_point not set in data provided to restore_save_point endpoint");
            $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
         }
      }
      else {
         $this->lH->log(2, $this->TAG, "data variable not set in data provided to restore_savepoint endpoint");
         $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
      }
   }
   
   /**
    * This function handles the delete_workflow endpoint
    * 
    * $_REQUEST['data'] variable
    * {
    *    workflow_id :  "Instance id for the workflow"
    * }
    */
   private function handleDeleteWorkflowEndpoint() {
      if(isset($_REQUEST['data'])) {
         $json = $this->getData($_REQUEST['data']);
         if(array_key_exists("workflow_id", $json)) {
            $status = Workflow::delete($this->config, $json['workflow_id']);
            
            $data = array(
                "status" => $status
            );
            
            $this->returnResponse($data);
         }
         else {
            $this->lH->log(2, $this->TAG, "workflow_id not set in data provided to delete_workflow endpoint");
            $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
         }
      }
      else {
         $this->lH->log(2, $this->TAG, "data variable not set in data provided to delete_workflow endpoint");
         $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
      }
   }
   
   /**
    * This function hanles the add_foreign_key endpoint
    * $_REQUEST['data'] variable
    * {
    *    workflow_id :  "Instance id for the workflow"
    *    sheet:         "The sheet to add the foreign key"
    *    columns:        "The columns in the sheet where the foreign key is to be applied"
    *    referencing:   {sheet, columns:[]}
    * }
    */
   private function handleAddForeignKeyEndpoint() {
      if(isset($_REQUEST['data'])) {
         $json = $this->getData($_REQUEST['data']);
         if(array_key_exists("workflow_id", $json)
                 && array_key_exists("sheet", $json)
                 && array_key_exists("columns", $json)
                 && array_key_exists("references", $json)
                 && array_key_exists("sheet", $json["references"])
                 && array_key_exists("columns", $json["references"])
                 && count($json['columns']) == count($json["references"]['columns'])) {
            $workflow = new Workflow($this->config, null, $this->userUUID, $json['workflow_id']);
            $savePoint = $workflow->addForeignKey($json['sheet'], $json['columns'], $json['references']);
            $status = $workflow->getCurrentStatus();
            $data = array(
                "save_point" => $savePoint,
                "status" => $status
            );
            
            $this->returnResponse($data);
         }
         else {
            $this->lH->log(2, $this->TAG, "workflow_id or sheet or columns or references not set in data provided to add_foreign_key endpoint");
            $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
         }
      }
      else {
         $this->lH->log(2, $this->TAG, "data variable not set in data provided to add_foreign_key endpoint");
         $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
      }
   }
   
   /**
    * This function handles the get_foreign_keys endpoint
    * 
    * $_REQUEST['data'] variable
    * {
    *    workflow_id :  "Instance id for the workflow"
    * }
    */
   private function handleGetForeignKeyEndpoint() {
      if(isset($_REQUEST['data'])) {
         $json = $this->getData($_REQUEST['data']);
         if(array_key_exists("workflow_id", $json)) {
            $workflow = new Workflow($this->config, null, $this->userUUID, $json['workflow_id']);
            $foreignKeys = $workflow->getForeignKeys();
            $status = $workflow->getCurrentStatus();
            $data = array(
                "foreign_keys" => $foreignKeys,
                "status" => $status
            );
            $this->returnResponse($data);
         }
         else {
            $this->lH->log(2, $this->TAG, "workflow_id not set in data provided to get_foreign_keys endpoint");
            $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
         }
      }
      else {
         $this->lH->log(2, $this->TAG, "data variable not set in data provided to get_foreign_keys endpoint");
         $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
      }
   }
   
   /**
    * This function handles the get_sheet_data endpoint
    * 
    * $_REQUEST['data'] variable
    * {
    *    workflow_id :  "Instance id for the workflow"
    *    sheet:         "Name of the sheet to get the data for"
    * }
    */
   private function handleGetSheetDataEndpoint() {
      if(isset($_REQUEST['data'])) {
         $json = $this->getData($_REQUEST['data']);
         if(array_key_exists("workflow_id", $json)
                 && array_key_exists("sheet", $json)) {
            $workflow = new Workflow($this->config, null, $this->userUUID, $json['workflow_id']);
            $sheetData = $workflow->getSheetData($json['sheet']);
            $status = $workflow->getCurrentStatus();
            $data = array(
                "data" => $sheetData,
                "status" => $status
            );
            $this->returnResponse($data);
         }
         else {
            $this->lH->log(2, $this->TAG, "workflow_id not set in data provided to get_foreign_keys endpoint");
            $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
         }
      }
      else {
         $this->lH->log(2, $this->TAG, "data variable not set in data provided to get_foreign_keys endpoint");
         $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
      }
   }
   
   /**
    * This function handles the dump_data endpoint
    * 
    * $_REQUEST['data'] variable
    * {
    *    workflow_id :  "Instance id for the workflow"
    * }
    */
   private function handleDumpDataEndpoint() {
      if(isset($_REQUEST['data'])) {
         $json = $this->getData($_REQUEST['data']);
         if(array_key_exists("workflow_id", $json)) {
            $workflow = new Workflow($this->config, null, $this->userUUID, $json['workflow_id']);
            $savePoint = $workflow->dumpData();
            $status = $workflow->getCurrentStatus();
            $data = array(
                "save_point" => $savePoint,
                "status" => $status
            );
            $this->returnResponse($data);
         }
         else {
            $this->lH->log(2, $this->TAG, "workflow_id not set in data provided to get_foreign_keys endpoint");
            $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
         }
      }
      else {
         $this->lH->log(2, $this->TAG, "data variable not set in data provided to get_foreign_keys endpoint");
         $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
      }
   }
   
   /**
    * This function handles the get_db_credentials endpoint
    * 
    * $_REQUEST['data'] variable
    * {
    *    workflow_id :  "Instance id for the workflow"
    * }
    */
   private function handleGetDbCredentialsEndpoint() {
      if(isset($_REQUEST['data'])) {
         $json = $this->getData($_REQUEST['data']);
         if(array_key_exists("workflow_id", $json)) {
            $data = Workflow::getUserDBCredentials($this->userUUID, $this->config, $json['workflow_id']);
            $this->returnResponse($data);
         }
         else {
            $this->lH->log(2, $this->TAG, "workflow_id not set in data provided to get_db_credentials endpoint");
            $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
         }
      }
      else {
         $this->lH->log(2, $this->TAG, "data variable not set in data provided to get_db_credentials endpoint");
         $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
      }
   }
   
   /**
    * This function handles the diff_schema endpoint
    * The diff_schema endpoint checkes for the differences in schema structure for
    * the specified workflows
    * 
    * $_REQUEST['data'] variable
    * {
    *    workflow_id    :  "Instance id for the main workflow"
    *    workflow_id_2  :  "Instance id for the workflow you are comparing main workflow with"
    *    type           :  "Can either be 'all', 'trivial' or 'non_trivial' "
    * }
    */
   private function handleGetSchemaDiffEndpoint(){
      if(isset($_REQUEST['data'])) {
         $json = $this->getData($_REQUEST['data']);
         if(array_key_exists("workflow_id", $json)
               && array_key_exists("workflow_id_2", $json)
               && array_key_exists("type", $json)
               && ($json['type'] == 'all' || $json['type'] == 'trivial' || $json['type'] == 'non_trivial')) {
            $diff = Workflow::getSchemaDifference($this->userUUID, $this->config, $json['workflow_id'], $json['workflow_id_2'], $json['type']);
            $this->returnResponse($diff);
         }
         else {
            $this->lH->log(2, $this->TAG, "workflow_id or workflow_id_2 not set in data provided to diff_schema endpoint");
            $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
         }
      }
      else {
         $this->lH->log(2, $this->TAG, "data variable not set in data provided to diff_schema endpoint");
         $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
      }
   }
   
   /**
    * This function handles the resolve_trivial_diff endpoint
    * The resolve_trivial_diff endpoint tries to resolve trivial differences in schema
    * 
    * $_REQUEST['data'] variable
    * {
    *    workflow_id    :  "Instance id for the main workflow"
    *    workflow_id_2  :  "Instance id for the workflow you are comparing main workflow with"
    *    "name"         :  "Name"
    * }
    */
   private function handleResolveTrivialDiffEndpoint() {
      if(isset($_REQUEST['data'])) {
         $json = $this->getData($_REQUEST['data']);
         if(array_key_exists("workflow_id", $json)
               && array_key_exists("workflow_id_2", $json)
               && array_key_exists("name", $json)) {
            $workflow = new Workflow($this->config, null, $this->userUUID, $json['workflow_id']);
            $savePoint = $workflow->resolveTrivialSchemaDiff( $json['name'], $json['workflow_id_2']);
            $status = $workflow->getCurrentStatus();
            $this->returnResponse(array(
               "save_point" => $savePoint,
               "status" => $status
            ));
         }
         else {
            $this->lH->log(2, $this->TAG, "workflow_id or workflow_id_2 not set in data provided to resolve_trivial_diff endpoint");
            $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
         }
      }
      else {
         $this->lH->log(2, $this->TAG, "data variable not set in data provided to resolve_trivial_diff endpoint");
         $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
      }
   }
   
   /**
    * This function handles the get_data endpoint
    * The get_data endpoint get's data from the database (and not the raw data files)
    * 
    * $_REQUEST['data'] variable
    * {
    *    workflow_id    :  "Instance id for the main workflow"
    *    filter         :  "What should be used to filter the data. Can either be all, query or prefix"
    * }
    */
   private function handleGetDataEndpoint() {
      if(isset($_REQUEST['data'])) {
         $json = $this->getData($_REQUEST['data']);
         if(array_key_exists("workflow_id", $json)
               && array_key_exists("filter", $json)
               && (($json['filter'] == "all")
                     || ($json['filter'] == "query" && array_key_exists("query", $json)) 
                     || ($json['filter'] == "prefix" && array_key_exists("prefix", $json) && is_array($json['prefix'])))) {
            $workflow = new Workflow($this->config, null, $this->userUUID, $json['workflow_id']);
            //get the path to the data file
            $url = $workflow->getData($json['filter'], $json['query'], $json['prefix']);
            $status = $workflow->getCurrentStatus();
            $this->returnResponse(array(
               "data_file" => $url,
               "status" => $status
            ));
         }
         else {
            $this->lH->log(2, $this->TAG, "One of the required fields not set in data provided to get_data endpoint");
            $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
         }
      }
      else {
         $this->lH->log(2, $this->TAG, "data variable not set in data provided to get_data endpoint");
         $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
      }
   }
   
   /*private function handleGetDatabaseAccessEndpoint() {
      if(isset($_REQUEST['data'])) {
         $json = $this->getData($_REQUEST['data']);
         if(array_key_exists("workflow_id", $json)) {
            
            $data = Workflow::
         }
         else {
            $this->lH->log(2, $this->TAG, "workflow_id not set in data provided to get_foreign_keys endpoint");
            $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
         }
      }
      else {
         $this->lH->log(2, $this->TAG, "data variable not set in data provided to get_foreign_keys endpoint");
         $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
      }
   }*/

   /**
    * This function generates a UUID for the user by combining ith with the server address
    */
   private function generateUserUUID($server, $user) {
      return $server."_:_".$user;
   }
   
   /**
    * This function seperates the server from
    * @param type $userUUID
    * @return null
    */
   public static function explodeUserUUID($userUUID) {
      $details = explode("_:_", $userUUID);
      if(count($details) == 2) {
         return array("server" => $details[0], "user" => $details[1]);
      }
      
      return null;
   }
   
   private function setStatusCode($code) {
      $this->lH->log(3, $this->TAG, "Setting HTTP status code to '$code'");
      if($code == ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST) {
         $this->lH->log(4, $this->TAG, print_r($_REQUEST, true));
      }
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
   
   /**
    * This function registers a client
    * 
    * @param String $uri         The client/user UUID
    * @param String $authMode    The authentication mode. Can either be 'local' or 'ldap'
    * @param String $cyperSecret Encrypted secret to be used when authenticating
    * @return boolean   TRUE if user created successfully
    * @throws WAException
    */
   private function addClient($uri, $authMode, $cyperSecret) {
      $security = new Security($this->Dbase);
      if($authMode == "local") {
         $this->lH->log(4, $this->TAG, "Adding ".$uri." as local user");
         $decryptedCypher = $security->decryptCypherText($cyperSecret);
         if($decryptedCypher != null) {
            try {
               $database = new Database($this->config);
               if($database != null){
                  $salt = $security->generateSalt();
                  $hash = $security->hashPassword($decryptedCypher, $salt);
                  $columns = array(
                      "uri" => "'$uri'",
                      "ldap_auth" => "'f'",
                      "secret" => "'$hash'",
                      "salt" => "'$salt'"
                  );
                  $database->runInsertQuery("clients", $columns);
                  return true;
               }
               else {
                  throw new WAException("Unable to authenticate client because database object wasn't initialized correctly", WAException::$CODE_WF_INSTANCE_ERROR, null);
               }
            }
            catch(WAException $ex) {
               throw new WAException("Unable to authenticate client becuasue of database error", WAException::$CODE_DB_QUERY_ERROR, $ex);
            }
         }
         else {
            throw new WAException("Unable to add client because cypher text provided couldn't be decrypted", WAException::$CODE_WF_PROCESSING_ERROR, null);
         }
      }
      else if($authMode == "ldap") {
         $this->lH->log(4, $this->TAG, "Adding ".$uri." as ldap user");
         try {
            $database = new Database($this->config);
            if($database != null){
               //try log see if can log in using ldap
               $userURI = ODKWorkflowAPI::explodeUserUUID($uri);
               $ldapAuth = $security->ldapAuth($userURI["user"], $decryptedCypher);
               //1 if an error occured, 2 if user not authed and 0 if everything is fine. Return values should matche those from authUser($user, $pass)
               if($ldapAuth == 0) {//user authed
                  $salt = "";
                  $hash = "";
                  $columns = array(
                      "uri" => "'$uri'",
                      "ldap_auth" => "'t'",
                      "secret" => "'$hash'",
                      "salt" => "'$salt'"
                  );
                  $database->runInsertQuery("clients", $columns);
                  return true;
               }
               else if($ldapAuth == 1) {//an error occurred
                  throw new WAException("An error occurred while trying to authenticate user over LDAP", WAException::$CODE_WF_PROCESSING_ERROR, null);
               }
               else if($ldapAuth == 2) {//user not authed
                  throw new WAException("An error occurred while trying to authenticate user over LDAP", WAException::$CODE_WF_DATA_MULFORMED_ERROR, null);
               }
            }
            else {
               throw new WAException("Unable to authenticate client because database object wasn't initialized correctly", WAException::$CODE_WF_INSTANCE_ERROR, null);
            }
         }
         catch(WAException $ex) {
            throw new WAException("Unable to authenticate client becuasue of database error", WAException::$CODE_DB_QUERY_ERROR, $ex);
         }
      }
      return false;
   }
   
   /**
    * This function authenticates a client against the client list
    * 
    * @param type $uri           Client's unique identifier. Use IP address
    * @param type $cypherSecret  The client secrete
    * 
    * @return string Auth Token/session id
    * @throws WAException
    */
   private function authUser($uri, $authMode, $cypherSecret) {
      $this->lH->log(4, $this->TAG, "Dbase object = ".print_r($this->Dbase, TRUE));
      $security = new Security($this->Dbase);
      $decryptedCypher = $security->decryptCypherText(base64_decode($cypherSecret));
      $this->lH->log(4, $this->TAG, "decryptedCipher = $decryptedCypher");
      if($decryptedCypher != null){
         if($authMode == "local") {
               try {
                  $database = new Database($this->config);
                  if($database != null){
                     $query = "select salt, secret, id from clients where uri = '{$uri}'";
                     $result = $database->runGenericQuery($query, true);
                     if(is_array($result) && count($result) == 1) {
                        $salt = $result[0]['salt'];
                        $secret = $result[0]['secret'];
                        $clientId = $result[0]['id'];
                        if($security->hashPassword($decryptedCypher, $salt) == $secret) {//client authenticated
                           //create session id
                           $sessionId = $this->setSessionId($database, $security, $clientId);

                           return $sessionId;
                        }
                     }
                  }
                  else {
                     throw new WAException("Unable to authenticate client because database object wasn't initialized correctly", WAException::$CODE_WF_INSTANCE_ERROR, null);
                  }
               }
               catch(WAException $ex) {
                  throw new WAException("Unable to authenticate client becuasue of database error", WAException::$CODE_DB_QUERY_ERROR, $ex);
               }
         }
         else if($authMode == "ldap") {
            try {
               $database = new Database($this->config);
               if($database != null){
                  $query = "select salt, secret, id, ldap_auth from clients where uri = '{$uri}'";
                  $result = $database->runGenericQuery($query, true);
                  if(is_array($result) && count($result) == 1) {
                     $clientId = $result[0]['id'];
                     $ldapAuth = $result[0]['ldap_auth'];
                     $userURI = ODKWorkflowAPI::explodeUserUUID($uri);
                     $ldapRes = $security->ldapAuth($userURI['user'], $decryptedCypher);
                     $this->lH->log(4, $this->TAG, "LDAP AUTH result = ".print_r($ldapRes, true));
                     if($ldapAuth == "t" && $ldapRes == 0) {//client authenticated
                        //create session id
                        $sessionId = $this->setSessionId($database, $security, $clientId);

                        return $sessionId;
                     }
                  }
               }
               else {
                  throw new WAException("Unable to authenticate client because database object wasn't initialized correctly", WAException::$CODE_WF_INSTANCE_ERROR, null);
               }
            }
            catch(WAException $ex) {
               throw new WAException("Unable to authenticate client becuasue of database error", WAException::$CODE_DB_QUERY_ERROR, $ex);
            }
         }
      }
      else {
         throw new WAException("Unable to authenticate client because cypher text provided couldn't be decrypted", WAException::$CODE_WF_PROCESSING_ERROR, null);
      }
      
      return null;
   }
   
   /**
    * This function determines whether the provided session is valid
    * 
    * @param String $uri         The client/user UUID
    * @param String $sessionId   The session id
    * @return boolean   TRUE if session is valid
    * @throws WAException
    */
   private function isSessionValid($uri, $sessionId) {
      try {
         $database = new Database($this->config);
         //get the client id
         $query = "select id from clients where uri = '$uri'";
         $result = $database->runGenericQuery($query, true);
         
         if(is_array($result) && count($result) == 1) {
            $clientId = $result[0]['id'];
            $query = "select update_time from sessions where session_id = '$sessionId' and client_id = $clientId";
            $result = $database->runGenericQuery($query, true);
            if(is_array($result) && count($result) == 1) {
               $lastUpdateTime = new DateTime($result[0]['update_time']);
               $timeDifference = (time() - $lastUpdateTime->getTimestamp())/60;//time difference in minutes
               if($timeDifference <= $this->config['timeout']) {
                  $query = "update sessions set update_time = '".Database::getMySQLTime()."' where session_id = '".$sessionId."'";
                  $database->runGenericQuery($query);
                  return true;
               }
            }
         }
         else {
            $this->lH->log(2, $this->TAG, "Unable to get the client id for the provided URI. Failed to auth client '$uri'");
         }
      } catch (WAException $ex) {
         throw new WAException("Unable to authenticate client because of a system error", WAException::$CODE_DB_QUERY_ERROR, $ex);
      }
      
      return false;
   }
   
   /**
    * This function adds a session id to 
    * 
    * @param Database $database
    * @param Security $security
    * 
    * @throws WAException
    */
   private function setSessionId($database, $security, $clientId) {
      try {
         $sessionId = $security->generateSalt();
         $database->runInsertQuery("sessions", array(
             "session_id" => "'$sessionId'",
             "client_id" => $clientId,
             "start_time" => "'".Database::getMySQLTime()."'",
             "update_time" => "'".Database::getMySQLTime()."'"
         ));
         return $sessionId;
      } catch (WAException $ex) {
         throw new WAException("Unable to record the client's session ID because of a database error", WAException::$CODE_DB_QUERY_ERROR, $ex);
      }
      
      return null;
   }
}

?>

