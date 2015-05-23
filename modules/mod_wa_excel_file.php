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
   private $cachePath;//directory where PHPExcel is going to cache cells
   
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
            //set the cache method. Will prevent process from being OOM killed by the kernel
            $subDirPath = $waFile->createWorkingSubdir();
            $this->cachePath = $subDirPath.'/phpexcel_cache';
            if(!file_exists($this->cachePath)){
               mkdir($this->cachePath, 0777, true);
            }
            $cacheMethod = PHPExcel_CachedObjectStorageFactory::cache_to_discISAM;
            $cacheSettings = array( 
                'dir' => $this->cachePath
            );
            PHPExcel_Settings::setCacheStorageMethod($cacheMethod, $cacheSettings);
            
            //read file into object
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
   public function processToMySQL($linkSheets) {
      if($this->excelObject != null) {
         $sheetNames = $this->excelObject->getSheetNames();
         $this->lH->log(4, $this->TAG, "{$this->waFile->getFSLocation()} has the following sheets ".print_r($sheetNames, true));
         if($sheetNames[0] != "main_sheet") $linkSheets = false;
         for($index = 0; $index < count($sheetNames); $index++){
            try {
               $currSheet = new WASheet($this->config, $this->database, $this->excelObject, $sheetNames[$index]);
               $primaryKeyThere = $currSheet->processColumns();
               if($sheetNames[$index] == "main_sheet" && $linkSheets == true) {
                  $linkSheets = $primaryKeyThere;
               }
               $currSheet->saveAsMySQLTable($linkSheets);
               
               //offload $currSheet from memory to prevent this process from being OOM killed
               $currSheet->unload();
               unset($currSheet);
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
   
   /**
    * This function extracts the data from the specified sheet
    * @param type $sheetName
    */
   public function getSheetData($sheetName) {
      try {
         if($this->excelObject != null) {
            try {
               $currSheet = new WASheet($this->config, $this->database, $this->excelObject, $sheetName);
               $data = $currSheet->getData();
               return $data;
            } catch (WAException $ex) {
               $this->lH->log(1, $this->TAG, "Unable to extract data from data file for workflow with id = '{$this->database->getDatabaseName()}'");
               throw new WAException("Unable to extract data from data file", WAException::$CODE_WF_INSTANCE_ERROR, $ex);
            }
         }
         else {
            $this->lH->log(1, $this->TAG, "Unable to extract data from the excel file at {$this->waFile->getFSLocation()}");
            throw new WAException("Unable to extract data from the excel file at {$this->waFile->getFSLocation()}", WAException::$CODE_WF_INSTANCE_ERROR, null);
         }
      } catch (WAException $ex) {
         $this->lH->log(1, $this->TAG, "Unable to get data for sheet '$sheetName' in workflow with id = '{$this->database->getDatabaseName()}'");
         throw new WAException("Unable to get data for sheet '$sheetName'", WAException::$CODE_WF_PROCESSING_ERROR, $ex);
      }
   }
   
   /**
    * This function dumps data from the excel file into the database
    */
   public function dumpData($deleteData = true) {
      try {
         if($this->excelObject != null) {
            $sheetNames = WASheet::getAllWASheets($this->config, $this->database->getDatabaseName(), $this->database);
            $this->lH->log(4, $this->TAG, "Sheet names  = ".print_r($sheetNames, true));
            //$sheetName = WASheet::getSheetOriginalName($this->database, $sheetName);
            try {
               //delete data from the sheets (start from last index)
               if(count($sheetNames) > 0 && $deleteData == true) {
                  $this->lH->log(3, $this->TAG, "Truncating all tables before dumping data");
                  for($index = count($sheetNames) - 1; $index >= 0; $index--) {
                     $query = "truncate table ".Database::$QUOTE_SI.$sheetNames[$index].Database::$QUOTE_SI." cascade";
                     $this->database->runGenericQuery($query);
                  }
               }
               //dump data into database tables (start from first)
               for($index = 0; $index < count($sheetNames); $index++){
                  $currSheet = new WASheet($this->config, $this->database, $this->excelObject, $sheetNames[$index]);
                  $currSheet->dumpData();
               }
            } catch (WAException $ex) {
               $this->lH->log(1, $this->TAG, "Unable to dump data from data file for workflow with id = '{$this->database->getDatabaseName()}'");
               throw new WAException("Unable to dump data from data file", WAException::$CODE_WF_INSTANCE_ERROR, $ex);
            }
         }
         else {
            $this->lH->log(1, $this->TAG, "Unable to dump data from the excel file at {$this->waFile->getFSLocation()}");
            throw new WAException("Unable to dump data from the excel file at {$this->waFile->getFSLocation()}", WAException::$CODE_WF_INSTANCE_ERROR, null);
         }
      } catch (WAException $ex) {
         $this->lH->log(1, $this->TAG, "Unable to dump data from excel file in workflow with id = '{$this->database->getDatabaseName()}'");
         throw new WAException("Unable to dump data from excel file", WAException::$CODE_WF_PROCESSING_ERROR, $ex);
      }
   }
   
   /**
    * This function unloades the PHPExcel object from memory and deletes cache files
    */
   public function unload() {
      $this->excelObject->disconnectWorksheets();
      WAFile::rmDir($this->cachePath);
   }
   
   /**
    * This function dumps data from the data provided into an PHPExcel object
    * 
    * @param Array   $config     Repository general config file
    * @param String  $workflowID The instance id of the workflow
    * @param String  $workingDir Where to save the excel file
    * @param String  $title      The title to be given to the excel file
    * @param Array   $data       An associative array of the data with the top heirarchy being the sheets,
    *                            second level being the rows and the third level the columns
    * 
    * @return PHPExcel  A PHPExcelObject containing the dumped data
    * @throws WAException
    */
   public static function saveAsExcelFile($config, $workflowID, $workingDir, $database, $title, $data) {
      include_once $config['common_folder_path'].'PHPExcel/Classes/PHPExcel.php';include_once 'mod_log.php';
      include_once 'mod_wa_exception.php';
      include_once 'mod_wa_file.php';
      try {
         $lH = new LogHandler("./");
         $tag = "washeet_static";
         $phpExcel = new PHPExcel();
         $creator = "ODK Workflow API";
         $phpExcel->getProperties()->setCreator($creator);
         $phpExcel->getProperties()->setLastModifiedBy($creator);
         $phpExcel->getProperties()->setTitle($title);
         $phpExcel->getProperties()->setSubject("Created using ".$creator);
         $phpExcel->getProperties()->setDescription("This Excel file has been generated using $creator that utilizes the PHPExcel library on PHP. $creator was created by Jason Rogena (j.rogena@cgiar.org)");
         $sheetNames = array_keys($data);
         try {
            for($sheetIndex = 0; $sheetIndex < count($data); $sheetIndex++) {
               if($sheetIndex > 0) {
                  $phpExcel->createSheet($sheetIndex);
               }
               $phpExcel->setActiveSheetIndex($sheetIndex);
               $phpExcel->getActiveSheet()->setTitle($sheetNames[$sheetIndex]);
               $sheetData = $data[$sheetNames[$sheetIndex]];
               //add the column titles
               if(count($sheetData) > 0) {
                  $columnNames = array_keys($sheetData[0]);
                  for($columnIndex = 0; $columnIndex < count($columnNames); $columnIndex++) {
                     $columnKey = PHPExcel_Cell::stringFromColumnIndex($columnIndex);//not sure if this should be 0 based or 1 based
                     $phpExcel->getActiveSheet()->setCellValue($columnKey."1", $columnNames[$columnIndex]);
                     $phpExcel->getActiveSheet()->getStyle($columnKey."1")->getFont()->setBold(true);
                     $phpExcel->getActiveSheet()->getColumnDimension($columnKey)->setAutoSize(true);
                  }
                  //add the data rows
                  for($rowIndex = 0; $rowIndex < count($sheetData); $rowIndex++) {
                     $currRow = $sheetData[$rowIndex];
                     $columnCount = count($currRow);
                     if($columnCount == count($columnNames)) {
                        for($columnIndex = 0; $columnIndex < $columnCount; $columnIndex++){
                           $currColumnName = $columnNames[$columnIndex];
                           $rowKey = $rowIndex + 2;
                           $columnKey = PHPExcel_Cell::stringFromColumnIndex($columnIndex);
                           $phpExcel->getActiveSheet()->setCellValue($columnKey.$rowKey, $currRow[$currColumnName]);
                        }
                     }
                     else {
                        $readableRI = $rowIndex + 1;
                        $lH->log(1, $tag, "Column count in current row ($readableRI) of $sheetNames[$sheetIndex] does not match the expected column count");
                        throw new WAException("Column count in current row ($readableRI) of $sheetNames[$sheetIndex] does not match the expected column count", WAException::$CODE_WF_PROCESSING_ERROR, null);
                     }
                  }
               }
               else {
                  $lH->log(2, $tag, "{$sheetNames[$sheetIndex]} does not have any data");
               }
            }
            //save the file
            $phpExcel->setActiveSheetIndex(0);
            $file = new WAFile($config, $workflowID, $database, $workingDir, "tmp", $title.".xlsx");
            $url = $file->saveAsExcelFile($phpExcel);
            return $url;
         } catch (WAException $ex1) {
            $lH->log(1, $tag, "An error occurred while trying to create excel file for data");
            throw new WAException("An error occurred while trying to create excel file for data", WAException::$CODE_WF_PROCESSING_ERROR, $ex1);
         }
      } catch (PHPExcel_Exception $ex) {
            $lH->log(1, $tag, "A PHPExcel error occurred while trying to create excel file for data");
            throw new WAException("A PHPExcel error occurred while trying to create excel file for data", WAException::$CODE_WF_PROCESSING_ERROR, $ex);
      }
   }
}

?>