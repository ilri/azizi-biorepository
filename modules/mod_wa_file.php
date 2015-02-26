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
   private static $TABLE_META_FILES = "__meta_files";
   
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
    * @param type $config        the repository_config object
    * @param type $workflowID    Id for the workflow holding this file
    * @param type $database      The database object to use to run queries
    * @param type $workingDir    The root working directory for the workflow
    * @param type $type          The type of file. Can either be TYPE_RAW or TYPE_PROCESSED
    */
   public function __construct($config, $workflowID, $database, $workingDir, $type) {
      include_once 'mod_log.php';
      include_once 'mod_wa_exception.php';
      
      $this->config = $config;
      $this->lH = new LogHandler("./");
      $this->database = $database;
      $this->type = $type;
      $this->workflowID = $workflowID;
      $this->workingDir = $workingDir;
      
      $this->createMetaFilesTable();
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
                         array("name" => "id" , "type"=>Database::$TYPE_INT , "length"=>11 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_PRIMARY , "auto_incr"=>true),
                         array("name" => "location" , "type"=>Database::$TYPE_VARCHAR , "length"=>200 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE , "auto_incr"=>false),
                         array("name" => "added_by" , "type"=>Database::$TYPE_VARCHAR , "length"=>200 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE , "auto_incr"=>false),
                         array("name" => "time_added" , "type"=>Database::$TYPE_DATETIME , "length"=>null , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE , "auto_incr"=>false),
                         array("name" => "last_modified" , "type"=>Database::$TYPE_DATETIME , "length"=>null , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE , "auto_incr"=>false),
                         array("name" => "workflow_type" , "type"=>Database::$TYPE_VARCHAR , "length"=>20 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE , "auto_incr"=>false)
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
      try {
         $subDirPath = $this->createWorkingSubdir($this->type);//try creating the subdirectory where the file is going to be downloaded into
         $location = $subDirPath."/".$filename;
         $bytes = file_put_contents($location, $url, LOCK_EX);//get exclusive write rights to file and write
            
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
   
   /**
    * This function records in the database the existence of this file
    */
   public function registerFile($location, $addedBy) {
      $this->lH->log(4, $this->TAG, "Recording in ".WAFile::$TABLE_META_FILES." the newly added file");
      try {
         $columns = array(
             "location" => $location,
             "added_by" => $addedBy,
             "time_added" => $this->database->getMySQLTime(),
             "last_modified" => $this->database->getMySQLTime(),
             "workflow_type" => $this->type
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
}
?>

