<?php

/**
 * This calss implements the Database class for the Workflow API.
 * Should only be used in the Workflow class and its child fields.
 * 
 * Not using the repository's Dbase object because that points to ILRI's main
 * MySQL Database. This object should be able to create a MySQL database in the 
 * MySQL testbed, add tables to that database etc.
 */
class Database {
   
   /**
    * Default constructor for this class. Initializes a PDO object to be used
    * in this class
    */
   public static $QUOTE_SI = '"';//quotes to be used for system identifiers
   public static $TYPE_SERIAL = "serial";//1 to 2147483647
   public static $TYPE_INT = "integer";
   public static $TYPE_DOUBLE = "double precision";
   public static $TYPE_VARCHAR = "varchar";
   public static $TYPE_TINYINT = "smallint";
   public static $TYPE_TIME = "time without time zone";
   public static $TYPE_DATE = "date";
   public static $TYPE_DATETIME = "timestamp without time zone";
   public static $TYPE_BOOLEAN = "boolean";
   
   public static $BOOL_TRUE = 't';
   public static $BOOL_FALSE = 'f';
   
   public static $KEY_PRIMARY = "primary";
   public static $KEY_UNIQUE = "unique";
   public static $KEY_NONE = null;
   
   private $TAG = "database";
   private $logH;//logging class
   private $config;//the repository_config object
   private $pdoObject;
   private $connectedDb;
   private $wInstanceId;//the workflow instance ID. Should correspond to the database name linked to that instance
   
   private $DEFAULT_DATABASE = "__dp_meta_root";//default database
   
   /**
    * Default constructor for this class.
    * 
    * @param type $config        The repository_config object
    * @param type $wInstanceId   The instance Id for the workflow. Should correspond
    *                            to the database specified in the PDO connection string.
    *                            Provide null if instance ID is still unknown
    * 
    * @throws WAException
    */
   public function __construct($config, $wInstanceId = null) {
      include_once 'mod_log.php';
      include_once 'mod_wa_exception.php';
      
      $this->config = $config;
      
      $this->logH = new LogHandler("./");//TODO: not sure if the default root dir specified in LogHandler is correct
      
      //initialize the PDO connection
      try {
         $this->connectedDb = $wInstanceId;
         if($this->connectedDb === NULL) $this->connectedDb = $this->DEFAULT_DATABASE;
         
         $this->logH->log(4, $this->TAG, "Trying to initialize database connection to {$this->connectedDb}");
         
         $this->pdoObject = new PDO("pgsql:dbname={$this->connectedDb};host={$config['testbed_dbloc']}", $config['testbed_user'], $config['testbed_pass']);
         $this->pdoObject->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      } catch (PDOException $ex) {
         $this->logH->log(1, $this->TAG, "An error occurred while trying to initialize database connection");
         
         throw new WAException("Unable to initialize connection to testbed database", WAException::$CODE_DB_CONNECT_ERROR, $ex);
      }
   }
   
   /**
    * This function returns the name of the database that has been bound to the
    * PDO object
    */
   public function getDatabaseName() {
      return $this->connectedDb;
   }
   
   /**
    * This function runs a generic SQL query
    * 
    * @param string  $query         The query to be executed
    * @param boolean $fetchResult   Set to true if expecting result
    * 
    * @return Multi Returns an associative array from the query or false if unable to execute query
    * @throws WAException
    */
   public function runGenericQuery($query, $fetchResults = false) {
      if($this->pdoObject !== null) {
         try {
            //run a prepared statement (PDO will handle escaping)
            $this->logH->log(4, $this->TAG, "About to run the following statement '$query'");
            $stmt = $this->pdoObject->prepare($query);

            $result = $stmt->execute();
            if($result === true) {
               $this->logH->log(4, $this->TAG, "Successfully run the statement '{$query}'");
               if($fetchResults === TRUE){
                  return $stmt->fetchAll(PDO::FETCH_ASSOC);
               }
            }
            else {
               $this->logH->log(1, $this->TAG, "An error occurred while trying to execute the SQL query '$query'");
               throw new WAException("Unable to execute this SQL statement '$query'", WAException::$CODE_DB_QUERY_ERROR, null);
            }

         } catch (PDOException $ex) {
            $this->logH->log(2, $this->TAG, "PDO Exception {$ex->getMessage()} thrown while trying to execute this SQL query '$query'");
            throw new WAException("PDOException thrown while trying to execute this SQL statement '$query'", WAException::$CODE_DB_QUERY_ERROR, $ex);
         }
      }
      else {
         $this->logH->log(1, $this->TAG, "Unable to run statement because PDO object is null for database object connected to '{$this->connectedDb}'");
         throw new WAException("Unable to run statement because PDO object is null", WAException::$CODE_DB_QUERY_ERROR, null);
      }
   }
   
   /**
    * This function quotes strings and escapes special characters
    */
   public function quote($string) {
      return $this->pdoObject->quote($string);
   }
   
   /**
    * This function closes the database connection held by this object
    * 
    * @throws WAException
    */
   public function close() {
      if($this->pdoObject !== null){
         $this->pdoObject = null;//Setting object to null should release connection. Refer to http://php.net/manual/en/pdo.connections.php
      }
      else {
         $this->logH->log(2, $this->TAG, "Was unable to release the connection to the PDO object");
         throw new WAException("The PDO connection is inactive or null", WAException::$CODE_DB_INACTIVE_ERROR, null);
      }
   }
   
   /**
    * This function tries to create the database for the workflow instance
    * 
    * @param string $name Name of the database to be created
    * @throws WAException
    */
   public function runCreatDatabaseQuery($name) {
      if($name !== null) {
         //check if the database exists
         try {
            $result = $this->getDatabaseNames();
            
            if(in_array($name, $result) === false) {//no database with said name exists
               $query = "create database {$name}";
               try {
                  $this->runGenericQuery($query);
               } 
               catch (WAException $ex) {
                  throw new WAException("Unable to create the {$name} database", WAException::$CODE_DB_CREATE_ERROR, $ex);
               }
            }
         } catch (WAException $ex) {
            //die(print_r($ex->getPrevious()->getPrevious()->getTrace(), true));
            throw new WAException("Unable to check if {$name} database exists", WAException::$CODE_DB_CREATE_ERROR, $ex);
         }
      }
      else {
         throw new WAException("Database name is null. Cannot create database", WAException::$CODE_DB_CREATE_ERROR, null);
      }
   }
   
   /**
    * This function returns time is an string that is insertable in MySQL
    * 
    * @return string Current time, insertable in MySQL as a DATETIME
    */
   public static function getMySQLTime() {
      return date('Y-m-d H:i:s');//current time in format MySQL understands
   }
   
   /**
    * This function returns a list of all database names from the server
    * 
    * @return Array
    * @throws WAException
    */
   public function getDatabaseNames() {
      //$query = "show databases";
      $query = "SELECT datname FROM pg_database WHERE datistemplate = false";
      try {
         $result = $this->runGenericQuery($query, true);
         $this->logH->log(4, $this->TAG, "Result from '$query' = ".print_r($result, TRUE));
         $names = array();
         for($index = 0; $index < count($result); $index++) {
            array_push($names, $result[$index]['datname']);
         }
         
         $this->logH->log(4, $this->TAG, "Database names = ".print_r($names, TRUE));
         
         return $names;
      } catch (WAException $ex) {
         $this->logH->log(1, $this->TAG, "Unable to get database names from the server");
         throw new WAException("Unable to get database names from the server", WAException::$CODE_DB_QUERY_ERROR, $ex);
      }
   }
   
   /**
    * This funciton returns a list of all table names in the specified database
    * 
    * @param type $databaseName  The database to check for tables in
    * 
    * @return Array
    * @throw WAException
    */
   public function getTableNames($databaseName) {
      $this->logH->log(4, $this->TAG, "Getting table names for database with name = '$databaseName'");
      $query = "SELECT table_name FROM information_schema.tables where table_catalog = '{$databaseName}' and table_schema = 'public'";//only fetch tables that are in the public schema
      try {
         $result = $this->runGenericQuery($query, true);
         $tables = array();
         for($index = 0; $index < count($result); $index++) {
            array_push($tables, $result[$index]['table_name']);
         }
         
         return $tables;
      } catch (WAException $ex) {
         $this->logH->log(1, $this->TAG, "Unable to get names of tables in '{$databaseName}'");
         throw new WAException("Unable to get table names from database server", WAException::$CODE_DB_QUERY_ERROR, $ex);
      }
   }
   
   /**
    * This function returns column details for the specified table
    * 
    * @param string $tableName Name of the table
    * @return Array Indexed array with column details for the specified table
    * @throws WAException
    */
   public function getTableDetails($database, $tableName) {
      //refer to http://www.postgresql.org/docs/9.4/static/infoschema-columns.html
      
      $query = "select column_name, data_type, character_maximum_length, is_nullable, column_default"
              . " from information_schema.columns"
              . " where table_name = '$tableName' and table_catalog = '$database'";
      //TODO: determine if column is serial and a key
      try {
         $result = $this->runGenericQuery($query, true);
         $columns = array();
         
         if(count($result) == 0){
            $this->logH->log(2, $this->TAG, "'$tableName' appears not to have any columns");
         }
         
         for($index = 0; $index < count($result); $index++) {
            $type = strtolower($result[$index]['data_type']);
            
            if($type == "character varying") $type = Database::$TYPE_VARCHAR;

            $nullable = false;
            if($result[$index]['is_nullable'] == "YES") {
               $nullable = true;
            }
            
            $default = $result[$index]['column_default'];
            if($default == "NULL") $default = null;//TODO: not sure if this will work with PostgreSQL

            $default = $result[$index]['Default'];
            if($default == "NULL") $default = null;//TODO: not sure if this will work with PostgreSQL
            
            $currColumn = array(
                "name" => $result[$index]['column_name'],
                "type" => $type,
                "length" => $result[$index]['character_maximum_length'],
                "nullable" => $nullable,
                "default" => $default,
                "key" => Database::$KEY_NONE
            );
            
            array_push($columns, $currColumn);
         }
         
         return $columns;
      } catch (WAException $ex) {
         $this->logH->log(1, $this->TAG, "Unable to get column details for table '$tableName' in '$database'");
         throw new WAException("Unable to get column details for table '$tableName'", WAException::$CODE_DB_QUERY_ERROR, $ex);
      }
   }
   
   /**
    * This function runs an insert query
    * 
    * @param string $table       The table where the query should be run
    * @param array $columns      An associative array with keys as column names and values as insert values
    * 
    * @throws WAException
    */
   public function runInsertQuery($table, $columns) {
      $this->logH->log(4, $this->TAG, "runInsertQuery called");
      $this->logH->log(4, $this->TAG, "columns = ".print_r($columns, true));
      
      $keys = array_keys($columns);
      $query = "insert into ".Database::$QUOTE_SI.$table.Database::$QUOTE_SI." (".implode(",", $keys).") ";
      $values ="values(";
      
      for($index = 0; $index < count($keys); $index++) {
         $values .= $columns[$keys[$index]];
         if($index == count($keys) - 1) {//last element
            $values .= ")";
         }
         else {
            $values .=", ";
         }
      }
      
      $query .= $values;
      $this->logH->log(4, $this->TAG, "Insert query = ".$query);
      try {
         $this->runGenericQuery($query);
      } catch (WAException $ex) {
         throw new WAException("An error occurred while trying to run insert query in '$table'", WAException::$CODE_DB_QUERY_ERROR, $ex);
      }
   }
   
   /**
    * This function creates an InnoDB table in the default database
    * 
    * @param string $name     The name of the table
    * @param array $columns   An array with the column details. Each array element
    *                         is an associative array and should contain the following
    *                         keys
    *                            - name      : Name of the column
    *                            - type      : Column datatype. Only variables $KEY_* allowed as values
    *                            - length    : Length of the column
    *                            - nullable  : Can either be true or false
    *                                          Nullable not supported when defining either primapr or unique key
    *                            - default   : Default column value. If value is null/NULL, literal sence is assumed
    *                                          Default value not supported when defining either primary or unique key
    *                                          NULL defult value not supported if nullable is false
    *                            - key       : Can either be $KEY_PRIMARY, $KEY_UNIQUE or $KEY_NONE.
    *                            - auto_incr : Can either be true or false. Only applies to $TYPE_INT type and non nullable fields
    * 
    * @throws WAException
    */
   public function runCreateTableQuery($name, $columns) {
      
      try {//check if table already exists
         $result = $this->getTableNames($this->getDatabaseName());
         if(in_array($name, $result) === false) {//the table does not exist
            //create table
            $createString = "create table ".Database::$QUOTE_SI.$name.Database::$QUOTE_SI." ";
            for($cIndex = 0; $cIndex < count($columns); $cIndex++) {
               if($cIndex == 0){
                  $createString .= "(";
               }

               $currColumn = $columns[$cIndex];
               //check if all the fields are there
               $defKeys = array_keys($currColumn);
               if(array_search("name", $defKeys) !== false
                       && array_search("type", $defKeys) !== false
                       && array_search("length", $defKeys) !== false
                       && array_search("nullable", $defKeys) !== false
                       && array_search("default", $defKeys) !== false
                       && array_search("key", $defKeys) !== false) {

                  //add name
                  $createString .= Database::$QUOTE_SI."{$currColumn['name']}".Database::$QUOTE_SI." ";
                  if($currColumn['type'] == Database::$TYPE_VARCHAR) {
                     if($currColumn['length'] != null) {
                        $createString .= "{$currColumn['type']}({$currColumn['length']}) ";
                     }
                     else {
                        $this->logH->log(1, $this->TAG, "Column '{$currColumn['name']}' is defined as type ".Database::$TYPE_VARCHAR." but length not defined for table with name as '$name' in database '{$this->getDatabaseName()}'");
                        throw new WAException("Column '{$currColumn['name']}' is defined as type ".Database::$TYPE_VARCHAR." but length not defined for table with name as '$name'", WAException::$CODE_DB_CREATE_ERROR, null);
                     }
                  }
                  else {
                     $createString .= "{$currColumn['type']} ";
                  }

                  if($currColumn['key'] == Database::$KEY_NONE
                          || $currColumn['key'] == Database::$KEY_UNIQUE) {//for columns that are not the primary key
                     
                     if($currColumn['key'] == Database::$KEY_UNIQUE) {
                        $createString .= "UNIQUE KEY ";
                     }
                     
                     //add default value
                     if($nullable === "null" 
                             && ($currColumn['default'] == null || $currColumn['default'] == "NULL" || $currColumn['default'] == "null")) {
                        //column is nullable and default value is null
                        $createString .= "default null ";
                     }
                     else if($nullable === "not null"
                             && ($currColumn['default'] == null || $currColumn['default'] == "NULL" || $currColumn['default'] == "null")) {
                        //column doesn't have a default value
                     }
                     else {
                        //default value is definately not null column however can be either nullable or not
                        if($currColumn['default'] !== null) {
                           $createString .= "default '{$currColumn['default']}' ";
                        }
                        else {
                           $createString .= "default null ";
                        }
                     }
                     
                     //add nullable
                     $nullable = "not null";
                     if($currColumn['nullable'] === true) $nullable = "null";
                     $createString .= "{$nullable} ";
                  }
                  else if($currColumn['key'] == Database::$KEY_PRIMARY){
                     $createString .= "PRIMARY KEY ";
                     
                     //check if is nullable
                     if($currColumn['nullable'] === true) {
                        $this->logH->log(1, $this->TAG, "Column with name '{$currColumn['name']}' in table '$name' defined as the primary key but nullable in database '{$this->getDatabaseName()}'");
                        throw new WAException("Column with name '{$currColumn['name']}' in table '$name' defined as the primary key but nullable", WAException::$CODE_DB_CREATE_ERROR, null);
                     }
                  }

                  if($cIndex == (count($columns) - 1)) {//last element
                     $createString .= ") ";
                  }
                  else {
                      $createString .=", ";
                  }
               }
               else {
                  throw new WAException("Defination fields missing for defining column with the difination '".print_r($currColumn, true)."'", WAException::$CODE_DB_CREATE_ERROR, null);
               }
            }

            //$createString .= "ENGINE = InnoDB";
            $this->logH->log(4, $this->TAG, "About to run the following query ".$createString);
            try {
               $this->runGenericQuery($createString);
               $this->logH->log(4, $this->TAG, "Query run successfully");
            } catch (WAException $ex) {
               $this->logH->log(1, $this->TAG, "An error occurred while trying to run the following query '{$createString}'");
               throw new WAException("Unable to run the create stament for the table '{$name}'", WAException::$CODE_DB_CREATE_ERROR, $ex);
            }
         }
         else {
            $this->logH->log(4, $this->TAG, "Table '$name' already exists. Not creating it");
         }
      } catch (WAException $ex) {
         $this->logH->log(1, $this->TAG, "An error occurred while trying to check wheter the table '$name' exists");
         throw new WAException("Unable to check whether the table '$name' already exists", WAException::$CODE_DB_CREATE_ERROR, $ex);
      }
   }
      
}
?>