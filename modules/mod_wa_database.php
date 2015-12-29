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
   public static $TYPE_INT = "numeric";
   public static $TYPE_DOUBLE = "double precision";
   public static $TYPE_VARCHAR = "varchar";
   public static $TYPE_TINYINT = "smallint";
   public static $TYPE_TIME = "time without time zone";
   public static $TYPE_DATE = "date";
   public static $TYPE_DATETIME = "timestamp without time zone";
   public static $TYPE_TIMESTAMP = "timestamp with time zone";
   public static $TYPE_BOOLEAN = "boolean";
   public static $MAX_TABLE_NAME_LENGTH = 63;

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

   private $DEFAULT_DATABASE = "dmp_master";//default database

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
      include_once 'mod_wa_file.php';
      //include_once $config['common_folder_path'].'azizi-shared-libs/dbmodules/mod_objectbased_dbase_v1.2.php';//TODO: make sure you are using dbase from this version and none other

      $this->config = $config;
      $this->logH = new LogHandler("./");//TODO: not sure if the default root dir specified in LogHandler is correct

      //initialize the PDO connection
      try {
         $this->connectedDb = $wInstanceId;
         if($this->connectedDb === NULL) $this->connectedDb = $this->DEFAULT_DATABASE;
         $this->logH->log(4, $this->TAG, "Trying to initialize database connection to {$this->connectedDb}");

         $this->pdoObject = new PDO("pgsql:dbname={$this->connectedDb};host={$config['dbloc']}", $config['user'], $config['pass']);
         $this->pdoObject->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      } catch (PDOException $ex) {
         $this->logH->log(1, $this->TAG, "An error occurred while trying to initialize database connection to {$this->connectedDb}");

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
            $stmt = $this->pdoObject->prepare($query);

            $result = $stmt->execute();
            if($result === true) {
               if($fetchResults === TRUE){
                  return $stmt->fetchAll(PDO::FETCH_ASSOC);
               }
            }
            else {
               $this->logH->log(1, $this->TAG, "An error occurred while trying to execute the SQL query '$query'");
               throw new WAException("Unable to execute this SQL statement '$query'", WAException::$CODE_DB_QUERY_ERROR, null);
            }

         } catch (PDOException $ex) {
            $this->logH->log(1, $this->TAG, "PDO Exception {$ex->getMessage()} thrown while trying to execute this SQL query '$query'");
            throw new WAException("PDOException thrown while trying to execute this SQL statement '$query'", WAException::$CODE_DB_QUERY_ERROR, $ex);
         }
      }
      else {
         $this->logH->log(1, $this->TAG, "Unable to run statement because PDO object is null for database object connected to '{$this->connectedDb}'");
         throw new WAException("Unable to run statement because PDO object is null", WAException::$CODE_DB_QUERY_ERROR, null);
      }
   }

   /**
    * Executes a query
    *
    * @param   string   $query         The query that will be executed
    * @param   array    $query_vars    (Optional) An array with the query variables if any
    * @param   boolean  $returnResult  (Optional) Whether to return a result
    * @param   string   $fetchMode     (Optional) The type of array that will be fetched. Can be MYSQ_BOTH, MYSQL_ASSOC, MYSQL_NUM. Defaults to MYSQL_ASSOC
    * @return  mixed    A multi-dimensioanl array with the results as fetched from the dbase when successful else it returns 1
    * @throws Exception
    *
    * @since   v1.2
    */
   public function executeQuery($query = '', $query_vars = NULL, $returnResult = false, $fetchMode = PDO::FETCH_ASSOC){
      if($this->pdoObject !== null) {
         try {
            //run a prepared statement (PDO will handle escaping)
            $stmt = $this->pdoObject->prepare($query);
            $result = $stmt->execute($query_vars);
            if($result === true) {
               if($returnResult === TRUE){
                  return $stmt->fetchAll($fetchMode);
               }
            }
            else {
               $this->logH->log(1, $this->TAG, "An error occurred while trying to execute the SQL query '$query'");
               throw new WAException("Unable to execute this SQL statement '$query'", WAException::$CODE_DB_QUERY_ERROR, null);
            }

         } catch (PDOException $ex) {
            $this->logH->log(1, $this->TAG, "PDO Exception {$ex->getMessage()} thrown while trying to execute this SQL query '$query'");
            throw new WAException("PDOException thrown while trying to execute this SQL statement '$query'", WAException::$CODE_DB_QUERY_ERROR, $ex);
         }
      }
      else {
         $this->logH->log(1, $this->TAG, "Unable to run statement because PDO object is null for database object connected to '{$this->connectedDb}'");
         throw new WAException("Unable to run statement because PDO object is null", WAException::$CODE_DB_QUERY_ERROR, null);
      }

////      echo '<pre> ***'. $query . print_r($query_vars, true) .'</pre>';
//      $this->dbStmt = $this->dbcon->prepare($query);
//      if(!$this->dbStmt->execute($query_vars)){
//         $err1 = $this->dbStmt->errorInfo();
//         if($err1[0] == 'HY093'){
//            $this->CreateLogEntry("Improper bound data types.\nStmt: $query\nVars:". print_r($query_vars, true), 'fatal');
//            $this->lastError = "There was an error while fetching data from the database.";
//            throw new Exception("Improper bound data types.\nStmt: $query\nVars:". print_r($query_vars, true), 0, null);
//         }
//         else{
//            $this->CreateLogEntry("Error while executing a db statement.\nVars:". print_r($query_vars, true) ."\nError Log:\n". print_r($err1, true), 'fatal', true);
//            $this->lastError = "There was an error while fetching data from the database.";
//            throw new Exception("Error while executing a db statement.\nVars:". print_r($query_vars, true) ."\nError Log:\n". print_r($err1, true), 0, null);
//         }
//      }
//      $err = $this->dbcon->errorInfo();
//      if($err[0] != 0){
//         $this->CreateLogEntry("Error while fetching data from the db.\n$err1[2]", 'fatal', true, '', '', true);
//         $this->lastError = "There was an error while fetching data from the database.";
//         throw new Exception("Error while fetching data from the db.\n$err1[2]", 0, null);
//      }
//
//      if($returnResult) {
//         return $this->dbStmt->fetchAll($fetchMode);
//      }
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
            $result = $this->checkIfDatabaseExists($name);

            if($result === false) {//no database with said name exists
               $this->logH->log(4, $this->TAG, "Database '$name' does not exists. Creating it");
               $query = "create database {$name} with owner = ".$this->config['user'];
               try {
                  $this->runGenericQuery($query);
               }
               catch (WAException $ex) {
                  throw new WAException("Unable to create the {$name} database", WAException::$CODE_DB_CREATE_ERROR, $ex);
               }
            }
            else {
               $this->logH->log(3, $this->TAG, "Database '$name' already exists. Not creating it");
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

   public function runDropDatabaseQuery($name) {
      if($name != null && $this->getDatabaseName() != $name) {
         try {
            $db2 = new Database($this->config, $name);
            $db2->dropAllOtherConnections();
            $db2->close();
            $query = "drop database ".$name;
            $this->runGenericQuery($query);
         } catch (WAException $ex) {
            throw new WAException("Unable to drop database '$name'", WAException::$CODE_DB_QUERY_ERROR, $ex);
         }
      }
      else {
         throw new WAException("Unable to drop database because object is currently connected to it", WAException::$CODE_DB_CLOSE_ERROR, null);
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
    * Check if a database exists in the server
    *
    * @param   string   $db_name    The name of the database
    * @return  boolean  Returns false if the database does not exists else it returns true
    * @throws  WAException    Throws an exception if there is an error while running the query
    */
   public function checkIfDatabaseExists($db_name){
      $this->logH->log(4, $this->TAG, "Check if the database '$db_name' exists");
      $query = "SELECT datname FROM pg_database WHERE datistemplate = false and datname=:datname";
      try {
         $result = $this->executeQuery($query,array('datname' => $db_name), true);
         if(count($result) == 0) return false;
         else if(count($result) == 1) return false;
         else{
            $this->logH->log(1, $this->TAG, "There are multiple database with the name '$db_name'");
            throw new WAException("Multiple databases with the same name '$db_name'", WAException::$CODE_DB_QUERY_ERROR, $ex);
         }
      } catch (WAException $ex) {
         $this->logH->log(1, $this->TAG, "Error while checking if the database '$db_name' exists");
         throw new WAException("Unable to get database names from the server", WAException::$CODE_DB_QUERY_ERROR, $ex);
      }
   }

   /**
    * This function returns a list of all database names from the server
    *
    * @return Array
    * @throws WAException
    */
   public function getDatabaseNames($user) {
      //$query = "show databases";
      $query = "select a.db_name, a.dmp_name from projects as a inner join project_access as b on a.db_name=b.instance where b.user_granted = :cur_user order by a.dmp_name";
      $this->logH->log(4, $this->TAG, "Getting all the databases which the user '$user' has access to...");
      try {
         $result = $this->executeQuery($query, array('cur_user' => $user), true);
         $names = array();
         $dbCount = count($result);
         for($index = 0; $index < $dbCount; $index++) {
            array_push($names, $result[$index]['db_name']);
         }
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
         $res_count = count($result);
         for($index = 0; $index < $res_count; $index++) {
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
         $pKeys = $this->getTablePrimaryKeys($tableName);
         $uKeys = $this->getTableUniqueKeys($tableName);
         $result = $this->runGenericQuery($query, true);
         $columns = array();

         if(count($result) == 0){
            $this->logH->log(2, $this->TAG, "'$tableName' appears not to have any columns");
         }

         $res_count = count($result);
         for($index = 0; $index < $res_count; $index++) {
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
            $key = Database::$KEY_NONE;
            if(in_array($result[$index]['column_name'], $pKeys)) $key = Database::$KEY_PRIMARY;
            else if(in_array($result[$index]['column_name'], $uKeys)) $key = Database::$KEY_UNIQUE;
            $currColumn = array(
                "name" => $result[$index]['column_name'],
                "type" => $type,
                "length" => $result[$index]['character_maximum_length'],
                "nullable" => $nullable,
                "default" => $default,
                "key" => $key
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
   public function runInsertQuery($table, $columns, $returnStatement = false) {
      $keys = array_keys($columns);
      $key_count = count($keys);
      $query = "insert into ".Database::$QUOTE_SI.$table.Database::$QUOTE_SI." (".Database::$QUOTE_SI.implode(Database::$QUOTE_SI.",".Database::$QUOTE_SI, $keys).Database::$QUOTE_SI.") ";
      $values ="values(";

      for($index = 0; $index < $key_count; $index++) {
         $values .= $columns[$keys[$index]];
         if($index == count($keys) - 1) {//last element
            $values .= ")";
         }
         else {
            $values .=", ";
         }
      }

      $query .= $values;
      try {
         if($returnStatement == true){
            return $query;
         }
         else {
            $this->runGenericQuery($query);
         }
      } catch (WAException $ex) {
         throw new WAException("An error occurred while trying to run insert query in '$table'", WAException::$CODE_DB_QUERY_ERROR, $ex);
      }
   }

   public function runUpdateQuery($table, $columns, $condition) {
      try {
         $this->logH->log(4, $this->TAG, "runUpdateQuery called");
         $this->logH->log(4, $this->TAG, "columns = ".print_r($columns, true));

         $keys = array_keys($columns);
         $columnValues = "";
         foreach($keys as $currKey) {
            if(strlen($columnValues) == 0) {
               $columnValues = Database::$QUOTE_SI.$currKey.Database::$QUOTE_SI." = ".$columns[$currKey];
            }
            else {
               $columnValues .= ", ".Database::$QUOTE_SI.$currKey.Database::$QUOTE_SI." = ".$columns[$currKey];
            }
         }
         $query = "update ".Database::$QUOTE_SI.$table.Database::$QUOTE_SI." set ".$columnValues." where ".$condition;
         $this->logH->log(4, $this->TAG, "Update query = ".$query);
         $this->runGenericQuery($query);
      }
      catch (WAException $ex) {
         throw new WAException("An error occurred while trying to run insert query in '$table'", WAException::$CODE_DB_QUERY_ERROR, $ex);
      }
   }

   /**
    * This function gets the primary keys corresponding to a table
    * @param type $tableName  Name of the table to get the keys
    */
   public function getTablePrimaryKeys($tableName) {
      $query = "SELECT a.attname, format_type(a.atttypid, a.atttypmod) AS data_type
         FROM pg_index i
         JOIN pg_attribute a ON a.attrelid = i.indrelid
         AND a.attnum = ANY(i.indkey)
         WHERE i.indrelid = (SELECT oid FROM pg_class WHERE relname = '$tableName')
         AND i.indisprimary";
      try {
         $result = $this->runGenericQuery($query, true);
         $res_count = count($result);
         $pkeys = array();
         if(is_array($result)) {
            if($res_count == 0) {
               $this->logH->log(2, $this->TAG, "'$tableName' does not have a primary key in the database '{$this->getDatabaseName()}'");
            }

            for($index = 0; $index < $res_count; $index++) {
               array_push($pkeys, $result[$index]['attname']);
            }
            return $pkeys;
         }
         else {
            throw new WAException("Unable to retrieve primary keys for '$tableName' because of an error in the query", WAException::$CODE_DB_QUERY_ERROR, null);
         }
      } catch (WAException $ex) {
         throw new WAException("Unable to retrieve primary keys for '$tableName'", WAException::$CODE_DB_QUERY_ERROR, $ex);
      }
   }

   /**
    * Get the foreign keys for the given table name
    * @param type $tableName
    * @return type
    * @throws WAException
    */
   public function getTableForeignKeys($tableName) {
      $query = "SELECT tc.constraint_name, array_agg(kcu.column_name::text) columns,
         max(ccu.table_name::text) AS foreign_table_name,
         array_agg(ccu.column_name::text) AS foreign_column_name
         FROM information_schema.table_constraints AS tc
         JOIN information_schema.key_column_usage AS kcu
         ON tc.constraint_name = kcu.constraint_name
         JOIN information_schema.constraint_column_usage AS ccu
         ON ccu.constraint_name = tc.constraint_name
         WHERE constraint_type = 'FOREIGN KEY' AND tc.table_name='$tableName' group by tc.constraint_name";
      try {
         $result = $this->runGenericQuery($query, true);
         if(is_array($result)) {
            $foreignKeys = array();
            $res_count = count($result);
            for($index = 0; $index < $res_count; $index++) {
               $this->logH->log(3, $this->TAG, "First character in foreign key '{$result[$index]['columns']}'  = ".substr($result[$index]['columns'], 0, 1));
               if(substr($result[$index]['columns'], 0, 2) == '{"') {
                  $columns = array_values(array_unique(explode('","', substr($result[$index]['columns'], 2, -2))));
               }
               else {
                  $columns = array_values(array_unique(explode(',', substr($result[$index]['columns'], 1, -1))));
               }
               if(substr($result[$index]['foreign_column_name'], 0, 2) == '{"') {
                  $refColumns = array_values(array_unique(explode('","', substr($result[$index]['foreign_column_name'], 2, -2))));
               }
               else {
                  $refColumns = array_values(array_unique(explode(',', substr($result[$index]['foreign_column_name'], 1, -1))));
               }
               $foreignKeys[$result[$index]['constraint_name']] = array(
                   "columns" => $columns,
                   "ref_table" => $result[$index]['foreign_table_name'],
                   "ref_columns" => $refColumns
               );
            }
            return $foreignKeys;
         }
      } catch (WAException $ex) {
         throw new WAException("Unable to get foreign keys for '$tableName'", WAException::$CODE_DB_QUERY_ERROR, $ex);
      }
   }

   public function getTableUniqueKeys($tableName) {
      $query = "SELECT kcu.column_name
         FROM information_schema.table_constraints AS tc
         JOIN information_schema.key_column_usage AS kcu
         ON tc.constraint_name = kcu.constraint_name
         JOIN information_schema.constraint_column_usage AS ccu
         ON ccu.constraint_name = tc.constraint_name
         WHERE constraint_type = 'UNIQUE' AND tc.table_name='$tableName'";
      try {
         $result = $this->runGenericQuery($query, true);
         if(is_array($result)) {
            $keys = array();
            $res_count = count($result);
            for($index = 0; $index < $res_count; $index++) {
               array_push($keys, $result[$index]['column_name']);
            }
            return $keys;
         }
         else {
            throw new WAException("Unable to retrieve unique keys for '$tableName'", WAException::$CODE_DB_QUERY_ERROR, null);
         }
      } catch (WAException $ex) {
         throw new WAException("Unable to retrieve unique keys for '$tableName'", WAException::$CODE_DB_QUERY_ERROR, $ex);
      }
   }

   /**
    * This function dumps the currently connected database to an SQL file
    *
    * @param type $workingDir    The workflow's working directory
    * @param type $filename      Name to be given to the backup file
    */
   public function backup($workingDir, $filename, $user, $description) {
      /*
       * #since you have to use the same pg_dump version as the server
       * #install using rpm from http://yum.postgresql.org/repopackages.php#pg93
       * wget -c http://yum.postgresql.org/9.3/redhat/rhel-6-x86_64/pgdg-centos93-9.3-1.noarch.rpm
       * yum install pgdg-centos93-9.3-1.noarch.rpm
       * #install just the client
       * yum install postgresql93.x86_64
       * #find out where pg_dump was put
       * rpm -ql postgresql93.x86_64|grep bin
       * #save the pg_dump path in the repository config file
       */
      try {
         $this->logH->log(3, $this->TAG, "Backing up '".$this->getDatabaseName()."'");
         //($config, $workflowID, $database, $workingDir, $type, $filename = null)
         $dumpFile = new WAFile($this->config, $this->getDatabaseName(), new Database($this->config, $this->getDatabaseName()), $workingDir, WAFile::$TYPE_BACKUP, $filename);
         $subDirPath = $dumpFile->createWorkingSubdir();
         $path = $subDirPath . "/" . $filename;
         $command = "export PGPASSWORD='".$this->config['cypher']."';".$this->config['pg_dump']." --clean -U ".$this->config['user']." {$this->getDatabaseName()} -h {$this->config['dbloc']} > $path";
         $this->logH->log(4, $this->TAG, "Backup command is '$command'");
         $output = shell_exec($command);
         $this->logH->log(3, $this->TAG, "Output from backup command is '$output'");
         if(file_exists($path)) {
            $columns = array(
               "location" => "'$filename'",
               "added_by" => "'$user'",
               "time_added" => "'{$this->getMySQLTime()}'",
               "last_modified" => "'{$this->getMySQLTime()}'",
               "workflow_type" => "'".WAFile::$TYPE_BACKUP."'",
               "comment" => "'$description'"
            );
            $this->runInsertQuery(WAFile::$TABLE_META_FILES, $columns);
         }
         else {
            $this->logH->log(1, $this->TAG, "Backup command yielded no output file for database '".$this->getDatabaseName()."'");
            throw new WAException("Backup command yielded no output file", WAException::$CODE_DB_BACKUP_ERROR, null);
         }
      } catch (WAException $ex) {
            $this->logH->log(1, $this->TAG, "An error occurred while trying to create backup file for database '".$this->getDatabaseName()."'");
            throw new WAException("An error occurred while trying to create backup file for database", WAException::$CODE_DB_BACKUP_ERROR, $ex);
      }
   }

   /**
    * This function restores a database from an SQL file. If the database does not
    * exist, it creates it (instead of restoring it).
    *
    * @param type $databaseName     The name of the database
    * @param type $restoreFile      The sql file to be used to restore the file
    * @param type $databaseExists   True if the database already exists
    * @throws WAException
    */
   public function restore($databaseName, $restoreFile, $databaseExists = true) {
      /*
       * #since you have to use the same pg_dump version as the server
       * #install using rpm from http://yum.postgresql.org/repopackages.php#pg93
       * wget -c http://yum.postgresql.org/9.3/redhat/rhel-6-x86_64/pgdg-centos93-9.3-1.noarch.rpm
       * yum install pgdg-centos93-9.3-1.noarch.rpm
       * #install just the client
       * yum install postgresql93.x86_64
       * #find out where pg_dump was put
       * rpm -ql postgresql93.x86_64|grep bin
       * #save the pg_dump path in the repository config file
       */
      if(file_exists($restoreFile)) {
         try {
            $this->logH->log(3, $this->TAG, "Restoring '$databaseName' to state defined in '$restoreFile'");
            //drop all the tables
            if($databaseExists == true) {
               $db2 = new Database($this->config, $databaseName);
               $db2->dropAllOtherConnections();
               $db2->runGenericQuery("drop schema public cascade");
               $db2->runGenericQuery("create schema public");
               $db2->close();
               $this->runDropDatabaseQuery($databaseName);
            }
            $this->runCreatDatabaseQuery($databaseName);//make sure the database exists
            $command = "export PGPASSWORD='".$this->config['cypher']."';".$this->config['psql']." -U ".$this->config['user']." {$databaseName} -f $restoreFile -h {$this->config['dbloc']}";
            $this->logH->log(4, $this->TAG, "pg_restore command is ".$command);
            $output = shell_exec($command);
            $this->logH->log(4, $this->TAG, "Message from pg_restore is ".$output);
         } catch (WAException $ex) {
            $this->logH->log(1, $this->TAG, "Could not restore database '$databaseName' because a database error occurred");
            throw new WAException("Could not restore database because a database (SQL) error occurred", WAException::$CODE_DB_QUERY_ERROR, $ex);
         }
      }
      else {
         $this->logH->log(1, $this->TAG, "Could not restore database '$databaseName' because backup file '$restoreFile' does not exist");
         throw new WAException("Could not restore database because backup file does not exist", WAException::$CODE_DB_BACKUP_ERROR, null);
      }
   }

   public function dropAllOtherConnections() {
      $query = "SELECT pg_terminate_backend(pg_stat_activity.pid)
               FROM pg_stat_activity
               WHERE datname = current_database()
               AND pid <> pg_backend_pid()";
      //change procpid to pid when updating to PostgreSQL 9.2 and above
      try {
         $this->runGenericQuery($query);
      } catch (WAException $ex) {
         throw new WAException("Could not drop all other exisiting connections to database", WAException::$CODE_DB_QUERY_ERROR, $ex);
      }
   }

   /**
    * This function alters a table column
    *
    * @param type $tableName  Name of the table where column is
    * @param type $existing   All properties for the existing column
    * @param type $new        All properties for the column after altering
    */
   public function runAlterColumnQuery($tableName, $existing, $new) {
      //instead of altering the current column, add new column after existing then drop existing
      $isSpecial = false;
      if($existing['key'] === Database::$KEY_PRIMARY || $existing['key'] === Database::$KEY_UNIQUE) $isSpecial = true;
      try {
         if($isSpecial == false) {//column considered special if it is currently part of a key
            $this->logH->log(3, $this->TAG, "Column '{$existing['name']}' not being considered as special during alter");
            //if column was previously part of the primary key, the primary key will be deleted
            //if column is going to be part of the primary key, first drop the existing primary key then add the column to primary key
            $tmpName = 'temp_column_odk_will_delete';
            $query = "alter table ".Database::$QUOTE_SI.$tableName.Database::$QUOTE_SI." rename column ".Database::$QUOTE_SI.$existing['name'].Database::$QUOTE_SI." to ".Database::$QUOTE_SI.$tmpName.Database::$QUOTE_SI;
            $this->logH->log(4, $this->TAG, "1. Renaming the column via $query");
            $this->runGenericQuery($query);
            $query = "alter table ".Database::$QUOTE_SI.$tableName.Database::$QUOTE_SI." add column ";
            $this->logH->log(4, $this->TAG, "2. Adding a column via $query");
            $query .= $this->getColumnExpression($new['name'], $new['type'], $new['length'], $new['key'], $new['default'], $new['nullable']);
            $this->runGenericQuery($query);
            $query = "alter table ".Database::$QUOTE_SI.$tableName.Database::$QUOTE_SI." drop column ".Database::$QUOTE_SI.$tmpName.Database::$QUOTE_SI;
            $this->logH->log(4, $this->TAG, "3. Deleting a column via $query");
            $this->runGenericQuery($query);
            if($new['key'] == Database::$KEY_PRIMARY){
               $this->addColumnsToPrimaryKey($tableName, array($new['name']));
            }
         }
         else {//column is special. That means the previous method (deleting existing column and replacing it with a new one wont work. The case for columns that are already part of a key)
            //check what needs to change in the column. Make sure you change the name last
            $this->logH->log(3, $this->TAG, "Column '{$existing['name']}' not being considered as special during alter");
            $this->logH->log(3, $this->TAG, "Column '{$existing['name']}' current details".print_r($existing, true));
            $this->logH->log(3, $this->TAG, "Column '{$existing['name']}' New details".print_r($new, true));

            if($new['name'] == $existing['name']) $new['name'] = null;
            if($new['type'] == $existing['type'] && $new['length'] == $existing['length']) {
               $new['type'] = null;
               $new['length'] = null;
            }
            if($new['nullable'] == null) $new['nullable'] = "null";
            if($new['nullable'] == "null" && $existing['nullable'] == null) $new['nullable'] = null;
            else if($new['nullable'] == $existing['nullable']) $new['nullable'] = null;
            if($new['default'] == $existing['default']) $new['default'] = null;
            if($new['key'] == $existing['key']) $new['key'] = null;
            //type
            $this->logH->log(4, $this->TAG, "Column '{$existing['name']}' being considered as special during alter");
            $smFixed = false;
            if($new['type'] != null) {
               if($new['type'] == Database::$TYPE_VARCHAR) $new['type'] .= "({$new['length']})";
               $query = "alter table ".Database::$QUOTE_SI.$tableName.Database::$QUOTE_SI
                     . " alter column ".Database::$QUOTE_SI.$existing['name'].Database::$QUOTE_SI
                     . " type ".$new['type'];
               $this->runGenericQuery($query);
               $smFixed = true;
               $this->logH->log(4, $this->TAG, "Changing type of {$existing['name']} to {$new['type']}");
            }
            //nullable
            if($new['nullable'] != null) {
               $nullValue = "set not null";
               if($new['nullable'] == false) {
                  $nullValue = "drop not null";
               }
               $query = "alter table ".Database::$QUOTE_SI.$tableName.Database::$QUOTE_SI
                     . " alter column ".Database::$QUOTE_SI.$existing['name'].Database::$QUOTE_SI
                     . " ".$nullValue;
               $this->runGenericQuery($query);
               $smFixed = true;
               $this->logH->log(4, $this->TAG, "Changing nullable of {$existing['name']} to $nullValue");
            }
            //default
            if($new['default'] != null) {//for default value of 'null' use null the string
               $query = "alter table ".Database::$QUOTE_SI.$tableName.Database::$QUOTE_SI
                     . " alter column ".Database::$QUOTE_SI.$existing['name'].Database::$QUOTE_SI
                     . " set default {$new['default']}";
               $this->runGenericQuery($query);
               $smFixed = true;
               $this->logH->log(4, $this->TAG, "Changing default value to {$new['default']} of {$existing['name']}");
            }
            if($new['key'] == Database::$KEY_PRIMARY) {
               $this->addColumnsToPrimaryKey($tableName, array($existing['name']));
               $smFixed = true;
               $this->logH->log(4, $this->TAG, "Adding {$existing['name']} to primary key");
            }
            else if($new['key'] == Database::$KEY_UNIQUE) {
               $query = "alter table ".Database::$QUOTE_SI.$tableName.Database::$QUOTE_SI
                     . " add constraint ".Database::$QUOTE_SI.$existing['name'].Workflow::generateRandomID(4).Database::$QUOTE_SI." unique(".Database::$QUOTE_SI.$existing['name'].Database::$QUOTE_SI.")";
               $this->runGenericQuery($query);
               $smFixed = true;
               $this->logH->log(4, $this->TAG, "Making {$existing['name']} unique");
            }
            //name
            if($new['name'] != null && $existing['name'] != $new['name']) {
               $query = "alter table ".Database::$QUOTE_SI.$tableName.Database::$QUOTE_SI
                     . " rename column ".Database::$QUOTE_SI.$existing['name'].Database::$QUOTE_SI
                     . " to".Database::$QUOTE_SI.$new['name'].Database::$QUOTE_SI;
               $this->runGenericQuery($query);
               $smFixed = true;
               $this->logH->log(4, $this->TAG, "Changing name of {$existing['name']} to {$new['name']}");
            }
            if($smFixed == true) {
               $this->logH->log(3, $this->TAG, "{$new['name']} in $tableName altered");
            }
            else {
               $this->logH->log(3, $this->TAG, "Could not find a reason for altering {$existing['name']} in $tableName");
            }
         }
      } catch (WAException $ex) {
         throw new WAException("Could not alter '{$existing['name']}' in '$tableName'", WAException::$CODE_DB_CREATE_ERROR, $ex);
      }
   }

   /**
    * This function adds a column to an already existing table
    *
    * @param type $tableName     Name of the table where column is
    * @param type $columnDetails All properties for the column
    */
   public function runAddColumnQuery($tableName, $columnDetails) {
      try {
         $columnExpression = $this->getColumnExpression($columnDetails['name'], $columnDetails['type'], $columnDetails['length'], $columnDetails['key'], $columnDetails['default'], $columnDetails['nullable']);
         $query = "alter table ".Database::$QUOTE_SI.$tableName.Database::$QUOTE_SI." add column ".$columnExpression;
         $this->runGenericQuery($query);
         if($columnDetails['key'] == Database::$KEY_PRIMARY) {
            $this->addColumnsToPrimaryKey($tableName, array($columnDetails['name']));
         }
      } catch (WAException $ex) {
         throw new WAException("Could not alter '{$existing['name']}' in '$tableName'", WAException::$CODE_DB_CREATE_ERROR, $ex);
      }
   }

   /**
    * This function adds the specified columns to the table's primary key
    *
    * @param type $tableName
    * @param type $primaryKeyColumns
    */
   public function addColumnsToPrimaryKey($tableName, $primaryKeyColumns) {
      try {
         $existingKey = $this->getTablePrimaryKeys($tableName);
         if(count($existingKey) > 0) {//drop existing primary key
            $query = "alter table ".Database::$QUOTE_SI.$tableName.Database::$QUOTE_SI." drop constraint ".Database::$QUOTE_SI.$tableName."_pkey".Database::$QUOTE_SI;
            $this->runGenericQuery($query);
         }
         //make sure only unique columns exist in $primaryKeyColumns
         $exk_count = count($existingKey);
         for($index = 0; $index < $exk_count; $index++) {
            if(!in_array($existingKey[$index], $primaryKeyColumns)) {
               array_push($primaryKeyColumns, $existingKey[$index]);
            }
         }

         //try creating the new primary key
         $pKey = Database::$QUOTE_SI . implode(Database::$QUOTE_SI.",".Database::$QUOTE_SI, $primaryKeyColumns) . Database::$QUOTE_SI;
         $query = "alter table ".Database::$QUOTE_SI.$tableName.Database::$QUOTE_SI." add primary key($pKey)";
         $this->runGenericQuery($query);

      } catch (WAException $ex) {
         throw new WAException("Unable to update the primary key for '$tableName'", WAException::$CODE_DB_QUERY_ERROR, $ex);
      }
   }

   /**
    * This function adds a foreign key to the specified table
    *
    * @param type $table         The table to add the foreign key
    * @param type $columns       The columns in the foreign key
    * @param type $refTable      The reference table
    * @param type $refColumns    The reference Columns
    */
   public function addForeignKey($table, $columns, $refTable, $refColumns) {
      if(count($columns) == count($refColumns)) {
         try {
            $this->logH->log(3, $this->TAG, "Creating a foreign key in '$table'");
            $foreignKeys = $this->getTableForeignKeys($table);
            $fKeys = array_keys($foreignKeys);
            $fkey_count = count($fKeys);
            //check if a foreign key joins table with refTable
            for($index = 0; $index < $fkey_count; $index++) {
               if($foreignKeys[$fKeys[$index]]['ref_table'] == $refTable) {
                  $query = "alter table ".Database::$QUOTE_SI.$table.Database::$QUOTE_SI." drop constraint ".Database::$QUOTE_SI.$fKeys[$index].Database::$QUOTE_SI;
                  $this->logH->log(2, $this->TAG, "About to drop '$table' foreign key ".  print_r($foreignKeys[$fKeys[$index]], true));
                  $this->runGenericQuery($query);
               }
            }
            //add foreign key
            $columns = Database::$QUOTE_SI.implode(Database::$QUOTE_SI.','.Database::$QUOTE_SI, $columns).Database::$QUOTE_SI;
            $rColumns = Database::$QUOTE_SI.implode(Database::$QUOTE_SI.','.Database::$QUOTE_SI, $refColumns).Database::$QUOTE_SI;
            $query = "alter table ".Database::$QUOTE_SI.$table.Database::$QUOTE_SI." add foreign key($columns) references ".Database::$QUOTE_SI.$refTable.Database::$QUOTE_SI."($rColumns)";
            $this->runGenericQuery($query);
         } catch (WAException $ex) {
            throw new WAException("Could not create foreign key from '$table' referencing '$refTable'", WAException::$CODE_DB_QUERY_ERROR, $ex);
         }
      }
      else {
         throw new WAException("Could not create foreign key from '$table' referencing '$refTable' because of a column count missmatch", WAException::$CODE_DB_QUERY_ERROR, null);
      }
   }

   /**
    * This function alters a table
    *
    * @param string $currName    Current table name
    * @param string $newName     New table name
    *
    * @throws WAException
    */
   public function runAlterTableQuery($currName, $newName) {
      $query = "alter table ".Database::$QUOTE_SI.$currName.Database::$QUOTE_SI." rename to ".Database::$QUOTE_SI.$newName.Database::$QUOTE_SI;
      try {
         $this->runGenericQuery($query);
      } catch (WAException $ex) {
         throw new WAException("Unable to rename '$currName' to '$newName'", WAException::$CODE_DB_QUERY_ERROR, null);
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
   public function runCreateTableQuery($name, $columns, $linkTables = false, $parentTable = null) {

      try {//check if table already exists
         $result = $this->getTableNames($this->getDatabaseName());
         if(in_array($name, $result) === false) {//the table does not exist
            //create table
            $pKey = array();
            $createString = "create table ".Database::$QUOTE_SI.$name.Database::$QUOTE_SI." ";
            $columnNames = array();
            $clmn_count = count($columns);
            for($cIndex = 0; $cIndex < $clmn_count; $cIndex++) {
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
                  try {
                     array_push($columnNames, $currColumn['name']);
                     $currColumnExpression = $this->getColumnExpression($currColumn['name'], $currColumn['type'], $currColumn['length'], $currColumn['key'], $currColumn['default'], $currColumn['nullable']);
                     if($currColumn['key'] == Database::$KEY_PRIMARY) array_push ($pKey, $currColumn['name']);
                     $createString .= $currColumnExpression;
                  } catch (WAException $ex) {
                     throw new WAException("Unable to create database column expression", WAException::$CODE_DB_CREATE_ERROR, $ex);
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
               if(count($pKey) > 0) $this->addColumnsToPrimaryKey($name, $pKey);
               if($linkTables == true && $name != "main_sheet" && array_search("secondary_key", $columnNames) !== false && $parentTable != null) {
                  $this->addForeignKey($name, array("secondary_key"), $parentTable, array("primary_key"));
               }
            } catch (WAException $ex) {
               $this->logH->log(1, $this->TAG, "An error occurred while trying to run the following query '{$createString}'");
               throw new WAException("Unable to run the create stament for the table '{$name}'", WAException::$CODE_DB_CREATE_ERROR, $ex);
            }
         }
         else {
            $this->logH->log(3, $this->TAG, "Table '$name' already exists. Not creating it");
         }
      } catch (WAException $ex) {
         $this->logH->log(1, $this->TAG, "An error occurred while trying to check wheter the table '$name' exists");
         throw new WAException("Unable to check whether the table '$name' already exists", WAException::$CODE_DB_CREATE_ERROR, $ex);
      }
   }

   /**
    * This function returns a column expression string that defines the characteristics
    * of a column to be used in insert and update statements
    *
    * @throws WAException
    */
   private function getColumnExpression($name, $type, $length, $key, $default, $isNullable) {
      $createString = Database::$QUOTE_SI."$name".Database::$QUOTE_SI." ";
      if($type == Database::$TYPE_VARCHAR) {
         if($length != null) {
            $createString .= "{$type}({$length}) ";
         }
         else {
            $this->logH->log(1, $this->TAG, "Column '{$name}' is defined as type ".Database::$TYPE_VARCHAR." but length not defined for table with name as '$name' in database '{$this->getDatabaseName()}'");
            throw new WAException("Column '{$name}' is defined as type ".Database::$TYPE_VARCHAR." but length not defined for table with name as '$name'", WAException::$CODE_DB_CREATE_ERROR, null);
         }
      }
      else {
         $createString .= "{$type} ";
      }

      if($key == Database::$KEY_NONE
              || $key == Database::$KEY_UNIQUE) {//for columns that are not the primary key

         if($key == Database::$KEY_UNIQUE) {
            $this->logH->log(3, $this->TAG, "'$name' has a unique key");
            $createString .= "UNIQUE ";
         }

         //add default value
         if($isNullable == true
                 && ($default == null || $default == "NULL" || $default == "null")) {
            //column is nullable and default value is null
            $createString .= "default null ";
         }
         else if($isNullable == false
                 && ($default == null || $default == "NULL" || $default == "null")) {
            //column doesn't have a default value
         }
         else {
            //default value is definately not null column however can be either nullable or not
            if($default !== null) {
               $createString .= "default {$default} ";
            }
            else {
               $createString .= "default null ";
            }
         }

         //add nullable
         $nullable = "not null";
         if($isNullable == true) $nullable = "null";
         $createString .= "{$nullable} ";
      }
      /*else if($key == Database::$KEY_PRIMARY){
         $createString .= "PRIMARY KEY ";

         //check if is nullable
         if($isNullable === true) {
            $this->logH->log(1, $this->TAG, "Column with name '{$name}' defined as the primary key but nullable in database '{$this->getDatabaseName()}'");
            throw new WAException("Column with name '{$name}' defined as the primary key but nullable", WAException::$CODE_DB_CREATE_ERROR, null);
         }
      }*/

      return $createString;
   }

   public function getFromClause($table, $mainTable, $fromClause = "") {
      if($table != $mainTable) {
         //get the foreign keys
         $foreignKeys = $this->getTableForeignKeys($table);
         if(count($foreignKeys) == 0) {
            $this->logH->log(1, $this->TAG, "No foreign keys gotten");
            throw new WAException("$table is not tied to any othe table", WAException::$CODE_WF_PROCESSING_ERROR, null);
         }
         else if(count($foreignKeys) == 1) {
            $arrayKeys = array_keys($foreignKeys);
            $foreignKeys = $foreignKeys[$arrayKeys[0]];
            if(strlen($fromClause) == 0) {
               $fromClause = "from ".Database::$QUOTE_SI.$table.Database::$QUOTE_SI." inner join".Database::$QUOTE_SI.$foreignKeys['ref_table'].Database::$QUOTE_SI;
            }
            else {
               $fromClause .= " inner join".Database::$QUOTE_SI.$foreignKeys['ref_table'].Database::$QUOTE_SI;
            }
            $fromClause .= " on ";
            $onClause = "";
            $fkc_count = count($foreignKeys['columns']);
            for($index = 0; $index < $fkc_count; $index++) {
               if(strlen($onClause) == 0) {
                  $onClause .= Database::$QUOTE_SI.$table.Database::$QUOTE_SI.".".Database::$QUOTE_SI.$foreignKeys['columns'][$index].Database::$QUOTE_SI." = ".Database::$QUOTE_SI.$foreignKeys['ref_table'].Database::$QUOTE_SI.".".Database::$QUOTE_SI.$foreignKeys['ref_columns'][$index].Database::$QUOTE_SI;
               }
               else {
                  $onClause .= " and ".Database::$QUOTE_SI.$table.Database::$QUOTE_SI.".".Database::$QUOTE_SI.$foreignKeys['columns'][$index].Database::$QUOTE_SI." = ".Database::$QUOTE_SI.$foreignKeys['ref_table'].Database::$QUOTE_SI.".".Database::$QUOTE_SI.$foreignKeys['ref_columns'][$index].Database::$QUOTE_SI;
               }
            }
            $fromClause .= $onClause;
            if($foreignKeys['ref_table'] == $mainTable) {
               return $fromClause;
            }
            else {
               return $this->getFromClause($foreignKeys['ref_table'], $mainTable, $fromClause);
            }
         }
         else {
            $this->logH->log(1, $this->TAG, "'$table' is linked to more than one parent table");
            throw new WAException("$table is linked to more than one parent table", WAException::$CODE_WF_PROCESSING_ERROR, null);
         }
      }
      else {
         $this->logH->log(1, $this->TAG, "'$table' is linked $'$mainTable'");
      }
   }

   public function getNumberOfParents($table, $number = 0) {
      try {
         $foreignKeys = $this->getTableForeignKeys($table);
         if(count($foreignKeys) == 1) {
            $arrayKeys = array_keys($foreignKeys);
            $number++;
            $number = $this->getNumberOfParents($foreignKeys[$arrayKeys[0]]['ref_table'], $number);
         }
         else if(count($foreignKeys) > 1){
            throw new WAException("Cannot get number of parents for $table since it has more than one foreign key", WAException::$CODE_WF_FEATURE_UNSUPPORTED_ERROR, null);
         }
      } catch (WAException $ex) {
         throw new WAException("Could not get the number of parents for $table", WAException::$CODE_WF_PROCESSING_ERROR, $ex);
      }
      return $number;
   }

}
?>