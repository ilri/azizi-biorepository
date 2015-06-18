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
   private static $workflowPrefix = "dmp_";//prefix to be used in instance IDs and db users
   
   public static $TABLE_META_CHANGES = "__meta_changes";
   public static $TABLE_META_VAR_NAMES = "__meta_var_names";
   public static $TABLE_META_ACCESS = "__meta_access";
   public static $TABLE_META_DOCUMENT = "__meta_document";
   public static $TABLE_META_ERRORS = "__meta_errors";
   public static $TABLE_META_NOTES = "__meta_notes";
   public static $WORKFLOW_ROOT_DIR = "../odk_workflow/";
   public static $CHANGE_SHEET = "sheet";
   public static $CHANGE_COLUMN = "column";
   
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
    * This function returns the name of the current workflow
    * 
    * @return String the name of the workflow
    */
   public function getWorkflowName() {
      return $this->workflowName;
   }
   
   /**
    * This function saves the user's access level in the MySQL database
    * 
    * @param type $user       The user to be granted access to the workflow
    * @param type $grantedBy  The user granting the access
    */
   public function grantUserAccess($user) {
      $this->lH->log(3, $this->TAG, "Granting '$user' access to workflow with id = '{$this->instanceId}'");
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
   
   /**
    * This function saves workflow details including:
    *    - the name
    *    - created by
    *    - time created
    *    - workflow_id
    *    - working directory
    *    - whether a process is currently running
    *    - the workflow health
    */
   private function saveWorkflowDetails() {
      $this->lH->log(3, $this->TAG, "Saving details for workflow with id = '{$this->instanceId}'");
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
         $this->createMetaNotesTable();
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
                     array("name" => "change_type" , "type"=>Database::$TYPE_VARCHAR , "length"=>100 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE),
                     array("name" => "original_sheet" , "type"=>Database::$TYPE_VARCHAR , "length"=>200 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE),
                     array("name" => "original_column" , "type"=>Database::$TYPE_VARCHAR , "length"=>200 , "nullable"=>true , "default"=>null , "key"=>Database::$KEY_NONE),
                     array("name" => "current_sheet" , "type"=>Database::$TYPE_VARCHAR , "length"=>200 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE),
                     array("name" => "current_column" , "type"=>Database::$TYPE_VARCHAR , "length"=>200 , "nullable"=>true , "default"=>null , "key"=>Database::$KEY_NONE),
                     array("name" => "file" , "type"=>Database::$TYPE_VARCHAR , "length"=>200 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE),
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
                     array("name" => "workflow_id" , "type"=>Database::$TYPE_VARCHAR , "length"=>30 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE),
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
   
   /**
    * This funciton checks whether the metaErrors table is created and creates it if not.
    * This table stores the code, message and timestamp for the error
    */
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
         $this->lH->log(1, $this->TAG, "An error occurred while trying to create the ".Workflow::$TABLE_META_ERRORS." table");
         array_push($this->errors, new WAException("Unable to create the ".Workflow::$TABLE_META_ERRORS." table", WAException::$CODE_DB_CREATE_ERROR, $ex));
         $this->healthy = false;
      }
   }
   
   private function createMetaNotesTable(){
      try {
         $this->database->runCreateTableQuery(Workflow::$TABLE_META_NOTES,
                 array(
                     array("name" => "id" , "type"=>Database::$TYPE_SERIAL , "length"=>11 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_PRIMARY),
                     array("name" => "message" , "type"=>Database::$TYPE_VARCHAR , "length"=>1000 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE),
                     array("name" => "user" , "type"=>Database::$TYPE_VARCHAR , "length"=>200 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE),
                     array("name" => "time_added" , "type"=>Database::$TYPE_DATETIME , "length"=>null , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE)
                     )
                 );
      } catch (WAException $ex) {
         $this->lH->log(1, $this->TAG, "An error occurred while trying to create the ".Workflow::$TABLE_META_NOTES." table");
         array_push($this->errors, new WAException("Unable to create the ".Workflow::$TABLE_META_ERRORS." table", WAException::$CODE_DB_CREATE_ERROR, $ex));
         $this->healthy = false;
      }
   }
   
   /**
    * This function creates the working directory for this workflow instance
    */
   private function createWorkingDir() {
      $this->lH->log(4, $this->TAG, "Creating working directory for workflow instance");
      if($this->instanceId != null){
         $this->workingDir = Workflow::$WORKFLOW_ROOT_DIR.$this->instanceId;
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
      $this->lH->log(3, $this->TAG, "Generating an instance id for this new workflow");
      $generated = false;
      while($generated === false) {
         //generate random id
         
         $randomID = Workflow::$workflowPrefix.Workflow::generateRandomID();
         
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
    * This function adds a note to the workflow
    * 
    * @param type $note
    */
   public function addNote($note) {
      try {
         $this->database->runInsertQuery(Workflow::$TABLE_META_NOTES, array(
            "message" => $this->database->quote($note),
            "user" => "'".$this->currUser."'",
            "time_added" => "'".Database::getMySQLTime()."'"
         ));
      } catch (WAException $ex) {
         $this->lH->log(1, $this->TAG, "An error occurred while trying to add a note to the workflow");
         array_push($this->errors, new WAException("Unable to add the note provided", WAException::$CODE_DB_QUERY_ERROR, $ex));
         $this->healthy = false;
         return;
      }
   }
   
   /**
    * This function fetches all the workflow notes
    * 
    * @return Array  An array of all the notes 
    */
   public function getAllNotes($pruneServer = true) {
      $notes = array();
      try {
         $query = "select * from ".Workflow::$TABLE_META_NOTES." order by time_added";
         $results = $this->database->runGenericQuery($query, true);
         foreach ($results as $currResult) {//remove the server component of the user field
            if($pruneServer == true) {
               $userDetails = ODKWorkflowAPI::explodeUserUUID($currResult['user']);
               $currResult['user'] = $userDetails['user'];
            }
            //unset($currResult['id']);
            $notes[] = $currResult;
         }
      } catch (WAException $ex) {
         $this->lH->log(1, $this->TAG, "An error occurred while trying to fetch all workflow notes");
         array_push($this->errors, new WAException("Unable to fetch notes for the workflow", WAException::$CODE_DB_QUERY_ERROR, $ex));
         $this->healthy = false;
         return;
      }
      return $notes;
   }
   
   /**
    * This function deletes a note using it's id
    * 
    * @param type $noteId  The note's id
    */
   public function deleteNote($noteId) {
      $savePoint = null;
      try {
         $savePoint = $this->save("Delete note");
         $query = "delete from ".Workflow::$TABLE_META_NOTES." where id = $noteId";
         $this->database->runGenericQuery($query);
      } catch (WAException $ex) {
         $this->lH->log(1, $this->TAG, "An error occurred while trying to delete a note");
         array_push($this->errors, new WAException("Unable to delete the note", WAException::$CODE_DB_QUERY_ERROR, $ex));
         $this->healthy = false;
         return;
      }
      return $savePoint;
   }
   
   /**
    * This function runs a generic non-select query in the database corresponding
    * to this workflow. The workflow is backed up before running the query
    * 
    * @param string $query The query to be run
    */
   public function runNonSelectQuery($query) {
      $savePoint = null;
      try {
         $queryParts = explode(" ", $query);
         $description = "Run ".$queryParts[0]." ".$queryParts[1]." ".$queryParts[2]." query";
         $savePoint = $this->save($description);
         if($this->healthy == true) {
            $this->database->runGenericQuery($query);
         }
      } catch (WAException $ex) {
         $this->lH->log(1, $this->TAG, "An error occurred while trying to run a generic query on the workflow");
         array_push($this->errors, new WAException("Unable to run the given query", WAException::$CODE_DB_QUERY_ERROR, $ex));
         $this->healthy = false;
         return;
      }
      return $savePoint;
   }
   
   /**
    * This function generates a random alpha numeric ID that will always start with
    * an alphabetical character
    * 
    * @return string The id
    */
   public static function generateRandomID($length = 20){
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
   public function addRawDataFile($url, $name = null, $mergeSheet = null, $mergeColumn = null) {
      if($name == null) $name = $this->instanceId.".xlsx";
      $this->lH->log(3, $this->TAG, "Adding '$name' in '$url' to the list of raw datafiles for '{$this->instanceId}'");
      //$this->lH->log(3, $this->TAG, "Adding raw data file to workflow with id = '{$this->instanceId}'");
      if($this->workingDir !== null 
              && $this->instanceId !== null
              && $this->database !== null){
         try {
            $file = new WAFile($this->config, $this->instanceId, $this->database, $this->workingDir, WAFile::$TYPE_RAW);
            $file->downloadFile($name, $url, $this->currUser);
            if($mergeSheet != null && $mergeColumn != null) {
               $file->setMergeColumn($mergeSheet, $mergeColumn);
            }
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
      $this->lH->log(3, $this->TAG, "Cleaing up workflow with id = '{$this->instanceId}'");
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
   public function convertDataFilesToMySQL($linkSheets) {
      $this->lH->log(3, $this->TAG, "Converting data files to SQL for workflow with id = '{$this->instanceId}'");
      $this->cacheIsProcessing();
      if($this->healthy == true
              && $this->database != null 
              && $this->instanceId != null
              && $this->workingDir != null
              && $this->config != null
              && $this->files != null) {
         //get only the data files
         $dataFiles = $this->getRawDataFiles();
         
         if(count($dataFiles) > 0) {
            if(count($dataFiles) == 1) {//currently only support one data file
               //delete any existing data table in the database
               $dataTables = WASheet::getAllWASheets($this->config, $this->instanceId, $this->database);
               if(count($dataTables) > 0) {
                  $this->lH->log(2, $this->TAG, "Workflow already has ".  count($dataTables)." data tables (before generating schema from Excel file). Dropping this tables");
               }
               for($i = 0; $i < count($dataTables); $i++) {
                  $this->database->runGenericQuery("drop table ".Database::$QUOTE_SI.$dataTables[$i].Database::$QUOTE_SI." cascade");
               }
               $excelFile = new WAExcelFile($dataFiles[0]);
               try {
                  $excelFile->processToMySQL($linkSheets);
                  $excelFile->unload();//Unloads from memory. Also deletes temporary files
               } catch (WAException $ex) {
                  $this->lH->log(1, $this->TAG, "Could not process data file for workflow with id = {$this->instanceId} to MySQL");
                  array_push($this->errors, $ex);
                  $this->healthy = false;
               }
            }
            else {
               $this->lH->log(1, $this->TAG, "Workflow with instance id = '{$this->instanceId}' has more than one data file");
               array_push($this->errors, new WAException("Workflow not linked to more than one data file. Feature currently not supported", WAException::$CODE_WF_FEATURE_UNSUPPORTED_ERROR, null));
               $this->healthy = false;
            }
         }
         else {
            $this->lH->log(1, $this->TAG, "Workflow with instance id = '{$this->instanceId}' does not have any linked data files");
            array_push($this->errors, new WAException("Workflow not linked to any data file", WAException::$CODE_WF_INSTANCE_ERROR, null));
            $this->healthy = false;
         }
      }
      else {
         $this->lH->log(1, $this->TAG, "Workflow with instance id = '{$this->instanceId}' not initialized properly. Unable to convert workflow's data files to MySQL'");
         $this->lH->log(4, $this->TAG, "Workflow instance details ".  print_r($this, true));
         array_push($this->errors, new WAException("Workflow not initialized properly. Unable to convert workflow's data files to MySQL'", WAException::$CODE_WF_INSTANCE_ERROR, null));
         $this->healthy = false;
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
   
   /**
    * This function recurrsively extracts error messages from exception objects
    * 
    * @param Exception $exception   The exception object from which to extract the message
    * @param int $level             The current level in the exception. 0 is root
    * @param string $currMessage    The current error message
    * 
    * @return string The extracted error message
    */
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
      /*if($this->database != null
              && $this->database->getDatabaseName() == $this->instanceId
              && $this->instanceId != null) {
         for($index = 0; $index < count($this->errors); $index++) {
            $currError = $this->errors[$index];
            try {
               $message = $this->database->quote(Workflow::getErrorMessage($currError));//TODO: not sure this will work
               $this->database->runInsertQuery(Workflow::$TABLE_META_ERRORS, array(
                   "code" => $currError->getCode(),
                   "message" => "'$message'",//TODO:find a way of nicely caching the error messages without creating an error in the process (of caching in the database)
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
      }*/
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
   public function save($description) {
      $this->lH->log(3, $this->TAG, "Creating a database dump for workflow with id = '{$this->instanceId}'");
      $filename = date("Y-m-d_H-i-s")."_".  Workflow::generateRandomID(5).".sql";
      if($this->healthy == true
              && $this->instanceId != null
              && $this->database->getDatabaseName() == $this->instanceId
              && $this->workingDir != NULL
              && $this->currUser != null) {
         try {
            $this->database->backup($this->workingDir, $filename, $this->currUser, $description);
            return $filename;
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
      return null;
   }
   
   /**
    * This function restores the specified workflow to a previous state
    * 
    * @param type $config
    * @param type $instanceId
    * @param type $restorePoint
    * @return array  Status showing whether operation was successful
    */
   public static function restore($config, $instanceId, $restorePoint) {
      include_once 'mod_wa_database.php';
      include_once 'mod_wa_exception.php';
      include_once 'mod_log.php';
      $lH = new LogHandler("./");
      $healthy = true;
      $errors = array();
      $lH->log(3, "waworkflow_static", "Restoring workflow with id = '{$instanceId}' to '$restorePoint' restore point");
      try {
         $database = new Database($config);
         $workflowDetails = Workflow::getWorkflowDetails($config, $instanceId);
         $lH->log(4, "waworkflow_static", "Workflow details before restore = ".print_r($workflowDetails, true));
         $workingDir = $workflowDetails['working_dir'];
         $path = WAFile::getSavePointFilePath($workingDir, $restorePoint);
         $database->restore($instanceId, $path);
      } catch (WAException $ex) {
         if($ex->getCode() != WAException::$CODE_DB_CLOSE_ERROR) {
            array_push($errors, $ex);
            $healthy = false;
            $lH->log(1, "waworkflow_static", "Unable to restore workflow with id = '{$instanceId}' to '$restorePoint' save point");
         }
      }
      
      $status = Workflow::getStatusArray($healthy, $errors);
      return $status;
   }
   
   /**
    * This function completely deletes a workflow
    * 
    * @param type $config     The repository config file
    * @param type $instanceId The instance id of the workflow to be deleted
    * 
    * @return Array  The status after deleting the workflow
    */
   public static function delete($config, $instanceId) {
      include_once 'mod_wa_database.php';
      include_once 'mod_wa_exception.php';
      include_once 'mod_wa_file.php';
      include_once 'mod_log.php';
      $lH = new LogHandler("./");
      $healthy = true;
      $errors = array();
      $lH->log(3, "waworkflow_static", "Deleting workflow with id = '{$instanceId}'");
      try {
         $database = new Database($config);
         $lH->log(2, "waworkflow_static", "About to drop database for workflow with instance id = '$instanceId'");
         $database->runDropDatabaseQuery($instanceId);
         $lH->log(2, "waworkflow_static", "About to delete filesystem directory corresponding to workflow with instance id = '$instanceId'");
         WAFile::rmDir(Workflow::$WORKFLOW_ROOT_DIR.$instanceId);
      } catch (WAException $ex) {
         array_push($errors, $ex);
         $healthy = false;
         $lH->log(1, "waworkflow_static", "Unable to delete workflow with id = '{$instanceId}'");
      }
      
      $status = Workflow::getStatusArray($healthy, $errors);
      return $status;
   }
   
   /**
    * This function records name changes for sheets in the current workflow
    * 
    * @param type $previousName  The current sheet name (should exist)
    * @param type $currentName   The new name for the sheet
    */
    private function recordSheetNameChange($previousName, $currentName, $files = null){
      if($this->database != null
              && $this->instanceId == $this->database->getDatabaseName()){
         try {
            $this->lH->log(3, $this->TAG, "Recording sheet name change from '$previousName' to '$currentName'");
            if($files != null) {
               $queryFiles = "'".implode("', '", $files)."'";
               $query = "select id,file from ".Workflow::$TABLE_META_CHANGES." where change_type = '".Workflow::$CHANGE_SHEET."' and current_sheet = '$previousName' and file in($queryFiles)";
            }
            else {
               $query = "select id,file from ".Workflow::$TABLE_META_CHANGES." where change_type = '".Workflow::$CHANGE_SHEET."' and current_sheet = '$previousName'";
            }
            $result = $this->database->runGenericQuery($query, true);
            if(is_array($result)) {
               $recordedFiles = array();//an array containing names of files for which the name change has already been recorded
               if(count($result) > 0) {//sheet has already changed name before
                  $in = "";
                  foreach($result as $currRes) {
                     if(strlen($in) == 0)$in = $currRes['id'];
                     else $in .= ", ".$currRes['id'];
                     $recordedFiles[] = $currRes['file'];
                  }
                  $query = "update ".Workflow::$TABLE_META_CHANGES." set current_sheet = '$currentName', change_time = '{$this->database->getMySQLTime()}', submitted_by = '{$this->currUser}' where id IN($in)";
                  $this->database->runGenericQuery($query);
               }
               //get a list of all raw files for which the name change has not been recorded
               $rawFiles = $this->getRawDataFiles();
               foreach($rawFiles as $currFile) {
                  $fileDetails = $currFile->getFileDetails();
                  if(array_search($fileDetails['filename'], $recordedFiles) === false 
                        && ($files == null || in_array($fileDetails['filename'], $files) == true)) {//we have not recorded the name change for this file
                     $columns = array(
                        "change_time" => "'".$this->database->getMySQLTime()."'",
                        "submitted_by" => "'".$this->currUser."'",
                        "change_type" => "'".Workflow::$CHANGE_SHEET."'",
                        "original_sheet" => "'".$previousName."'",
                        "current_sheet" => "'".$currentName."'",
                        "file" => "'".$fileDetails['filename']."'"
                      );
                      $this->database->runInsertQuery(Workflow::$TABLE_META_CHANGES, $columns);
                  }
               }
               //update all changed columns in change table to the current sheet name
               if($files != null) {
                  $query = "update ".Workflow::$TABLE_META_CHANGES." set current_sheet = '$currentName' where current_sheet = '$previousName' and change_type = '".Workflow::$CHANGE_COLUMN."' and file in('".implode("', '", $files)."')";
               }
               else {
                  $query = "update ".Workflow::$TABLE_META_CHANGES." set current_sheet = '$currentName' where current_sheet = '$previousName' and change_type = '".Workflow::$CHANGE_COLUMN."'";
               }
               $this->database->runGenericQuery($query);
               
               //update the merge_table value for all rows in the meta files table
               $query = "update ".WAFile::$TABLE_META_FILES." set merge_table = '$currentName' where merge_table = '$previousName'";
               $this->database->runGenericQuery($query);
            }
            else {
               array_push($this->errors, new WAException("Unable to check if sheet has changed name before", WAException::$CODE_DB_QUERY_ERROR, null));
               $this->healthy = false;
               $this->lH->log(1, $this->TAG, "Unable to check if sheet has changed name before in workflow with id = '{$this->instanceId}'");
            }
         } catch (WAException $ex) {
            array_push($this->errors, new WAException("Unable to check if sheet has changed name before", WAException::$CODE_DB_QUERY_ERROR, $ex));
            $this->healthy = false;
            $this->lH->log(1, $this->TAG, "Unable to check if sheet has changed name before in workflow with id = '{$this->instanceId}'");
         }
      }
      else {
         array_push($this->errors, new WAException("Unable to record sheet name change because workflow wasn't initialized correctly", WAException::$CODE_WF_INSTANCE_ERROR, null));
         $this->healthy = false;
         $this->lH->log(1, $this->TAG, "Unable to record sheet name change because workflow with id = '{$this->instanceId}' wasn't initialized correctly");
      }
   }
   
   /**
    * This function records name changes for columns
    * 
    * @param string $sheetName   The name of the sheet where the column is found
    * @param type $previousName  The current name for the column
    * @param type $currentName   The new name given to the column
    */
   private function recordColumnNameChange($sheetName, $previousName, $currentName, $files = null){
      if($this->database != null
              && $this->instanceId == $this->database->getDatabaseName()){
         try {
            $this->lH->log(3, $this->TAG, "Recording column name change from '$previousName' to '$currentName'");
            if($files != null) {
               $query = "select id, file from ".Workflow::$TABLE_META_CHANGES." where change_type = '".Workflow::$CHANGE_COLUMN."' and current_sheet = '$sheetName' and current_column = '$previousName' and file in('".implode("', '", $files)."')";
            }
            else {
               $query = "select id, file from ".Workflow::$TABLE_META_CHANGES." where change_type = '".Workflow::$CHANGE_COLUMN."' and current_sheet = '$sheetName' and current_column = '$previousName'";
            }
            $result = $this->database->runGenericQuery($query, true);
            if(is_array($result)) {
               $recordedFiles = array();
               if(count($result) > 0) {//column has already changed name before
                  $in = "";
                  foreach($result as $currRes) {
                     if(strlen($in) == 0)$in = $currRes['id'];
                     else $in .= ", ".$currRes['id'];
                     $recordedFiles[] = $currRes['file'];
                  }
                  $query = "update ".Workflow::$TABLE_META_CHANGES." set current_column = '$currentName', change_time = '{$this->database->getMySQLTime()}', submitted_by = '{$this->currUser}' where id  IN($in)";
                  $this->database->runGenericQuery($query);
               }
               //get a list of all raw files for which the name change has not been recorded
               $rawFiles = $this->getRawDataFiles();
               foreach($rawFiles as $currFile) {
                  $fileDetails = $currFile->getFileDetails();
                  if(array_search($fileDetails['filename'], $recordedFiles) === false
                        && ($files == null || in_array($fileDetails['filename'], $files) == true)) {//we have not recorded the name change for this file
                     $columns = array(
                        "change_time" => "'".$this->database->getMySQLTime()."'",
                        "submitted_by" => "'".$this->currUser."'",
                        "change_type" => "'".Workflow::$CHANGE_COLUMN."'",
                        "original_sheet" => "'".$sheetName."'",
                        "original_column" => "'".$previousName."'",
                        "current_sheet" => "'".$sheetName."'",
                        "current_column" => "'".$currentName."'",
                        "file" => "'".$fileDetails['filename']."'"
                     );
                     $this->database->runInsertQuery(Workflow::$TABLE_META_CHANGES, $columns);
                  }
               }
               //update the merge_column value for all rows in the meta files table
               $query = "update ".WAFile::$TABLE_META_FILES." set merge_column = '$currentName' where merge_table = '$sheetName' and merge_column = '$previousName'";
               $this->database->runGenericQuery($query);
            }
            else {
               array_push($this->errors, new WAException("Unable to check if column has changed name before", WAException::$CODE_DB_QUERY_ERROR, null));
               $this->healthy = false;
               $this->lH->log(1, $this->TAG, "Unable to check if column has changed name before in workflow with id = '{$this->instanceId}'");
            }
         } catch (WAException $ex) {
            array_push($this->errors, new WAException("Unable to check if column has changed name before", WAException::$CODE_DB_QUERY_ERROR, $ex));
            $this->healthy = false;
            $this->lH->log(1, $this->TAG, "Unable to check if column has changed name before in workflow with id = '{$this->instanceId}'");
         }
      }
      else {
         array_push($this->errors, new WAException("Unable to record column name change because workflow wasn't initialized correctly", WAException::$CODE_WF_INSTANCE_ERROR, null));
         $this->healthy = false;
         $this->lH->log(1, $this->TAG, "Unable to record column name change because workflow with id = '{$this->instanceId}' wasn't initialized correctly");
      }
   }
   
   /**
    * This function modifies a sheet column
    * 
    * @param type $sheet         The name of the sheet
    * @param type $columnDetails Details of the column to be modified
    */
   public function modifyColumn($sheetName, $columnDetails){
      $savePoint = null;
      try {
         $this->lH->log(3, $this->TAG, "Modifying column in workflow with id = '{$this->instanceId}'. Sheet name = '$sheetName' and column details = ".  print_r($columnDetails, true));
         //try saving the workflow instance first
         $description = "Modify $sheetName -> {$columnDetails['original_name']}";
         if($columnDetails['delete'] == true) {
            $description = "Delete $sheetName -> {$columnDetails['original_name']}";
         }
         $savePoint = $this->save($description);
         $this->truncateWorkflow();
         if($this->healthy == true) {
            try {
               $sheet = new WASheet($this->config, $this->database, null, $sheetName);
               $sheet->alterColumn($columnDetails);
               if($columnDetails['delete'] == false) $this->recordColumnNameChange($sheetName, $columnDetails['original_name'], $columnDetails['name']);
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
      } catch (WAException $ex) {
         array_push($this->errors, $ex);
         $this->healthy = false;
         $this->lH->log(1, $this->TAG, "Unable to modify column in workflow with instance id = '{$this->instanceId}'");
      }
      
      $this->setIsProcessing(false);
      $this->cacheIsProcessing();
      $this->cacheErrors();
      $this->cacheHealth();
      return $savePoint;
   }
   
   /**
    * This function modifies the workflow name
    * 
    * @param string $name  The new workflow name
    */
   public function modifyName($newName){
      $this->lH->log(3, $this->TAG, "Modifying column in workflow with id = '{$this->instanceId}'. Sheet name = '$sheetName' and column details = ".  print_r($columnDetails, true));
      //try saving the workflow instance first
      $description = "Rename to $newName";
      $savePoint = $this->save($description);
      if($this->healthy == true) {
         try {
            $query = "update ".Workflow::$TABLE_META_DOCUMENT." set workflow_name = ".$this->database->quote($newName);
            $this->database->runGenericQuery($query);
         } catch (WAException $ex) {
            array_push($this->errors, $ex);
            $this->healthy = false;
            $this->lH->log(1, $this->TAG, "Could not rename workflow with instance id = '{$this->instanceId}' to '$newName'");
         }
      }
      else {
         array_push($this->errors, new WAException("Unable to rename workflow bacause it wasn't successfully backed up", WAException::$CODE_WF_INSTANCE_ERROR, null));
         $this->healthy = false;
         $this->lH->log(1, $this->TAG, "Unable to rename workflow with id = '{$this->instanceId}' bacause it wasn't successfully backed up");
      }
      
      $this->setIsProcessing(false);
      $this->cacheIsProcessing();
      $this->cacheErrors();
      $this->cacheHealth();
      return $savePoint;
   }
   
   /**
    * This function modifies a sheet
    * 
    * @param array $sheetDetails
    */
   public function modifySheet($sheetDetails) {
      $this->lH->log(3, $this->TAG, "Modifying sheet in workflow with id = '{$this->instanceId}'. Sheet details = ".  print_r($sheetDetails, true));
      $savePoint = null;
      try {
         if(array_key_exists("original_name", $sheetDetails)
                  && array_key_exists("name", $sheetDetails)
                  && array_key_exists("delete", $sheetDetails)) {
            $description = "Modify ".$sheetDetails['original_name'];
            if($sheetDetails['delete'] == true) {
               $description = "Delete ".$sheetDetails['original_name'];
            }
            $savePoint = $this->save($description);
            $this->truncateWorkflow();
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
                     $this->recordSheetNameChange($sheetDetails['original_name'], $sheetDetails['name']);
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
      } catch (WAException $ex) {
         array_push($this->errors, $ex);
         $this->healthy = false;
         $this->lH->log(1, $this->TAG, "Unable to modify sheet in workflow with instance id = '{$this->instanceId}'");
      }
      
      $this->setIsProcessing(false);
      $this->cacheIsProcessing();
      $this->cacheErrors();
      $this->cacheHealth();
      return $savePoint;
   }
   
   /**
    * This function get's data from the database
    * 
    * @param String $filter   The filter to be used for getting the data. Can be 'all', 'query' or 'prefix'
    * @param type $query      If filter is query, the corresponding query to be used to get the data
    * @param type $prefix     If filter is prefix, the prefix to be used to select columns to be fetched
    * 
    * @return string A url to the data file
    */
   public function getData($filter, $query = null, $prefix = null) {
      $url = "";
      if($this->healthy == true
            && $this->database != null) {
         $sheetData = array();
         if($filter == "all" || $filter == "prefix"){//user wants all the data
            if($filter == "all") $prefix = array();
            $dataTables = WASheet::getAllWASheets($this->config, $this->instanceId, $this->database);
            //get data from all the sheets
            for($index = 0; $index < count($dataTables); $index++) {
               $currSheet = new WASheet($this->config, $this->database, null, $dataTables[$index]);
               $currSheetData = $currSheet->getDatabaseData($prefix);
               if($filter == "all" || ($filter == "prefix" && count($currSheetData) > 0)) {//do not add sheet to list of sheets to be added to the excel file if we are filtering based on prefix and no data fetched from the sheet
                  $sheetData[$currSheet->getSheetName()] = $currSheetData;
               }
               else {
                  $this->lH->log(2, $this->TAG, "No data available in ".$currSheet->getSheetName().". Not adding sheet to excel file");
               }
            }
         }
         else if($filter == "query") {
            if($query != null) {
               $sheetData['data'] = $this->database->runGenericQuery($query, true);
               $sheetData['meta'] = array(array("query" => $query));
            }
            else {
               array_push($this->errors, new WAException("Query not provided for fetching data from sheet", WAException::$CODE_WF_PROCESSING_ERROR, null));
               $this->healthy = false;
               $this->lH->log(1, $this->TAG, "Unable to get data because no query was provided for fetching data");
            }
         }
         //save the fetched data in a file
         $name = "";
         $rand = Workflow::generateRandomID(5);
         if($filter == "all") $name = $this->instanceId."_".$rand;
         else if($filter == "prefix") $name = $this->instanceId."_prefix_".$rand;
         else if($filter == "query") $name = $this->instanceId."_query_".$rand;
         $relativeURL = WAExcelFile::saveAsExcelFile($this->config, $this->instanceId, $this->workingDir, $this->database, $name, $sheetData);
         return "http://".$_SERVER["HTTP_HOST"].str_replace("..", "", $relativeURL);
      }
      else {
         array_push($this->errors, new WAException("Unable to get data because the workflow is unhealthy", WAException::$CODE_WF_INSTANCE_ERROR, null));
         $this->healthy = false;
         $this->lH->log(1, $this->TAG, "Unable to get data because the workflow with instance id = '{$this->instanceId}' is unhealthy");
      }
      return $url;
   }
   
   /**
    * This function returns a list of all the data files associated to the current
    * workflow
    * 
    * @return Array The list of files
    */
   public function getRawDataFiles() {
      $dataFiles = array();
      for($index = 0; $index < count($this->files); $index++) {
         $currFile = $this->files[$index];
         if($currFile->getType() == WAFile::$TYPE_RAW) {
            array_push($dataFiles, $currFile);
         }
      }
      return $dataFiles;
   }
   
   /**
    * This function dumps data from the data files into the database
    */
   public function dumpData() {
      $savePoint = $this->save("Data dump");
      if($this->files != null
              && is_array($this->files)
              && count($this->files) > 0
              && $this->healthy == true){
         //get only the data files
         $dataFiles = $this->getRawDataFiles();
         if(count($dataFiles) > 0) {
            //delete any existing data table in the database
            for($index = 0; $index < count($dataFiles); $index++) {
               $this->lH->log(3, $this->TAG, "Now dumping ".$dataFiles[$index]->getFSLocation());
               try {
                  $excelFile = new WAExcelFile($dataFiles[$index]);
                  if($index == 0) {
                     $excelFile->dumpData(true);//first delete existing data before dumping
                  }
                  else {
                     $excelFile->dumpData(false);//don't delete existing data before dumping
                  }
               } catch (WAException $ex) {
                  $this->healthy = false;
                  $this->lH->log(1, $this->TAG, "Could not dump data from the data file for workflow with id = {$this->instanceId} to MySQL");
                  array_push($this->errors, $ex);
               }
            }
         }
         else {
            $this->healthy = false;
            $this->lH->log(1, $this->TAG, "Workflow with instance id = '{$this->instanceId}' does not have any linked data files");
            array_push($this->errors, new WAException("Workflow not linked to any data file", WAException::$CODE_WF_INSTANCE_ERROR, null));
         }
      }
      else {
         $this->healthy = false;
         array_push($this->errors, new WAException("Unable to dump data because the workflow wasn't initialized correctly", WAException::$CODE_WF_INSTANCE_ERROR, null));
         $this->healthy = false;
         $this->lH->log(1, $this->TAG, "Unable to dump data because workflow with id = '{$this->instanceId}' wasn't initialized correctly");
      }
      
      $this->setIsProcessing(false);
      $this->cacheIsProcessing();
      $this->cacheErrors();
      $this->cacheHealth();
      return $savePoint;
   }
   
   /**
    * This function gets the schema details for all data storing tables from the
    * MySQL database storing data for this workflow
    * 
    * @return Array Array with schema data for all the data storing MySQL tables
    */
   public function getSchema() {
      $this->lH->log(3, $this->TAG, "Getting schema for workflow with id = '{$this->instanceId}'");
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
    * This function resolves trivial differences between this workflow and the 
    * one provided
    * 
    * @param type $newName       The name to be given to the new merged schema
    * @param type $workflow2Id   The Instance id for the second workflow
    * @return type
    */
   public function resolveVersionSchemaDiff($newName, $workflow2Id){
      $workflow2 = new Workflow($this->config, null, $this->currUser, $workflow2Id);
      $savePoint = $this->save("Resolve trivial version differences with ".$workflow2->getWorkflowName());
      $instanceId2 = $this->generateInstanceID();
      $workingDir2 = Workflow::$WORKFLOW_ROOT_DIR.$instanceId2;
      //create a workflow with the schema before the merge
      $status = Workflow::copyWorkflow($this->config, $this->instanceId, $instanceId2, $this->workingDir, $workingDir2, $savePoint);
      if($this->healthy == TRUE && $status['healthy'] == true){
         try {
            //truncate data from all the data tables
            $this->truncateWorkflow();
            $rawDiff = Workflow::getVersionDifference($this->currUser, $this->config, $this->instanceId, $workflow2Id, "trivial");
            $diff = $rawDiff['diff'];
            $this->lH->log(4, $this->TAG, "Diff = ".print_r($diff, true));
            $diffCount = count($diff);
            for($index = 0; $index < $diffCount; $index++) {
               $currDiff = $diff[$index];
               if($currDiff['level'] == "sheet" && $currDiff['type'] == "missing"  && is_null($currDiff[$this->instanceId])){
                  $missingSheet = $currDiff[$workflow2Id];
                  $sheetObject = new WASheet($this->config, $this->database, -1, $missingSheet['name']);//for excel object, put -1 instead of null so as to prevent the object from getting column details from the database
                  $sheetObject->saveAsMySQLTable(FALSE, $missingSheet['columns']);
                  $this->lH->log(3, $this->TAG, "Adding sheet '{$missingSheet['name']}' to {$this->instanceId}");
                  $this->lH->log(4, $this->TAG, "New sheet details".print_r($missingSheet['name'], true));
               }
               else if($currDiff['level'] == "column" && $currDiff['type'] == "conflict") {
                  //check what trivial change should be made (can either be length or nullable)
                  $column = $currDiff[$this->instanceId];
                  if($currDiff[$this->instanceId]['nullable'] != $currDiff[$workflow2Id]['nullable'] && $currDiff[$this->instanceId]['nullable'] == false) {//nullable value does not match
                     $column['nullable'] = true;
                  }
                  if($currDiff[$this->instanceId]['length'] < $currDiff[$workflow2Id]['length']) {//lenght of the column in this workflow is less than that of the reference workflow
                     $column['length'] = $currDiff[$workflow2Id]['length'];
                  }
                  $sheetObject = new WASheet($this->config, $this->database, null, $currDiff['sheet']);
                  $column['original_name'] = $column['name'];
                  $column['delete'] = false;
                  $sheetObject->alterColumn($column);
                  $this->lH->log(3, $this->TAG, "Resolving trivial conflict in '{$column['sheet']} - {$column['name']}' in {$this->instanceId}");
                  $this->lH->log(4, $this->TAG, "Column details".print_r($column, true));
               }
               else if($currDiff['level'] == "column" && $currDiff['type'] == "missing" && is_null($currDiff[$this->instanceId])) {
                  $currDiff[$workflow2Id]['nullable'] = true;
                  $this->database->runAddColumnQuery($currDiff['sheet'], $currDiff[$workflow2Id]);
                  $this->lH->log(3, $this->TAG, "Creating new column '{$currDiff['sheet']} - {$currDiff[$workflow2Id]['name']}' in {$this->instanceId}");
                  $this->lH->log(4, $this->TAG, "Column details".print_r($currDiff[$workflow2Id], true));
               }
               else if($currDiff['level'] == "column" && $currDiff['type'] == "missing" && is_null($currDiff[$workflow2Id])) {
                  $column = $currDiff[$this->instanceId];
                  $column['nullable'] = true;
                  $sheetObject = new WASheet($this->config, $this->database, null, $currDiff['sheet']);
                  $column['original_name'] = $column['name'];
                  $column['delete'] = false;
                  $sheetObject->alterColumn($column);
                  $this->lH->log(3, $this->TAG, "Resolving trivial conflict in '{$column['sheet']} - {$column['name']}' in {$this->instanceId}");
                  $this->lH->log(4, $this->TAG, "Column details".print_r($column, true));
               }
            }
            $this->copyData($workflow2);
            //rename the workflow
            $savePoint = $this->modifyName($newName);
         } catch (WAException $ex) {
            array_push($this->errors, $ex);
            $this->healthy = false;
            $this->lH->log(1, $this->TAG, "Unable to resolve trivial version changes because of an error");
         }
      }
      else {
         $this->errors = array_merge($this->errors, $status['errors']);
         array_push($this->errors, new WAException("Unable to resolve trivial version changes because workflow is unhealthy", WAException::$CODE_WF_INSTANCE_ERROR, null));
         $this->healthy = false;
         $this->lH->log(1, $this->TAG, "Unable to resolve trivial version changes because workflow with id = '{$this->instanceId}' is unhealthy");
      }
      return $savePoint;
   }
   
   private function copyData($originWorkflow, $originMergeKey = null, $destMergeKey = null) {
      //dump variable changes from workflow2
      try {
         //copy the changes
         $originMetaChanges = $originWorkflow->getMetaChanges();
         foreach ($originMetaChanges as $currChange) {
            $metaChangesKeys = array_keys($currChange);
            foreach($metaChangesKeys as $currKey) {
               $currChange[$currKey] = "'".$currChange[$currKey]."'";
            }
            $this->database->runInsertQuery(Workflow::$TABLE_META_CHANGES, $currChange);
         }
         
         //copy the raw data files from workflow_2 to this workflow
         $dataFiles = $originWorkflow->getRawDataFiles();
         $mergeKeyFiles = array();
         for($index = 0; $index < count($dataFiles); $index++) {
            $name = basename($dataFiles[$index]->getFSLocation());
            $mergeSheet = null;
            $mergeColumn = null;
            if($destMergeKey != null) {
               $mergeSheet = $destMergeKey['sheet'];
               $mergeColumn = $destMergeKey['column'];
            }
            $this->addRawDataFile($dataFiles[$index]->getFSLocation(), $name, $mergeSheet, $mergeColumn);
            if($originMergeKey != null && $destMergeKey != null && in_array($name, $mergeKeyFiles) == false) {//check if current file has a recording for the merge change
               //record the column first before the sheet
               $this->recordColumnNameChange($originMergeKey['sheet'], $originMergeKey['column'], $destMergeKey['column'], array($name));
               $this->recordSheetNameChange($originMergeKey['sheet'], $destMergeKey['sheet'], array($name));
               $mergeKeyFiles[] = $name;
            }
         }
         //copy the notes
         $notes = $originWorkflow->getAllNotes(false);
         foreach($notes as $currNote) {
            $noteKeys = array_keys($currNote);
            foreach($noteKeys as $currKey) {
               $currNote[$currKey] = "'".$currNote[$currKey]."'";
            }
            $this->database->runInsertQuery(Workflow::$TABLE_META_NOTES, $currNote);
         }
      } catch (WAException $ex) {
         throw new WAException("Unable to copy metadata from {$originWorkflow->getInstanceId()} to {$targetWorkflow->getInstanceId()}", WAException::$CODE_WF_PROCESSING_ERROR, $ex);
      }
   }
   
   /**
    * This function deletes all the data in the workflow's database
    * 
    * @throws WAException
    */
   private function truncateWorkflow() {
      try {
         $dataTables = WASheet::getAllWASheets($this->config, $this->instanceId, $this->database);
         for($index = 0; $index < count($dataTables); $index++) {
            $query = "truncate table ".Database::$QUOTE_SI.$dataTables[$index].Database::$QUOTE_SI." cascade";
            $this->database->runGenericQuery($query);
         }
      } catch (WAException $ex) {
         throw new WAException("Was unable to truncate the database", WAException::$CODE_DB_QUERY_ERROR, $ex);
      }
   }
   
   /**
    * This function resolves any merge differences between this workflow and the
    * workflow identified by $workflow2Id. The difference between this function and
    * resolveTrivialSchemaDiff is that this function should be called when working with
    * schemas that are entirely different from each other
    * @param type $newName
    * @param type $workflow2Id
    */
   public function resolveMergeDiff($newName, $workflow2Id, $key1, $key2) {
      $workflow2 = new Workflow($this->config, null, $this->currUser, $workflow2Id);
      $savePoint = $this->save("Resolve trivial merge differences with ".$workflow2->getWorkflowName());
      $instanceId2 = $this->generateInstanceID();
      $workingDir2 = Workflow::$WORKFLOW_ROOT_DIR.$instanceId2;
      //create a workflow with the schema before the merge
      $status = Workflow::copyWorkflow($this->config, $this->instanceId, $instanceId2, $this->workingDir, $workingDir2, $savePoint);
      if($this->healthy == TRUE && $status['healthy'] == true){
         try {
            //truncate data from all the data tables
            $this->truncateWorkflow();
            $rawDiff = Workflow::getMergeDifferences($this->currUser, $this->config, $this->instanceId, $workflow2Id, "all", $key1, $key2);//get all the merge differences
            $this->lH->log(4, $this->TAG, "Merge diff = ".print_r($rawDiff, true));
            $diff = $rawDiff['diff'];
            $diffCount = count($diff);
            for($index = 0; $index < $diffCount; $index++) {//only work on differences that are not conflicts
               $currDiff = $diff[$index];
               if($currDiff['level'] == "sheet" && $currDiff['type'] == "missing" && is_null($currDiff[$this->instanceId])){
                  $missingSheet = $currDiff[$workflow2Id];
                  $sheetObject = new WASheet($this->config, $this->database, -1, $missingSheet['name']);//for excel object, put -1 instead of null so as to prevent the object from getting column details from the database
                  $sheetObject->saveAsMySQLTable(FALSE, $missingSheet['columns']);
                  $this->lH->log(3, $this->TAG, "Adding sheet '{$missingSheet['name']}' to {$this->instanceId}");
                  $this->lH->log(4, $this->TAG, "New sheet details".print_r($missingSheet, true));
               }
               else if($currDiff['level'] == "column" && $currDiff['type'] == "missing" && is_null($currDiff[$this->instanceId])) {
                  $currDiff[$workflow2Id]['nullable'] = true;
                  $this->database->runAddColumnQuery($currDiff['sheet'][$this->instanceId], $currDiff[$workflow2Id]);
                  $this->lH->log(3, $this->TAG, "Creating new column '{$currDiff['sheet'][$this->instanceId]} - {$currDiff[$workflow2Id]['name']}' in {$this->instanceId}");
                  $this->lH->log(4, $this->TAG, "Column details".print_r($currDiff[$workflow2Id], true));
               }
               else if($currDiff['level'] == "column" && $currDiff['type'] == "missing" && is_null($currDiff[$workflow2Id])) {
                  $column = $currDiff[$this->instanceId];
                  $column['nullable'] = true;
                  $sheetObject = new WASheet($this->config, $this->database, null, $currDiff['sheet'][$this->instanceId]);
                  $column['original_name'] = $column['name'];
                  $column['delete'] = false;
                  $sheetObject->alterColumn($column);
                  $this->lH->log(3, $this->TAG, "Resolving trivial conflict in '{$column['sheet']} - {$column['name']}' in {$this->instanceId}");
                  $this->lH->log(4, $this->TAG, "Column details".print_r($column, true));
               }
            }
            $this->copyData($workflow2, $key2, $key1);
            //rename the workflow
            $savePoint = $this->modifyName($newName);
         } catch (WAException $ex) {
            array_push($this->errors, $ex);
            $this->healthy = false;
            $this->lH->log(1, $this->TAG, "Unable to resolve trivial changes because of an error");
         }
      }
      else {
         $this->errors = array_merge($this->errors, $status['errors']);
         array_push($this->errors, new WAException("Unable to resolve trivial merge changes because workflow is unhealthy", WAException::$CODE_WF_INSTANCE_ERROR, null));
         $this->healthy = false;
         $this->lH->log(1, $this->TAG, "Unable to resolve trivial merge changes because workflow with id = '{$this->instanceId}' is unhealthy");
      }
      return $savePoint;
   }
   
   /**
    * This function returns an array of all the data in the changes meta table
    * 
    * @return Array An array with all the meta changes
    * @throws WAException
    */
   public function getMetaChanges() {
      try {
         $query = "select change_time, submitted_by, change_type, original_sheet, original_column, current_sheet, current_column, file from ".Workflow::$TABLE_META_CHANGES;
         $result = $this->database->runGenericQuery($query, true);
         if(is_array($result)) {
            return $result;
         }
      } catch (WAException $ex) {
         array_push($this->errors, $ex);
         $this->healthy = false;
         throw new WAException($message, WAException::$CODE_WF_PROCESSING_ERROR, $ex);
      }
   }
   
   /**
    * This function adds a foreign key to the workflow
    * 
    * @param type $sheet
    * @param type $columns
    * @param type $referencing
    */
   public function addForeignKey($sheet, $columns, $referencing) {
      $savePoint = $this->save("Add foreign key ".$sheet."(".implode(",", $columns).")");
      if($this->healthy == true) {
         try {
            $this->database->addForeignKey($sheet, $columns, $referencing['sheet'], $referencing['columns']);
         } catch (WAException $ex) {
            array_push($this->errors, $ex);
            $this->healthy = false;
            $this->lH->log(1, $this->TAG, "Unable to add a foreign key to workflow with id = '{$this->instanceId}'");
         }
      }
      $this->setIsProcessing(false);
      $this->cacheIsProcessing();
      $this->cacheErrors();
      $this->cacheHealth();
      return $savePoint;
   }
   
   /**
    * This function gets all foreign keys in the the database corresponding to this
    * workflow
    */
   public function getForeignKeys() {
      if($this->healthy == true
              && $this->database != null
              && $this->instanceId != null
              && $this->database->getDatabaseName() == $this->instanceId) {
         try {
            $tables = WASheet::getAllWASheets($this->config, $this->instanceId, $this->database);
            $return = array();
            for($index = 0; $index < count($tables); $index++) {
               $currFKeys = $this->database->getTableForeignKeys($tables[$index]);
               $fKeyKeys = array_keys($currFKeys);
               $this->lH->log(4, $this->TAG, "Curr fK = ".  print_r($currFKeys, true));
               $formatted = array();
               for($keyIndex = 0; $keyIndex < count($fKeyKeys); $keyIndex++) {
                  $this->lH->log(4, $this->TAG, "Foreign keys = ".print_r($currFKeys[$fKeyKeys[$keyIndex]], true));
                  $formatted[] = array(
                      "ref_sheet" => $currFKeys[$fKeyKeys[$keyIndex]]['ref_table'], 
                      "ref_columns" => $currFKeys[$fKeyKeys[$keyIndex]]['ref_columns'],
                      "columns" => $currFKeys[$fKeyKeys[$keyIndex]]['columns']
                  );
               }
               if(count($formatted) > 0) {
                  $return[$tables[$index]] = $formatted;
               }
            }
            return $return;
         } catch (WAException $ex) {
            array_push($this->errors, $ex);
            $this->healthy = false;
            $this->lH->log(1, $this->TAG, "Unable to get foreign keys because workflow with id = '{$this->instanceId}' wasn't initialized correctly");
         }
      }
      else {
         array_push($this->errors, new WAException("Unable to get foreign keys because workflow instance wasn't initialized correctly", WAException::$CODE_WF_INSTANCE_ERROR, null));
         $this->healthy = false;
         $this->lH->log(1, $this->TAG, "Unable to get foreign keys because workflow with id = '{$this->instanceId}' wasn't initialized correctly");
      }
   }
   
   /**
    * This function returns the data corresponding to the provided sheet
    * @param type $sheetName
    */
   public function getSheetData($sheetName) {
      if($this->files != null
              && is_array($this->files)) {
         try {
            $dataFiles = $this->getRawDataFiles();
            if(count($dataFiles) > 0) {
               //check get data from the first file that has the sheet
               foreach($dataFiles as $currFile) {
                  $excelFile = new WAExcelFile($currFile);
                  if($excelFile->isSheetInFile($sheetName)) {
                     $data = $excelFile->getSheetData($sheetName);
                     return $data;
                  }
               }
            }
            else {//should not happen
               array_push($this->errors, new WAException("Workflow does not have data files", WAException::$CODE_WF_INSTANCE_ERROR, null));
               $this->healthy = false;
               $this->lH->log(1, $this->TAG, "Workflow with id = '{$this->instanceId}'does not have data files");
            }
         } catch (WAException $ex) {
            array_push($this->errors, $ex);
            $this->healthy = false;
            $this->lH->log(1, $this->TAG, "Could not get data for Workflow with id = '{$this->instanceId}'");
         }
      }
      else {
         array_push($this->errors, new WAException("Unable to get sheet data because workflow instance wasn't initialized correctly", WAException::$CODE_WF_INSTANCE_ERROR, null));
         $this->healthy = false;
         $this->lH->log(1, $this->TAG, "Unable to get sheet data for '$sheetName' because workflow with id = '{$this->instanceId}' wasn't initialized correctly");
      }
   }
   
   /**
    * This function gets all the save points for this instance.
    * Function is static inorder to avoid importing messed up workflow context if
    * an error occurres in the workflow. Allows clients to rollback to previous 
    * savepoints event if workflow health is bad
    */
   public static function getSavePoints($config, $instanceId) {
      include_once 'mod_wa_database.php';
      include_once 'mod_wa_file.php';
      include_once 'mod_wa_exception.php';
      include_once 'mod_log.php';
      
      $errors = array();
      $healthy = true;
      $formatted = array();//list of formatted save points
      $lH = new LogHandler("./");
      $lH->log(3, "waworkflow_static", "Getting save points for workflow with id = '{$instanceId}'");
      $database = new Database($config, $instanceId);
      if($database != null
              && $instanceId != null
              && $instanceId == $database->getDatabaseName()) {
         $workflowDetails = Workflow::getWorkflowDetails($config, $instanceId);
         $workingDir = $workflowDetails['working_dir'];
         try {
            $savePointFiles = WAFile::getAllSavePointFiles($config, $instanceId, $database, $workingDir);
            for($index = 0; $index < count($savePointFiles); $index++) {
               $currFile = $savePointFiles[$index];
               $currFileDetails = $currFile->getFileDetails();
               
               $currFileDetails["creator"] = ODKWorkflowAPI::explodeUserUUID($currFileDetails['creator']);
               unset($currFileDetails["type"]);
               unset($currFileDetails['time_last_modified']);
               
               array_push($formatted, $currFileDetails);
            }
         } catch (WAException $ex) {
            array_push($errors, $ex);
            $healthy = false;
            $lH->log(1, "waworkflow_static", "Unable to get save point files for workflow with id = '{$instanceId}'");
         }
      }
      else {
         array_push($errors, new WAException("Unable to get data tables because workflow instance wasn't initialized correctly", WAException::$CODE_WF_INSTANCE_ERROR, null));
         $healthy = false;
         $lH->log(1, "waworkflow_static", "Unable to get data tables because workflow with id = '{$instanceId}' wasn't initialized correctly");
      }
      
      $result = array(
          "save_points" => $formatted,
          "status" => Workflow::getStatusArray($healthy, $errors)
      );
      return $result;
   }
   
   /**
    * This function copies the contents of the first workflow to the new workflow
    * 
    * @param array $config          The repository_config file
    * @param type $oldWorkflowId    The id of the existing workflow
    * @param type $newWorkflowId    The id of the new workflow
    * @param type $oldWorkingDir    The working directory for the existing workflow
    * @param type $newWorkingDir    The working directory for the new workflow
    * @param type $savePoint        The save point in the existing workflow for which to dump into the new workflow
    * 
    * @return array  An array containing the status after the copy
    */
   public static function copyWorkflow($config, $oldWorkflowId, $newWorkflowId, $oldWorkingDir, $newWorkingDir, $savePoint) {
      include_once 'mod_wa_database.php';
      include_once 'mod_wa_exception.php';
      include_once 'mod_log.php';
      include_once 'mod_wa_file.php';
      
      $errors = array();
      $healthy = true;
      $lH = new LogHandler("./");
      $lH->log(3, "waworkflow_static", "Updating the workflow details for '$oldWorkflowId' to '$newWorkflowId'");
      try {
         WAFile::copyDir($oldWorkingDir, $newWorkingDir);
         $database = new Database($config);
         $database->restore($newWorkflowId, WAFile::getSavePointFilePath($oldWorkingDir, $savePoint), false);//create the database for $newWorkflowId using $savePoint
         $database2 = new Database($config, $newWorkflowId);
         $query = "update ".Workflow::$TABLE_META_DOCUMENT." set workflow_id = '$newWorkflowId', working_dir = '$newWorkingDir', time_created = '".Database::getMySQLTime()."'";
         $database2->runGenericQuery($query);
      } catch (WAException $ex) {
         array_push($errors, $ex);
         $healthy = false;
         $lH->log(1, "waworkflow_static", "Unable to copy workflow from '{$oldWorkflowId}' to '{$newWorkflowId}'".  print_r($ex, true));
      }
      
      return Workflow::getStatusArray($healthy, $errors);
   }
   
   /**
    * This function returns schema differences between the provided workflows
    * 
    * @param string $userUUID    The current user's UUID
    * @param array $config       The repository config file
    * @param string $workflowID1 The instance id for the first workflow
    * @param string $workflowID2 The instance id for the second workflow
    * @param string $diffType    Can either be 'all', 'trivial' or 'non_trivial'
    * 
    * @return Array  An array containing the differences plus status
    */
   public static function getVersionDifference($userUUID, $config, $workflowID1, $workflowID2, $diffType = "all") {
      include_once 'mod_wa_database.php';
      include_once 'mod_wa_exception.php';
      include_once 'mod_log.php';
      
      $errors = array();
      $healthy = true;
      $diff = array();
      $lH = new LogHandler("./");
      $lH->log(3, "waworkflow_static", "Determining schema difference between '{$workflowID1}' and '{$workflowID2}'");
      try {
         $workflow1 = new Workflow($config, null, $userUUID, $workflowID1);
         $schema1 = $workflow1->getSchema();
         $lH->log(4, "waworkflow_static", "Schema1 = ".print_r($schema1, true));
         $workflow2 = new Workflow($config, null, $userUUID, $workflowID2);
         $schema2 = $workflow2->getSchema();
         $lH->log(4, "waworkflow_static", "Schema2 = ".print_r($schema2, true));
         $status1 = $workflow1->getCurrentStatus();
         $status2 = $workflow2->getCurrentStatus();
         if($status1['healthy'] == true && $status2['healthy'] == true){
            $sheet1Indexes = array();//an array containing indexes of all the sheets in workflow 1
            $sheet2Indexes = array();//an array containing indexes of all the sheets in workflow 2
            $noSheets = count($schema1['sheets']);
            for($index = 0; $index < $noSheets; $index++){
               $sheet1Indexes[$schema1['sheets'][$index]['name']] = $index;
            }
            $lH->log(4, "waworkflow_static", "Sheet 1 indexes = ".print_r($sheet1Indexes, true));
            $noSheets = count($schema2['sheets']);
            for($index = 0; $index < $noSheets; $index++){
               $sheet2Indexes[$schema2['sheets'][$index]['name']] = $index;
            }
            $lH->log(4, "waworkflow_static", "Sheet 2 indexes = ".print_r($sheet2Indexes, true));
            
            $schema1SheetNames = array_keys($sheet1Indexes);
            $schema2SheetNames = array_keys($sheet2Indexes);
            $commonSheetNames = array();
            //check which sheets are in workflow1 and not in workflow2
            $noNames = count($schema1SheetNames);
            for($index = 0; $index < $noNames; $index++){
               if(in_array($schema1SheetNames[$index], $schema2SheetNames) == false){
                  $lH->log(4, "waworkflow_static", "Sheet {$schema1SheetNames[$index]} not in $workflowID2");
                  if($diffType == "all" || $diffType == "trivial"){
                     $diff[] = array(
                        "level" => "sheet",
                        "type" => "missing",
                        $workflowID1 => $schema1['sheets'][$sheet1Indexes[$schema1SheetNames[$index]]],
                        $workflowID2 => null
                     );
                  }
               }
               else {
                  $commonSheetNames[] = $schema1SheetNames[$index];
               }
            }
            $lH->log(4, "waworkflow_static", "Common sheet names = ".print_r($commonSheetNames, true));
            //check which sheets are in workflow2 and not in workflow1
            if($diffType == "all" || $diffType == "trivial"){
               $noNames = count($schema2SheetNames);
               for($index = 0; $index < $noNames; $index++){
                  if(in_array($schema2SheetNames[$index], $schema1SheetNames) == false){
                     $lH->log(4, "waworkflow_static", "Sheet {$schema2SheetNames[$index]} not in $workflowID1");
                     $diff[] = array(
                        "level" => "sheet",
                        "type" => "missing",
                        $workflowID1 => null,
                        $workflowID2 => $schema2['sheets'][$sheet2Indexes[$schema2SheetNames[$index]]]
                     );
                  }
               }
            }
            
            //for each of the common sheets
            $noCommonSheets = count($commonSheetNames);
            for($index = 0; $index < $noCommonSheets; $index++){
               $currSheetName = $commonSheetNames[$index];
               $lH->log(4, "waworkflow_static", "Comparing columns in $currSheetName");
               $currSheetIn1 = $schema1['sheets'][$sheet1Indexes[$currSheetName]];
               $currSheetIn2 = $schema2['sheets'][$sheet2Indexes[$currSheetName]];
               
               //get the column indexes
               $col1Indexes = array();
               $col2Indexes = array();
               $colSize = count($currSheetIn1['columns']);
               for($colIndex = 0; $colIndex < $colSize; $colIndex++){
                  $col1Indexes[$currSheetIn1['columns'][$colIndex]['name']] = $colIndex;
               }
               $lH->log(4, "waworkflow_static", "Column1Indexes = ".  print_r($col1Indexes, TRUE));
               $colSize = count($currSheetIn2['columns']);
               for($colIndex = 0; $colIndex < $colSize; $colIndex++){
                  $col2Indexes[$currSheetIn2['columns'][$colIndex]['name']] = $colIndex;
               }
               $lH->log(4, "waworkflow_static", "Column2Indexes = ".  print_r($col2Indexes, TRUE));
               
               $col1Names = array_keys($col1Indexes);
               $col2Names = array_keys($col2Indexes);
               $commonColumnNames = array();
               $colSize = count($col1Names);
               //check which columns are in workflow1 and not workflow2
               for($colIndex = 0; $colIndex < $colSize; $colIndex++){
                  if(in_array($col1Names[$colIndex], $col2Names) == false){
                     $lH->log(4, "waworkflow_static", "{$col1Names[$colIndex]} not in $workflowID2");
                     if($diffType == "all" || $diffType == "trivial"){
                        $diff[] = array(
                           "level" => "column",
                           "type" => "missing",
                           "sheet" => $currSheetName,
                           $workflowID1 => $currSheetIn1['columns'][$col1Indexes[$col1Names[$colIndex]]],
                           $workflowID2 => null
                        );
                     }
                  }
                  else {
                     $commonColumnNames[] = $col1Names[$colIndex];
                  }
               }
               $lH->log(4, "waworkflow_static", "Common column names = ".  print_r($commonColumnNames, TRUE));
               $colSize = count($col2Names);
               //check which columns are in workflow2 and not workflow1
               if($diffType == "all" || $diffType == "trivial"){
                  for($colIndex = 0; $colIndex < $colSize; $colIndex++){
                     if(in_array($col2Names[$colIndex], $col1Names) == false){
                        $lH->log(4, "waworkflow_static", "{$col2Names[$colIndex]} not in $workflowID1");
                        $diff[] = array(
                           "level" => "column",
                           "type" => "missing",
                           "sheet" => $currSheetName,
                           $workflowID1 => null,
                           $workflowID2 => $currSheetIn2['columns'][$col2Indexes[$col2Names[$colIndex]]]
                        );
                     }
                  }
               }
               
               //for each of the common columns, check which ones are different
               
               $colSize = count($commonColumnNames);
               for($colIndex = 0; $colIndex < $colSize; $colIndex++){
                  $currCol1 = $currSheetIn1['columns'][$col1Indexes[$commonColumnNames[$colIndex]]];
                  $lH->log(4, "waworkflow_static", "Current column1 = ".print_r($currCol1,true));
                  $currCol2 = $currSheetIn2['columns'][$col2Indexes[$commonColumnNames[$colIndex]]];
                  $lH->log(4, "waworkflow_static", "Current column2 = ".print_r($currCol2,true));
                  if($diffType == "trivial"){//Trivial cases. Only when length or nullable differ
                     if($currCol1['type'] == $currCol2['type']
                        && $currCol1['default'] == $currCol2['default']
                        && ($currCol1['length'] != $currCol2['length']
                        || $currCol1['nullable'] != $currCol2['nullable'])) {//TODO: not catered for key and present
                        $lH->log(4, "waworkflow_static", "{$currCol2['name']} in $workflowID1 and $workflowID2 differ");
                        $diff[] = array(
                           "level" => "column",
                           "type" => "conflict",
                           "sheet" => $currSheetName,
                           $workflowID1 => $currCol1,
                           $workflowID2 => $currCol2
                        );
                     }
                  }
                  else if($diffType == "non_trivial"){//When type or default value differ
                     if($currCol1['type'] != $currCol2['type']
                        || $currCol1['default'] != $currCol2['default']) {//TODO: not catered for key and present
                        $lH->log(4, "waworkflow_static", "{$currCol2['name']} in $workflowID1 and $workflowID2 differ");
                        $diff[] = array(
                           "level" => "column",
                           "type" => "conflict",
                           "sheet" => $currSheetName,
                           $workflowID1 => $currCol1,
                           $workflowID2 => $currCol2
                        );
                     }
                  }
                  else {//all cases
                     if($currCol1['length'] != $currCol2['length']
                        || $currCol1['nullable'] != $currCol2['nullable']
                        || $currCol1['type'] != $currCol2['type']
                        || $currCol1['default'] != $currCol2['default']) {//TODO: not catered for key and present
                        $lH->log(4, "waworkflow_static", "{$currCol2['name']} in $workflowID1 and $workflowID2 differ");
                        $diff[] = array(
                           "level" => "column",
                           "type" => "conflict",
                           "sheet" => $currSheetName,
                           $workflowID1 => $currCol1,
                           $workflowID2 => $currCol2
                        );
                     }
                  }
               }
            }
         }
         else {
            if($status1['healthy'] == false && $status2['healthy'] == false){
               $error = new WAException("Both workflows are not healthy. Cannot get schema difference", WAException::$CODE_WF_INSTANCE_ERROR);
               $lH->log(1, "waworkflow_static", "Unable to get schema differences between '{$workflowID1}' and '{$workflowID2}' because both workflows are not healthy");
            }
            else if($status1['healthy'] == false){
               $error = new WAException("Workflow with instance id = '$workflowID1' is not healthy. Cannot get schema difference with '$workflowID2'", WAException::$CODE_WF_INSTANCE_ERROR);
               $lH->log(1, "waworkflow_static", "Unable to get schema differences between '{$workflowID1}' and '{$workflowID2}' because '{$workflow1}' is not healthy");
            }
            else if($status2['healthy'] == false){
               $error = new WAException("Workflow with instance id = '$workflowID2' is not healthy. Cannot get schema difference with '$workflowID1'", WAException::$CODE_WF_INSTANCE_ERROR);
               $lH->log(1, "waworkflow_static", "Unable to get schema differences between '{$workflowID1}' and '{$workflowID2}' because '{$workflow2}' is not healthy");
            }
            $healthy = false;
            array_push($errors, $error);
         }
      } catch (WAException $ex) {
         array_push($errors, $ex);
         $healthy = false;
         $lH->log(1, "waworkflow_static", "Unable to get schema differences between '{$workflowID1}' and '{$workflowID2}'");
      }
      $result = array(
         "diff" => $diff,
         "workflow_1" => $workflowID1,
         "workflow_2" => $workflowID2,
         "status" => Workflow::getStatusArray($healthy, $errors)
      );
      return $result;
   }
   
   /**
    * This function returns schema differences between the provided workflows
    * 
    * @param string $userUUID    The current user's UUID
    * @param array $config       The repository config file
    * @param string $workflowID1 The instance id for the first workflow
    * @param string $workflowID2 The instance id for the second workflow
    * @param string $diffType    Can either be 'all', 'trivial' or 'non_trivial'
    * @param Array $mergeSheet1  An array containing the details for the column in the first workflow to be used for merging
    * @param Array $mergeSheet2  An array containing the details for the column in the second workflow to be used for merging
    * 
    * @return Array  An array containing the differences plus status
    */
   public static function getMergeDifferences($userUUID, $config, $workflowID1, $workflowID2, $diffType = "all", $mergeSheet1, $mergeSheet2) {
      include_once 'mod_wa_database.php';
      include_once 'mod_wa_exception.php';
      include_once 'mod_log.php';
      
      $errors = array();
      $healthy = true;
      $diff = array();
      $lH = new LogHandler("./");
      $lH->log(3, "waworkflow_static", "Determining schema difference between '{$workflowID1}' and '{$workflowID2}'");
      try {
         $workflow1 = new Workflow($config, null, $userUUID, $workflowID1);
         $schema1 = $workflow1->getSchema();
         $lH->log(4, "waworkflow_static", "Schema1 = ".print_r($schema1, true));
         $workflow2 = new Workflow($config, null, $userUUID, $workflowID2);
         $schema2 = $workflow2->getSchema();
         $lH->log(4, "waworkflow_static", "Schema2 = ".print_r($schema2, true));
         $status1 = $workflow1->getCurrentStatus();
         $status2 = $workflow2->getCurrentStatus();
         if($status1['healthy'] == true && $status2['healthy'] == true){
            $sheet1Indexes = array();//an array containing indexes of all the sheets in workflow 1
            $sheet2Indexes = array();//an array containing indexes of all the sheets in workflow 2
            $noSheets = count($schema1['sheets']);
            for($index = 0; $index < $noSheets; $index++){
               $sheet1Indexes[$schema1['sheets'][$index]['name']] = $index;
            }
            $lH->log(4, "waworkflow_static", "Sheet 1 indexes = ".print_r($sheet1Indexes, true));
            $noSheets = count($schema2['sheets']);
            for($index = 0; $index < $noSheets; $index++){
               $sheet2Indexes[$schema2['sheets'][$index]['name']] = $index;
            }
            $lH->log(4, "waworkflow_static", "Sheet 2 indexes = ".print_r($sheet2Indexes, true));
            
            $schema1SheetNames = array_keys($sheet1Indexes);
            $schema2SheetNames = array_keys($sheet2Indexes);
            //make sure the merging sheets are fine
            if($mergeSheet1['sheet'] != $mergeSheet2['sheet']) {//the merging sheets have different names
               //check if there is a sheet in the first workflow with the name $mergeSheet2['sheet']
               if(array_search($mergeSheet2['sheet'], $schema1SheetNames) !== false) {//this will most definately lead to a conflict
                  $healthy = false;
                  $error = new WAException("There exists a sheet with the name '{$mergeSheet2['sheet']}' in '{$workflow1->getInstanceId()}'", WAException::$CODE_WF_PROCESSING_ERROR, null);
                  array_push($errors, $error);
               }
               //do the same thing for the reverse
               if(array_search($mergeSheet1['sheet'], $schema2SheetNames) !== false) {//this will most definately lead to a conflict
                  $healthy = false;
                  $error = new WAException("There exists a sheet with the name '{$mergeSheet1['sheet']}' in '{$workflow2->getInstanceId()}'", WAException::$CODE_WF_INSTANCE_ERROR, null);
                  array_push($errors, $error);
               }
            }
            //make sure the first sheet exists in the first workflow
            if(array_search($mergeSheet1['sheet'], $schema1SheetNames) === false) {
               $healthy = false;
               $error = new WAException("'{$mergeSheet1['sheet']}' does not exist in '{$workflow1->getInstanceId()}'", WAException::$CODE_WF_INSTANCE_ERROR, null);
               array_push($errors, $error);
            }
            //do the same thing for the reverse
            if(array_search($mergeSheet2['sheet'], $schema2SheetNames) === false) {
               $healthy = false;
               $error = new WAException("'{$mergeSheet2['sheet']}' does not exist in '{$workflow2->getInstanceId()}'", WAException::$CODE_WF_INSTANCE_ERROR, null);
               array_push($errors, $error);
            }
            if($healthy == true) {
               $commonSheetNames = array();
               //check which sheets are in workflow1 and not in workflow2
               $noNames = count($schema1SheetNames);
               for($index = 0; $index < $noNames; $index++){
                  if(in_array($schema1SheetNames[$index], $schema2SheetNames) == false && $schema1SheetNames[$index] != $mergeSheet1['sheet']){
                     $lH->log(4, "waworkflow_static", "Sheet {$schema1SheetNames[$index]} not in $workflowID2");
                     if($diffType == "all" || $diffType == "trivial"){
                        $diff[] = array(
                           "level" => "sheet",
                           "type" => "missing",
                           $workflowID1 => $schema1['sheets'][$sheet1Indexes[$schema1SheetNames[$index]]],
                           $workflowID2 => null
                        );
                     }
                  }
                  else {
                     $commonSheetNames[] = $schema1SheetNames[$index];
                  }
               }
               $lH->log(4, "waworkflow_static", "Common sheet names = ".print_r($commonSheetNames, true));
               //check which sheets are in workflow2 and not in workflow1
               if($diffType == "all" || $diffType == "trivial"){
                  $noNames = count($schema2SheetNames);
                  for($index = 0; $index < $noNames; $index++){
                     if(in_array($schema2SheetNames[$index], $schema1SheetNames) == false && $schema2SheetNames[$index] != $mergeSheet2['sheet']){
                        $lH->log(4, "waworkflow_static", "Sheet {$schema2SheetNames[$index]} not in $workflowID1");
                        $diff[] = array(
                           "level" => "sheet",
                           "type" => "missing",
                           $workflowID1 => null,
                           $workflowID2 => $schema2['sheets'][$sheet2Indexes[$schema2SheetNames[$index]]]
                        );
                     }
                  }
               }
               //for each of the common sheets
               $noCommonSheets = count($commonSheetNames);
               for($index = 0; $index < $noCommonSheets; $index++){
                  $currSheetName1 = $commonSheetNames[$index];
                  $currSheetName2 = $commonSheetNames[$index];
                  if($currSheetName1 == $mergeSheet1['sheet']) {//we are currently handling the merging sheets
                     $currSheetName2 = $mergeSheet2['sheet'];
                     $currSheetIn1 = $schema1['sheets'][$sheet1Indexes[$mergeSheet1['sheet']]];
                     $currSheetIn2 = $schema2['sheets'][$sheet2Indexes[$mergeSheet2['sheet']]];
                  }
                  else {
                     $currSheetIn1 = $schema1['sheets'][$sheet1Indexes[$currSheetName1]];
                     $currSheetIn2 = $schema2['sheets'][$sheet2Indexes[$currSheetName2]];
                  }
                  $lH->log(4, "waworkflow_static", "Comparing columns in $currSheetName");

                  //get the column indexes
                  $col1Indexes = array();
                  $col2Indexes = array();
                  $colSize = count($currSheetIn1['columns']);
                  for($colIndex = 0; $colIndex < $colSize; $colIndex++){
                     $col1Indexes[$currSheetIn1['columns'][$colIndex]['name']] = $colIndex;
                  }
                  $lH->log(4, "waworkflow_static", "Column1Indexes = ".  print_r($col1Indexes, TRUE));
                  $colSize = count($currSheetIn2['columns']);
                  for($colIndex = 0; $colIndex < $colSize; $colIndex++){
                     $col2Indexes[$currSheetIn2['columns'][$colIndex]['name']] = $colIndex;
                  }
                  $lH->log(4, "waworkflow_static", "Column2Indexes = ".  print_r($col2Indexes, TRUE));

                  $col1Names = array_keys($col1Indexes);
                  $col2Names = array_keys($col2Indexes);
                  //make sure everything is fine with the merging columns
                  if($mergeSheet1['sheet'] == $currSheetName1) {
                     if($mergeSheet1['column'] != $mergeSheet2['column']) {
                        //check if there is a column in the first sheet with the name $mergeSheet2['column']
                        if(array_search($mergeSheet2['column'], $col1Names) !== false) {//this will most definately lead to a conflict
                           $healthy = false;
                           $error = new WAException("There exists a column with the name '{$mergeSheet2['column']}' in '{$workflow1->getInstanceId()}'", WAException::$CODE_WF_PROCESSING_ERROR, null);
                           array_push($errors, $error);
                        }
                        //do the same thing for the reverse
                        if(array_search($mergeSheet1['column'], $col2Names) !== false) {//this will most definately lead to a conflict
                           $healthy = false;
                           $error = new WAException("There exists a sheet with the name '{$mergeSheet1['column']}' in '{$workflow2->getInstanceId()}'", WAException::$CODE_WF_INSTANCE_ERROR, null);
                           array_push($errors, $error);
                        }
                     }
                     //make sure the first column exists in the first sheet
                     if(array_search($mergeSheet1['column'], $col1Names) === false) {
                        $healthy = false;
                        $error = new WAException("'{$mergeSheet1['column']}' does not exist in '$currSheetName1' of '{$workflow1->getInstanceId()}'", WAException::$CODE_WF_INSTANCE_ERROR, null);
                        array_push($errors, $error);
                     }
                     //do the same thing for the reverse
                     if(array_search($mergeSheet2['column'], $col2Names) === false) {
                        $healthy = false;
                        $error = new WAException("'{$mergeSheet2['column']}' does not exist in '$currSheetName2' of '{$workflow2->getInstanceId()}'", WAException::$CODE_WF_INSTANCE_ERROR, null);
                        array_push($errors, $error);
                     }
                  }
                  if($healthy == true) {
                     $commonColumnNames = array();
                     $colSize = count($col1Names);
                     //check which columns are in workflow1 and not workflow2
                     for($colIndex = 0; $colIndex < $colSize; $colIndex++){
                        if(in_array($col1Names[$colIndex], $col2Names) == false){
                           $lH->log(4, "waworkflow_static", "{$col1Names[$colIndex]} not in $workflowID2");
                           if($diffType == "all" || $diffType == "trivial"
                                 && ($currSheetIn1['name'] != $mergeSheet1['sheet'] || ($currSheetIn1['name'] == $mergeSheet1['sheet'] && $currSheetIn1['columns'][$col1Indexes[$col1Names[$colIndex]]]['name'] != $mergeSheet1['column']))){
                              $diff[] = array(
                                 "level" => "column",
                                 "type" => "missing",
                                 "sheet" => array($workflowID1 => $currSheetName1, $workflowID2 => $currSheetName2),
                                 $workflowID1 => $currSheetIn1['columns'][$col1Indexes[$col1Names[$colIndex]]],
                                 $workflowID2 => null
                              );
                           }
                        }
                        else {
                           $commonColumnNames[] = $col1Names[$colIndex];
                        }
                     }
                     $lH->log(4, "waworkflow_static", "Common column names = ".  print_r($commonColumnNames, TRUE));
                     $colSize = count($col2Names);
                     //check which columns are in workflow2 and not workflow1
                     if($diffType == "all" || $diffType == "trivial"){
                        for($colIndex = 0; $colIndex < $colSize; $colIndex++){
                           if(in_array($col2Names[$colIndex], $col1Names) == false 
                                 && ($currSheetIn2['name'] != $mergeSheet2['sheet'] || ($currSheetIn2['name'] == $mergeSheet2['sheet'] && $currSheetIn2['columns'][$col2Indexes[$col2Names[$colIndex]]]['name'] != $mergeSheet2['column']))){
                              $lH->log(4, "waworkflow_static", "{$col2Names[$colIndex]} not in $workflowID1");
                              $diff[] = array(
                                 "level" => "column",
                                 "type" => "missing",
                                 "sheet" => array($workflowID1 => $currSheetName1, $workflowID2 => $currSheetName2),
                                 $workflowID1 => null,
                                 $workflowID2 => $currSheetIn2['columns'][$col2Indexes[$col2Names[$colIndex]]]
                              );
                           }
                        }
                     }
                     //for each of the common columns, check which ones are different
                     $colSize = count($commonColumnNames);
                     for($colIndex = 0; $colIndex < $colSize; $colIndex++){
                        if($mergeSheet1['column'] == $commonColumnNames[$colIndex]) {//currently comparing the merging columns
                           $currCol1 = $currSheetIn1['columns'][$col1Indexes[$mergeSheet1['column']]];
                           $lH->log(4, "waworkflow_static", "Current column1 = ".print_r($currCol1,true));
                           $currCol2 = $currSheetIn2['columns'][$col2Indexes[$mergeSheet2['column']]];
                           $lH->log(4, "waworkflow_static", "Current column2 = ".print_r($currCol2,true));
                           //make sure the two merging columns are not nullable
                           if($currCol1['nullable'] == true) {
                              $healthy = false;
                              $error = new WAException("'{$currCol1['name']}' in '{$workflow1->getInstanceId()}' is nullable", WAException::$CODE_WF_PROCESSING_ERROR, null);
                              array_push($errors, $error);
                           }
                           if($currCol2['nullable'] == true) {
                              $healthy = false;
                              $error = new WAException("'{$currCol2['name']}' in '{$workflow2->getInstanceId()}' is nullable", WAException::$CODE_WF_PROCESSING_ERROR, null);
                              array_push($errors, $error);
                           }
                        }
                        else {
                           $currCol1 = $currSheetIn1['columns'][$col1Indexes[$commonColumnNames[$colIndex]]];
                           $lH->log(4, "waworkflow_static", "Current column1 = ".print_r($currCol1,true));
                           $currCol2 = $currSheetIn2['columns'][$col2Indexes[$commonColumnNames[$colIndex]]];
                           $lH->log(4, "waworkflow_static", "Current column2 = ".print_r($currCol2,true));
                        }
                        if($healthy == true) {
                           if($diffType == "trivial"){//Trivial cases. Only when length or nullable differ
                              if($currCol1['type'] == $currCol2['type']
                                 && $currCol1['default'] == $currCol2['default']
                                 && ($currCol1['length'] != $currCol2['length']
                                 || $currCol1['nullable'] != $currCol2['nullable'])) {//TODO: not catered for key and present
                                 $lH->log(4, "waworkflow_static", "{$currCol2['name']} in $workflowID1 and $workflowID2 differ");
                                 $diff[] = array(
                                    "level" => "column",
                                    "type" => "conflict",
                                    "sheet" => array($workflowID1 => $currSheetName1, $workflowID2 => $currSheetName2),
                                    $workflowID1 => $currCol1,
                                    $workflowID2 => $currCol2
                                 );
                              }
                           }
                           else if($diffType == "non_trivial"){//When type or default value differ
                              if($currCol1['type'] != $currCol2['type']
                                 || $currCol1['default'] != $currCol2['default']) {//TODO: not catered for key and present
                                 $lH->log(4, "waworkflow_static", "{$currCol2['name']} in $workflowID1 and $workflowID2 differ");
                                 $diff[] = array(
                                    "level" => "column",
                                    "type" => "conflict",
                                    "sheet" => array($workflowID1 => $currSheetName1, $workflowID2 => $currSheetName2),
                                    $workflowID1 => $currCol1,
                                    $workflowID2 => $currCol2
                                 );
                              }
                           }
                           else if($diffType == "all") {//all cases
                              if($currCol1['length'] != $currCol2['length']
                                 || $currCol1['nullable'] != $currCol2['nullable']
                                 || $currCol1['type'] != $currCol2['type']
                                 || $currCol1['default'] != $currCol2['default']) {//TODO: not catered for key and present
                                 $lH->log(4, "waworkflow_static", "{$currCol2['name']} in $workflowID1 and $workflowID2 differ");
                                 $diff[] = array(
                                    "level" => "column",
                                    "type" => "conflict",
                                    "sheet" => array($workflowID1 => $currSheetName1, $workflowID2 => $currSheetName2),
                                    $workflowID1 => $currCol1,
                                    $workflowID2 => $currCol2
                                 );
                              }
                           }
                        }
                     }
                  }
               }
            }
         }
         else {
            if($status1['healthy'] == false && $status2['healthy'] == false){
               $error = new WAException("Both workflows are not healthy. Cannot get schema difference", WAException::$CODE_WF_INSTANCE_ERROR);
               $lH->log(1, "waworkflow_static", "Unable to get schema differences between '{$workflowID1}' and '{$workflowID2}' because both workflows are not healthy");
            }
            else if($status1['healthy'] == false){
               $error = new WAException("Workflow with instance id = '$workflowID1' is not healthy. Cannot get schema difference with '$workflowID2'", WAException::$CODE_WF_INSTANCE_ERROR);
               $lH->log(1, "waworkflow_static", "Unable to get schema differences between '{$workflowID1}' and '{$workflowID2}' because '{$workflow1}' is not healthy");
            }
            else if($status2['healthy'] == false){
               $error = new WAException("Workflow with instance id = '$workflowID2' is not healthy. Cannot get schema difference with '$workflowID1'", WAException::$CODE_WF_INSTANCE_ERROR);
               $lH->log(1, "waworkflow_static", "Unable to get schema differences between '{$workflowID1}' and '{$workflowID2}' because '{$workflow2}' is not healthy");
            }
            $healthy = false;
            array_push($errors, $error);
         }
      } catch (WAException $ex) {
         array_push($errors, $ex);
         $healthy = false;
         $lH->log(1, "waworkflow_static", "Unable to get schema differences between '{$workflowID1}' and '{$workflowID2}'");
      }
      $result = array(
         "diff" => $diff,
         "workflow_1" => $workflowID1,
         "workflow_2" => $workflowID2,
         "status" => Workflow::getStatusArray($healthy, $errors)
      );
      return $result;
   }
   
   /**
    * This function returns the username and password to be used by a user to access
    * the specified workflow
    * 
    * @param String $userURI     The user's UUID
    * @param Array $config       repository config file
    * @param type $instanceId    The instance id for the workflow
    * @return Array  An array containing both the status and and the database credentials
    */
   public static function getUserDBCredentials($userURI, $config, $instanceId) {
      include_once 'mod_wa_database.php';
      include_once 'mod_wa_exception.php';
      include_once 'mod_log.php';
      include_once 'mod_wa_sheet.php';
      
      $errors = array();
      $healthy = true;
      $credentials = array();//list of formatted save points
      $lH = new LogHandler("./");
      $lH->log(3, "waworkflow_static", "Getting save points for workflow with id = '{$instanceId}'");
      try {
         $database = new Database($config);
         $query = "select db_username, db_password from clients where uri = ".$database->quote($userURI);
         $result = $database->runGenericQuery($query, TRUE);
         if(is_array($result) && count($result) == 1){
            $db2 = new Database($config, $instanceId);
            $username = $result[0]['db_username'];
            $password = $result[0]['db_password'];
            $sheetNames = WASheet::getAllWASheets($config, $instanceId, $db2);
            //TODO: get all table names
            if($username == null || strlen($username) == 0 || $password == null || strlen($password) == 0) {//user not given a connection password
               $randomUsername = Workflow::$workflowPrefix.Workflow::generateRandomID(10);
               $randomPassword = Workflow::generateRandomID(20);
               $query = "create user $randomUsername with password ".$database->quote($randomPassword);
               $database->runGenericQuery($query);
               $query = "update clients set db_username = ".$database->quote($randomUsername).", db_password = ".$database->quote($randomPassword)." where uri = ".$database->quote($userURI);
               $database->runGenericQuery($query);
               $username = $randomUsername;
               $password = $randomPassword;
            }
            $query = "grant connect on database $instanceId to $username";
            $db2->runGenericQuery($query);
            $query = "grant usage on schema public to $username";
            $db2->runGenericQuery($query);
            $query = "grant select on ".Database::$QUOTE_SI.implode(Database::$QUOTE_SI.",".Database::$QUOTE_SI, $sheetNames).Database::$QUOTE_SI." to $username";
            $db2->runGenericQuery($query);
            $credentials['user'] = $username;
            $credentials['password'] = $password;
            if($config['testbed_dbloc'] == "localhost" || $config['testbed_dbloc'] == "127.0.0.1"){
               $credentials['host'] = $_SERVER['SERVER_ADDR'];
            }
            else {
               $credentials['host'] = $config['testbed_dbloc'];
            }
         }
      } catch (WAException $ex) {
         array_push($errors, $ex);
         $healthy = false;
         $lH->log(1, "waworkflow_static", "Unable to get database credentails for user $userURI on workflow '{$instanceId}'");
      }
      
      $result = array(
          "credentials" => $credentials,
          "status" => Workflow::getStatusArray($healthy, $errors)
      );
      return $result;
   }
   
   public static function getAllMetaTables() {
      include_once 'mod_wa_file.php';
      $tables = array(
         Workflow::$TABLE_META_CHANGES,
         Workflow::$TABLE_META_VAR_NAMES,
         Workflow::$TABLE_META_ACCESS,
         Workflow::$TABLE_META_DOCUMENT,
         Workflow::$TABLE_META_ERRORS,
         Workflow::$TABLE_META_NOTES,
         WAFile::$TABLE_META_FILES
      );
      return $tables;
   }
   
   /**
    * This function returns an array containing details of workflows that the
    * specified user has access to
    * 
    * @param array   $config  repository_config object to be used
    * @param string  $user    user we are getting access for
    * @param boolean $admin   set to TRUE if you want to get all the workflows
    */
   public static function getUserWorkflows($config, $user, $admin) {
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
         if($admin == true) {
            $lH->log(3, "static_workflow", "User '$user' is admin. Listing all the workflows");
         }
         $result = $database->getDatabaseNames();
         if($result !== false){
            $accessibleDbs = array();//array to store details for all the databases user has access to
            $lH->log(4, "static_workflow","All the databases = ".print_r($result, true));
            for($index = 0; $index < count($result); $index++) {
               //$currDbName = $result[$index]['Database'];
               $currDbName = $result[$index];
               //check if current database qualifies to store workflow details
               
               $newDatabase = new Database($config, $currDbName);
               
               $tableNames = $newDatabase->getTableNames($currDbName);
               
               $metaTables = 0;
               $allMetaTables = Workflow::getAllMetaTables();
               if($tableNames !== false) {
                  //check each of the table names to see if the meta tables exist
                  for($tIndex = 0; $tIndex < count($tableNames); $tIndex++) {
                     if(in_array($tableNames[$tIndex], $allMetaTables)== true) {
                        $metaTables++;
                     }
                  }
               }
               
               if($metaTables == count($allMetaTables)) {//database has all the meta tables
                  $lH->log(3, "static_workflow", "'$currDbName' complies to the DMP Structure");
                  //check whether the user has access to this database
                  $query = "select *"
                       . " from ".Database::$QUOTE_SI.Workflow::$TABLE_META_ACCESS.Database::$QUOTE_SI
                       . " where ".Database::$QUOTE_SI."user_granted".Database::$QUOTE_SI." = '$user'";
                  $access = $newDatabase->runGenericQuery($query, true);
                  $lH->log(3, "static_workflow", "access = ''$access' and access = ".print_r($access, true));
                  if($admin == true || count($access) > 0) {
                     try {
                        $details = Workflow::getWorkflowDetails($config, $currDbName);
                        array_push($accessibleDbs, $details);
                     } catch (WAException $ex) {
                        $jsonReturn['status'] = Workflow::getStatusArray(false, array($ex));
                        return $jsonReturn;  
                     }
                  }
                  else {
                     $lH->log(4, "static_workflow", "User '$user' does not have access to '$currDbName'");
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
            $jsonReturn['status'] = Workflow::getStatusArray(false, array(new WAException("Unable to check if user has access to a database2", WAException::$CODE_DB_QUERY_ERROR, NULL)));
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
               //change the time format
               $timeObject = new DateTime($result[0]['time_created']);
               $result[0]['time_created'] = $timeObject->format(DateTime::ISO8601);
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