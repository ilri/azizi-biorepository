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
   private $excelObject;
   private $sheetName;
   private $columnArray;//this array stores the sheet columns in an array with the first excel sheet being the indexes of the first level arrays consecutive rows as array items
   
   /**
    * Default constructor for this class
    * 
    * @param Object   $config       The repository_config object
    * @param Database $database     The database object to be used for queries
    * @param PHPExcel $excelObject  The excel object where data is to be read from
    * @param string   $sheetName    Name of the sheet in the excelObject to process
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
   }
   
   /**
    * This function saves this object as a MySQL table. Sheet columns become
    * MySQL columns
    * 
    * @throws WAException
    */
   public function saveAsMySQLTable() {
      try {
         $this->processColumns();
         if(is_array($this->columnArray) 
                 && count($this->columnArray) > 0) {
            $columnNames = array_keys($this->columnArray);
            
            $mysqlColumns = array();
            for($index = 0; $index < count($columnNames); $index++) {
               $currColumn = new WAColumn($this->config, $this->database, $columnNames[$index], $this->columnArray[$columnNames[$index]]);
               $currMySQLColumn = $currColumn->getMySQLDetails();
               array_push($mysqlColumns, $currMySQLColumn);
            }
            
            if(count($mysqlColumns) == 0 || count($columnNames) != count($mysqlColumns)) {
               $this->lH->log(1, $this->TAG, "Number of MySQL columns for sheet with name = '{$this->sheetName}' does not match the number of columns in excel file for workflow with id = '{$this->database->getDatabaseName()}'");
               throw new WAException("Number of MySQL columns for sheet with name = '{$this->sheetName}' does not match the number of columns in data file", WAException::$CODE_WF_PROCESSING_ERROR, null);
            }
            else {//everything seems to be fine with the data to be pushed to the MySQL database
               try {
                  
                  $this->database->runCreateTableQuery($this->sheetName, $mysqlColumns);
                  
               } catch (WAException $ex) {
                  $this->lH->log(1, $this->TAG, "Unable to create MySQL table for sheet with name = '{$this->sheetName}' for workflow with id = '{$this->database->getDatabaseName()}'");
                  throw new WAException("Unable to create MySQL table for sheet with name = '{$this->sheetName}'", WAException::$CODE_WF_PROCESSING_ERROR, $ex);
               }
            }
         }
      } catch (WAException $ex) {
         $this->lH->log(1, $this->TAG, "Unable to process columns for sheet with name = '{$this->sheetName}' in workflow with id = '{$this->database->getDatabaseName()}'");
         throw new WAException("Unable to process columns for sheet with name = '{$this->sheetName}'", WAException::$CODE_WF_PROCESSING_ERROR, $ex);
      }
   }
   
   /**
    * This function processes each of the columns in the sheet to determin their
    * datatypes
    * 
    * @throws WAException
    */
   private function processColumns() {
      try {
         $this->switchToThisSheet();
         foreach($this->excelObject->getActiveSheet()->getRowIterator() as $row){
            $rowIndex = $row->getRowIndex();
            $this->lH->log(4, $this->TAG, "Current row index = $rowIndex");
            
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
         }
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
}