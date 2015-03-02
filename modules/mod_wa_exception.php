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
   public static $CODE_DB_ZERO_RESULT_ERROR = 100012;//No result returned from the database when at least one result expected
   public static $CODE_FS_UNKNOWN_LOCATION_ERROR = 100013;//Unable to determine filesystem location for file
   public static $CODE_FS_UNKNOWN_TYPE_ERROR = 100014;//Unable to determine type of file (whether raw, processed or backup file)
   public static $CODE_WF_CREATE_ERROR = 100015;//unable to create an object because of a resource error
   public static $CODE_WF_PROCESSING_ERROR = 100016;//a general processing error occurred
   public static $CODE_WF_DATA_MULFORMED_ERROR = 100017;//MySQL or Excel data seems to be mulformed
   public static $CODE_WF_FEATURE_UNSUPPORTED_ERROR = 100018;//Feature being requested by client not (yet) supported

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

