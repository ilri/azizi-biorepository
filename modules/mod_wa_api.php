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
   private static $workflowStatus = array('deleted' => 'Deleted');

   private $lH;
   private $config;
   private $dmpAdmin;
   private $dmpMasterConfig;     // The config to the master database
   private $server;               // The origin of this request. Usually an IP address
   private $user;          // The user who originated the request
   private $cur_session;       // The current session
   private $uuid;

   public function __construct() {
      include_once 'mod_wa_workflow.php';
      include_once 'mod_log.php';
      include_once 'mod_wa_database.php';
      include_once 'mod_wa_exception.php';

      // initialize the log handler
      $this->lH = new LogHandler("./");

      $this->settingsDir = $this->ROOT."config/main.ini";
      $this->config = Config::$config;
      $this->readDbSettings();
      $this->config['common_folder_path'] = OPTIONS_COMMON_FOLDER_PATH;
      include_once OPTIONS_COMMON_FOLDER_PATH."azizi-shared-libs/authmodules/mod_security_v0.1.php";

      $this->Dbase = new DBase("mysql");
      $this->Dbase->InitializeConnection($this->config);
      $this->lH->log(4, $this->TAG, "ODK Workflow API called");
   }

   /**
    * This function handles requests being handled by this class
    */
   public function trafficController(){
      // process the passed parameters
      $cookies = filter_input_array(INPUT_COOKIE);

      $token = is_array($_POST['token']) ? $_POST['token'] : json_decode($_POST['token'], true);
      $this->server = $token['server'];
      $this->user = (empty($token['user'])) ? $_SESSION['username'] : $token['user'];
      $this->cur_session = (empty($token['session'])) ? $cookies['repository'] : $token['session'];
      $this->secret = (empty($token['secret'])) ? $_SESSION['password'] : $token['secret'];
      $this->auth_mode = (empty($token['auth_mode'])) ? $_SESSION['auth_type'] : $token['auth_mode'];

      // generate the uuid for this session
      $this->uuid = $this->generateUserUUID($this->server, $this->user);

      if(OPTIONS_REQUESTED_SUB_MODULE == ""){//user does not know what to do. Return text
         $this->lH->log(1, $this->TAG, "Client called API without parameters. Setting status code to ".ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
         $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
      }
      else if (OPTIONS_REQUESTED_SUB_MODULE == "auth"){
         if(!empty($this->server) && !empty($this->user) && !empty($this->secret) && !empty($this->auth_mode) && ($this->auth_mode == 'local' || $this->auth_mode == 'ldap') ) {
            try {
               $sessionId = $this->authUser($this->uuid, $this->auth_mode, $this->secret);
               $data = array("session" => $sessionId, "status" => array("healthy" => true, "errors" => array()) );
               $this->returnResponse($data);
            } catch (WAException $ex) {
               $data = array("session" => null, "status" => array("healthy" => FALSE, "errors" =>array(Workflow::getErrorMessage($ex))) );
               $this->returnResponse($data);
            }
         }
         else {
            if(empty($this->server)) $this->lH->log(1, $this->TAG, "Server variable not set in data provided to API during authentication");
            if(empty($this->user)) $this->lH->log(1, $this->TAG, "User variable not set in data provided to API during authentication");
            if(empty($this->secret)) $this->lH->log(1, $this->TAG, "Secret variable not set in data provided to API during authentication");
            if(empty($this->auth_mode)) $this->lH->log(1, $this->TAG, "Auth mode variable not set in data provided to API during authentication");
            $this->lH->log(1, $this->TAG, "Token variable not set in data provided to auth endpoint");
            $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
         }
      }
      else {
         if(!empty($this->server) && !empty($this->user) && !empty($this->cur_session) ) {
            //check if session id is still valid
            try {
               if($this->isSessionValid($this->uuid, $this->cur_session)) {
                  $this->lH->log(4, $this->TAG, "We have a valid session, please proceed");
                  // we have a valid session so lets go to the necessary sub module
                  if (OPTIONS_REQUESTED_SUB_MODULE == "init_workflow") $this->handleInitWorkflowEndpoint();
                  else if (OPTIONS_REQUESTED_SUB_MODULE == "update_workflow") $this->handleUpdateWorkflowEndpoint();
                  else if(OPTIONS_REQUESTED_SUB_MODULE == "register") $this->handleRegisterEndpoint();
                  else if (OPTIONS_REQUESTED_SUB_MODULE == "get_workflows") $this->handleGetWorkflowsEndpoint();
                  else if (OPTIONS_REQUESTED_SUB_MODULE == "process_mysql_schema") $this->handleProcessMysqlSchemaEndpoint();
                  else if (OPTIONS_REQUESTED_SUB_MODULE == "get_working_status") $this->handleGetWorkingStatusEndpoint();
                  else if (OPTIONS_REQUESTED_SUB_MODULE == "get_workflow_schema") $this->handleGetWorkflowSchemaEndpoint();
                  else if (OPTIONS_REQUESTED_SUB_MODULE == "alter_field") $this->handleAlterFieldEndpoint();
                  else if (OPTIONS_REQUESTED_SUB_MODULE == "alter_sheet") $this->handleAlterSheetEndpoint();
                  else if (OPTIONS_REQUESTED_SUB_MODULE == "get_save_points") $this->handleGetSavePointsEndpoint();
                  else if (OPTIONS_REQUESTED_SUB_MODULE == "restore_save_point") $this->handleRestoreSavePointEndpoint();
                  else if (OPTIONS_REQUESTED_SUB_MODULE == "delete_workflow") $this->handleDeleteWorkflowEndpoint();
                  else if (OPTIONS_REQUESTED_SUB_MODULE == "add_foreign_key") $this->handleAddForeignKeyEndpoint();
                  else if (OPTIONS_REQUESTED_SUB_MODULE == "get_foreign_keys") $this->handleGetForeignKeyEndpoint();
                  else if (OPTIONS_REQUESTED_SUB_MODULE == "get_sheet_data") $this->handleGetSheetDataEndpoint();
                  else if(OPTIONS_REQUESTED_SUB_MODULE == "dump_data") $this->handleDumpDataEndpoint();
                  else if(OPTIONS_REQUESTED_SUB_MODULE == "alter_name") $this->handleAlterNameEndpoint();
                  else if(OPTIONS_REQUESTED_SUB_MODULE == "get_db_credentials") $this->handleGetDbCredentialsEndpoint();
                  else if(OPTIONS_REQUESTED_SUB_MODULE == "get_schema_diff") $this->handleGetSchemaDiffEndpoint();
                  else if(OPTIONS_REQUESTED_SUB_MODULE == "get_merge_diff") $this->handleGetMergeDiffEndpoint();
                  else if(OPTIONS_REQUESTED_SUB_MODULE == "resolve_version_diff") $this->handleResolveVersionDiffEndpoint();
                  else if(OPTIONS_REQUESTED_SUB_MODULE == "resolve_merge_diff") $this->handleResolveMergeDiffEndpoint();
                  else if(OPTIONS_REQUESTED_SUB_MODULE == "get_data") $this->handleGetDataEndpoint();
                  else if(OPTIONS_REQUESTED_SUB_MODULE == "add_note") $this->handleAddNoteEndpoint();
                  else if(OPTIONS_REQUESTED_SUB_MODULE == "get_notes") $this->handleGetNotesEndpoint();
                  else if(OPTIONS_REQUESTED_SUB_MODULE == "delete_note") $this->handleDeleteNoteEndpoint();
                  else if(OPTIONS_REQUESTED_SUB_MODULE == "run_query") $this->handleRunQueryEndpoint();
                  else if(OPTIONS_REQUESTED_SUB_MODULE == "grant_user_access") $this->handleGrantUserAccessEndpoint();
                  else if(OPTIONS_REQUESTED_SUB_MODULE == "revoke_user_access") $this->handleRevokeUserAccessEndpoint();
                  else if(OPTIONS_REQUESTED_SUB_MODULE == "get_access_level") $this->handleGetAccessLevelEndpoint();
                  else if(OPTIONS_REQUESTED_SUB_MODULE == "get_users") $this->handleGetUsersEndpoint();
                  else if(OPTIONS_REQUESTED_SUB_MODULE == "get_columns_data") $this->handleGetColumnsDataEndpoint();
                  else {
                     $this->lH->log(2, $this->TAG, "No recognised endpoint specified in data provided to API");
                     $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
                  }
               }
               else {
                  $this->lH->log(1, $this->TAG, "not so fast....{$this->uuid}, {$this->cur_session}");
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
            if(empty($this->server)) $this->lH->log(1, $this->TAG, "Server variable not set in data provided to API");
            if(empty($this->user)) $this->lH->log(1, $this->TAG, "User variable not set in data provided to API");
            if(empty($this->cur_session)) $this->lH->log(1, $this->TAG, "Session variable not set in data provided to API");
            $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
         }
      }
   }

   /**
    * Read the database configurations and add them to the config variable
    */
   private function readDbSettings(){
      if(file_exists($this->settingsDir)) {
			$settings = parse_ini_file($this->settingsDir);
			// dmp db settings
         if(parse_ini_file($settings['dmp_dbsettings_file']) !== false) {//check for both availability and ini correctness
				$dmp_settings = parse_ini_file($settings['dmp_dbsettings_file'], true);
              $this->config['pg_dbloc'] = $dmp_settings['dmp_admin']['dbloc'];
              $this->config['pg_user'] = $dmp_settings['dmp_admin']['user'];
              $this->config['pg_pass'] = $dmp_settings['dmp_admin']['cypher'];
			}
			else {
				$this->lH->log(1, $this->TAG, "The file '{$settings['dmp_dbsettings_file']}' with the DMP database settings doesn't exist or is not parsable as an ini file");
			}
		}
		else {
         $this->lH->log(1, $this->TAG, "The file with the repository settings doesn't exist");
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
               $odkInstance = (isset($json['odk_instance'])) ? $json['odk_instance'] : NULL;
               $workflow = new Workflow($this->config, $json['workflow_name'], $this->uuid, NULL, $odkInstance);

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
      $this->returnResponse(Workflow::getUserWorkflows($this->config, $this->uuid, $this->isUserAdmin($this->uuid)));
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
            $workflow = new Workflow($this->config, null, $this->uuid, $json['workflow_id']);

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
            $workflow = new Workflow($this->config, null, $this->uuid, $json['workflow_id']);
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
            $workflow = new Workflow($this->config, null, $this->uuid, $json['workflow_id']);
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
            $workflow = new Workflow($this->config, null, $this->uuid, $json['workflow_id']);
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
            $workflow = new Workflow($this->config, null, $this->uuid, $json['workflow_id']);
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
            $workflow = new Workflow($this->config, null, $this->uuid, $json['workflow_id']);
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
            $data = array("status" => $status);

            // update the main record with a delete status
            Workflow::updateMainWorkflowStatus($this->user, $this->config, $json['workflow_id'], ODKWorkflowAPI::$workflowStatus['deleted']);
            Workflow::cleanProjectAccessTable($this->config, $json['workflow_id']);
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
            $workflow = new Workflow($this->config, null, $this->uuid, $json['workflow_id']);
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
            $workflow = new Workflow($this->config, null, $this->uuid, $json['workflow_id']);
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
            $workflow = new Workflow($this->config, null, $this->uuid, $json['workflow_id']);
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
            $workflow = new Workflow($this->config, null, $this->uuid, $json['workflow_id']);
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
            $data = Workflow::getUserDBCredentials($this->uuid, $this->config, $json['workflow_id']);
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
            $diff = Workflow::getVersionDifference($this->uuid, $this->config, $json['workflow_id'], $json['workflow_id_2'], $json['type']);
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
    * This function handles the diff_schema endpoint
    * The diff_schema endpoint checkes for the differences in schema structure for
    * the specified workflows
    *
    * $_REQUEST['data'] variable
    * {
    *    workflow_id    :  "Instance id for the main workflow"
    *    workflow_id_2  :  "Instance id for the workflow you are comparing main workflow with"
    *    type           :  "Can either be 'all', 'trivial' or 'non_trivial' "
    *    key_1          :  "Key to be used in the first workflow for merging. Should be an array with the sheet and column name"
    *    key_2          :  "Key to be used in the second workflow for merging. Should be an array with the sheet and column name"
    * }
    */
   private function handleGetMergeDiffEndpoint(){
      if(isset($_REQUEST['data'])) {
         $json = $this->getData($_REQUEST['data']);
         if(array_key_exists("workflow_id", $json)
               && array_key_exists("workflow_id_2", $json)
               && array_key_exists("type", $json)
               && ($json['type'] == 'all' || $json['type'] == 'trivial' || $json['type'] == 'non_trivial')
               && array_key_exists("key_1", $json)
               && (is_array($json['key_1']) && array_key_exists("sheet", $json['key_1']) && array_key_exists("column", $json['key_1']))
               && array_key_exists("key_2", $json)
               && (is_array($json['key_2']) && array_key_exists("sheet", $json['key_2']) && array_key_exists("column", $json['key_2']))) {
            $diff = Workflow::getMergeDifferences($this->uuid, $this->config, $json['workflow_id'], $json['workflow_id_2'], $json['type'], $json['key_1'], $json['key_2']);
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
    * This function handles the resolve_version_diff endpoint
    * The resolve_version_diff endpoint tries to resolve trivial differences in schema
    *
    * $_REQUEST['data'] variable
    * {
    *    workflow_id    :  "Instance id for the main workflow"
    *    workflow_id_2  :  "Instance id for the workflow you are comparing main workflow with"
    *    "name"         :  "Name"
    * }
    */
   private function handleResolveVersionDiffEndpoint() {
      if(isset($_REQUEST['data'])) {
         $json = $this->getData($_REQUEST['data']);
         if(array_key_exists("workflow_id", $json)
               && array_key_exists("workflow_id_2", $json)
               && array_key_exists("name", $json)) {
            $workflow = new Workflow($this->config, null, $this->uuid, $json['workflow_id']);
            $savePoint = $workflow->resolveVersionSchemaDiff( $json['name'], $json['workflow_id_2']);
            $status = $workflow->getCurrentStatus();
            $this->returnResponse(array(
               "save_point" => $savePoint,
               "status" => $status
            ));
         }
         else {
            $this->lH->log(2, $this->TAG, "workflow_id or workflow_id_2 not set in data provided to resolve_version_diff endpoint");
            $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
         }
      }
      else {
         $this->lH->log(2, $this->TAG, "data variable not set in data provided to resolve_version_diff endpoint");
         $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
      }
   }

   /**
    * This function handles the resolve_merge_diff endpoint
    * The resolve_merge_diff endpoint tries to resolve trivial differences in schema
    *
    * $_REQUEST['data'] variable
    * {
    *    workflow_id    :  "Instance id for the main workflow"
    *    workflow_id_2  :  "Instance id for the workflow you are comparing main workflow with"
    *    "name"         :  "Name"
    * }
    */
   private function handleResolveMergeDiffEndpoint() {
      if(isset($_REQUEST['data'])) {
         $json = $this->getData($_REQUEST['data']);
         if(array_key_exists("workflow_id", $json)
               && array_key_exists("workflow_id_2", $json)
               && array_key_exists("name", $json)
               && array_key_exists("key_1", $json)
               && (is_array($json['key_1']) && array_key_exists("sheet", $json['key_1']) && array_key_exists("column", $json['key_1']))
               && array_key_exists("key_2", $json)
               && (is_array($json['key_2']) && array_key_exists("sheet", $json['key_2']) && array_key_exists("column", $json['key_2']))) {
            $workflow = new Workflow($this->config, null, $this->uuid, $json['workflow_id']);
            $savePoint = $workflow->resolveMergeDiff($json['name'], $json['workflow_id_2'], $json['key_1'], $json['key_2']);
            $status = $workflow->getCurrentStatus();
            $this->returnResponse(array(
               "save_point" => $savePoint,
               "status" => $status
            ));
         }
         else {
            $this->lH->log(2, $this->TAG, "workflow_id or workflow_id_2 not set in data provided to resolve_merge_diff endpoint");
            $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
         }
      }
      else {
         $this->lH->log(2, $this->TAG, "data variable not set in data provided to resolve_merge_diff endpoint");
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
               && array_key_exists("email", $json)
               && (($json['filter'] == "all")
                     || ($json['filter'] == "query" && array_key_exists("query", $json))
                     || ($json['filter'] == "prefix" && array_key_exists("prefix", $json) && is_array($json['prefix']))
                     || ($json['filter'] == "time" && array_key_exists("start_time", $json) && array_key_exists("end_time", $json) && array_key_exists("time_column", $json)))) {
            $workflow = new Workflow($this->config, null, $this->uuid, $json['workflow_id']);
            //get the path to the data file
            $url = $workflow->getData($json['filter'], $json['email'], $json['query'], $json['prefix'], $json['time_column'], $json['start_time'], $json['end_time']);
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

   /**
    * This function handles the add_note endpoint
    * The add_note endpoint adds any form of note to the workflow
    *
    * $_REQUEST['data'] variable
    * {
    *    workflow_id :  "The instance id for the workflow"
    *    note        :  "The note text"
    * }
    */
   private function handleAddNoteEndpoint() {
      if(isset($_REQUEST['data'])) {
         $json = $this->getData($_REQUEST['data']);
         if(array_key_exists("workflow_id", $json)
               && array_key_exists("note", $json)) {
            $workflow = new Workflow($this->config, null, $this->uuid, $json['workflow_id']);
            $workflow->addNote($json['note']);
            $status = $workflow->getCurrentStatus();
            $this->returnResponse(array(
               "status" => $status
            ));
         }
         else {
            $this->lH->log(2, $this->TAG, "One of the required fields not set in data provided to add_note endpoint");
            $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
         }
      }
      else {
         $this->lH->log(2, $this->TAG, "data variable not set in data provided to add_note endpoint");
         $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
      }
   }

   /**
    * This function handles the get_notes endpoint
    * The get_notes endpoint gets all notes corresponding to a workflow
    *
    * $_REQUEST['data'] variable
    * {
    *    workflow_id :  "The instance id for the workflow"
    * }
    */
   private function handleGetNotesEndpoint() {
      if(isset($_REQUEST['data'])) {
         $json = $this->getData($_REQUEST['data']);
         if(array_key_exists("workflow_id", $json)) {
            $workflow = new Workflow($this->config, null, $this->uuid, $json['workflow_id']);
            $notes = $workflow->getAllNotes();
            $status = $workflow->getCurrentStatus();
            $this->returnResponse(array(
               "notes" => $notes,
               "status" => $status
            ));
         }
         else {
            $this->lH->log(2, $this->TAG, "One of the required fields not set in data provided to get_notes endpoint");
            $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
         }
      }
      else {
         $this->lH->log(2, $this->TAG, "data variable not set in data provided to get_notes endpoint");
         $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
      }
   }

   /**
    * This function handles the delete_note endpoint
    * The delete_note endpoint deletes a note using its id
    *
    * $_REQUEST['data'] variable
    * {
    *    workflow_id :  "The instance id for the workflow",
    *    "note_id"   :  "The id corresponding to the note"
    * }
    */
   private function handleDeleteNoteEndpoint() {
      if(isset($_REQUEST['data'])) {
         $json = $this->getData($_REQUEST['data']);
         if(array_key_exists("workflow_id", $json)
               && array_key_exists("note_id", $json)) {
            $workflow = new Workflow($this->config, null, $this->uuid, $json['workflow_id']);
            $savePoint = $workflow->deleteNote($json['note_id']);
            $status = $workflow->getCurrentStatus();
            $this->returnResponse(array(
               "save_point" => $savePoint,
               "status" => $status
            ));
         }
         else {
            $this->lH->log(2, $this->TAG, "One of the required fields not set in data provided to delete_note endpoint");
            $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
         }
      }
      else {
         $this->lH->log(2, $this->TAG, "data variable not set in data provided to delete_note endpoint");
         $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
      }
   }

   /**
    * This function handles the get_notes endpoint
    * The get_notes endpoint gets all notes corresponding to a workflow
    *
    * $_REQUEST['data'] variable
    * {
    *    workflow_id :  "The instance id for the workflow"
    *    query       :  "The non-select query to be run"
    * }
    */
   private function handleRunQueryEndpoint() {
      if(isset($_REQUEST['data'])) {
         $json = $this->getData($_REQUEST['data']);
         if(array_key_exists("workflow_id", $json)
               && array_key_exists("query", $json)) {
            $workflow = new Workflow($this->config, null, $this->uuid, $json['workflow_id']);
            $savePoint = $workflow->runNonSelectQuery($json['query']);
            $status = $workflow->getCurrentStatus();
            $this->returnResponse(array(
               "save_point" => $savePoint,
               "status" => $status
            ));
         }
         else {
            $this->lH->log(2, $this->TAG, "One of the required fields not set in data provided to get_notes endpoint");
            $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
         }
      }
      else {
         $this->lH->log(2, $this->TAG, "data variable not set in data provided to get_notes endpoint");
         $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
      }
   }

   /**
    * This function handles the grant_user_access endpoint
    * The grant_user_access endpoint grants a user access to a workflow
    *
    * $_REQUEST['data'] variable
    * {
    *    workflow_id :  "The instance id for the workflow"
    *    user       :  "The non-select query to be run"
    * }
    */
   private function handleGrantUserAccessEndpoint() {
      if(isset($_REQUEST['data'])) {
         $json = $this->getData($_REQUEST['data']);
         if(array_key_exists("workflow_id", $json)
               && array_key_exists("user", $json)
               && array_key_exists("access_level", $json)) {
            $workflow = new Workflow($this->config, null, $this->uuid, $json['workflow_id']);
            $workflow->grantUserAccess($this->generateUserUUID($this->server, $json['user']), $json['access_level']);
            $workflow->addUser2GlobalAccessList($this->generateUserUUID($this->server, $json['user']), $json['access_level']);
            $status = $workflow->getCurrentStatus();
            $this->returnResponse(array(
               "status" => $status
            ));
         }
         else {
            $this->lH->log(2, $this->TAG, "One of the required fields not set in data provided to get_notes endpoint");
            $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
         }
      }
      else {
         $this->lH->log(2, $this->TAG, "data variable not set in data provided to get_notes endpoint");
         $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
      }
   }

   /**
    * This function handles the revoke_user_access endpoint
    * The revoke_user_access endpoint revokes all access a user has to a workflow
    *
    * $_REQUEST['data'] variable
    * {
    *    workflow_id :  "The instance id for the workflow"
    *    user       :  "The non-select query to be run"
    * }
    */
   private function handleRevokeUserAccessEndpoint() {
      if(isset($_REQUEST['data'])) {
         $json = $this->getData($_REQUEST['data']);
         if(array_key_exists("workflow_id", $json)
               && array_key_exists("user", $json)) {
            $workflow = new Workflow($this->config, null, $this->uuid, $json['workflow_id']);
            $workflow->revokeUserAccess($this->generateUserUUID($this->server, $json['user']));
            $status = $workflow->getCurrentStatus();
            $this->returnResponse(array(
               "status" => $status
            ));
         }
         else {
            $this->lH->log(2, $this->TAG, "One of the required fields not set in data provided to get_notes endpoint");
            $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
         }
      }
      else {
         $this->lH->log(2, $this->TAG, "data variable not set in data provided to get_notes endpoint");
         $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
      }
   }

   /**
    * This function handles the get_access_level endpoint
    * The get_access_level endpoint check what the access level for the current user
    * in the specified workflow is
    *
    * $_REQUEST['data'] variable
    * {
    *    workflow_id :  "The instance id for the workflow"
    * }
    */
   private function handleGetAccessLevelEndpoint() {
      if(isset($_REQUEST['data'])) {
         $json = $this->getData($_REQUEST['data']);
         if(array_key_exists("workflow_id", $json)) {
            $workflow = new Workflow($this->config, null, $this->uuid, $json['workflow_id']);
            $accessLevel = $workflow->getAccessLevel($this->uuid);
            $status = $workflow->getCurrentStatus();
            $this->returnResponse(array(
               "access_level" => $accessLevel,
               "status" => $status
            ));
         }
         else {
            $this->lH->log(2, $this->TAG, "One of the required fields not set in data provided to get_notes endpoint");
            $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
         }
      }
      else {
         $this->lH->log(2, $this->TAG, "data variable not set in data provided to get_notes endpoint");
         $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
      }
   }

   /**
    * This function handles the get_access_level endpoint
    * The get_access_level endpoint check what the access level for the current user
    * in the specified workflow is
    *
    * $_REQUEST['data'] variable
    * {
    *    workflow_id :  "The instance id for the workflow"
    * }
    */
   private function handleGetUsersEndpoint() {
      if(isset($_REQUEST['data'])) {
         $json = $this->getData($_REQUEST['data']);
         if(array_key_exists("workflow_id", $json)) {
            $workflow = new Workflow($this->config, null, $this->uuid, $json['workflow_id']);
            $users = $workflow->getUsers();
            $status = $workflow->getCurrentStatus();
            $this->returnResponse(array(
               "users" => $users,
               "status" => $status
            ));
         }
         else {
            $this->lH->log(2, $this->TAG, "One of the required fields not set in data provided to get_notes endpoint");
            $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
         }
      }
      else {
         $this->lH->log(2, $this->TAG, "data variable not set in data provided to get_notes endpoint");
         $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
      }
   }

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
                  $columns = array("uri" => "'$uri'", "ldap_auth" => "'f'", "secret" => "'$hash'", "salt" => "'$salt'");
                  $database->runInsertQuery("clients", $columns);
                  return true;
               }
               else {
                  throw new WAException("Unable to authenticate client because database object wasn't initialized correctly", WAException::$CODE_WF_INSTANCE_ERROR, null);
               }
            }
            catch(WAException $ex) {
               throw new WAException("Unable to authenticate client because of database error", WAException::$CODE_DB_QUERY_ERROR, $ex);
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
      $security = new Security($this->Dbase);
      $decryptedCypher = $security->decryptCypherText(base64_decode($cypherSecret));
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
                     if($ldapAuth == "t" && $ldapRes == 0) {//client authenticated
                        //create session id
                        $this->lH->log(4, $this->TAG, "The user '{$userURI['user']}' has been authenticated succesfully via LDAP");
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
         $security = new Security($this->Dbase);
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
            else{
               // no record in the sessions table... so add it
               $this->lH->log(4, $this->TAG, "No sessions for '$uri'. Adding one and then attempt to log in again....");
               try {
                  $sessions = $this->setSessionId($database, $security, $clientId);
                  if(is_null($sessions)){
                     $this->lH->log(1, $this->TAG, "Cannot add a session into the sessions table");
                  }
                  else{
                     return true;
                  }
               } catch (WAException $ex) {
                  $this->lH->log(1, $this->TAG, "An error occurred while trying to insert a new session");
                  try {
                     $query = "delete from sessions where client_id = $clientId";
                     $database->runGenericQuery($query);
                     $query = "delete from clients where uri = '$uri'";
                     $database->runGenericQuery($query);
                  } catch (WAException $ex) {
                     $this->lH->log(1, $this->TAG, "An error occurred while trying to delete the client and its session '$uri'");
                     return false;
                  }
               }
            }
         }
         else {
            // this user is given access to the DMP but doesn't have a record in the clients table, so lets add him/her
            $this->lH->log(4, $this->TAG, "Unable to get the client id '$uri' for the provided URI in the database. Will attempt to add the client");
            $this->handleRegisterEndpoint();
            // if all is ok, call this functuion again
            if(!$this->isSessionValid($uri, $sessionId)){
               $this->lH->log(1, $this->TAG, "Added the client '$uri' in the database successfully but I can't log in, so I will now delete the client.");
               try {
                  $query = "delete from clients where uri = '$uri'";
                  $database->runGenericQuery($query);
               } catch (WAException $ex) {
                  $this->lH->log(1, $this->TAG, "An error occurred while trying to delete the client '$uri'");
                  return false;
               }
            }
            else{
               return true;
            }
         }
      } catch (WAException $ex) {
         throw new WAException("Unable to authenticate client because of a system error", WAException::$CODE_DB_QUERY_ERROR, $ex);
      }
      return true;
   }

   /**
    * This function checks if the user is an admin
    *
    * @param type $userURI The user's URI
    */
   private function isUserAdmin($userURI) {
      try {
         $database = new Database($this->config);
         $query = "select is_admin from clients where uri = '$userURI'";
         $result = $database->runGenericQuery($query, true);
         if(count($result) == 1) {
            if($result[0]['is_admin'] == 1) {
               $this->lH->log(3, $this->TAG, $userURI." is an admin account");
               return true;
            }
         }
         else {
            throw new WAException("Inconsistent number of records returned while trying to determing if user is an admin", WAException::$CODE_WF_DATA_MULFORMED_ERROR, null);
         }
      } catch (WAException $ex) {
         throw new WAException("Could not check if user is an admin", WAException::$CODE_DB_QUERY_ERROR, $ex);
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

   /**
    * Handles the function of getting grouped data from a workflow
    */
   private function handleGetColumnsDataEndpoint(){
      include_once 'mod_wa_column.php';

      if(isset($_REQUEST['data'])) {
         $json = $this->getData($_REQUEST['data']);
         $this->lH->log(4, $this->TAG, "Data:: ". print_r(json_decode($json['columns'], true), true));

         if(array_key_exists("workflow_id", $json)) {
            $workflow = new Workflow($this->config, null, $this->uuid, $json['workflow_id']);
            $data = $workflow->getGroupedColumnsData(json_decode($json['columns'], true));
            $status = $workflow->getCurrentStatus();
            $this->returnResponse(array(
               "columnsData" => $data,
               "status" => $status
            ));
         }
         else {
            $this->lH->log(2, $this->TAG, "One of the required fields not set in data provided to get_columns_data endpoint");
            $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
         }
      }
      else {
         $this->lH->log(2, $this->TAG, "data variable not set in data provided to get_columns_data endpoint");
         $this->setStatusCode(ODKWorkflowAPI::$STATUS_CODE_BAD_REQUEST);
      }
   }

   /**
    * Update an existing workflow with new submissions
    */
   private function handleUpdateWorkflowEndpoint(){

   }
}
?>