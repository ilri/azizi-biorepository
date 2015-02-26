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
   
   private static $TABLE_META_CHANGES = "__meta_changes";
   private static $TABLE_META_VAR_NAMES = "__meta_var_names";
   private static $TABLE_META_ACCESS = "__meta_access";
   private static $TABLE_META_DOCUMENT = "__meta_document";
   
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
      
      $this->config = $config;
      $this->lH = new LogHandler("./");
      $this->errors = array();
      $this->healthy = true;
      $this->files = array();
      $this->currUser = $currUser;
      $this->workflowName = $workflowName;
      
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
      }
      
      //make sure the working directory exists
      $this->createWorkingDir();
      
      //make sure meta tables have been created
      $this->initMetaDBTables();
      
      $this->grantUserAccess($this->currUser);
      $this->saveWorkflowDetails();
   }
   
   /**
    * This function saves the user's access level in the MySQL database
    * 
    * @param type $user       The user to be granted access to the workflow
    * @param type $grantedBy  The user granting the access
    */
   private function grantUserAccess($user) {
      $columns = array(
          "user_granted" => $user,
          "time_granted" => Database::getMySQLTime(),
          "granted_by" => $this->currUser
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
      $columns = array(
          "workflow_name" => $this->workflowName,
          "created_by" => $this->currUser,
          "time_created" => Database::getMySQLTime(),
          "workflow_id" => $this->instanceId,
          "working_dir" => $this->workingDir
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
                     array("name" => "id" , "type"=>Database::$TYPE_INT , "length"=>11 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_PRIMARY , "auto_incr"=>true),
                     array("name" => "change_time" , "type"=>Database::$TYPE_DATETIME , "length"=>null , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE , "auto_incr"=>false),
                     array("name" => "submitted_by" , "type"=>Database::$TYPE_VARCHAR , "length"=>200 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE , "auto_incr"=>false),
                     array("name" => "dump_file" , "type"=>Database::$TYPE_VARCHAR , "length"=>200 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE , "auto_incr"=>false)
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
                     array("name" => "id" , "type"=>Database::$TYPE_INT , "length"=>11 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_PRIMARY , "auto_incr"=>true),
                     array("name" => "original_name" , "type"=>Database::$TYPE_VARCHAR , "length"=>100 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE , "auto_incr"=>false),
                     array("name" => "curr_name" , "type"=>Database::$TYPE_VARCHAR , "length"=>100 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE , "auto_incr"=>false),
                     array("name" => "change_by" , "type"=>Database::$TYPE_VARCHAR , "length"=>200 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE , "auto_incr"=>false),
                     array("name" => "change_time" , "type"=>Database::$TYPE_DATETIME , "length"=>null , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE , "auto_incr"=>false)
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
                     array("name" => "id" , "type"=>Database::$TYPE_INT , "length"=>11 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_PRIMARY , "auto_incr"=>true),
                     array("name" => "user_granted" , "type"=>Database::$TYPE_VARCHAR , "length"=>200 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE , "auto_incr"=>false),
                     array("name" => "time_granted" , "type"=>Database::$TYPE_DATETIME , "length"=>null , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE , "auto_incr"=>false),
                     array("name" => "granted_by" , "type"=>Database::$TYPE_VARCHAR , "length"=>200 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE , "auto_incr"=>false)
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
                     array("name" => "id" , "type"=>Database::$TYPE_INT , "length"=>11 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_PRIMARY , "auto_incr"=>true),
                     array("name" => "workflow_name" , "type"=>Database::$TYPE_VARCHAR , "length"=>200 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE , "auto_incr"=>false),
                     array("name" => "created_by" , "type"=>Database::$TYPE_VARCHAR , "length"=>200 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE , "auto_incr"=>false),
                     array("name" => "time_created" , "type"=>Database::$TYPE_DATETIME , "length"=>null , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE , "auto_incr"=>false),
                     array("name" => "workflow_id" , "type"=>Database::$TYPE_VARCHAR , "length"=>20 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE , "auto_incr"=>false),
                     array("name" => "working_dir" , "type"=>Database::$TYPE_VARCHAR , "length"=>200 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE , "auto_incr"=>false)
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
            $result = $this->database->runGenericQuery("show databases", true);//since all databases names correspond to the workflow instances
            
            $isDuplicate = false;
            for($index = 0; $index < count($result); $index++){
               if($result[$index]['Database'] === $randomID) {
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
   private function generateRandomID(){
      //get first random alphabetic character
      $characters = 'abcdefghijklmnopqrstuvwxyz';
      $randomString = $characters[rand(0, strlen($characters))];
      
      //generate the next 19 alphanumerical characters
      $length = 19;
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
      }
   }
   
   /**
    * This function fetches the raw data file from the specified URL.
    * Assumes that the file is a excel file. Saves it as an .xlsx file
    * 
    * @param String $url The URL where the data file exists
    */
   public function addRawDataFile($url) {
      if($this->workingDir !== null){
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
    * This function returns an array with the status of this object and any 
    * cached error message
    */
   public function getStatusArray() {
      $errorMessages = array();
      
      for($index = 0; $index < count($this->errors); $index++) {
         $currError = $this->errors[$index];//should be an object of type WAException
         array_push($errorMessages, array("code" => $currError->getCode(), "message" => $this->getErrorMessage($currError)));
      }
      
      $status = array(
          "healthy" => $this->healthy,
          "errors" => $errorMessages
      );
      
      return $status;
   }
   
   private function getErrorMessage($exception, $level = 0, $currMessage = "") {
      $prepend = " -> ";
      if($level == 0) $prepend = "";
      $currMessage .= $prepend.$exception->getMessage();
      $previous = $exception->getPrevious();
      if($previous === null || $level > 5) {
         return $currMessage;
      }
      else {
         return $this->getErrorMessage($previous, $level+1, $currMessage);
      }
   }
   
   /**
    * This function returns the Workflow instance id for this object
    */
   public function getInstanceId() {
      return $this->instanceId;
   }
}
?>