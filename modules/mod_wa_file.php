<?php

/**
 * This class handles all functions for a file linked to a workflow
 */
class WAFile {
   public static $TYPE_RAW = "raw_data";
   public static $TYPE_PROCESSED = "processed_data";
   public static $TYPE_BACKUP = "backups";
   public static $WORKING_SUB_DIRS = array(
       "raw_data" => "/raw_data",
       "processed_data" => "/processed_data",
       "backups" => "/backups");
   public static $TABLE_META_FILES = "__meta_files";
   
   private $TAG = "wafile";
   private $config;
   private $workflowID;
   private $database;
   private $lH;
   private $type;
   private $workingDir;
   private $filename;
   
   /**
    * Default constructor for this class
    * 
    * @param Object     $config        the repository_config object
    * @param string     $workflowID    Id for the workflow holding this file
    * @param Database   $database      The database object to use to run queries
    * @param string     $workingDir    The root working directory for the workflow
    * @param string     $type          The type of file. Can either be TYPE_RAW or TYPE_PROCESSED
    * @param string     $filename      Name of the file as stored in the filesystem
    * 
    * @throws WAException
    */
   public function __construct($config, $workflowID, $database, $workingDir, $type, $filename = null) {
      include_once 'mod_log.php';
      include_once 'mod_wa_exception.php';
      
      $this->config = $config;
      $this->lH = new LogHandler("./");
      $this->database = $database;
      $this->type = $type;
      $this->workflowID = $workflowID;
      $this->workingDir = $workingDir;
      $this->filename = $filename;
      
      try {
         $this->createMetaFilesTable();
      } catch (WAException $ex) {
         $this->lH->log(1, $this->TAG, "An error occurred while trying to initialize a WAFile object");
         throw new Exception("Unable to correctly initialize WAFile object", WAException::$CODE_DB_CREATE_ERROR, $ex);
      }
   }
   
   /**
    * This fuction returns the config class held by this object
    */
   public function getConfig() {
      return $this->config;
   }
   
   /**
    * This function returns the database object held by this object
    * 
    * @return Database
    */
   public function getDatabase() {
      return $this->database;
   }
   
   /**
    * This function returns the workflow type for the file
    */
   public function getType() {
      return $this->type;
   }
   
   /**
    * This function checks whether the metaFiles table is created and creates it if not.
    * This table stores locations for the varioius files for the workspace instance
    * 
    * @throws WAException
    */
   private function createMetaFilesTable() {
      if($this->workflowID === $this->database->getDatabaseName()) {
         try {//try creating the table
            $this->database->runCreateTableQuery(WAFile::$TABLE_META_FILES,
                     array(
                         array("name" => "id" , "type"=>Database::$TYPE_SERIAL , "length"=>null , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_PRIMARY),
                         array("name" => "location" , "type"=>Database::$TYPE_VARCHAR , "length"=>200 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE),
                         array("name" => "added_by" , "type"=>Database::$TYPE_VARCHAR , "length"=>200 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE),
                         array("name" => "time_added" , "type"=>Database::$TYPE_DATETIME , "length"=>null , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE),
                         array("name" => "last_modified" , "type"=>Database::$TYPE_DATETIME , "length"=>null , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE),
                         array("name" => "workflow_type" , "type"=>Database::$TYPE_VARCHAR , "length"=>20 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE)
                         )
           );
         } catch (WAException $ex) {//error occurred while trying to create table
            $this->lH->log(1, $this->TAG, "An error occurred while trying to create the ".WAFile::$TABLE_META_FILES." table");
            array_push($this->errors, new WAException("Unable to create the ".WAFile::$TABLE_META_FILES." table", WAException::$CODE_DB_CREATE_ERROR, $ex));
         }
      }
      else {
         throw new WAException("Unable to create the meta files table because the database object is liked to the wrong database", WAException::$CODE_DB_CREATE_ERROR, $ex);
      }
   }
   
   /**
    * This function tries to download the file and records its details in the
    * database
    * 
    * @param string $filename    Name to give to downloaded file
    * @param string $url         Valid url from where to download the file
    * @param string $addedBy     Name of the user downloading the file
    * 
    * @throws WAException
    */
   public function downloadFile($filename, $url, $addedBy) {
      if(filter_var($url, FILTER_VALIDATE_URL)) {//url is valid
         try {
            $subDirPath = $this->createWorkingSubdir($this->type);//try creating the subdirectory where the file is going to be downloaded into
            $location = $subDirPath."/".$filename;
            $bytes = file_put_contents($location, fopen($url, 'r'));//get exclusive write rights to file and write

            if($bytes === FALSE) {//failed to download the file
               $this->lH->log(1, $this->TAG, "Unable to donwload the file ".$filename);
               throw new WAException("Unable to download file ".$filename." from ".$url, WAException::$CODE_FS_DOWNLOAD_ERROR, NULL);
            }
            else {
               $this->lH->log(4, $this->TAG, "File ".$filename." successfully downloaded");
               $this->filename = $filename;
               try {
                  $this->registerFile($filename, $addedBy);
               } catch (WAException $ex) {
                  $this->lH->log(1, $this->TAG, "File downloaded successfully but unable to record existence of the file in the database");
                  throw new WAException("Unable to register existence of new file '$filename' in the database", WAException::$CODE_DB_REGISTER_FILE_ERROR, $ex);
               }
            }

         } catch (WAException $ex) {
            throw new WAException("Unable to create subdirectory where downloading file is going to be stored", WAException::$CODE_FS_DOWNLOAD_ERROR, $ex);
         }
      }
      else {
         $this->lH->log(1, $this->TAG, "Provided URL to data file is not valid for workflow with id = {$this->instanceId}");
         throw new WAException("The provided URL to the data file is not valid", WAException::$CODE_WF_DATA_MULFORMED_ERROR, null);
      }
   }
   
   /**
    * This function records in the database the existence of this file
    */
   public function registerFile($location, $addedBy) {
      $this->lH->log(4, $this->TAG, "Recording in ".WAFile::$TABLE_META_FILES." the newly added file");
      try {
         $columns = array(
             "location" => "'$location'",
             "added_by" => "'$addedBy'",
             "time_added" => "'{$this->database->getMySQLTime()}'",
             "last_modified" => "'{$this->database->getMySQLTime()}'",
             "workflow_type" => "'{$this->type}'"
         );
         
         $this->database->runInsertQuery(WAFile::$TABLE_META_FILES, $columns);
      } catch (WAException $ex) {
         throw new WAException("Unable to record the existence of a new file in the database", WAException::$CODE_DB_REGISTER_FILE_ERROR, $ex);
      }
   }
   
   /**
    * This function creates subdirectories in the working directory
    * 
    * @return the path for the created sub directory
    * @throws WAException
    */
   private function createWorkingSubdir($subDirKey) {
      if(file_exists($this->workingDir) && is_dir($this->workingDir)){
         $dirKeys = array_keys(WAFile::$WORKING_SUB_DIRS);
         if(array_search($subDirKey, $dirKeys) !== false){//subdir recognised
            $this->lH->log(4, $this->TAG, "Trying to create subdir ".WAFile::$WORKING_SUB_DIRS[$subDirKey]." in {$this->workingDir}");
            $subDirPath = $this->workingDir.WAFile::$WORKING_SUB_DIRS[$subDirKey];
            
            if(file_exists($subDirPath)) {//subdirectory already exists
               //check if is directory or file
               if(!is_dir($subDirPath)) {//file is not a directory
                  $this->lH->log(1, $this->TAG, "Appears like a file that is not a directory exists in {$this->workingDir} working directory that is also named ".WAFile::$WORKING_SUB_DIRS[$subDirKey]);
                  throw new WAException("Unable to create working subdirectory ".WAFile::$WORKING_SUB_DIRS[$subDirKey]." because a file with a similar name already exists", WAException::$CODE_FS_CREATE_DIR_ERROR, null);
               }
               else {
                  $this->lH->log(4, $this->TAG, WAFile::$WORKING_SUB_DIRS[$subDirKey]." already exists in {$this->workingDir}");
                  return $subDirPath;
               }
            }
            else {
               $result = mkdir($subDirPath);
               if($result === TRUE){//dir made successfully
                  $this->lH->log(4, $this->TAG, WAFile::$WORKING_SUB_DIRS[$subDirKey]." created in {$this->workingDir}");
                  return $subDirPath;
               }
               else {
                  $this->lH->log(4, $this->TAG, "Unable to create ".WAFile::$WORKING_SUB_DIRS[$subDirKey]." in {$this->workingDir}");
                  throw new WAException("Unable to create directory ".WAFile::$WORKING_SUB_DIRS[$subDirKey]." in ".$this->workingDir, WAException::$CODE_FS_CREATE_DIR_ERROR, null);
               }
            }
         }
      }
   }
   
   /**
    * This function returns the file system location for the file
    * 
    * @throws WAException
    */
   public function getFSLocation() {
      if($this->workingDir !== null
              && $this->type !== null
              && $this->workingDir !== null
              && $this->filename !== null) {
         if(array_key_exists($this->type, WAFile::$WORKING_SUB_DIRS)) {
            $name = $this->workingDir.WAFile::$WORKING_SUB_DIRS[$this->type]."/".$this->filename;
            
            //check if file exists
            if(file_exists($name)) {
               return $name;
            }
            else {
               $this->lH->log(1, $this->TAG, "File object holding file that does not exist in the file system. '$name' does not exist in the filesystem");
               throw new WAException("File object holding file that does not exist in the file system", WAException::$CODE_FS_UNKNOWN_LOCATION_ERROR, null);
            }
         }
         else {
            $this->lH->log(1, $this->TAG, "Could not determine which workflow type '{$this->type}' is");
            throw new WAException("Could not determine the workflow type for file", WAException::$CODE_FS_UNKNOWN_TYPE_ERROR, null);
         }
      }
      else {
         $this->lH->log(1, $this->TAG, "Could not determine filesystem location for file with filename = '{$this->filename}', workingDir = '{$this->workingDir}' and workflow type = '{$this->type}'");
         throw new WAException("Could not get filesystem location for file", WAException::$CODE_FS_UNKNOWN_LOCATION_ERROR, null);
      }
   }
   
   /**
    * This function completely erases a directory from disk
    * 
    * @return boolean   True if able to completely delete directory
    */
   public static function rmDir($dir) {
      
      if (!file_exists($dir)) {
          return true;
      }

      if (!is_dir($dir)) {
          return unlink($dir);
      }

      foreach (scandir($dir) as $item) {
          if ($item == '.' || $item == '..') {
              continue;
          }

          if (!WAFile::rmDir($dir . DIRECTORY_SEPARATOR . $item)) {
              return false;
          }

      }
      return rmdir($dir);
   }
   
   /**
    * This function returns a list of all WAFiles connected to a workflow
    * 
    * @param Database $database
    * @param string $workflowId
    * @param string $workingDir
    * 
    * @return Array  An array with WAFile objects
    * @throws WAException
    */
   public static function getAllWorkflowFiles($config, $workflowId, $workingDir) {
      include_once 'mod_log.php';
      include_once 'mod_wa_database.php';
      $lH = new LogHandler("./");
      
      $database = new Database($config, $workflowId);
      
      $query = "select ".Database::$QUOTE_SI."location".Database::$QUOTE_SI.",".Database::$QUOTE_SI."workflow_type".Database::$QUOTE_SI.""
              . " from ".WAFile::$TABLE_META_FILES;
      try {
         $result = $database->runGenericQuery($query, true);
         if($result !== false){
            $files = array();
            for($index = 0; $index < count($result); $index++){
               //initialize all the files
               try {
                  $currFile = new WAFile($config, $workflowId, $database, $workingDir, $result[$index]['workflow_type'], $result[$index]['location']);
                  array_push($files, $currFile);
               } catch (WAException $ex) {
                  $lH->log(1, "wafile_static", "An error occurred while trying to initialize one of the workflow files for workflow with id = '$workflowId'");
                  throw new Exception("An error occurred while trying to initialize one of the workflow files", WAException::$CODE_WF_INSTANCE_ERROR, null);
               }
            }
            
            //$database->close();
            
            return $files;
         }
         else {
            $lH->log(1, "wafile_static", "Unable to determine which files are owned by workflow with id = '$workflowId'");
            throw new Exception("Unable to determine which files are owned by workflow", WAException::$CODE_DB_QUERY_ERROR, null);
         }
      } catch (WAException $ex) {
         $lH->log(1, "wafile_static", "Unable to determine which files are owned by workflow with id = '$workflowId'");
         throw new Exception("Unable to determine which files are owned by workflow", WAException::$CODE_DB_QUERY_ERROR, $ex);
      }
   }
}
?>

