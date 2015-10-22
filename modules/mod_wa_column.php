<?php

/**
 * This class implements a sheet column
 */
class WAColumn {
   
   private $TAG = "wacolumn";
   private $config;
   private $database;
   private $lH;
   private $name;
   private $data;
   private $type;
   private $length;
   private $nullable;
   private $default;
   private $key;
   
   /**
    * Default constructor for this class
    * 
    * @param Object     $config     The repository_config object
    * @param Database   $database   Database object to be used to run queries
    * @param string     $name       Name of the column
    * @param Array      $data       Data corresponding to that column
    */
   public function __construct($config, $database, $name, $data = null, $type = null, $length = null, $nullable = null, $default = null, $key = null) {
      include_once 'mod_log.php';
      include_once 'mod_wa_database.php';
      include_once 'mod_wa_exception.php';
      
      $this->lH = new LogHandler("./");
      $this->config = $config;
      $this->database = $database;
      $this->name = $name;
      $this->data = $data;
      
      $this->type = $type;
      $this->length = $length;
      $this->nullable = $nullable;
      $this->default = $default;
      $this->key = $key;
   }
   
   /**
    * This function returns the schema representing this object as an array
    */
   public function getSchema() {
      $schema = array(
          "name" => $this->name,
          "type" => $this->type,
          "length" => $this->length,
          "nullable" => $this->nullable,
          "default" => $this->default,
          "key" => $this->key,
          "present" => true
      );
      
      return $schema;
   }
   
   /**
    * This function returns the current column name (in memory)
    */
   public function getName() {
      return $this->name;
   }
   
   public function getType() {
      return $this->type;
   }
   
   /**
    * This function updates the column details
    * 
    * @param string  $name       New name to be given to the column
    * @param string  $type       Can be any of the Database::$TYPE_* types
    * @param int     $length     The length of the column
    * @param boolean $nullable   TRUE if column is nullable
    * @param string  $default    The default value for the column
    * @param string  $key        Can either be Database::$KEY_NONE, Database::$KEY_UNIQUE or Database::$KEY_PRIMARY
    * 
    * @throws WAException
    */
   public function update($sheetName, $name, $type, $length, $nullable, $default, $key) {
      
      try {
         $new = array(
            "name" => $name,
            "type" => $type,
            "length" => $length,
            "nullable" => $nullable,
            "default" => $default,
            "key" => $key
         );
         $existing = array(
            "name" => $this->name,
            "type" => $this->type,
            "length" => $this->length,
            "nullable" => $this->nullable,
            "default" => $this->default,
            "key" => $this->key
         );
         $this->database->runAlterColumnQuery($sheetName, $existing, $new);
         $this->name = $name;
         $this->type = $type;
         $this->length = $length;
         $this->nullable = $nullable;
         $this->default = $default;
         $this->key = $key;
      } catch (WAException $ex) {
         throw new WAException("Unable to alter the column '{$this->name}'", WAException::$CODE_WF_INSTANCE_ERROR, $ex);
      }
   }
   
   public function setData($data) {
      $this->data = $data;
   }
   
   public function getData() {
      return $this->data;
   }
   
   /**
    * This function returns the MySQL details for this column in form of an array
    * that can be used with the database object
    */
   public function getMySQLDetails($linkSheets = false){
      if($this->data != null || count($this->data) > 0) {
         $type = Database::$TYPE_VARCHAR;
         $length = -1;
         $nullable = false;
         
         $typeVarchar = 0;
         $typeInteger = 0;
         $typeDouble = 0;
         $typeTinyInt = 0;
         $typeTime = 0;
         $typeDate = 0;
         $typeDateTime = 0;
         $typeBoolean = 0;
         $typeTimestamp = 0;
         
         for($index = 0; $index < count($this->data); $index++){
            
            if(WAColumn::isNull($this->data[$index])){//means this column is nullable
               $nullable = true;
               //cannot use this cell to determing column datatype
               //assume that current cell can be any type
               $typeVarchar++;
               $typeInteger++;
               $typeDouble++;
               $typeTinyInt++;
               $typeTime++;
               $typeDate++;
               $typeDateTime++;
               $typeBoolean++;
               $typeTimestamp++;
            }
            else {
               //$this->lH->log(4, $this->TAG, "Determining datatype for '{$this->data[$index]}'");
               //determine maximum length
               if($this->data[$index] !== null
                       && strlen($this->data[$index]) > $length) {
                  $length = strlen($this->data[$index]);
               }
               
               //no need to test for string. String is default value
               $typeVarchar++;
               
               if(WAColumn::isDate($this->data[$index])) {
                  //$this->lH->log(4, $this->TAG, "'{$this->data[$index]}' is of type date");
                  $typeDate++;
               }
               else if(WAColumn::isTime($this->data[$index])) {
                  //$this->lH->log(4, $this->TAG, "'{$this->data[$index]}' is of type time");
                  $typeTime++;
               }
               else if(WAColumn::isDatetime($this->data[$index])) {
                  //$this->lH->log(4, $this->TAG, "'{$this->data[$index]}' is of type datetime");
                  $typeDateTime++;
               }
               else if(WAColumn::isTimestamp($this->data[$index])) {
                  $typeTimestamp++;
               }
               else if(WAColumn::isDouble($this->data[$index])) {
                  //$this->lH->log(4, $this->TAG, "'{$this->data[$index]}' is of type double");
                  $typeDouble++;
               }
               else if(WAColumn::isTinyInt($this->data[$index])) {
                  //$this->lH->log(4, $this->TAG, "'{$this->data[$index]}' is of type tinyint");
                  $typeTinyInt++;
               }
               else if(WAColumn::isInt($this->data[$index])) {
                  //$this->lH->log(4, $this->TAG, "'{$this->data[$index]}' is of type int");
                  $typeInteger++;
               }
               else if(WAColumn::isBoolean($this->data[$index])) {
                  //$this->lH->log(4, $this->TAG, "'{$this->data[$index]}' is of type tinyint");
                  $typeBoolean++;
               }
            }
         }
         
         if($length == -1
                 || $length == 0) {//none of the data cells had data
            $this->lH->log(2, $this->TAG, "Could not determine the variable size of column '{$this->name}'. Setting length to default of 50 characters");
            $length = 50;//default length for the variables
         }
         
         //determine type
         if($typeInteger == $typeVarchar
                 && $typeDouble == $typeVarchar
                 && $typeTinyInt == $typeVarchar
                 && $typeDate == $typeVarchar
                 && $typeTime == $typeVarchar
                 && $typeDateTime == $typeVarchar
                 && $typeTimestamp == $typeVarchar
                 && $typeBoolean == $typeVarchar) {//none of the cells could be used to determine type because they were considered null
            
            $this->lH->log(2, $this->TAG, "Could not determine the datatype for column '{$this->name}'. Setting type to default type varchar");
            $type = Database::$TYPE_VARCHAR;
            
         }
         else {
            //order of the following if blocks matters
            if($typeInteger == $typeVarchar) {
               $type = Database::$TYPE_INT;
            }
            if($typeTinyInt == $typeVarchar) {
               $type = Database::$TYPE_TINYINT;
            }
            if($typeBoolean == $typeVarchar) {
               $type = Database::$TYPE_BOOLEAN;
            }
            if($typeDouble == $typeVarchar) {
               $type = Database::$TYPE_DOUBLE;
            }
            
            if($typeDate == $typeVarchar) {
               $type = Database::$TYPE_DATE;
            }
            if($typeTime == $typeVarchar) {
               $type = Database::$TYPE_TIME;
            }
            if($typeDateTime == $typeVarchar) {
               $type = Database::$TYPE_DATETIME;
            }
            if($typeTimestamp == $typeVarchar) {
               $type = Database::$TYPE_TIMESTAMP;
            }
         }
         $key = Database::$KEY_NONE;
         if($linkSheets == true && $this->name == "primary_key") $key = Database::$KEY_UNIQUE; 
         return array("name" => $this->name , "type"=>$type , "length"=>$length , "nullable"=>$nullable, "default" => null , "key"=>$key);
      }
      else {
         $this->lH->log(2, $this->TAG, "Unable to determine datatype for '$name' column because no data was provided. Assuming nullable varchar(50) datatype");
         return array("name" => $this->name , "type"=>Database::$TYPE_VARCHAR , "length"=>50 , "nullable"=>true, "default" => null , "key"=>Database::$KEY_NONE);
      }
   }
   
   /**
    * Checkes whether provided string is null
    * 
    * @param string $string  String to be checked
    * 
    * @return boolean TRUE if string is null
    */
   public static function isNull($string) {
      if($string == null 
              || strlen($string) == 0 
              || $string == "null"
              || $string == "NULL") {
         return true;
      }
      return false;
   }
   
   /**
    * Checkes whether provided string is a date. Dates expected to be in these
    * formats:
    *     - yyyy-mm-dd
    * 
    * @param string $string   String to be checked
    * 
    * @return boolean TRUE if provided string is date
    */
   public static function isDate($string) {
      $string = ltrim($string, "'");//' character might have been appended to prevent excel processors from modifying value
      if($string != null && strlen($string) > 0){
         if(DateTime::createFromFormat('Y-m-d', $string) !== false) {//successfully parsed as a date
            return true;
         }
      }
      return false;
   }
   
   /**
    * Checkes whether provided string is of type time. Time expected to be in these
    * formats:
    *     - hh:mm:ss
    * 
    * @param string $string   String to be checked
    * 
    * @return boolean TRUE if provided string is time
    */
   public static function isTime($string) {
      $string = ltrim($string, "'");//' character might have been appended to prevent excel processors from modifying value
      if($string != null && strlen($string) > 0){
         if(DateTime::createFromFormat('H:i:s', $string) !== false) {//successfully parsed as a date
            return true;
         }
      }
      return false;
   }
   
   /**
    * Checkes whether provided string is a datetime. Datetimes expected to follow the
    * ISO8601 format e.g:
    *     - yyyy-mm-dd hh:mm:ss.ms
    *     - yyyy-mm-dd hh:mm:ss
    * 
    * @param string $string   String to be checked
    * 
    * @return boolean TRUE if provided string is date
    */
   public static function isDatetime($string) {
      $string = ltrim($string, "'");//' character might have been appended to prevent excel processors from modifying value
      if($string != null && strlen($string) > 0){
         if(preg_match("/\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}(\.\d+)?/", $string) === 1) {//successfully parsed as a date
            return true;
         }
      }
      return false;
   }
   
   /**
    * Checkes whether provided string is a datetime. Datetimes expected to follow the
    * ISO8601 format e.g:
    *     - yyyy-mm-ddThh:mm:ss.ms tz
    * 
    * @param string $string   String to be checked
    * 
    * @return boolean TRUE if provided string is date
    */
   public static function isTimestamp($string) {
      $string = ltrim($string, "'");//' character might have been appended to prevent excel processors from modifying value
      if($string != null && strlen($string) > 0){
         if(preg_match("/\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}(\.\d+)?[\-,\+]\d{2}/", $string) === 1) {//successfully parsed as a date
            return true;
         }
      }
      return false;
   }
   
   /**
    * Checks whether provided string is an integer
    * 
    * @param string $v  String to be checked
    * 
    * @return boolean TRUE if provided string is integer
    */
   public static function isInt($v) {
      if($v != null && strlen($v) > 0) {
         if(is_numeric($v)) {
            return true;
         }
      }
      
      return false;
   }
   
   /**
    * Checks whether provided string is a tiny integer
    * 
    * @param type $string  String to be checked
    * 
    * @return boolean TRUE if provided string is a tiny integer
    */
   public static function isTinyInt($string){
      if(WAColumn::isInt($string) && strlen($string) == 1) {
         return true;
      }
      return false;
   }
   
   /**
    * Checks whether provided string is a double
    * 
    * @param type $string  String to be checked
    * 
    * @return boolean TRUE if provided string is a double
    */
   public static function isDouble($string) {
      if($string != null && strlen($string) > 0){
         if(is_numeric($string) 
                 && strpos($string, ".") !== false) {
            return true;
         }   
      }
      
      return false;
   }
   
   /**
    * Checks whether provided string is a boolean
    * 
    * @param type $string  String to be checked
    * 
    * @return boolean TRUE if provided string is a boolean
    */
   public static function isBoolean($string) {
      if($string === true 
              || $string === false
              || preg_match("/true/i", $string) === 1
              || preg_match("/false/i", $string) === 1) {
         return true;
      }
      return false;
   }
   
   public static function isTrue($string) {
      if($string === true
              || preg_match("/true/i", $string) === 1) {
         return true;
      }
      return false;
   }
   
   public static function getOriginalColumnName($database, $file, $sheetName, $currentName) {
      include_once 'mod_wa_exception.php';
      try {
         $query = "select original_column from ".Workflow::$TABLE_META_CHANGES." where current_column = '$currentName' and current_sheet = '$sheetName' and change_type = '".Workflow::$CHANGE_COLUMN."' and file = '$file'";
         $result = $database->runGenericQuery($query, TRUE);
         if(is_array($result)) {
            if(count($result) == 1) {
               return $result[0]['original_column'];
            }
            else if(count($result) == 0) {
               return $currentName;
            }
            else {
               throw new WAException("Multiple records in the database indicating name change for '$currentName'", WAException::$CODE_DB_ZERO_RESULT_ERROR, null);
            }
         }
         else {
            throw new WAException("Unable to determine what '$currentName' was originally called", WAException::$CODE_DB_QUERY_ERROR, null);
         }
      } catch (WAException $ex) {
         throw new WAException("Unable to determine what '$currentName' was originally called", WAException::$CODE_DB_QUERY_ERROR, $ex);
      }
   }
   
   /**
    * This function tries to remove the sheet name from the column name in an
    * effort to try minimize the length of the column. Make sure you record the
    * change (if name changes) in the meta changes table
    * 
    * @param String $sheetName   Name of the sheet
    * @param String $columnName  Name of the column
    * @return String The new column name or the original column name if name doesn't change
    */
   public static function trimColumnName($sheetName, $columnName){
      if(substr($columnName, 0, strlen($sheetName)) == $sheetName) {//the column name starts with the sheet name
         $columnName = str_replace($sheetName."-", "", $columnName);
      }
      return $columnName;
   }
}