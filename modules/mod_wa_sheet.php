<?php

/**
 * This class implements logic for a table in the workflow. Tables can be
 * described as What was initially excel sheets in data files
 */

class WASheet {
   private $TAG = "washeet";
   private $config;
   private $lH;
   private $database;
   private $excelObject; //excel object and columns can be used interchangebly. They both store the sheet schema
   private $columns;//array of WAColumns in this object
   private $sheetName;
   private $columnArray;//this array stores the sheet columns in an array with the first excel sheet being the indexes of the first level arrays consecutive rows as array items
   private $fileDetails;
   private $isMain;

   /**
    * Default constructor for this class
    *
    * @param Object   $config       The repository_config object
    * @param Database $database     The database object to be used for queries
    * @param PHPExcel $excelObject  The excel object where data is to be read from
    * @param string   $sheetName    Name of the sheet in the excelObject to process
    *
    */
   public function __construct($config, $database, $excelObject, $sheetName, $fileDetails = null) {
      include_once 'mod_log.php';
      include_once 'mod_wa_database.php';
      include_once 'mod_wa_exception.php';
      include_once 'mod_wa_column.php';

      $this->config = $config;
      $this->lH = new LogHandler("./");
      $this->database = $database;
      $this->excelObject = $excelObject;
      $this->sheetName = $sheetName;
      $this->columns = array();
      $this->fileDetails = $fileDetails;
      $this->isMain = false;

      if($this->excelObject == null) {//means data already stored in database
         $this->getCachedCopy();//this function will not initialize $this->excelObject but instead will initialize $this->$columnSchemas
      }
   }

   public function getSheetName() {
      return $this->sheetName;
   }

   public function setIsMain($isMain) {
      $this->isMain = $isMain;
   }

   public function getColumns() {
      return $this->columns;
   }

   public function getColumn($columnName) {
      foreach ($this->columns as $currColumn) {
         if($currColumn->getName() == $columnName) {
            return $currColumn;
         }
      }
      throw WAException("Unable to get column with the name '$columnName' in $this->sheetName", WAException::$CODE_WF_INSTANCE_ERROR, null);
   }

   public function getPrimaryKey() {
      foreach($this->columns as $currColumn) {
         if($currColumn->getKey() == Database::$KEY_PRIMARY) {
            return $currColumn;
         }
      }
      $this->lH->log(1, $this->TAG, "Could not get the primary key for '{$this->sheetName}'");
      throw WAException("$this->sheetName does not have a primary key", WAException::$CODE_WF_INSTANCE_ERROR, null);
   }

   /**
    * This function initializes this object using details stored in the MySQL database
    * @param boolean $initColumns   Whether to also initialize all the columns
    *
    * @throws WAException
    */
   private function getCachedCopy() {
      if($this->sheetName != null
              && $this->database != null) {
         try {
            //$query = "describe `{$this->sheetName}`";
            $result = $this->database->getTableDetails($this->database->getDatabaseName(), $this->sheetName);//TODO: might need a check on whether database name is same as workflow instance
            $res_count = count($result);
            for($index = 0; $index < $res_count; $index++) {
               $currColumn = new WAColumn($this->config, $this->database, $result[$index]['name'], $this->lH, null, $result[$index]['type'], $result[$index]['length'], $result[$index]['nullable'], $result[$index]['default'], $result[$index]['key']);
               array_push($this->columns, $currColumn);
            }
         } catch (WAException $ex) {
            $this->lH->log(1, $this->TAG, "Unable to get schema details for data table (sheet) with name = '$this->sheetName'");
            throw new WAException("Unable to get schema details for data table (sheet) with name = '$this->sheetName'", WAException::$CODE_DB_QUERY_ERROR, $ex);
         }
      }
      else {
         $this->lH->log(1, $this->TAG, "Unable to get schema details for data table (sheet) with name = '$this->sheetName' from the database because sheet object not initialized correctly");
         throw new WAException("Unable to get schema details for data table (sheet) with name = '$this->sheetName' from the database because sheet object not initialized correctly", WAException::$CODE_WF_INSTANCE_ERROR, null);
      }
   }

   /**
    * This function gets data for this sheet that has been dumped in the database
    *
    * @param String $prefix   The prefix to determine which columns to get data for
    *
    * @return array  An associative array with the data
    */
   public function getDatabaseData($prefix = array(), $whereClause = "") {
      $data = array();
      $selectColumnsString = "";
      $entireSheetMatches = false;//whether the entire sheet matches prefix
      $pref_count = count($prefix);
      if($pref_count == 0){
         $entireSheetMatches = true;
      }
      else {
         for($pIndex = 0; $pIndex < $pref_count; $pIndex++) {
            $currPrefix = $prefix[$pIndex];
            if($currPrefix === "" || strrpos($this->sheetName, $currPrefix, -strlen($this->sheetName)) !== FALSE) {//column has prefix
               $entireSheetMatches = true;
               $this->lH->log(3, $this->TAG, "Entire sheet ".$this->sheetName." matches the prefix '$currPrefix'");
               break;
            }
         }
      }
      try {
         $clmn_count = count($this->columns);
         for($cIndex = 0; $cIndex < $clmn_count; $cIndex++) {
            $currColumn = $this->columns[$cIndex];
            if($entireSheetMatches == true) {//add the column since the entire sheet matches at least one prefix
               if(strlen($selectColumnsString) == 0) $selectColumnsString = Database::$QUOTE_SI.$currColumn->getName().Database::$QUOTE_SI;
               else $selectColumnsString .= ", ".Database::$QUOTE_SI.$currColumn->getName().Database::$QUOTE_SI;
            }
            else {
               //search if the current column matches any of the prefixes

               for($pIndex = 0; $pIndex < $pref_count; $pIndex++){
                  $currPrefix = $prefix[$pIndex];
                  if(strrpos($currColumn->getName(), $currPrefix, -strlen($currColumn->getName())) !== FALSE) {
                     if(strlen($selectColumnsString) == 0) $selectColumnsString = Database::$QUOTE_SI.$currColumn->getName().Database::$QUOTE_SI;
                     else $selectColumnsString .= ", ".Database::$QUOTE_SI.$currColumn->getName().Database::$QUOTE_SI;
                     break;
                  }
               }
            }
         }
         if(strlen($selectColumnsString) > 0) {
            $query = "select $selectColumnsString from ".Database::$QUOTE_SI.$this->sheetName.Database::$QUOTE_SI." ".$whereClause;
            $data = $this->database->runGenericQuery($query, TRUE);
         }
         else {
            $this->lH->log(2, $this->TAG, "None of the columns in ".$this->sheetName." match the prefix '$prefix'");
         }
         return $data;
      } catch (WAException $ex) {
         $this->lH->log(2, $this->TAG, "An error occurred while trying to get database data from ".$this->sheetName);
         throw new WAException("An error occurred while trying to get database data from ".$this->sheetName, WAException::$CODE_WF_PROCESSING_ERROR, $ex);
      }
   }

   /**
    * This function renames this sheet even in the database
    *
    * @param type $newName
    * @throws WAException
    */
   public function rename($newName) {
      if($this->database != null) {
         try {
            $this->database->runAlterTableQuery($this->sheetName, $newName);
         } catch (WAException $ex) {
            $this->lH->log(1, $this->TAG, "Could not rename '{$this->sheetName}' because of a database error");
            throw new WAException("Could not rename '{$this->sheetName}' because of a database error", WAException::$CODE_WF_INSTANCE_ERROR, $ex);
         }
      }
      else {
         $this->lH->log(1, $this->TAG, "Could not rename '{$this->sheetName}' because sheet object wasn't initialized correctly");
         throw new WAException("Could not rename '{$this->sheetName}' because sheet object wasn't initialized correctly", WAException::$CODE_WF_INSTANCE_ERROR, null);
      }
   }

   /**
    * This function dumps data from the data file into the sheet's database table
    */
   public function dumpData() {
      $this->getCachedCopy();
      if($this->sheetName != null
              && $this->columns != null
              && $this->fileDetails != null) {
         try {
            $this->lH->log(3, $this->TAG, "Dumping data for '{$this->sheetName}' from '{$this->fileDetails['filename']}'");
            //get the list of columns in the database table. Only dump columns that are in the database
            $this->processColumns();
            //move what is in columnArray to an sql insert statement
            $dataColumns = array();
            $clmn_count = count($this->columns);
            for($index = 0; $index < $clmn_count; $index++) {
               $currColumn = $this->columns[$index];

               $oColumnName = WAColumn::getOriginalColumnName($this->database, $this->fileDetails['filename'], $this->sheetName, $currColumn->getName());
               $this->lH->log(4, $this->TAG, "Current original column name = ".$oColumnName);
               $currColumn->setData($this->columnArray[$oColumnName]);
               $dataColumns[] = $currColumn;
            }
            $dcl_count = count($dataColumns);
            $this->lH->log(4, $this->TAG, "Number of data columns = $dcl_count");
            if($dcl_count > 0) {
               //get the maximum row count
               $rowCount = 0;
               for($z = 0; $z < $dcl_count; $z++) {
                  $currDataColumn = $dataColumns[$z];
                  if(count($currDataColumn->getData()) > $rowCount) {
                     $rowCount = count($currDataColumn->getData());
                  }
               }
               //$rowCount = count($dataColumns[count($dataColumns) - 1]->getData());//use the last column since the first column might be blank
               $this->lH->log(4, $this->TAG, "Number of data rows = ".$rowCount);
               $fullQuery = "";
               for($rIndex = 0; $rIndex < $rowCount; $rIndex++) {
                  $row = array();
                  for($cIndex = 0; $cIndex < $dcl_count; $cIndex++){
                     if(count($dataColumns[$cIndex]->getData()) > 0){
                        $cData = $dataColumns[$cIndex]->getData();
                        $cValue = $cData[$rIndex];
                        if(WAColumn::isNull($cValue)) {
                           $cValue = "null";
                        }
                        else {
                           if($dataColumns[$cIndex]->getType() == Database::$TYPE_DATE
                                    || $dataColumns[$cIndex]->getType() == Database::$TYPE_DATETIME
                                    || $dataColumns[$cIndex]->getType() == Database::$TYPE_TIME){
                               $cValue = "'".$cValue."'";
                            }
                            else if($dataColumns[$cIndex]->getType() == Database::$TYPE_VARCHAR) {
                               $cValue = $this->database->quote($cValue);
                            }
                            else if($dataColumns[$cIndex]->getType() == Database::$TYPE_BOOLEAN){
                               if(WAColumn::isTrue($cValue)){
                                  $cValue = "'t'";
                               }
                               else {
                                  $cValue = "'f'";
                               }
                            }
                        }
                        $row[$dataColumns[$cIndex]->getName()] = $cValue;
                     }
                  }
                  //check if we want to insert or update row
                  if($this->fileDetails['merge_table'] == $this->sheetName && !is_null($this->fileDetails['merge_column'])) {
                     $this->lH->log(3, $this->TAG, "Using update query to copy data from '{$this->fileDetails['filename']}' into '{$this->sheetName}'");
                     if(isset($row[$this->fileDetails['merge_column']])) {
                        $condition = Database::$QUOTE_SI.$this->fileDetails['merge_column'].Database::$QUOTE_SI." = ".$row[$this->fileDetails['merge_column']];
                        $this->database->runUpdateQuery($this->sheetName, $row, $condition);
                     }
                     else {
                        throw new WAException("Could not dump data into '{$this->sheetName}' because the value for the merging column is null in the current row", WAException::$CODE_WF_DATA_MULFORMED_ERROR, null);
                     }
                  }
                  else {
                     $this->lH->log(3, $this->TAG, "Using insert query to copy data from '{$this->fileDetails['filename']}' into '{$this->sheetName}'");
                     $this->database->runInsertQuery($this->sheetName, $row);
                  }
                  /*if(strlen($fullQuery) == 0){
                     $fullQuery = $this->database->runInsertQuery($this->sheetName, $row, true)."; \n";
                  }
                  else {
                     $fullQuery = $fullQuery.$this->database->runInsertQuery($this->sheetName, $row, true)."; \n";
                  }*/
               }
               /*if(strlen($fullQuery) > 0){
                  $this->database->runGenericQuery($fullQuery);
               }
               else {
                  $this->lH->log(2, $this->TAG, "Constructed insert query for dumping data into '{$this->sheetName}' is empty");
               }*/
            }
            else {
               $this->lH->log(1, $this->TAG, "Could not determine the columns in '{$this->sheetName}' to dump data for workflow with id = '{$this->database->getDatabaseName()}'");
               throw new WAException("Could not determine the columns in '{$this->sheetName}' to dump data", WAException::$CODE_WF_PROCESSING_ERROR, null);
            }
         } catch (WAException $ex) {
            $this->lH->log(1, $this->TAG, "An error occurred while trying to dump data into '{$this->sheetName}' for workflow with id = '{$this->database->getDatabaseName()}'");
            throw new WAException("An error occurred while trying to dump data into '{$this->sheetName}'", WAException::$CODE_WF_PROCESSING_ERROR, $ex);
         }
      }
      else {
         $this->lH->log(1, $this->TAG, "Could not dump data into '{$this->sheetName}' for workflow with id = '{$this->database->getDatabaseName()}' because sheet object was not initialized correctly");
         $this->lH->log(4, $this->TAG, "Sheet name = {$this->sheetName}");
         $this->lH->log(4, $this->TAG, "Columns = ".  print_r($this->columns, true));
         throw new WAException("Could not dump data into '{$this->sheetName}' because the sheet object wasn't initialized correctly", WAException::$CODE_WF_PROCESSING_ERROR, null);
      }
   }

   /**
    * This function deletes this sheet from the database
    *
    * @throws WAException
    */
   public function delete() {
      if($this->database != null) {
         $query = "drop table ".Database::$QUOTE_SI.$this->sheetName.Database::$QUOTE_SI." cascade";
         try {
            $this->database->runGenericQuery($query);
            $this->unload();
         } catch (WAException $ex) {
            $this->lH->log(1, $this->TAG, "Could not delete '{$this->sheetName}' because of a database error");
            throw new WAException("Could not delete '{$this->sheetName}' because of a database error", WAException::$CODE_WF_INSTANCE_ERROR, $ex);
         }
      }
      else {
         $this->lH->log(1, $this->TAG, "Could not delete '{$this->sheetName}' because sheet object wasn't initialized correctly");
         throw new WAException("Could not delete '{$this->sheetName}' because sheet object wasn't initialized correctly", WAException::$CODE_WF_INSTANCE_ERROR, null);
      }
   }

   /**
    * This function unloads all the columns owned by this sheet from memory
    */
   public function unload() {
      unset($this->database);
      unset($this->excelObject);
      unset($this->columns);
      unset($this->columnArray);
   }

   public function alterColumn($columnDetails) {
      //check whether column already exists
      if(array_key_exists("original_name", $columnDetails)
              && array_key_exists("name", $columnDetails)
              && array_key_exists("delete", $columnDetails)
              && array_key_exists("type", $columnDetails)
              && array_key_exists("length", $columnDetails)
              && array_key_exists("nullable", $columnDetails)
              && array_key_exists("default", $columnDetails)
              && array_key_exists("key", $columnDetails)) {
         $column = null;
         $clmn_count = count($this->columns);
         for($index = 0; $index < $clmn_count; $index++) {
            $currColumn = $this->columns[$index];
            if($currColumn->getName() == $columnDetails['original_name']) {
               $column = $currColumn;
               break;
            }
         }

         if($column != null) {
            if($columnDetails['delete'] === false) {
               $this->lH->log(3, $this->TAG, "Altering '{$columnDetails['original_name']}' in '{$this->sheetName}'");
               try {
                  $column->update($this->sheetName, $columnDetails['name'], $columnDetails['type'], $columnDetails['length'], $columnDetails['nullable'], $columnDetails['default'], $columnDetails['key']);
               }
               catch (WAException $ex) {
                  $this->lH->log(1, $this->TAG, "Could not alter column details for '{$columnDetails['original_name']}' in {$this->sheetName}");
                  throw new WAException("Could not alter table details for sheet '{$this->sheetName}'", WAException::$CODE_WF_INSTANCE_ERROR, $ex);
               }
            }
            else {
               $this->lH->log(3, $this->TAG, "Dropping '{$columnDetails['original_name']}' in '{$this->sheetName}'");
               try {
                  $query = "alter table ".Database::$QUOTE_SI.$this->sheetName.Database::$QUOTE_SI." drop column ".Database::$QUOTE_SI.$columnDetails['original_name'].Database::$QUOTE_SI;
                  $this->database->runGenericQuery($query);
               } catch (Exception $ex) {
                  $this->lH->log(1, $this->TAG, "Could not drop column '{$columnDetails['original_name']}' in '{$this->sheetName}'");
                  throw new WAException("Could not update table details for sheet '{$this->sheetName}'", WAException::$CODE_WF_INSTANCE_ERROR, $ex);
               }
            }
         }
         else {
            $this->lH->log(1, $this->TAG, "Could not find column with name '{$columnDetails['original_name']}' in '{$this->sheetName}'");
            throw new WAException("Could not find column with name '{$columnDetails['original_name']}' in '{$this->sheetName}'", WAException::$CODE_WF_INSTANCE_ERROR, null);
         }
      }
      else {
         $this->lH->log(1, $this->TAG, "Column details for '{$columnDetails['original_name']}' in '{$this->sheetName}' mulformed");
         throw new WAException("Column details for '{$columnDetails['original_name']}' mulformed", WAException::$CODE_WF_INSTANCE_ERROR, null);
      }
   }

   /**
    * This function returns the schema corresponding to this sheet as an array
    *
    * @return Array
    */
   public function getSchema() {
      if($this->sheetName != null
              && $this->columns != null) {
         $schema = array(
             "name" => $this->sheetName,
             "is_main" => $this->isMain,
             "columns" => array()
         );

         //$this->lH->log(4, $this->TAG, "Columns = ".print_r($this->columns, true));

         $clmn_count = count($this->columns);
         for($index = 0; $index < $clmn_count; $index++) {
            $currColumn = $this->columns[$index];
            array_push($schema['columns'], $currColumn->getSchema());
         }

         return $schema;
      }
      else {
         $this->lH->log(1, $this->TAG, "Unable to get schema details for data table (sheet) with name = '$this->sheetName' because sheet object not initialized correctly");
         throw new WAException("Unable to get schema details for data table (sheet) with name = '$this->sheetName' because sheet object not initialized correctly", WAException::$CODE_WF_INSTANCE_ERROR, null);
      }
   }

   /**
    * This function saves this object as a MySQL table. Sheet columns become
    * MySQL columns
    *
    * @throws WAException
    */
   public function saveAsMySQLTable($workflow, $linkSheets, $mysqlColumns = array(), $allSheetNames = null) {
      try {
         $columnNames = array_keys($this->columnArray);
         $columnsProvided = false;
         if(count($mysqlColumns) > 0){
            $columnsProvided = true;
         }
         if($linkSheets == true) {
            $primayKey = array("name" => $this->sheetName."_gen_pk" , "type"=>  Database::$TYPE_SERIAL , "length"=>null , "nullable"=>false, "default" => null , "key"=>Database::$KEY_PRIMARY);
            array_push($mysqlColumns, $primayKey);
         }
         if($columnsProvided == false){
            $this->processColumns();
            if(is_array($this->columnArray)
              && count($this->columnArray) > 0) {
               $this->switchToThisSheet();
               $columnCount = count($columnNames);
               for($index = 0; $index < $columnCount; $index++) {
                  $currColumn = new WAColumn($this->config, $this->database, $columnNames[$index], $this->columnArray[$columnNames[$index]]);
                  $currMySQLColumn = $currColumn->getMySQLDetails($workflow, $this->sheetName, $linkSheets);
                  array_push($mysqlColumns, $currMySQLColumn);
               }
            }
            else {
               $this->lH->log(1, $this->TAG, "Unable to get column details for sheet with name '{$this->sheetName}' in workflow with id = '{$this->database->getDatabaseName()}'");
               throw new WAException("Unable to get column details for sheet with name '{$this->sheetName}'", WAException::$CODE_WF_PROCESSING_ERROR, null);
            }
         }

         $columnCount = count($columnNames);
         if($linkSheets == true && $columnsProvided == false) $columnCount++;

         if($columnsProvided == false && (count($mysqlColumns) == 0 || $columnCount != count($mysqlColumns))) {
            $this->lH->log(1, $this->TAG, "Number of MySQL columns for sheet with name = '{$this->sheetName}' does not match the number of columns in excel file for workflow with id = '{$this->database->getDatabaseName()}'");
            throw new WAException("Number of MySQL columns for sheet with name = '{$this->sheetName}' does not match the number of columns in data file", WAException::$CODE_WF_PROCESSING_ERROR, null);
         }
         else {//everything seems to be fine with the data to be pushed to the MySQL database
            try {
               //determine the parent table
               $parentSheet = null;
               if($linkSheets == true && $allSheetNames != null) {
                  $parentSheet = $this->getParentSheet($allSheetNames);
               }
               else if($linkSheets == true) {
                  throw new WAException("Could not determine the parent sheet for '$this->sheetName'. The names of all the workflow sheets were not provided", WAException::$CODE_WF_PROCESSING_ERROR, null);
               }
               $this->database->runCreateTableQuery($this->sheetName, $mysqlColumns, $linkSheets, $parentSheet);
            } catch (WAException $ex) {
               $this->lH->log(1, $this->TAG, "Unable to create database table for sheet with name = '{$this->sheetName}' for workflow with id = '{$this->database->getDatabaseName()}'");
               throw new WAException("Unable to create MySQL table for sheet with name = '{$this->sheetName}'", WAException::$CODE_WF_PROCESSING_ERROR, $ex);
            }
         }
      } catch (WAException $ex) {
         $this->lH->log(1, $this->TAG, "Unable to process columns for sheet with name = '{$this->sheetName}' in workflow with id = '{$this->database->getDatabaseName()}'");
         throw new WAException("Unable to process columns for sheet with name = '{$this->sheetName}'", WAException::$CODE_WF_PROCESSING_ERROR, $ex);
      }
   }

   private function getParentSheet($allSheetNames) {
      if($this->columnArray != null && count($this->columnArray) > 0) {
         if(isset($this->columnArray['secondary_key'])) {
            $firstSeconaryKey = $this->columnArray['secondary_key'][0];
            if(isset($firstSeconaryKey) && strlen($firstSeconaryKey) > 0) {
               $parent = "";
               foreach ($allSheetNames as $currSheetName) {
                  if($currSheetName != $this->sheetName && strpos($firstSeconaryKey, $currSheetName) !== false) {
                     if(strlen($currSheetName) > strlen($parent)) $parent = $currSheetName;
                  }
               }
               if(strlen($parent) > 0) {
                  return $parent;
               }
               else {
                  return "main_sheet";
               }
            }
            else {
               throw new WAException("Could not determine parent sheet for '{$this->sheetName}' since the first row for 'secondary_key' is not set", WAException::$CODE_WF_DATA_MULFORMED_ERROR, null);
            }
         }
         else if($this->sheetName == "main_sheet") {//we don't expect main_sheet to have a parent
            return null;
         }
         else {//file probably not generated by the parser
            throw new WAException("Could not determine parent sheet for '{$this->sheetName}' since the sheet does not have the 'secondary_key' column and is not the main_sheet sheet", WAException::$CODE_WF_DATA_MULFORMED_ERROR, null);
         }
      }
      else {
         throw new WAException("Could not determine the parent sheet for {$this->sheetName} because column data has not been set", WAException::$CODE_WF_DATA_MULFORMED_ERROR, null);
      }
   }

   /**
    * This function returns the sheet's data as an associative array
    */
   public function getData() {
      try {
         $this->processColumns(true);//process columns but limit the number of rows processed
         return $this->columnArray;
      } catch (WAException $ex) {
         $this->lH->log(1, $this->TAG, "Unable to extract data from sheet with name = '{$this->sheetName}' in workflow with id = '{$this->database->getDatabaseName()}'");
         throw new WAException("Unable to extract data from sheet with name = '{$this->sheetName}'", WAException::$CODE_WF_PROCESSING_ERROR, $ex);
      }
   }

   /**
    * This function processes each of the columns in the sheet to determin their
    * datatypes
    *
    * @throws WAException
    */
   public function processColumns($limit = false) {
      try {
         $primaryKeyThere = false;
         $this->switchToThisSheet();
         /*Refer to http://stackoverflow.com/questions/8583915/phpexcel-read-all-values-date-time-numbers-as-strings
          * for an explanation on how to use toArray
          */

         $activeSheet = $this->excelObject->getActiveSheet()->toArray(null, true, false, false);
         $acs_count = count($activeSheet);
         $this->lH->log(4, $this->TAG, "The sheet '{$this->sheetName}' has $acs_count rows");
         for($rowIndex = 0; $rowIndex < $acs_count; $rowIndex++){
            $columnNumber = -1;
            if($rowIndex == 0) {//we are in the first row (assume first row has column headings)
               //create an array for each column heading
               $columnIndex = 0;
               while($columnNumber == -1) {//while the number of columns is still unknown
                  $cellValue = trim($activeSheet[$rowIndex][$columnIndex]);
                  if($primaryKeyThere == false && $cellValue == "primary_key"){
                     $primaryKeyThere = true;
                     $this->lH->log(3, $this->TAG, "Sheet '{$this->sheetName}' has a primary_key column");
                  }
                  if(strlen($cellValue) == 0) {//the cell is empty (Column headings should not be empty unless all other rows for respective column are also empty)
                     $columnNumber = $columnIndex;
                     $this->lH->log(3, $this->TAG, "Sheet '{$this->sheetName}' has $columnNumber columns");
                  }
                  else {
                     $currHeadingName = $cellValue;
                     if(strlen($currHeadingName) > 0){
                        $this->columnArray[$currHeadingName] = array();
                     }
                     else {
                        $readableCIndex = $columnIndex + 1;
                        throw new WAException("Column number ".$readableCIndex." in '{$this->sheetName}' is empty", WAException::$CODE_WF_PROCESSING_ERROR, null);
                     }
                  }
                  $columnIndex++;
               }
            }
            else {//iterating though data rows (not heading rows)
               $cla_count = count($this->columnArray);
               if($cla_count > 0) {

                  for($columnIndex = 0; $columnIndex < $cla_count; $columnIndex++){
                     $headingCell = $activeSheet[0][$columnIndex];
                     $cellValue = trim($activeSheet[$rowIndex][$columnIndex]);
                     $this->columnArray[$headingCell][$rowIndex - 1] = $cellValue;
                  }

                  //$this->lH->log(4, $this->TAG, "Columns for sheet '{$this->sheetName}' are ".print_r($this->columnArray, true));
               }
               else {
                  $this->lH->log(2, $this->TAG, "Sheet with name = '{$this->sheetName}' has no heading columns. Will be ignoring this sheet");
               }
               if($limit == true && $rowIndex >= 20) {
                  break;
               }
            }
         }
         return $primaryKeyThere;
      } catch (WAException $ex) {
         $this->lH->log(1, $this->TAG, "Unable to process columns in sheet with name = '{$this->sheetName}' for workflow with id = {$this->database->getDatabaseName()}");
         throw new WAException("Unable to process columns in sheet with name = '{$this->sheetName}'", WAException::$CODE_WF_PROCESSING_ERROR, $ex);
      }
   }

   /**
    * This function makes the current sheet default in the excelObject
    *
    * @throws WAException
    */
   private function switchToThisSheet() {
      if($this->excelObject != null
            && $this->fileDetails != null) {
         $originalName = $this->getSheetOriginalName($this->database, $this->fileDetails['filename'], $this->sheetName);
         $this->excelObject->setActiveSheetIndexByName($originalName);
      }
      else {
         $this->lH->log(1, $this->TAG, "Unable to switch to sheet with name = '{$this->sheetName}' for workflow with id = '{$this->database->getDatabaseName()}'");
         throw new WAException("Unable to switch to sheet with name as '{$this->sheetName}'", WAException::$CODE_WF_CREATE_ERROR, null);
      }
   }

   /**
    * This function returns a string representing the cell name
    *
    * @param type $rowIndex      The 1 based index of the cell row
    * @param type $columnIndex   The 1 based index of the cell column
    *
    * @return String Name of the cell e.g
    */
   public function getCellName($rowIndex, $columnIndex) {
      return PHPExcel_Cell::stringFromColumnIndex($columnIndex).$rowIndex;
   }

   /**
    * This function returns an array with names of all sheets that belong to
    * the provided workflow instance
    *
    * @param Object     $config
    * @param string     $workflowId
    * @param Database   $database
    */
   public static function getAllWASheets($config, $workflowId, $database) {
      include_once 'mod_wa_database.php';
      include_once 'mod_log.php';
      include_once 'mod_wa_exception.php';

      $lH = new LogHandler("./");
      if($database->getDatabaseName() == $workflowId) {
         try {
            $result = $database->getTableNames($workflowId);
            if(is_array($result)) {
               $tables = array();
               $metaTables = Workflow::getAllMetaTables();

               $res_count = count($result);
               for($index = 0; $index < $res_count; $index++) {
                  if(!in_array($result[$index], $metaTables)) {
                     array_push($tables, $result[$index]);
                  }
               }

               if(count($tables) == 0) {
                  $lH->log(2, "washeet_static", "Workflow with id = '$workflowId' does not have data tables");
               }
               $mainSheetName = WASheet::getSheetCurrentName($database, "main_sheet");
               $mainSheetPos = array_search($mainSheetName, $tables);
               if($mainSheetPos !== false){
                  unset($tables[$mainSheetPos]);
                  $tables = array_merge(array($mainSheetName), $tables);
               }
               return $tables;
            }
            else {
               throw new WAException("Unable to get data sheets for workflow", WAException::$CODE_DB_QUERY_ERROR, null);
            }
         } catch (WAException $ex) {
            throw new WException("Unable to get data sheets for workflow", WAException::$CODE_DB_QUERY_ERROR, $ex);
         }
      }
      else {
         throw new WAException("Unable to get data sheets for workflow  because connected to the wrong database", WAException::$CODE_WF_INSTANCE_ERROR, null);
      }
   }

   /**
    * This function returns the name of the main sheet in the workflow. The main
    * sheet is the one without foreign keys. If more than one sheet fits this criteria
    * then null is returned
    *
    * @param Array      $config
    * @param String     $workfowId
    * @param Database   $database
    */
   public static function getMainSheet($config, $workflowId, $database) {
      include_once 'mod_wa_database.php';
      include_once 'mod_log.php';
      include_once 'mod_wa_exception.php';

      $lH = new LogHandler("./");
      try {
         $allSheets = WASheet::getAllWASheets($config, $workflowId, $database);
         $mainSheets = array();
         foreach($allSheets as $currSheetName) {
            $currForeignKeys = $database->getTableForeignKeys($currSheetName);
            if(count($currForeignKeys) == 0) $mainSheets[] = $currSheetName;
         }
         if(count($mainSheets) == 1) return $mainSheets[0];
         else {
            $lH->log(1, "wa_sheet_static", "More than one sheet can be considered as the main sheet");
            throw new WAException("More than one sheet can be considered as the main sheet");
         }
      } catch (WAException $ex) {
         $lH->log(1, "wa_sheet_static", "Unable to get the name of the main sheet in '{$workflowId}'");
         throw new WAException("Unable to get the name of the main sheet", WAException::$CODE_WF_INSTANCE_ERROR, $ex);
      }
   }

   public static function getSheetOriginalName($database, $file, $currentName) {
      include_once 'mod_wa_exception.php';
      try {
         $query = "select original_sheet from ".Workflow::$TABLE_META_CHANGES." where current_sheet = '$currentName' and change_type = '".Workflow::$CHANGE_SHEET."' and file = '".$file."'";
         $result = $database->runGenericQuery($query, TRUE);
         if(is_array($result)) {
            if(count($result) == 1) {
               return $result[0]['original_sheet'];
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
    * This function sorts the provided sheets based on the the parents with sheets
    * that have no parents showing up first and sheets with the biggest heirarchy
    * of parents showing up last
    */
   public static function sortSheets($database, $sheets) {
      $lH = new LogHandler("./");
      try {
         $sheetClasses = array();
         $maxNoParents = 0;
         foreach($sheets as $currSheet) {
            $lH->log(4, "wa_sheet_static", "Checking the number of parents for the sheet '$currSheet'");
            $noParents = $database->getNumberOfParents($currSheet);
            if(!isset($sheetClasses[$noParents])) {
               $sheetClasses[$noParents] = array();
            }
            $sheetClasses[$noParents][] = $currSheet;
            if($noParents > $maxNoParents) $maxNoParents = $noParents;
         }
         $sortedSheets = array();
         for($index = 0; $index <= $maxNoParents; $index++) {
            $sortedSheets = array_merge($sortedSheets, $sheetClasses[$index]);
         }
         return $sortedSheets;
      } catch (WAException $ex) {
         throw new WAException("Could not sort sheets based on their parents", WAException::$CODE_WF_PROCESSING_ERROR, $ex);
      }
   }

   public static function getSheetCurrentName($database, $originalName, $file = null){
      include_once 'mod_wa_exception.php';
      try {
         if($file != null) {
            $query = "select current_sheet from ".Workflow::$TABLE_META_CHANGES." where original_sheet = '$originalName' and change_type = '".Workflow::$CHANGE_SHEET."' and file = '$file'";
         }
         else {
            $query = "select current_sheet from ".Workflow::$TABLE_META_CHANGES." where original_sheet = '$originalName' and change_type = '".Workflow::$CHANGE_SHEET."'";
         }
         $result = $database->runGenericQuery($query, TRUE);
         if(is_array($result)) {
            if(count($result) > 0) {
               return $result[0]['current_sheet'];
            }
            else if(count($result) == 0) {
               return $originalName;
            }
         }
         else {
            throw new WAException("Unable to determine what '$currentName' was originally called", WAException::$CODE_DB_QUERY_ERROR, null);
         }
      } catch (WAException $ex) {
         throw new WAException("Unable to determine what '$currentName' was originally called", WAException::$CODE_DB_QUERY_ERROR, $ex);
      }
   }
}