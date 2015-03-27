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
   
   /**
    * Default constructor for this class
    * 
    * @param Object   $config       The repository_config object
    * @param Database $database     The database object to be used for queries
    * @param PHPExcel $excelObject  The excel object where data is to be read from
    * @param string   $sheetName    Name of the sheet in the excelObject to process
    * 
    */
   public function __construct($config, $database, $excelObject, $sheetName) {
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
      
      if($this->excelObject == null) {//means data already stored in database
         $this->getCachedCopy();//this function will not initialize $this->excelObject but instead will initialize $this->$columnSchemas
      }
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
            for($index = 0; $index < count($result); $index++) {
               $currColumn = new WAColumn($this->config, $this->database, $result[$index]['name'], null, $result[$index]['type'], $result[$index]['length'], $result[$index]['nullable'], $result[$index]['default'], $result[$index]['key']);
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
         for($index = 0; $index < count($this->columns); $index++) {
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
             "columns" => array()
         );
         
         //$this->lH->log(4, $this->TAG, "Columns = ".print_r($this->columns, true));
         
         for($index = 0; $index < count($this->columns); $index++) {
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
   public function saveAsMySQLTable($linkSheets) {
      try {
         //$this->processColumns();
         $this->switchToThisSheet();
         if(is_array($this->columnArray) 
                 && count($this->columnArray) > 0) {
            $columnNames = array_keys($this->columnArray);
            
            $mysqlColumns = array();
            if($linkSheets == true) {
               $primayKey = array("name" => $this->sheetName."_gen_pk" , "type"=>  Database::$TYPE_SERIAL , "length"=>null , "nullable"=>false, "default" => null , "key"=>Database::$KEY_PRIMARY);
               array_push($mysqlColumns, $primayKey);
            }
            for($index = 0; $index < count($columnNames); $index++) {
               $currColumn = new WAColumn($this->config, $this->database, $columnNames[$index], $this->columnArray[$columnNames[$index]]);
               $currMySQLColumn = $currColumn->getMySQLDetails($linkSheets);
               array_push($mysqlColumns, $currMySQLColumn);
            }
            
            $columnCount = count($columnNames);
            if($linkSheets) $columnCount++;
            if(count($mysqlColumns) == 0 || $columnCount != count($mysqlColumns)) {
               $this->lH->log(1, $this->TAG, "Number of MySQL columns for sheet with name = '{$this->sheetName}' does not match the number of columns in excel file for workflow with id = '{$this->database->getDatabaseName()}'");
               throw new WAException("Number of MySQL columns for sheet with name = '{$this->sheetName}' does not match the number of columns in data file", WAException::$CODE_WF_PROCESSING_ERROR, null);
            }
            else {//everything seems to be fine with the data to be pushed to the MySQL database
               try {
                  
                  $this->database->runCreateTableQuery($this->sheetName, $mysqlColumns, $linkSheets);
                  
               } catch (WAException $ex) {
                  $this->lH->log(1, $this->TAG, "Unable to create database table for sheet with name = '{$this->sheetName}' for workflow with id = '{$this->database->getDatabaseName()}'");
                  throw new WAException("Unable to create MySQL table for sheet with name = '{$this->sheetName}'", WAException::$CODE_WF_PROCESSING_ERROR, $ex);
               }
            }
         }
         else {
            $this->lH->log(2, $this->TAG, "Unable to get column details for sheet with name '{$this->sheetName}' in workflow with id = '{$this->database->getDatabaseName()}'");
         }
      } catch (WAException $ex) {
         $this->lH->log(1, $this->TAG, "Unable to process columns for sheet with name = '{$this->sheetName}' in workflow with id = '{$this->database->getDatabaseName()}'");
         throw new WAException("Unable to process columns for sheet with name = '{$this->sheetName}'", WAException::$CODE_WF_PROCESSING_ERROR, $ex);
      }
   }
   
   /**
    * This function returns the sheet's data as an associative array
    */
   public function getData() {
      try {
         $this->processColumns();
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
   public function processColumns() {
      try {
         $primaryKeyThere = false;
         $this->switchToThisSheet();
         /*Refer to http://stackoverflow.com/questions/8583915/phpexcel-read-all-values-date-time-numbers-as-strings
          * for an explanation on how to use toArray
          */
         
         $activeSheet = $this->excelObject->getActiveSheet()->toArray(null, true, false, false);
         for($rowIndex = 0; $rowIndex < count($activeSheet); $rowIndex++){
            $this->lH->log(4, $this->TAG, "Current row index = $rowIndex");
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
                     $this->columnArray[$cellValue] = array();
                  }
                  $columnIndex++;
               }
            }
            else {//iterating though data rows (not heading rows)
               if(count($this->columnArray) > 0) {
                  
                  for($columnIndex = 0; $columnIndex < count($this->columnArray); $columnIndex++){
                     $headingCell = $activeSheet[0][$columnIndex];
                     $cellValue = trim($activeSheet[$rowIndex][$columnIndex]);
                     $this->columnArray[$headingCell][$rowIndex - 1] = $cellValue;
                  }
                  
                  //$this->lH->log(4, $this->TAG, "Columns for sheet '{$this->sheetName}' are ".print_r($this->columnArray, true));
               }
               else {
                  $this->lH->log(2, $this->TAG, "Sheet with name = '{$this->sheetName}' has no heading columns. Will be ignoring this sheet");
               }
            }
         }
         /*foreach($this->excelObject->getActiveSheet()->getRowIterator() as $row){
            $rowIndex = $row->getRowIndex();
            
            $columnNumber = -1;
            if($rowIndex == 1){//the first row
               $columnIndex = 0;
               while($columnNumber == -1) {//while the number of columns is still unknown
                  $cellValue = trim($this->excelObject->getActiveSheet()->getCell($this->getCellName($rowIndex, $columnIndex))->getValue());
                  if(strlen($cellValue) == 0) {//the cell is empty
                     $columnNumber = $columnIndex;
                     $this->lH->log(3, $this->TAG, "Sheet '{$this->sheetName}' has $columnNumber columns");
                  }
                  else {
                     $this->columnArray[$cellValue] = array();
                  }
                  $columnIndex++;
               }
            }
            else {
               if(count($this->columnArray) > 0) {
                  
                  for($columnIndex = 0; $columnIndex < count($this->columnArray); $columnIndex++){
                     $headingCell = trim($this->excelObject->getActiveSheet()->getCell($this->getCellName(1, $columnIndex))->getValue());
                     $cellValue = trim($this->excelObject->getActiveSheet()->getCell($this->getCellName($rowIndex, $columnIndex))->getValue());
                     $this->columnArray[$headingCell][$rowIndex - 2] = $cellValue;
                     
                     if($columnIndex == (count($this->columnArray) - 1)) {//the last column in this row
                        //check if there is something in the column to the right
                        $cellToRight = trim($this->excelObject->getActiveSheet()->getCell($this->getCellName($rowIndex, $columnIndex + 1))->getValue());
                        if(strlen($cellToRight) > 0){//there is something in the column to the right
                           //excel sheet data is mulformed
                           $this->lH->log(1, $this->TAG, "Sheet '{$this->sheetName}' seems to be mulformed for workflow with id = '{$this->database->getDatabaseName()}'");
                           throw new WAException("Sheet '{$this->sheetName}' seems to be mulformed", WAException::$CODE_WF_DATA_MULFORMED_ERROR, null);
                        }
                     }
                  }
                  
                  //$this->lH->log(4, $this->TAG, "Columns for sheet '{$this->sheetName}' are ".print_r($this->columnArray, true));
               }
               else {
                  $this->lH->log(2, $this->TAG, "Sheet with name = '{$this->sheetName}' has no heading columns. Will be ignoring this sheet");
               }
            }
         }*/
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
              && $this->sheetName != null) {
         $this->excelObject->setActiveSheetIndexByName($this->sheetName);
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
               $metaTables = array();
               array_push($metaTables, WAFile::$TABLE_META_FILES);
               array_push($metaTables, Workflow::$TABLE_META_ACCESS);
               array_push($metaTables, Workflow::$TABLE_META_CHANGES);
               array_push($metaTables, Workflow::$TABLE_META_DOCUMENT);
               array_push($metaTables, Workflow::$TABLE_META_ERRORS);
               array_push($metaTables, Workflow::$TABLE_META_VAR_NAMES);
               
               for($index = 0; $index < count($result); $index++) {
                  if(!in_array($result[$index], $metaTables)) {
                     array_push($tables, $result[$index]);
                  }
               }
               
               if(count($tables) == 0) {
                  $lH->log(2, "washeet_static", "Workflow with id = '$workflowId' does not have data tables");
               }
               
               return $tables;
            }
            else {
               $lH->log(1, $this->TAG, "Unable to get data sheets for workflow with id = '{$workflowId}'");
               throw new Exception("Unable to get data sheets for workflow", WAException::$CODE_DB_QUERY_ERROR, null);
            }
         } catch (WAException $ex) {
            $lH->log(1, $this->TAG, "Unable to get data sheets for workflow with id = '{$workflowId}'");
            throw new Exception("Unable to get data sheets for workflow", WAException::$CODE_DB_QUERY_ERROR, $ex);
         }
      }
      else {
         $lH->log(1, $this->TAG, "Unable to get data sheets for workflow with id = '{$workflowId}' because connected to the wrong database");
         throw new Exception("Unable to get data sheets for workflow  because connected to the wrong database", WAException::$CODE_WF_INSTANCE_ERROR, null);
      }
   }
   
   public static function getSheetOriginalName($database, $currentName) {
      include_once 'mod_wa_exception.php';
      try {
         $query = "select original_sheet from ".Workflow::$TABLE_META_CHANGES." where current_sheet = '$currentName'";
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
}