<?php

/**
 * This class implements functions for an Excel file in the ODK Workflow API
 */
class WAExcelFile {
   private $TAG = "waexcelfile";
   private $waFile;
   private $config;
   private $database;
   private $excelObject;
   private $lH;
   private $sheets;
   
   /**
    * Default constructor for this class
    * 
    * @param   WAFile   $waFile  WAFile containing details on the file
    * 
    * @throws WAException
    */
   public function __construct($waFile) {
      include_once 'mod_wa_exception.php';
      include_once 'mod_log.php';
      include_once 'mod_wa_sheet.php';
      include_once 'mod_wa_database.php';
      
      $this->lH = new LogHandler("./");
      $this->waFile = $waFile;
      $this->config = $waFile->getConfig();
      $this->sheets = array();
      $this->database = $waFile->getDatabase();
      
      include_once $this->config['common_folder_path'].'PHPExcel/Classes/PHPExcel.php';
      include_once $this->config['common_folder_path'].'PHPExcel/Classes/PHPExcel/IOFactory.php';
      
      //try reading the excel file
      try {
         $location = $waFile->getFSLocation();
         try {
            $inputFileType = PHPExcel_IOFactory::identify($location);
            $objReader = PHPExcel_IOFactory::createReader($inputFileType);
            $objReader->setReadDataOnly(true);
            $this->excelObject = $objReader->load($location);
         } catch (PHPExcel_Reader_Exception $ex) {
            $this->lH->log(1, $this->TAG, "PHPExcel_Read_Exception thrown while trying to read this file '$location'");
            throw new WAException("Could read excel file into PHPExcel object", WAException::$CODE_WF_CREATE_ERROR, $ex);
         }
         
      } catch (WAException $ex) {
         $this->lH->log(1, $this->TAG, "An error occurred while trying to load an excel file in the workflow");
         throw new WAException("Could not determine location for excel file", WAException::$CODE_FS_UNKNOWN_LOCATION_ERROR, $ex);
      }
      
   }
   
   /**
    * This function loads WA Sheets from the files into memory.
    * Function might be time consuming so call after returning response to client
    * then let client check back periodically
    * 
    * @throws WAException
    */
   public function processToMySQL() {
      if($this->excelObject != null) {
         $sheetNames = $this->excelObject->getSheetNames();
         $this->lH->log(4, $this->TAG, "{$this->waFile->getFSLocation()} has the following sheets ".print_r($sheetNames, true));
         for($index = 0; $index < count($sheetNames); $index++){
            try {
               $currSheet = new WASheet($this->config, $this->database, $this->excelObject, $sheetNames[$index]);
               $currSheet->saveAsMySQLTable();
            } catch (WAException $ex) {
               $this->lH->log(1, $this->TAG, "An error occurred while trying to process the {$sheetNames[$index]} sheet in ");
               throw new WAException("An error occurred while trying to process the {$sheetNames[$index]}", WAException::$CODE_WF_CREATE_ERROR, $ex);
            }
         }
      }
      else {
         $this->lH->log(1, $this->TAG, "Unable to process the excel at {$this->waFile->getFSLocation()}");
         throw new WAException("Unable to process Excel data file", WAException::$CODE_WF_INSTANCE_ERROR, null);
      }
   }
}

?>