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
       "backups" => "/backups",
       "tmp" => "/tmp"
      );
   public static $TABLE_META_FILES = "__meta_files";

   private $TAG = "wafile";
   private $config;
   private $workflowID;
   private $database;
   private $lH;
   private $type;
   private $workingDir;
   private $filename;
   private $creator;
   private $timeCreated;
   private $timeLastModified;
   private $comment;
   private $mergeTable;
   private $mergeColumn;

   /**
    * Default constructor for this class
    *
    * @param Object     $config           The repository_config object
    * @param string     $workflowID       Id for the workflow holding this file
    * @param Database   $database         The database object to use to run queries
    * @param string     $workingDir       The root working directory for the workflow
    * @param string     $type             The type of file. Can either be TYPE_RAW or TYPE_PROCESSED
    * @param string     $filename         Name of the file as stored in the filesystem
    * @param string     $creator          URI of the person who created/uploaded the file
    * @param DateTime   $timeCreated      The time the file was created
    * @param DateTime   $timeLastModified Last modification date for the file
    * @param string     $comment          Comment appended to the file
    * @param string     $mergeTable       The name of the table to be used to merge data during data dumps
    * @param string     $mergeColumn      The name of the column in the mergeTable to be used to merge data during data dumps
    *
    * @throws WAException
    */
   public function __construct($config, $workflowID, $database, $workingDir, $type, $filename = null, $creator = null, $timeCreated = null, $timeLastModified = null, $comment = null, $mergeTable = null, $mergeColumn = null) {
      include_once 'mod_log.php';
      include_once 'mod_wa_exception.php';

      $this->config = $config;
      $this->lH = new LogHandler("./");
      $this->database = $database;
      $this->type = $type;
      $this->workflowID = $workflowID;
      $this->workingDir = $workingDir;
      $this->filename = $filename;
      $this->creator = $creator;
      $this->timeCreated = $timeCreated;
      $this->timeLastModified = $timeLastModified;
      $this->comment = $comment;
      $this->mergeTable = $mergeTable;
      $this->mergeColumn = $mergeColumn;
      try {
         $this->createMetaFilesTable();
      } catch (WAException $ex) {
         $this->lH->log(1, $this->TAG, "An error occurred while trying to initialize a WAFile object");
         throw new WAException("Unable to correctly initialize WAFile object", WAException::$CODE_DB_CREATE_ERROR, $ex);
      }
   }

   /**
    * This function returns all file details as an array
    *
    * @return Array An array with the file details
    */
   public function getFileDetails() {
      $time = null;
      if($this->timeCreated != null) {
         $time = $this->timeCreated->format(DateTime::ISO8601);
      }

      $modifiedTime = null;
      if($this->timeLastModified != null) {
         $modifiedTime = $this->timeLastModified->format(DateTime::ISO8601);
      }

      $details = array(
         "filename" => $this->filename,
         "type" => $this->type,
         "creator" => $this->creator,
         "time_created" => $time,
         "time_last_modified" => $modifiedTime,
         "comment" => $this->comment,
         "merge_table" => $this->mergeTable,
         "merge_column" => $this->mergeColumn
      );

      return $details;
   }

   /**
    * This function tries to save the current file as an excel file
    *
    * @param PHPExcel $excelObject  The PHPExcel object to be saved as a file
    * @return String The URL to the file
    *
    * @throws WAException
    */
   public function saveAsExcelFile($excelObject) {
      include_once $this->config['common_folder_path'].'PHPExcel/Classes/PHPExcel/IOFactory.php';
      try {
         $objWriter = new PHPExcel_Writer_Excel2007($excelObject);
         $subDirPath = $this->createWorkingSubdir();
         $objWriter->save($subDirPath."/".$this->filename);
         return $subDirPath."/".$this->filename;
      } catch (WAException $ex) {
         $this->lH->log(1, $this->TAG, "An error occurred while trying to save excel object as a file");
         throw new WAException("An error occurred while trying to save excel object as a file", WAException::$CODE_WF_PROCESSING_ERROR, $ex);
      }
   }

   /**
    * This function checks whether the file exists in the filesystem.
    * Make sure the filename is set first.
    *
    * @return boolean TRUE if file exists in the filesystem
    * @throws WAException
    */
   public function fileInFilesystem() {
      if($this->filename != null
              && $this->workingDir != null
              && $this->type != null) {
         $location = $this->workingDir."/".WAFile::$WORKING_SUB_DIRS[$this->type]."/".$this->filename;
         $this->lH->log(4, $this->TAG, "Checking if ".$location." exists");
         if(file_exists($location)) {
            return true;
         }
         return false;
      }
      else {
         throw new WAException("Unable to check wheter file exists because it wasn't initialized correctly", WAException::$CODE_WF_INSTANCE_ERROR, null);
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
      if(
              $this->workflowID !== null
              && $this->database !== null
              && $this->database->getDatabaseName() == $this->workflowID) {
         try {//try creating the table
            $this->database->runCreateTableQuery(WAFile::$TABLE_META_FILES,
                     array(
                        array("name" => "id" , "type"=>Database::$TYPE_SERIAL , "length"=>null , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_PRIMARY),
                        array("name" => "location" , "type"=>Database::$TYPE_VARCHAR , "length"=>200 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE),
                        array("name" => "added_by" , "type"=>Database::$TYPE_VARCHAR , "length"=>200 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE),
                        array("name" => "time_added" , "type"=>Database::$TYPE_DATETIME , "length"=>null , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE),
                        array("name" => "last_modified" , "type"=>Database::$TYPE_DATETIME , "length"=>null , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE),
                        array("name" => "workflow_type" , "type"=>Database::$TYPE_VARCHAR , "length"=>20 , "nullable"=>false , "default"=>null , "key"=>Database::$KEY_NONE),
                        array("name" => "comment" , "type"=>Database::$TYPE_VARCHAR , "length"=>100 , "nullable"=>true , "default"=>"''" , "key"=>Database::$KEY_NONE),
                        array("name" => "merge_table" , "type"=>Database::$TYPE_VARCHAR , "length"=>100 , "nullable"=>true , "default"=>null , "key"=>Database::$KEY_NONE),
                        array("name" => "merge_column" , "type"=>Database::$TYPE_VARCHAR , "length"=>100 , "nullable"=>true , "default"=>null , "key"=>Database::$KEY_NONE)
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
      if(filter_var($url, FILTER_VALIDATE_URL) || file_exists($url)) {//url is valid
         try {
            $subDirPath = $this->createWorkingSubdir();//try creating the subdirectory where the file is going to be downloaded into
            $location = $subDirPath."/".$filename;
            $fileDownloaded = false;
            //depending on whether file is local or remote, copy it to workflow working directory
            if(filter_var($url, FILTER_VALIDATE_URL)){
               $this->lH->log(4, $this->TAG, "Downloading $url from remote server");
               $bytes = file_put_contents($location, fopen($url, 'r'));//get exclusive write rights to file and write

               if($bytes === FALSE) {//failed to download the file
                  $this->lH->log(1, $this->TAG, "Unable to donwload the file ".$filename);
                  throw new WAException("Unable to download file ".$filename." from ".$url, WAException::$CODE_FS_DOWNLOAD_ERROR, NULL);
               }
               else {
                  $fileDownloaded = true;
               }
            }
            else {
               $this->lH->log(4, $this->TAG, "Fetching $url as local file");
               $res = copy($url, $location);
               if($res === FALSE) {
                  $this->lH->log(1, $this->TAG, "unable to copy $url to $location");
                  throw new WAException("Unable to copy file ".$filename." from ".$url, WAException::$CODE_FS_DOWNLOAD_ERROR, NULL);
               }
               else {
                  $fileDownloaded = true;
               }
            }
            //record existance of file in database
            if($fileDownloaded == true) {
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
         throw new WAException("The provided URL doesn't point to a valid data file", WAException::$CODE_WF_DATA_MULFORMED_ERROR, null);
      }
   }

   /**
    * This function sets the table and column to be used for merging data during
    * data dumps if this file has been imported from another project
    */
   public function setMergeColumn($tableName, $columnName) {
      try {
         $this->lH->log(3, $this->TAG, "Updating the merge columm for '{$this->filename}' to '$tableName' - '$columnName'");
         $query = "update ".WAFile::$TABLE_META_FILES." set merge_table = '$tableName', merge_column = '$columnName' where location = '{$this->filename}' and workflow_type = '{$this->type}'";
         $this->database->runGenericQuery($query);
      } catch (WAException $ex) {
         throw new WAException("Unable to record the merge table and column for '{$this->filename}'", WAException::$CODE_DB_QUERY_ERROR, $ex);
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
   public function createWorkingSubdir() {
      $subDirKey = $this->type;
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

      $query = "select *"
              . " from ".WAFile::$TABLE_META_FILES." order by id";
      try {
         $result = $database->runGenericQuery($query, true);
         if($result !== false){
            $files = array();
            $res_count = count($result);
            for($index = 0; $index < $res_count; $index++){
               //initialize all the files
               try {
                  //$currFile = new WAFile($config, $workflowId, $database, $workingDir, $result[$index]['workflow_type'], $result[$index]['location']);
                  $currFile = new WAFile($config, $workflowId, $database, $workingDir, $result[$index]['workflow_type'], $result[$index]['location'], $result[$index]['added_by'], new DateTime($result[$index]['time_added']), new DateTime($result[$index]['last_modified']), $result[$index]['comment'], $result[$index]['merge_table'], $result[$index]['merge_column']);
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

   /**
    * This function returns all valid save point files
    *
    * @param Object     $config        repository_config object
    * @param Workflow   $workflowID    Instance id for the workflow
    * @param Database   $database      Database object to run queries in
    * @param string     $workingDir    The working directory for the workflow
    */
   public static function getAllSavePointFiles($config, $workflowID, $database, $workingDir) {
      include_once 'mod_log.php';
      include_once 'mod_wa_exception.php';
      $lH = new LogHandler("./");
      try {
         $allFiles = WAFile::getAllWorkflowFiles($config, $workflowID, $workingDir);
         $savePoints = array();
         foreach($allFiles as $currFile) {
            $fileDetails = $currFile->getFileDetails();
            if($fileDetails['type'] == WAFile::$TYPE_BACKUP) {
               $savePoints[] = $currFile;
            }
         }
         return $savePoints;
      } catch (WAException $ex) {
            $lH->log(1, "wafile_static", "Unable to fetch backup file details from the database for workflow with id = '{$workflowID}'");
            throw new WAException("Unable to fetch backup file details from the database", WAException::$CODE_DB_QUERY_ERROR, $ex);
      }
   }

   /**
    * This function returns the path for the backup file correspoinding to the provided save point
    *
    * @param string  $savePoint  Name of the save point file
    *
    * @return string Path to the .sql dump file corresponding to the save point
    */
   public static function getSavePointFilePath($workingDir, $savePoint) {
      $location = $workingDir.WAFile::$WORKING_SUB_DIRS[WAFile::$TYPE_BACKUP]."/".$savePoint;
      return $location;
   }

   /**
    * This function deletes the php_excel caches
    */
   public static function clearCache($workingDir) {
      //delete raw data caches
      //check when last the directory was edited before deleting
      $phpExcelCache = $workingDir.WAFile::$WORKING_SUB_DIRS["raw_data"]."/phpexcel_cache";
      WAFile::rmDir($phpExcelCache);
   }

   /**
    * This function copies the contents of a source directory to a destination
    * directory
    *
    * @param String $src   The source directory
    * @param String $dst   The destination directory
    */
   public static function copyDir($src, $dst) {
      $dir = opendir($src);
      @mkdir($dst);
      while(false !== ( $file = readdir($dir)) ) {
         if (( $file != '.' ) && ( $file != '..' )) {
            if ( is_dir($src . '/' . $file) ) {
               WAFile::copyDir($src . '/' . $file,$dst . '/' . $file);
            }
            else {
               copy($src . '/' . $file, $dst . '/' . $file);
            }
         }
      }
      closedir($dir);
   }
}
?>

