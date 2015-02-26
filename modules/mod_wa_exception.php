<?php

/* 
 * This file implements the WAException class
 */
class WAException extends Exception {
   public static $CODE_DB_CONNECT_ERROR = 100001;//Was unable to connect to the testbed database
   public static $CODE_DB_CLOSE_ERROR = 100002;//Was unable to close the connection to the database
   public static $CODE_DB_INACTIVE_ERROR = 100003;//The PDO connection is either null or inactive
   public static $CODE_DB_QUERY_ERROR = 100004;
   public static $CODE_WF_INSTANCE_ERROR = 100005;
   public static $CODE_FS_CREATE_DIR_ERROR = 100006;//unable to create directory
   public static $CODE_FS_DOWNLOAD_ERROR = 100007;
   public static $CODE_DB_CREATE_ERROR = 100008;//error in either creating a database or a table
   public static $CODE_DB_COLUMN_MISSMATCH = 100009;//column number missmatch in insert query
   public static $CODE_DB_REGISTER_FILE_ERROR = 100010;//unable to record in the database the existence of a new file
   public static $CODE_FS_RM_DIR_ERROR= 100011;//unable to remove a directory

   public function __construct($message, $code = 0, Exception $previous = null) {
      parent::__construct($message, $code, $previous);
   }
   
   /**
    * This function generates a JSONObject representing the exception
    */
   public function getJsonObject(){
      return array("code" => $this->getCode(), "message" => $this->getMessage(), "trace" => $this->getTraceAsString());
   }
}
?>

