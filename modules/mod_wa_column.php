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
   
   /**
    * Default constructor for this class
    * 
    * @param Object     $config     The repository_config object
    * @param Database   $database   Database object to be used to run queries
    * @param string     $name       Name of the column
    * @param Array      $data       Data corresponding to that column
    */
   public function __construct($config, $database, $name, $data) {
      include_once 'mod_log.php';
      include_once 'mod_wa_database.php';
      include_once 'mod_wa_exception.php';
      
      $this->lH = new LogHandler("./");
      $this->config = $config;
      $this->database = $database;
      $this->name = $name;
      $this->data = $data;
   }
   
   /**
    * This function returns the MySQL details for this column in form of an array
    * that can be used with the database object
    */
   public function getMySQLDetails(){
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
            }
            else {
               $this->lH->log(4, $this->TAG, "Determining datatype for '{$this->data[$index]}'");
               //determine maximum length
               if($this->data[$index] !== null
                       && strlen($this->data[$index]) > $length) {
                  $length = strlen($this->data[$index]);
               }
               
               //no need to test for string. String is default value
               $typeVarchar++;
               
               if(WAColumn::isDate($this->data[$index])) {
                  $this->lH->log(4, $this->TAG, "'{$this->data[$index]}' is of type date");
                  $typeDate++;
               }
               if(WAColumn::isTime($this->data[$index])) {
                  $this->lH->log(4, $this->TAG, "'{$this->data[$index]}' is of type time");
                  $typeTime++;
               }
               if(WAColumn::isDatetime($this->data[$index])) {
                  $this->lH->log(4, $this->TAG, "'{$this->data[$index]}' is of type datetime");
                  $typeDateTime++;
               }
               
               if(WAColumn::isInt($this->data[$index])) {
                  $this->lH->log(4, $this->TAG, "'{$this->data[$index]}' is of type int");
                  $typeInteger++;
               }
               if(WAColumn::isDouble($this->data[$index])) {
                  $this->lH->log(4, $this->TAG, "'{$this->data[$index]}' is of type double");
                  $typeDouble++;
               }
               if(WAColumn::isTinyInt($this->data[$index])) {
                  $this->lH->log(4, $this->TAG, "'{$this->data[$index]}' is of type tinyint");
                  $typeTinyInt++;
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
                 && $typeDateTime == $typeVarchar) {//none of the cells could be used to determine type because they were considered null
            
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
         }
         
         return array("name" => $this->name , "type"=>$type , "length"=>$length , "nullable"=>$nullable, "default" => null , "key"=>Database::$KEY_NONE , "auto_incr"=>false);
      }
      else {
         $this->lH->log(2, $this->TAG, "Unable to determine datatype for '$name' column because no data was provided. Assuming nullable varchar(50) datatype");
         return array("name" => $this->name , "type"=>Database::$TYPE_VARCHAR , "length"=>50 , "nullable"=>true, "default" => null , "key"=>Database::$KEY_NONE , "auto_incr"=>false);
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
   
   
}