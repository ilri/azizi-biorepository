<?php

/* 
 * This class implements the Workflow clas in the ODK Workflow API
 */
class Workflow {
   private $TAG = "workflow";
   private $config;
   private $workflowName;
   private $instanceId;
   private $database;
   private $lH;
   private $errors;//stores all the caught exceptions
   private $healthy;//flag storing the health state of this object. ie. whether processing can continue on the object or not
   private $workingDir;//directory where all the workflow files will be stored
   private $files;//list of WAFiles held by this workflow instance
   private $currUser;//current user interracting with instance
   private $processing;//flag to be used with asynchronous processes. Set to true if asynchronous task working for current instance (example of task is converting excel sheets to MySQL tables)
   
   public static $TABLE_META_CHANGES = "__meta_changes";
   public static $TABLE_META_VAR_NAMES = "__meta_var_names";
   public static $TABLE_META_ACCESS = "__meta_access";
   public static $TABLE_META_DOCUMENT = "__meta_document";
   public static $TABLE_META_ERRORS = "__meta_errors";
   
   /**
    * Default class contructor
    * @param Object $config repository_config object
    * @param String $instanceID  Unique instance ID for the Workflow.
    *                            Set to null if new workflow
    */
   public function __construct($config, $workflowName, $currUser, $instanceId = null) {
      include_once 'mod_wa_database.php';
      include_once 'mod_wa_exception.php';
      include_once 'mod_log.php';
      include_once 'mod_wa_file.php';
      include_once 'mod_wa_excel_file.php';
      include_once 'mod_wa_sheet.php';
      include_once 'mod_wa_api.php';
      
      $this->config = $config;
      $this->lH = new LogHandler("./");
      $this->errors = array();
      $this->healthy = true;
      $this->files = array();
      $this->currUser = $currUser;
      $this->workflowName = $workflowName;
      $this->processing = false;
      
      $this->instanceId = $instanceId;
      
      $this->initDbConnection();
      
      //check if instanceId is null and try generating one if it is
      if($this->instanceId === null){
         $this->instanceId = $this->generateInstanceID();
         try {
            $this->database->runCreatDatabaseQuery($this->instanceId);
            $this->initDbConnection();//since instance id has changed, reinitialize database to point to the correct database
         } catch (Exception $ex) {
            $this->lH->log(1, $this->TAG, "A problem occurred while trying to create the database for this workflow instance");
            array_push($this->errors, $ex);
            $this->healthy = false;
         }
         
         //make sure the working directory exists
         $this->createWorkingDir();

         //make sure meta tables have been created
         $this->initMetaDBTables();

         $this->grantUserAccess($this->currUser);
         $this->saveWorkflowDetails();
      }
      else {
         $this->lH->log(3, $this->TAG, "Initializing workflow with id = '$instanceId' from MySQL");
         if($this->healthy === true){//make sure you are connected to the right database
            //initialize all the things
            try {
               if($this->database->getDatabaseName() == $this->instanceId) {
                  $workflowDetails = Workflow::getWorkflowDetails($this->config, $this->instanceId);//get workflow details from the MySQL database
                  $this->workflowName =  $workflowDetails['workflow_name'];
                  $this->workingDir = $workflowDetails['working_dir'];
                  
                  //initialize all the files
                  try {
                     $this->files = WAFile::getAllWorkflowFiles($this->config, $this->instanceId, $this->workingDir);
                  } catch (WAException $ex) {
                     $this->lH->log(1, $this->TAG, "An error occurred while trying to intialize files for workflow with id = '{$this->instanceId}'");
                     $this->healthy = false;
                     array_push($this->errors, new WAException("An error occurred while trying to initialize worflow files", WAException::$CODE_WF_INSTANCE_ERROR, $ex));
                  }

                  //get cached status from the database
                  $this->getCachedStatus();
               }
               else {
                  $this->lH->log(1, $this->TAG, "Unable to restore workflow state from MySQl database for workflow with id = '{$this->instanceId}' because database object connected to the wrong database");
                  $this->healthy = false;
                  array_push($this->errors, new WAException("Unable to restore workflow state from database because database object connected to the wrong database", WAException::$CODE_WF_INSTANCE_ERROR, null));
               }
            } catch (WAException $ex) {
               $this->lH->log(1, $this->TAG, "An error occurred while trying restore workflow state from MySQl database for workflow with id = '{$this->instanceId}'");
               $this->healthy = false;
               array_push($this->errors, new WAException("An error occurred while trying to restore state from database", WAException::$CODE_WF_INSTANCE_ERROR, $ex));
            }
           
         }
         else {
            $this->lH->log(1, $this->TAG, "Cannot continue initializing workflow instance with id = '{$this->instanceId}'. Object is already unhealthy");
            $this->healthy = false;
            array_push($this->errors, new WAException("Cannot continue initializing workflow instance. Object is already unhealthy", WAException::$CODE_WF_INSTANCE_ERROR, null));
         }
         
      }
   }
   
   /**
    * This function saves the user's access level in the MySQL database
    * 
    * @param type $user       The user to be granted access to the workflow
    * @param type $grantedBy  The user granting the access
    */
   private function grantUserAccess($user) {
      $columns = array(
          "user_granted" => "'{$user}'",
          "time_granted" => "'".Database::getMySQLTime()."'",
          "granted_by" => "'{$this->currUser}'"
      );
      try {
         $this->database->runInsertQuery(Workflow::$TABLE_META_ACCESS, $columns);
      } catch (WAException $ex) {
         array_push($this->errors, $ex);
         $this->healthy = false;
         $this->lH->log(1, $this->TAG, "An error occurred while trying to grant '$user' access to the workflow '{$this->instanceId}'");
      }
   }
   
   private function saveWorkflowDetails() {
      $this->lH->log(4, $this->TAG, "************************************************************ inserting into meta document table");
      
      $processing = Database::$BOOL_FALSE;
      if($this->processing === true) $processing = Database::$BOOL_TRUE;
      
      $healthy = Database::$BOOL_FALSE;
      if($this->healthy === true) $healthy = Database::$BOOL_TRUE;
      
      $columns = array(
          "workflow_name" => "'{$this->workflowName}'",
          "created_by" => "'{$this->currUser}'",
          "time_created" => "'".Database::getMySQLTime()."'",
          "workflow_id" => "'{$this->instanceId}'",
          "working_dir" => "'{$this->workingDir}'",
          "processing" => "'{$processing}'",
          "health" => "'{$healthy}'"
      );
      
      try {
         $this->database->runInsertQuery(Workflow::$TABLE_META_DOCUMENT, $columns);
      } catch (WAException $ex) {
         array_push($this->errors, $ex);
         $this->healthy = false;
         $this->lH->log(1, $this->TAG, "An error occurred while trying to workflow details in the database for workflow with id = '{$this->instanceId}'");
      }
   }
   
   /**
    * This function initializes the meta tables for this workflow instance
    * 
    */
   private function initMetaDBTables() {
      if($this->database->getDatabaseName() == $this->instanceId) {
         $this->createMetaAccessTable();
         $this->createMetaChangesTable();
         $this->createMetaDocumentTable();
         $this->createMetaVarNamesTable();
         $this->createMetaErrorsTable();
      }
      else {
         $this->lH->log(1, $this->TAG, "Unable to create meta tables because database object connected to the wrong database ('{$this->database->getDatabaseName()}')");
         array_push($this->errors, new WAException("Unable to create meta tables because not connected to the right database", WAException::$CODE_DB_CONNECT_ERROR, null));
         $this->healthy = false;
      }
   }
   
   /**
    * This function checks whether the metaChanges table is created and creates it if not.
    * This table stores changes made to the form schema
    */
   private function createMetaChangesTable() {
      try {
         $this->database->runCreateTableQuery(Workflow::$TABLE_META_CHANGES,
                 array(
                     array("name" => "id" , "type"=>Database::$TYPE_SERIAL , "length"=>11 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_PRIMARY),
                     array("name" => "change_time" , "type"=>Database::$TYPE_DATETIME , "length"=>null , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE),
                     array("name" => "submitted_by" , "type"=>Database::$TYPE_VARCHAR , "length"=>200 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE),
                     array("name" => "dump_file" , "type"=>Database::$TYPE_VARCHAR , "length"=>200 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE)
                     )
                 );
      } catch (WAException $ex) {
         $this->lH->log(1, $this->TAG, "An error occurred while trying to create the ".Workflow::$TABLE_META_CHANGES." table");
         array_push($this->errors, new WAException("Unable to create the ".Workflow::$TABLE_META_CHANGES." table", WAException::$CODE_DB_CREATE_ERROR, $ex));
         $this->healthy = false;
      }
   }
   
   /**
    * This function checks whether the metaVarNames table  is created and creates it if not.
    * This table stores variable names (and their initial names)
    */
   private function createMetaVarNamesTable() {
      try {
         $this->database->runCreateTableQuery(Workflow::$TABLE_META_VAR_NAMES,
                 array(
                     array("name" => "id" , "type"=>Database::$TYPE_SERIAL , "length"=>null , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_PRIMARY),
                     array("name" => "original_name" , "type"=>Database::$TYPE_VARCHAR , "length"=>100 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE),
                     array("name" => "curr_name" , "type"=>Database::$TYPE_VARCHAR , "length"=>100 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE),
                     array("name" => "change_by" , "type"=>Database::$TYPE_VARCHAR , "length"=>200 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE),
                     array("name" => "change_time" , "type"=>Database::$TYPE_DATETIME , "length"=>null , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE)
                     )
                 );
      } catch (WAException $ex) {
         $this->lH->log(1, $this->TAG, "An error occurred while trying to create the ".Workflow::$TABLE_META_VAR_NAMES." table");
         array_push($this->errors, new WAException("Unable to create the ".Workflow::$TABLE_META_VAR_NAMES." table", WAException::$CODE_DB_CREATE_ERROR, $ex));
         $this->healthy = false;
      }
   }
   
   /**
    * This function checks whether the metaChanges  is created and creates it if not.
    * This table stores users that have access to the workflow
    */
   private function createMetaAccessTable() {
      try{
         $this->database->runCreateTableQuery(Workflow::$TABLE_META_ACCESS,
                 array(
                     array("name" => "id" , "type"=>Database::$TYPE_SERIAL , "length"=>null , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_PRIMARY),
                     array("name" => "user_granted" , "type"=>Database::$TYPE_VARCHAR , "length"=>200 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE),
                     array("name" => "time_granted" , "type"=>Database::$TYPE_DATETIME , "length"=>null , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE),
                     array("name" => "granted_by" , "type"=>Database::$TYPE_VARCHAR , "length"=>200 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE)
                     )
                 );
      } catch (WAException $ex) {
         $this->lH->log(1, $this->TAG, "An error occurred while trying to create the ".Workflow::$TABLE_META_ACCESS." table");
         array_push($this->errors, new WAException("Unable to create the ".Workflow::$TABLE_META_ACCESS." table", WAException::$CODE_DB_CREATE_ERROR, $ex));
         $this->healthy = false;
      }
   }
   
   /**
    * This funciton checks whether the metaDocument table is created and creates it if not.
    * This table stores the name, version and working directory for the document being worked on
    */
   private function createMetaDocumentTable() {
      try {
         $this->database->runCreateTableQuery(Workflow::$TABLE_META_DOCUMENT,
                 array(
                     array("name" => "id" , "type"=>Database::$TYPE_SERIAL , "length"=>11 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_PRIMARY),
                     array("name" => "workflow_name" , "type"=>Database::$TYPE_VARCHAR , "length"=>200 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE),
                     array("name" => "created_by" , "type"=>Database::$TYPE_VARCHAR , "length"=>200 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE),
                     array("name" => "time_created" , "type"=>Database::$TYPE_DATETIME , "length"=>null , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE),
                     array("name" => "workflow_id" , "type"=>Database::$TYPE_VARCHAR , "length"=>20 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE),
                     array("name" => "working_dir" , "type"=>Database::$TYPE_VARCHAR , "length"=>200 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE),
                     array("name" => "processing" , "type"=>Database::$TYPE_BOOLEAN , "length"=>null , "nullable"=>false , "default"=>'false' , "key"=>Database::$KEY_NONE),
                     array("name" => "health" , "type"=>Database::$TYPE_BOOLEAN , "length"=>null , "nullable"=>false , "default"=>'true' , "key"=>Database::$KEY_NONE)
                     )
                 );
      } catch (WAException $ex) {
         $this->lH->log(1, $this->TAG, "An error occurred while trying to create the ".Workflow::$TABLE_META_DOCUMENT." table");
         array_push($this->errors, new WAException("Unable to create the ".Workflow::$TABLE_META_DOCUMENT." table", WAException::$CODE_DB_CREATE_ERROR, $ex));
         $this->healthy = false;
      }
   }
   
   private function createMetaErrorsTable() {
      try {
         $this->database->runCreateTableQuery(Workflow::$TABLE_META_ERRORS,
                 array(
                     array("name" => "id" , "type"=>Database::$TYPE_SERIAL , "length"=>11 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_PRIMARY),
                     array("name" => "code" , "type"=>Database::$TYPE_INT , "length"=>null , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE),
                     array("name" => "message" , "type"=>Database::$TYPE_VARCHAR , "length"=>250 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE),
                     array("name" => "time_added" , "type"=>Database::$TYPE_DATETIME , "length"=>null , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE)
                     )
                 );
      } catch (WAException $ex) {
         $this->lH->log(1, $this->TAG, "An error occurred while trying to create the ".Workflow::$TABLE_META_DOCUMENT." table");
         array_push($this->errors, new WAException("Unable to create the ".Workflow::$TABLE_META_DOCUMENT." table", WAException::$CODE_DB_CREATE_ERROR, $ex));
         $this->healthy = false;
      }
   }
   
   /**
    * This function creates the working directory for this workflow instance
    */
   private function createWorkingDir() {
      $this->lH->log(4, $this->TAG, "Creating working directory for workflow instance");
      if($this->instanceId != null){
         $this->workingDir = "../odk_workflow/".$this->instanceId;
         if(file_exists($this->workingDir)) {//file exists
            //check if is directory
            if(is_dir($this->workingDir)) {
               $this->lH->log(4, $this->TAG, "Workflow instance directory already exists");
            }
            else {
               $this->lH->log(1, $this->TAG, "Working directory for workflow instance is read as a file and not a directory");
               array_push($this->errors, new WAException("Unable to create working directory for workflow instance because a file with the same name already exists", WAException::$CODE_FS_CREATE_DIR_ERROR, null));
               $this->healthy= false;
               $this->workingDir = null;
            }
         }
         else {//workind directory does not exist
            $result = mkdir($this->workingDir, 0755, true);
            if($result === FALSE){
               $this->lH->log(1, $this->TAG, "Was unable to create a working directory for the current workflow instance");
               array_push($this->errors, new WAException("Unable to create working directory for workflow instance because of a filesystem problem", WAException::$CODE_FS_CREATE_DIR_ERROR, null));
               $this->healthy = false;
               $this->workingDir = null;
            }
            else {
               $this->lH->log(4, $this->TAG, "Working directory for workflow instance successfully created");
            }
         }
      }
      else {
         array_push($this->errors, new WAException("Unable to create working directory for workflow instance because path is null", WAException::$CODE_FS_CREATE_DIR_ERROR, null));
         $this->lH->log(1, $this->TAG, "Unable to create working directory for workflow instance because path is null");
         $this->healthy = false;
         $this->workingDir = null;
      }
   }
   
   /**
    * This function generates a 20 character random string that meets MySQL's Schema
    * Object Name guidelines. Refer to https://dev.mysql.com/doc/refman/5.0/en/identifiers.html
    * The function also checks whether the instance id is unique
    * 
    * @return String Returns the instance id or null if an error occurres
    */
   private function generateInstanceID() {
      $generated = false;
      while($generated === false) {
         //generate random id
         $randomID = $this->generateRandomID();
         
         //check if random id is unique
         try{
            //$result = $this->database->runGenericQuery("show databases", true);//since all databases names correspond to the workflow instances
            $result = $this->database->getDatabaseNames();
            
            $isDuplicate = false;
            for($index = 0; $index < count($result); $index++){
               if($result[$index] === $randomID) {
                  $isDuplicate = true;
                  break;
               }
            }
            
            if($isDuplicate === false){//no database with a similar name exists. 
               return $randomID;
            }
            //otherwise, the loop will rerun
            //theoretically, this loop should only run once
         }
         catch (WAException $ex) {
            $this->lH->log(1, $this->TAG, "An error occurred while trying to create a unique workflow id");
            array_push($this->errors, new WAException("Unable to create a unique workflow ID for the form", WAException::$CODE_WF_INSTANCE_ERROR, $ex));
            $this->healthy = false;
            return;
         }
      }
   }
   
   /**
    * This function generates a random alpha numeric ID that will always start with
    * an alphabetical character
    * 
    * @return string The id
    */
   private function generateRandomID($length = 20){
      //get first random alphabetic character
      $characters = 'abcdefghijklmnopqrstuvwxyz';
      $randomString = $characters[rand(0, strlen($characters))];
      
      //generate the next 19 alphanumerical characters
      $length = $length - 1;
      $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
      for ($i = 0; $i < $length; $i++) {
          $randomString .= $characters[rand(0, strlen($characters) - 1)];
      }
      
      return $randomString;
   }
   
   /**
    * This function intializes the database object
    */
   private function initDbConnection(){
      $this->lH->log(4, $this->TAG, "Initializing the database object for the workflow instance");
      
      //close current database connection
      if($this->database != null) {
         try {
            $this->database->close();
         } catch (WAException $ex) {
            //do nothing. Inability to close database just means the PDO object in database was already null
            array_push($this->errors, $ex);
            //no need to change health status to not_healthy because this shouldn't be a bad thing
         }
      }
      
      //reinitiate new database connection
      try {
         $this->database = new Database($this->config, $this->instanceId);
      } catch (WAException $ex) {
         array_push($this->errors, $ex);
         $this->healthy = false;
         $this->lH->log(1, $this->TAG, "Unable to initialize the database object for workflow with id = {$this->instanceId}");
      }
   }
   
   /**
    * This function fetches the raw data file from the specified URL.
    * Assumes that the file is a excel file. Saves it as an .xlsx file
    * 
    * @param String $url The URL where the data file exists
    */
   public function addRawDataFile($url) {
      if($this->workingDir !== null 
              && $this->instanceId !== null
              && $this->database !== null){
         try {
            $file = new WAFile($this->config, $this->instanceId, $this->database, $this->workingDir, WAFile::$TYPE_RAW);
            $file->downloadFile($this->instanceId.".xlsx", $url, $this->currUser);
            array_push($this->files, $file);
         } catch (Exception $ex) {
            $this->lH->log(1, $this->TAG, "An error occurred while trying to add a new file to the workflow");
            array_push($this->errors, $ex);
            $this->healthy = false;
         }
      }
      else {
         $this->lH->log(1, $this->TAG, "An error occurred while trying to add a new file to the workflow because workflow object not initialized correctly");
         array_push($this->errors, new WAException("An error occurred while trying to add a new file to the workflow because workflow object not initialized correctly", WAException::$CODE_WF_INSTANCE_ERROR, null));
         $this->healthy = false;
      }
   }
   
   /**
    * This function cleans up all the resources if this object is unhealthy
    */
   public function cleanUp() {
      if($this->healthy == FALSE) {
         $this->lH->log(2, $this->TAG, "Workflow instance appears to be unhealthy. Cleaning the database and files");
         if($this->instanceId != null) {
            $this->lH->log(2, $this->TAG, "Dropping the '{$this->instanceId}' database");
            try {
               $this->database->close();
               $this->database = new Database($this->config);//make sure you connect to the default database before trying to delete the database storing instance data
               $this->database->runGenericQuery("drop database ".$this->instanceId);
            } catch (Exception $ex) {
               array_push($this->errors, $ex);
               $this->lH->log(1, $this->TAG, "An error occurred while trying to drop the database '{$this->instanceId}'");
               $this->healthy = false;
            }
         }
         if(file_exists($this->workingDir) && is_dir($this->workingDir)) {
            $result = WAFile::rmDir($this->workingDir);
            if($result == FALSE) {
               array_push($this->errors, new WAException("Unable to delete the workflow's working directory", WAException::$CODE_FS_RM_DIR_ERROR, null));
               $this->lH->log(1, $this->TAG, "An error occurred while trying to remove the instance's working directory");
               $this->healthy = false;
            }
         }
      }
   }
   
   /**
    * This function releases all locks on hardware resources. Should be called
    * last for any workflow object
    */
   public function finalize() {
      $this->database->close();
   }
   
   /**
    * This function sets the is processing flag for this instance to either
    * TRUE or FALSE. Flag will be auto reverted after processing finished
    * 
    * @param bool $isProcessing Whether instance has something running
    */
   public function setIsProcessing($isProcessing) {
      $this->processing = $isProcessing;
   }
   
   /**
    * This function takes each of the data files added to this workflow and tries
    * to create MySQL tables for them. Currently only supports one data file
    * 
    * @todo Save processing status in MySQL
    */
   public function convertDataFilesToMySQL() {
      $this->cacheIsProcessing();
      if($this->healthy == true
              && $this->database != null 
              && $this->instanceId != null
              && $this->workingDir != null
              && $this->config != null
              && $this->files != null) {
         //get only the data files
         $dataFiles = array();
         for($index = 0; $index < count($this->files); $index++) {
            $currFile = $this->files[$index];
            if($currFile->getType() == WAFile::$TYPE_RAW) {
               array_push($dataFiles, $currFile);
            }
         }
         
         if(count($dataFiles) > 0) {
            if(count($dataFiles) == 1) {//currently only support one data file
               $excelFile = new WAExcelFile($dataFiles[0]);
               try {
                  //TODO: how to detect if process is OOM killed by kernel
                  $excelFile->processToMySQL();
                  $excelFile->unload();//Unloads from memory. Also deletes temporary files
               } catch (WAException $ex) {
                  $this->lH->log(1, $this->TAG, "Could not process data file for workflow with id = {$this->instanceId} to MySQL");
                  array_push($this->errors, $ex);
               }
            }
            else {
               $this->lH->log(1, $this->TAG, "Workflow with instance id = '{$this->instanceId}' has more than one data file");
               array_push($this->errors, new WAException("Workflow not linked to more than one data file. Feature currently not supported", WAException::$CODE_WF_FEATURE_UNSUPPORTED_ERROR, null));
            }
         }
         else {
            $this->lH->log(1, $this->TAG, "Workflow with instance id = '{$this->instanceId}' does not have any linked data files");
            array_push($this->errors, new WAException("Workflow not linked to any data file", WAException::$CODE_WF_INSTANCE_ERROR, null));
         }
      }
      else {
         $this->lH->log(1, $this->TAG, "Workflow with instance id = '{$this->instanceId}' not initialized properly. Unable to convert workflow's data files to MySQL'");
         array_push($this->errors, new WAException("Workflow not initialized properly. Unable to convert workflow's data files to MySQL'", WAException::$CODE_WF_INSTANCE_ERROR, null));
      }
      $this->setIsProcessing(false);
      $this->cacheIsProcessing();
      $this->cacheErrors();
      $this->cacheHealth();
   }
   
   /**
    * This function returns an array with the status of this object and any 
    * cached error message
    */
   public function getCurrentStatus() {
      return Workflow::getStatusArray($this->healthy, $this->errors, $this->processing);
   }
   
   /**
    * This function returns an array with the status of this object and any 
    * cached error message
    */
   public static function getStatusArray($health, $errors, $processing = false) {
      $errorMessages = array();
      
      for($index = 0; $index < count($errors); $index++) {
         $currError = $errors[$index];//should be an object of type WAException
         array_push($errorMessages, array("code" => $currError->getCode(), "message" => Workflow::getErrorMessage($currError)));
      }
      
      $status = array(
          "healthy" => $health,
          "errors" => $errorMessages,
          "processing" => $processing
      );
      
      return $status;
   }
   
   public static function getErrorMessage($exception, $level = 0, $currMessage = "") {
      $prepend = " -> ";
      if($level == 0) $prepend = "";
      $currMessage .= $prepend.$exception->getMessage();
      $previous = $exception->getPrevious();
      if($previous === null || $level > 5) {
         return $currMessage;
      }
      else {
         return Workflow::getErrorMessage($previous, $level+1, $currMessage);
      }
   }
   
   /**
    * This function returns the Workflow instance id for this object
    */
   public function getInstanceId() {
      return $this->instanceId;
   }
   
   /**
    * This function sets the processing flag in the META_DOCUMENT table as either 
    * TRUE or FALSE depending on what is provided
    */
   public function cacheIsProcessing() {
      $processing = $this->processing;
      if($processing === true) $processing = Database::$BOOL_TRUE;
      else $processing = Database::$BOOL_FALSE;
      
      if($this->database != null
              && $this->instanceId != null
              && $this->database->getDatabaseName() == $this->instanceId
              && $this->instanceId != null) {
         $query = "update ".Database::$QUOTE_SI.Workflow::$TABLE_META_DOCUMENT.Database::$QUOTE_SI." set processing = '{$processing}' where workflow_id = '{$this->instanceId}'";
         try {
            $this->database->runGenericQuery($query);
         } catch (WAException $ex) {
            array_push($this->errors, $ex);
            $this->healthy = false;
            $this->lH->log(1, $this->TAG, "Unable to update processing flag in ".Workflow::$TABLE_META_DOCUMENT." table for workflow with id = '{$this->instanceId}'");
         }
      }
      else {
         array_push($this->errors, new WAException("Unable to update the processing flag in ".Workflow::$TABLE_META_DOCUMENT." because workflow instance wasn't initialized correctly", WAException::$CODE_WF_INSTANCE_ERROR, null));
         $this->healthy = false;
         $this->lH->log(1, $this->TAG, "Unable to update the processing flag in ".Workflow::$TABLE_META_DOCUMENT." because workflow with id = '{$this->instanceId}' wasn't initialized correctly");
      }
   }
   
   /**
    * This function caches health state in the META_DOCUMENT table
    */
   public function cacheHealth() {
      $health = Database::$BOOL_TRUE;
      if($this->healthy == false) $health = Database::$BOOL_FALSE;
      
      if($this->database != null
              && $this->database->getDatabaseName() == $this->instanceId
              && $this->instanceId != null) {
         $query = "update ".Database::$QUOTE_SI.Workflow::$TABLE_META_DOCUMENT.Database::$QUOTE_SI." set health = '{$health}' where workflow_id = '{$this->instanceId}'";
         try {
            $this->database->runGenericQuery($query);
         } catch (WAException $ex) {
            array_push($this->errors, $ex);
            $this->healthy = false;
            $this->lH->log(1, $this->TAG, "Unable to update health flag in ".Workflow::$TABLE_META_DOCUMENT." table for workflow with id = '{$this->instanceId}'");
         }
      }
      else {
         array_push($this->errors, new WAException("Unable to update the health flag in ".Workflow::$TABLE_META_DOCUMENT." because workflow instance wasn't initialized correctly", WAException::$CODE_WF_INSTANCE_ERROR, null));
         $this->healthy = false;
         $this->lH->log(1, $this->TAG, "Unable to update the health flag in ".Workflow::$TABLE_META_DOCUMENT." because workflow with id = '{$this->instanceId}' wasn't initialized correctly");
      }
   }
   
   /**
    * This function caches errors caught by this object in the META_ERRORS table
    */
   public function cacheErrors() {
      if($this->database != null
              && $this->database->getDatabaseName() == $this->instanceId
              && $this->instanceId != null) {
         for($index = 0; $index < count($this->errors); $index++) {
            $currError = $this->errors[$index];
            try {
               $message = $this->database->quote(Workflow::getErrorMessage($currError));//TODO: not sure this will work
               $this->database->runInsertQuery(Workflow::$TABLE_META_ERRORS, array(
                   "code" => $currError->getCode(),
                   "message" => "'$message'",
                   "time_added" => "'".Database::getMySQLTime()."'"
               ));
            } catch (WAException $ex) {
               //array_push($this->errors, $ex);
               $this->healthy = false;
               $this->lH->log(1, $this->TAG, "Unable to cache errors in ".Workflow::$TABLE_META_ERRORS." table for workflow with id = '{$this->instanceId}'");
            }
         }
      }
      else {
         //array_push($this->errors, new WAException("Unable to cache errors in ".Workflow::$TABLE_META_ERRORS." because workflow instance wasn't initialized correctly", WAException::$CODE_WF_INSTANCE_ERROR, null));
         $this->healthy = false;
         $this->lH->log(1, $this->TAG, "Unable to cache errors in ".Workflow::$TABLE_META_ERRORS." because workflow with id = '{$this->instanceId}' wasn't initialized correctly");
      }
   }
   
   /**
    * This function gets the cached status from the database
    */
   public function getCachedStatus() {
      if($this->database->getDatabaseName() == $this->instanceId
              && $this->instanceId != null) {
         //get processing and health status
         $query = "select processing, health from ".Database::$QUOTE_SI.Workflow::$TABLE_META_DOCUMENT.Database::$QUOTE_SI." order by id desc limit 1";
         try {
            $result = $this->database->runGenericQuery($query, true);
            //get status information
            if(is_array($result)) {
               if(count($result) == 1) {
                  $processing = $result[0]['processing'];
                  if($processing === Database::$BOOL_TRUE) $this->processing = true;
                  else $this->processing = false;
                  
                  $health = $result[0]['health'];
                  if($health == Database::$BOOL_TRUE) $this->healthy = true;
                  else $this->healthy = false;
               }
               else {
                  $this->lH->log(2, $this->TAG, "No cached status information for workflow with id = '{$this->instanceId}'");
               }
            }
            else {
               array_push($this->errors, new WAException("Unable to get cached status from ".Workflow::$TABLE_META_DOCUMENT, WAException::$CODE_DB_QUERY_ERROR, null));
               $this->healthy = false;
               $this->lH->log(1, $this->TAG, "Unable to get cached status from ".Workflow::$TABLE_META_DOCUMENT." for workflow with id = {$this->instanceId}");
            }
            
            //get cached errors
            $query = "select ".Database::$QUOTE_SI."code".Database::$QUOTE_SI.", ".Database::$QUOTE_SI."message".Database::$QUOTE_SI." from ".Database::$QUOTE_SI.Workflow::$TABLE_META_ERRORS.Database::$QUOTE_SI." order by id";
            $result = $this->database->runGenericQuery($query, true);
            if(is_array($result)) {
               for($index = 0; $index < count($result); $index++) {
                  array_push($this->errors, new WAException($result[$index]['message'], $result[$index]['code']));
               }
            }
            else {
               array_push($this->errors, new WAException("Unable to get cached errors from ".Workflow::$TABLE_META_ERRORS, WAException::$CODE_DB_QUERY_ERROR, null));
               $this->healthy = false;
               $this->lH->log(1, $this->TAG, "Unable to get cached status from ".Workflow::$TABLE_META_ERRORS." for workflow with id = {$this->instanceId}");
            }
         } catch (WAException $ex) {
            array_push($this->errors, new WAException("Unable to get cached status from ".Workflow::$TABLE_META_DOCUMENT, WAException::$CODE_WF_INSTANCE_ERROR, $ex));
            $this->healthy = false;
            $this->lH->log(1, $this->TAG, "Unable to get cached status from ".Workflow::$TABLE_META_DOCUMENT." for workflow with id = {$this->instanceId}");
         }
      }
      else {
         array_push($this->errors, new WAException("Unable to get cached status from ".Workflow::$TABLE_META_DOCUMENT." because workflow instance wasn't initialized correctly", WAException::$CODE_WF_INSTANCE_ERROR, null));
         $this->healthy = false;
         $this->lH->log(1, $this->TAG, "Unable to get cached status from ".Workflow::$TABLE_META_ERRORS." because workflow with id = '{$this->instanceId}' wasn't initialized correctly");
      }
   }
   
   /**
    * This function creates a save point for the workflow
    */
   public function save() {
      $filename = date("Y-m-d_H-i-s")."_".$this->generateRandomID(5).".sql";
      if($this->healthy == true
              && $this->instanceId != null
              && $this->database->getDatabaseName() == $this->instanceId
              && $this->workingDir != NULL
              && $this->currUser != null) {
         try {
            $this->database->backup($this->workingDir, $filename, $this->currUser);
         } catch (WAException $ex) {
            array_push($this->errors, $ex);
            $this->healthy = false;
            $this->lH->log(1, $this->TAG, "Unable to save the workflow with instance id = '{$this->instanceId}'");
         }
      }
      else {
         array_push($this->errors, new WAException("Unable to save workflow instance because it wasn't initialized correctly", WAException::$CODE_WF_INSTANCE_ERROR, null));
         $this->healthy = false;
         $this->lH->log(1, $this->TAG, "Unable to save workflow with id = '{$this->instanceId}' because it wasn't initialized correctly");
      }
   }
   
   /**
    * 
    * @param type $restorePoint
    */
   public function restore($restorePoint) {
      try {
         //connect to default database before trying to restore workflow database
         if($this->database != null) $this->database->close();
         $this->database = new Database($this->config);
         
         $path = WAFile::getSavePointFilePath($this->workingDir, $restorePoint);
         $this->database->restore($this->instanceId, $path);
      } catch (WAException $ex) {
         if($ex->getCode() != WAException::$CODE_DB_CLOSE_ERROR) {
            array_push($this->errors, $ex);
            $this->healthy = false;
            $this->lH->log(1, $this->TAG, "Unable to restore workflow with id = '{$this->instanceId}' to '$restorePoint' save point");
         }
      }
   }
   
   /**
    * This function modifies a sheet column
    * 
    * @param type $sheet         The name of the sheet
    * @param type $columnDetails Details of the column to be modified
    */
   public function modifyColumn($sheetName, $columnDetails){
      //try saving the workflow instance first
      $this->save();
      if($this->healthy == true) {
         try {
            $sheet = new WASheet($this->config, $this->database, null, $sheetName);
            $sheet->alterColumn($columnDetails);
         } catch (WAException $ex) {
            array_push($this->errors, $ex);
            $this->healthy = false;
            $this->lH->log(1, $this->TAG, "Could not modify column details for '{$columnDetails['original_name']}' in '$sheetName' in the workflow with instance '{$this->instanceId}'");
         }
      }
      else {
         array_push($this->errors, new WAException("Unable to modify column because workflow instance wasn't successfully backed up", WAException::$CODE_WF_INSTANCE_ERROR, null));
         $this->healthy = false;
         $this->lH->log(1, $this->TAG, "Unable to modify column because workflow with instance id = '{$this->instanceId}' wasn't successfully backed up");
      }
      
      $this->setIsProcessing(false);
      $this->cacheIsProcessing();
      $this->cacheErrors();
      $this->cacheHealth();
   }
   
   /**
    * This function modifies a sheet
    * 
    * @param array $sheetDetails
    */
   public function modifySheet($sheetDetails) {
      if(array_key_exists("original_name", $sheetDetails)
              && array_key_exists("name", $sheetDetails)
              && array_key_exists("delete", $sheetDetails)) {
         $this->save();
         if($this->healthy == true) {
            try {
               $sheet = new WASheet($this->config, $this->database, null, $sheetDetails['original_name']);
               if($sheetDetails['delete'] == true){
                  $this->lH->log(3, $this->TAG, "Going to delete '".$sheetDetails['original_name']."' in '{$this->instanceId}' workflow");
                  $sheet->delete();
               }
               else {
                  $this->lH->log(3, $this->TAG, "Going to rename '".$sheetDetails['original_name']."' to '".$sheetDetails['name']."' in the '{$this->instanceId}' workflow");
                  $sheet->rename($sheetDetails['name']);
               }
            } catch (WAException $ex) {
               array_push($this->errors, new WAException("Unable to modify sheet because sheet object wasn't initialized correctly", WAException::$CODE_WF_INSTANCE_ERROR, $ex));
               $this->healthy = false;
               $this->lH->log(1, $this->TAG, "Unable to modify sheet because sheet object wasn't initialized correctly in workflow with instance id = '{$this->instanceId}'");
            }
         }
         else {
            array_push($this->errors, new WAException("Unable to modify sheet because workflow instance wasn't successfully backed up", WAException::$CODE_WF_INSTANCE_ERROR, null));
            $this->healthy = false;
            $this->lH->log(1, $this->TAG, "Unable to modify sheet because workflow with instance id = '{$this->instanceId}' wasn't successfully backed up");
         }
      }
      else {
         array_push($this->errors, new WAException("Unable to modify sheet because provided sheet details are mulformed", WAException::$CODE_WF_INSTANCE_ERROR, null));
         $this->healthy = false;
         $this->lH->log(1, $this->TAG, "Unable to modify sheet because provided sheet details are mulformed for workflow with instance id = '{$this->instanceId}'");
      }
      
      $this->setIsProcessing(false);
      $this->cacheIsProcessing();
      $this->cacheErrors();
      $this->cacheHealth();
   }
   
   /**
    * This function gets the schema details for all data storing tables from the
    * MySQL database storing data for this workflow
    * 
    * @return Array Array with schema data for all the data storing MySQL tables
    */
   public function getSchema() {
      if($this->healthy == true && $this->instanceId == $this->database->getDatabaseName()
              && $this->instanceId != null) {
         $dataTables = WASheet::getAllWASheets($this->config, $this->instanceId, $this->database);
         
         $sheets = array();
         try {
            for($index = 0; $index < count($dataTables); $index++) {
               $currSheet = new WASheet($this->config, $this->database, null, $dataTables[$index]);
               array_push($sheets, $currSheet->getSchema());
            }
            
            $schema = array(
                "workflow_id" => $this->instanceId,
                "title" => $this->workflowName,
                "sheets" => $sheets
            );
            
            return $schema;
         } catch (WAException $ex) {
            array_push($this->errors, $ex);
            $this->healthy = false;
            $this->lH->log(1, $this->TAG, "Unable to get data table schemas for workflow with id = '{$this->instanceId}'");
         }
      }
      else {
         array_push($this->errors, new WAException("Unable to get data tables because workflow instance wasn't initialized correctly", WAException::$CODE_WF_INSTANCE_ERROR, null));
         $this->healthy = false;
         $this->lH->log(1, $this->TAG, "Unable to get data tables because workflow with id = '{$this->instanceId}' wasn't initialized correctly");
      }
   }
   
   /**
    * This function gets all the save points for this instance
    */
   public function getSavePoints() {
      if($this->database != null
              && $this->instanceId != null
              && $this->instanceId == $this->database->getDatabaseName()) {
         try {
            $savePointFiles = WAFile::getAllSavePointFiles($this->config, $this->instanceId, $this->database, $this->workingDir);
            $formatted = array();
            for($index = 0; $index < count($savePointFiles); $index++) {
               $currFile = $savePointFiles[$index];
               $currFileDetails = $currFile->getFileDetails();
               
               $currFileDetails["creator"] = ODKWorkflowAPI::explodeUserUUID($currFileDetails['creator']);
               unset($currFileDetails["type"]);
               unset($currFileDetails['time_last_modified']);
               
               array_push($formatted, $currFileDetails);
            }
            
            return $formatted;
         } catch (WAException $ex) {
            array_push($this->errors, $ex);
            $this->healthy = false;
            $this->lH->log(1, $this->TAG, "Unable to get save point files for workflow with id = '{$this->instanceId}'");
         }
      }
      else {
         array_push($this->errors, new WAException("Unable to get data tables because workflow instance wasn't initialized correctly", WAException::$CODE_WF_INSTANCE_ERROR, null));
         $this->healthy = false;
         $this->lH->log(1, $this->TAG, "Unable to get data tables because workflow with id = '{$this->instanceId}' wasn't initialized correctly");
      }
   }
   
   /**
    * This function returns an array containing details of workflows that the
    * specified user has access to
    * 
    * @param array   $config  repository_config object to be used
    * @param string  $user    user we are getting access for
    */
   public static function getUserWorkflows($config, $user) {
      include_once 'mod_wa_database.php';
      include_once 'mod_wa_exception.php';
      include_once 'mod_log.php';
      
      $lH = new LogHandler("./");
      $database = new Database($config);
      $jsonReturn = array(
          "workflows" => array(),
          "status" => array("health" => true, "errors" => array())
      );//object to store return json for this function
      
      //get all the databases that look like store workflow details
      //$query = "show databases";
      try {
         $result = $database->getDatabaseNames();
         if($result !== false){
            $accessibleDbs = array();//array to store details for all the databases user has access to
            for($index = 0; $index < count($result); $index++) {
               //$currDbName = $result[$index]['Database'];
               $currDbName = $result[$index];
               //check if current database qualifies to store workflow details
               
               $newDatabase = new Database($config, $currDbName);
               
               $tableNames = $newDatabase->getTableNames($currDbName);
               
               $metaTables = 0;
               
               if($tableNames !== false) {
                  //check each of the table names to see if the meta tables exist
                  for($tIndex = 0; $tIndex < count($tableNames); $tIndex++) {
                     /*if($tableNames[$tIndex]['Tables_in_'.$currDbName] == Workflow::$TABLE_META_ACCESS
                             || $tableNames[$tIndex]['Tables_in_'.$currDbName] == Workflow::$TABLE_META_CHANGES
                             || $tableNames[$tIndex]['Tables_in_'.$currDbName] == Workflow::$TABLE_META_DOCUMENT
                             || $tableNames[$tIndex]['Tables_in_'.$currDbName] == Workflow::$TABLE_META_VAR_NAMES) {
                        $metaTables++;
                     }*/
                     if($tableNames[$tIndex] == Workflow::$TABLE_META_ACCESS
                             || $tableNames[$tIndex] == Workflow::$TABLE_META_CHANGES
                             || $tableNames[$tIndex] == Workflow::$TABLE_META_DOCUMENT
                             || $tableNames[$tIndex] == Workflow::$TABLE_META_VAR_NAMES) {
                        $metaTables++;
                     }
                  }
               }
               
               if($metaTables == 4) {//database has all the meta tables
                  //check whether the user has access to this database
                  $query = "select *"
                          . " from ".Database::$QUOTE_SI.Workflow::$TABLE_META_ACCESS.Database::$QUOTE_SI
                          . " where ".Database::$QUOTE_SI."user_granted".Database::$QUOTE_SI." = '$user'";
                  $access = $newDatabase->runGenericQuery($query, true);
                  if($access !== false) {
                     if(count($access) > 0) {//user has access to the workflow
                        try {
                           $details = Workflow::getWorkflowDetails($config, $currDbName);
                           array_push($accessibleDbs, $details);
                           
                        } catch (WAException $ex) {
                           $jsonReturn['status'] = Workflow::getStatusArray(false, array($ex));
                           return $jsonReturn;
                        }
                     }
                  }
                  else {
                     $jsonReturn['status'] = Workflow::getStatusArray(false, array(new WAException("Unable to check if user has access to a database", WAException::$CODE_DB_QUERY_ERROR, NULL)));
                     return $jsonReturn;
                  }
               }
               else {//database not usable with this API
                  $lH->log(4, "static_workflow", "$currDbName not usable with the WorkflowAPI");
               }
               
               $newDatabase->close();
            }
            //return the accessible databases
            $jsonReturn['workflows'] = $accessibleDbs;
            return $jsonReturn;
         }
         else {
            $jsonReturn['status'] = Workflow::getStatusArray(false, array(new WAException("Unable to check if user has access to a database", WAException::$CODE_DB_QUERY_ERROR, NULL)));
            return $jsonReturn;
         }
      } catch (WAException $ex) {
         $jsonReturn['status'] = Workflow::getStatusArray(false, array(new WAException("Unable to available database in MySQL", WAException::$CODE_DB_QUERY_ERROR, $ex)));
         return $jsonReturn;
      }
   }
   
   /**
    * This function gets the specified workflow's details from the database
    * 
    * @param string     $dbName     The name of the MySQL database where the workflow is stored
    * @param Database   $database   Database object to be used to perform MySQL queries
    * 
    * @return Array  Associative array containing the database details
    * @throws WAException
    */
   private static function getWorkflowDetails($config, $dbName){
      require_once 'mod_wa_database.php';
      $database = new Database($config, $dbName);
      $query = "select workflow_name, created_by, time_created, workflow_id, working_dir"
              . " from ".Database::$QUOTE_SI.Workflow::$TABLE_META_DOCUMENT.Database::$QUOTE_SI
              . " order by id desc limit 1";//get the latest document details
      try {
         $result = $database->runGenericQuery($query, true);
         if($result !== false) {
            if(count($result) > 0) {
               return $result[0];
            }
            else {
               throw new WAException("No workflow details stored in the database", WAException::$CODE_DB_ZERO_RESULT_ERROR, null);
            }
         }
         else {
            throw new WAException("Unable to get workflow details from the database", WAException::$CODE_DB_QUERY_ERROR, null);
         }
      } catch (WAException $ex) {
         throw new WAException("Unable to get workflow details from the database", WAException::$CODE_DB_QUERY_ERROR, $ex);
      }
   }
}
?>