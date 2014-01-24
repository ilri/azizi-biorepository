<?php
/**
 * The main class of the system. All other classes inherit from this main one
 *
 * @category   AVID
 * @package    ODKParser
 * @author     Jason Rogena <j.rogena@cgiar.org>
 * @since      v0.1
 */
error_reporting(E_ALL);

class Parser {
   /**
    * @var string    Tag to be used in logging
    */
   private $TAG = "Parse.php";
   
   /**
    * @var string       Relative path to the root of this project
    */
   private $ROOT = "../";
   
   /**
    * @var LogHandler   Object to handle all the logging
    */
   private $logHandler;
   
   /**
    * @var assoc_array  Associative array containig settings to be used by this file
    */
   private $settings;
   
   /**
    * @var PhpExcel     A place holder for the last error that occured. Useful for sending data back to the user
    */
   private $phpExcel;
   
   /**
    * @var Json         Json object parsed from the json string gotten from post
    */
   private $jsonObject;
   
   /**
    * @var  array       array containig indexes of all excel sheets created by this object
    */
   private $sheetIndexes;
   
   /**
    * @var array        Associative array containing all column names in all excel sheets in the format [sheet_name][column_index]
    */
   private $allColumnNames;
   
   /**
    * @var array        Associative array containing the name of the next row in each excel sheet
    */
   private $nextRowName;
   
   /**
    * @var string       Relative path where all images will be downloaded to for the current excel file
    */
   private $imagesDir;
   
   /**
    * @var string       Relative path to where all downloads in this project are put
    */
   private $downloadDir;
   
   /**
    * @var string       ID for this php session
    */
   private $sessionID;
   
   /**
    * @var string       XML string gotten from last post request
    */
   private $xmlString;
   
   /**
    * @var array       Associative array of keys and values for strings gotten from the XML string  
    */
   private $xmlValues;
   
   /**
    * @var string      URI to where exactly the project is in apache
    */
   private $rootDirURI;
   
   /**
    * @var string      Type of output. Can either be 'analysis' or 'viewing' 
    */
   private $parseType;
   
   /**
    * @var array      Array of language codes for languages specified in xml file as translations
    */
   private $languageCodes;
   
   /**
    * @var string     ODK instance id obtained from xml file
    */
   private $odkInstance;
   
   /**
    * @var array      Array of all input ids that store scanned barcodes
    */
   private $scannedBCs;
   
   /**
    * @var array      Array of all input ides that store manually entered barcodes
    */
   private $manualBCs;
   
   /**
    * @var object     Object containing general functions 
    */
   private $gTasks;

   /**
    * @var string     Wheter images should be downloaded from their respective urls. Can either be 'yes' or 'no'
    */
   private $dwnldImages;
   
   /**
    * @var array      Multidimensional array that holds all the cells in the CSV in the form $cells[row][column]. Values of the first row are considered headings
    */
   private $cells;
   
   /**
    * @var string     Name of the file caching the authentication cookies from the ODK aggregate server
    */
   private $authCookies;
   
   /**
    * @var array      Multidimensional array with sheets and the headings in those sheets. Headings are the first row of the CSV
    *                 Used only when parsing CSV
    */
   private $sheets;
   
   /**
    * @var string     prefix to be used when Parsing CSV when comparing keys in xml file with those in the CSV
    */
   private $idPrefix;
   
   /**
    * @var array      Array of all select input IDs obtained from the xml file 
    */
   private $selectIDs;
   
   /**
    *
    * @var string     The aggregate username to be used when Authenticating against the Aggregate server when fetching content from there
    */
   private $aUsername;
   
   /**
    *
    * @var string     The aggregate password to be used when Authenticating against the Aggregate server when fetching content from there
    */
   private $aPassword;
   
   /**
    *
    * @var string     User agent cURL will pretend to be during authentication against aggregate server
    */
   private $userAgent;
   
   /**
    *
    * @var string     URL through which authentication on the aggregate server will be done
    */
   private $authURL;
   
   public function __construct() {
      //load settings
      $this->loadSettings();
      
      //include modules
      include_once $this->ROOT.'modules/mod_log.php';
      $this->logHandler = new LogHandler();
      
      include_once $this->settings['common_lib_dir'].'mod_general_v0.6.php';
      $this->gTasks = new GeneralTasks();
      
      $this->logHandler->log(3, $this->TAG, 'initializing the Parser object');
      
      //init other vars
      $this->parseType = $_POST['parseType'];
      $this->logHandler->log(3, $this->TAG, 'requested parse type is '.$this->parseType);
      $this->sheetIndexes = array();
      $this->allColumnNames = array();
      $this->nextRowName = array();
      $this->sessionID = session_id();
      if($this->sessionID == NULL || $this->sessionID == "") {
         $this->sessionID = round(microtime(true) * 1000);
      }
      $this->imagesDir = $this->ROOT.'download/'.$this->sessionID.'/images';
      $this->downloadDir = $this->ROOT.'download/'.$this->sessionID;
      $this->xmlValues = array();
      $this->dwnldImages = $_POST['dwnldImages'];
      
      $this->cells = array();
      $this->authCookies = $this->ROOT."download/AUTH".mt_rand();
      $this->sheets = array();
      $this->idPrefix = "";
      $this->selectIDs = array();
      $this->authURL = "http://hpc.ilri.cgiar.org/aggregate/local_login.html?redirect=";
      
      //$this->rootDirURI = "/~jason/ilri/ODKParser/";
      $this->rootDirURI = $this->settings['root_uri'];
      
      $this->loadXML();
      include_once $this->settings['common_lib_dir'].'PHPExcel/PHPExcel.php';
      
      $mainSheetKey = "main_sheet";
      
      if(isset($_POST['jsonString']) && strlen($_POST['jsonString']) > 0){
          $this->logHandler->log(3, $this->TAG, 'Input is json. Parsing it as such');
          
          $this->parseJson();
          $jsonWidth = $this->gTasks->getArrayDepth($this->jsonObject);
          $this->logHandler->log(3, $this->TAG, 'Width of json is '.$jsonWidth);
          $this->logHandler->log(3, $this->TAG, 'Length of json is '.count($this->jsonObject));
          
          $jsonComplexity = count($this->jsonObject) * $jsonWidth;
          
          
          if($jsonComplexity > 500) {
              $this->logHandler->log(3, $this->TAG, 'Json appears to be complex ('.$jsonComplexity.'), excel sheet cells will be cached on disk');
              if(!file_exists($this->ROOT.'tmp')){
                  mkdir($this->ROOT.'tmp', 0777, true);    
              }
              $cacheMethod = PHPExcel_CachedObjectStorageFactory::cache_to_discISAM;
              $cacheSettings = array( 'dir' => $this->ROOT.'tmp');
              PHPExcel_Settings::setCacheStorageMethod($cacheMethod, $cacheSettings);
              
          }
          else{
              $this->logHandler->log(3, $this->TAG, 'Json is not complex ('.$jsonComplexity.'), excel sheets will not be cached on disk');
          }
          
          $this->phpExcel = new PHPExcel();
          $this->setExcelMetaData();
          
          $currMainSheetItem = 0;
          foreach($this->jsonObject as $currentJsonObject) {
              $sensibleIndex = $currMainSheetItem +1;
              $this->logHandler->log(3, $this->TAG, 'Now at main sheet item '.$sensibleIndex.' of '.count($this->jsonObject));
              $this->createSheetRow($currentJsonObject, $mainSheetKey, NULL, $currMainSheetItem);
              $currMainSheetItem++;
          }
      
      }
      else if(isset($_POST['csvString']) && strlen($_POST['csvString']) > 0){
          $this->logHandler->log(3, $this->TAG, 'Input is csv. Parsing it as such');
          
          $this->aUsername = $this->settings['aggregate_user'];
          $this->aPassword = $this->settings['aggregate_pass'];
          $this->userAgent = "Mozilla/5.0 (X11; Linux x86_64; rv:26.0) Gecko/20100101 Firefox/26.0";
          
          $this->parseCSV();
          
          $this->phpExcel = new PHPExcel();
          $this->setExcelMetaData();
          
          //replace all : in headings with -
          for($i=0; $i<sizeof($this->cells[0]); $i++){
              $this->cells[0][$i] = str_replace(":", "-", $this->cells[0][$i]);
          }
          
          $rowIndex = 0;
          while($rowIndex < sizeof($this->cells)){
              if(is_array($this->cells[$rowIndex])){
                  $readableRIndex = $rowIndex+1;
                  $this->logHandler->log(3, $this->TAG, 'At '.$readableRIndex." of ".sizeof($this->cells));
                  $this->logHandler->log(3, $this->TAG, 'Current CSV row has '.sizeof($this->cells[$rowIndex])." columns");
                  $columnIndex = 0;
                  
                  while($columnIndex < sizeof($this->cells[$rowIndex])){
                      $this->insertCSVCell($this->cells[$rowIndex][$columnIndex], $rowIndex, $this->cells[0][$columnIndex], $mainSheetKey);
                      $columnIndex++;
                  }    
              }
              $rowIndex++;
         }

         unlink($this->authCookies);
      }
      
      
      //clean all sheets
      if($this->parseType !== "viewing"){
          $sheetArrayKeys = array_keys($this->sheetIndexes);
          foreach ($sheetArrayKeys as $currSheet){
              $this->cleanSheet($currSheet);
          }
      }
      
      $this->phpExcel->setActiveSheetIndex($this->sheetIndexes[$mainSheetKey]);
      
      //save the excel object
      $this->logHandler->log(3, $this->TAG, 'saving the excel file as '.$_POST['fileName'].'.xlsx');
      if(!file_exists($this->downloadDir)){
         mkdir($this->downloadDir,0777,true);
      }
      $objWriter = new PHPExcel_Writer_Excel2007($this->phpExcel);
      $objWriter->save($this->downloadDir.'/'.$_POST['fileName'].'.xlsx');
      
      //create dictionary and save in download dir
      $this->logHandler->log(3, $this->TAG, 'Creating dictionary');
      $dictionary = new PHPExcel();
      $dictionary->getProperties()->setCreator($_POST['creator']);
      $dictionary->getProperties()->setLastModifiedBy($_POST['creator']);
      $dictionary->getProperties()->setTitle("dictionary");
      $dictionary->getProperties()->setSubject("Created using ODK Parser");
      $dictionary->getProperties()->setDescription("This Excel file has been generated using ODK Parser that utilizes the PHPExcel library on PHP. ODK Parse was created by Jason Rogena (j.rogena@cgiar.org)");
      
      $dictionary->setActiveSheetIndex(0);
      $dictionary->getActiveSheet()->setTitle("dictionary");
      $dictionary->getActiveSheet()->setCellValue("A1", "Code");
      $dictionary->getActiveSheet()->getStyle("A1")->getFont()->setBold(TRUE);
      $dictionary->getActiveSheet()->getColumnDimension("A")->setAutoSize(true);
      $lang = array("B","C","D","E","F","G","H","I","J","K","L","M","N","O","P");
      $codeIndex = 0;
      $allCodes = array_keys($this->xmlValues);
      foreach ($this->xmlValues as $currCode){
          //get number of languages
          $noLanguages = sizeof($currCode);
          
          if($codeIndex === 0){//is the first code, label all the language columns
              for ($i = 0; $i < $noLanguages; $i++){
                  $dictionary->getActiveSheet()->setCellValue($lang[$i]."1", $this->languageCodes[$i]);
                  $dictionary->getActiveSheet()->getStyle($lang[$i]."1")->getFont()->setBold(TRUE);
                  $dictionary->getActiveSheet()->getColumnDimension($lang[$i])->setAutoSize(true);
              }
          }
          $rowID = $codeIndex +2;
          
          $dictionary->getActiveSheet()->setCellValue("A".$rowID, $allCodes[$codeIndex]);
          
          for($i = 0; $i < $noLanguages; $i++){
              $dictionary->getActiveSheet()->setCellValue($lang[$i].$rowID, $currCode[$i]);
          }
          $codeIndex++;
      }
      
      $dicObjWriter = new PHPExcel_Writer_Excel2007($dictionary);
      $dicObjWriter->save($this->downloadDir.'/dictionary.xlsx');
      
      //zip parsed files
      $zipName = 'download/'.$this->sessionID.'.zip';
      //$this->zipParsedItems($this->downloadDir, $this->ROOT.$zipName);
      $this->logHandler->log(3, $this->TAG, 'zipping output files into '.$zipName);
      $this->gTasks->zipDir($this->downloadDir, $this->ROOT.$zipName);
      //$this->deleteDir($this->downloadDir);
      $this->logHandler->log(3, $this->TAG, 'deleting temporary dir '.$this->downloadDir);
      $this->gTasks->deleteDir($this->downloadDir);
      
      //send zip file to specified email
      $this->sendZipURL($zipName);
   }
   
   
   /*
    * This function loads settings from the main ini file
    */
   private function loadSettings() {
      $settingsDir = $this->ROOT."config/";
      if(file_exists($settingsDir."main.ini")) {
         $this->settings = parse_ini_file($settingsDir."main.ini");
      }
   }
   
   /**
    * Appends meta data to the excel file using details provided in post request. Meta data is good
    */
   private function setExcelMetaData() {
      $this->logHandler->log(3, $this->TAG, 'setting excel metadata');
      $this->phpExcel->getProperties()->setCreator($_POST['creator']);
      $this->phpExcel->getProperties()->setLastModifiedBy($_POST['creator']);
      $this->phpExcel->getProperties()->setTitle($_POST['fileName']);
      $this->phpExcel->getProperties()->setSubject("Created using ODK Parser");
      $this->phpExcel->getProperties()->setDescription("This Excel file has been generated using ODK Parser that utilizes the PHPExcel library on PHP. ODK Parse was created by Jason Rogena (j.rogena@cgiar.org)");
   }
   
   /**
    * This function returns the column name eg A, B, AA, AZ corresponding to a key in a json object
    * 
    * @param    string     $parentKey     The name of the excel sheet e.g main_sheet to be referenced agains 
    * @param    string     $key           The title of column in the excel sheet. Note that column titles in the excel sheet correspond to keys in a json object
    * @param    integer    $index         Index of $key in parent sheet if already known. Defaults to -1
    * 
    * @return   string     returns an excel column name/id eg AA AB etc
    */
   private function getColumnName($parentKey, $key, $index = -1){//a maximum of 676 (26*26) columns
     if($index === -1){
         $indexOfKey = array_search($key, $this->allColumnNames[$parentKey]);
         return PHPExcel_Cell::stringFromColumnIndex($indexOfKey);
     }
     else{
         return PHPExcel_Cell::stringFromColumnIndex($index);
     }
   }
   
   /**
    * The heart of this object. Is a recurssive function that takes in a json object and creates an excel sheet row corresponding to that object.
    * The keys in the json have corresponding columns eg in {"key1":"val1","key2":"val2"} there are corresponding columns for key1 and key2 in
    * the excel sheet. If the json object passed to this class is part of a json array (which it most likely is) the other json objects in the
    * json array will correspond to the other rows in the excel sheet eg:
    *   jsonArray = [jsonObject1, jsonObject2],
    *   jsonObject1 = {"key1":"val1_fromJO1","key2":"val2_fromJO1"} and
    *   jsonObject2 = {"key2":"val2_fromJO2","key3":"val3_fromJO2"}
    * 
    *   The excel sheet corresponding to jsonArray will look like:
    *   ________________________________________________
    *   |     key1      |     key2     |     key3      |
    *   ------------------------------------------------
    *   |  val1_fromJO1 | val2_fromJO1 |     NULL      |
    *   |      NULL     | val2_fromJO2 |  val3_fromJO2 |
    * 
    * 
    * @param    assoc-array     $jsonObject         The jsonObject with which you want to generate the excel sheet row     
    * @param    string          $parentKey          The name of the sheet in which you want to create the row eg main_sheet
    * @param    string          $parentCellName     Defaults to NULL. If specified, this is the id of the parent row in cases where the provided jsonObject is an instance in a repeat
    * @param    int             $rowIndex           Defaults to NULL. If specified, this is the index of the jsonObject in its parent jsonArray. "primary_key" column is filled with respective value if specified
    */
   private function createSheetRow($jsonObject, $parentKey, $parentCellName = NULL, $rowIndex = -1) {
      $this->logHandler->log(4, $this->TAG, 'creating a new sheet row in '.$parentKey);
      //check if sheet for parent key exists
      $sheetArrayKeys = array_keys($this->sheetIndexes);
      $isNewSheet = FALSE;
      if(!in_array($parentKey, $sheetArrayKeys)) {
          //sheet for parentKey does not exist. We now attempt to create it
          
         $isNewSheet = TRUE;
         $this->logHandler->log(2, $this->TAG, 'sheet for '.$parentKey.' does not exist');
         //create sheet for parent key
         
         $this->logHandler->log(4, $this->TAG, 'size of sheet indexes before '.sizeof($this->sheetIndexes));
         $this->sheetIndexes[$parentKey] = sizeof($this->sheetIndexes);
         
         $this->logHandler->log(4, $this->TAG, 'size of sheet indexes now '.sizeof($this->sheetIndexes));
         $this->nextRowName[$parentKey] = 2;
         $this->allColumnNames[$parentKey] = array();
         
         if(sizeof($this->sheetIndexes)>1){
            $this->phpExcel->createSheet();
            $this->logHandler->log(4, $this->TAG, 'this is not the first sheet, therefore calling createSheet');
         }
         $this->phpExcel->setActiveSheetIndex($this->sheetIndexes[$parentKey]);
         $this->logHandler->log(4, $this->TAG, 'set active sheet index to '.$this->sheetIndexes[$parentKey]);
         $this->phpExcel->getActiveSheet()->setTitle($parentKey);
      }
      else {
         //Sheet corresponding to parentKey already exists, set active sheet to that which corresponds to parent key
         $this->phpExcel->setActiveSheetIndex($this->sheetIndexes[$parentKey]);
         $this->logHandler->log(4, $this->TAG, 'sheet for '.$parentKey.' already exists');
      }
      
      //split keys and values in jsonObject
      $this->logHandler->log(4, $this->TAG, 'splitting keys and values in jsonObject');
      $keys = array_keys($jsonObject);
      $values = array();
      $index = 0;
      foreach($jsonObject as $value) {
         $values[$index] = $value;
         $index++;
      }
      $this->logHandler->log(4, $this->TAG, 'size of values is '.sizeof($values));
      
      //get next row name in sheet corresponding to  parent key
      $rowName = $this->nextRowName[$parentKey];
      $this->logHandler->log(4, $this->TAG, 'row name is '.$rowName);
      
      //set primary_key as first cell in row if required
      if($rowIndex !== -1){
          if(!in_array("primary_key", $this->allColumnNames[$parentKey])){
              $this->logHandler->log(4, $this->TAG, 'pushing primary_key to allColumnNames array for ' . $parentKey);
              array_push($this->allColumnNames[$parentKey], "primary_key");
          }
          
          $columnName = $this->getColumnName($parentKey, "primary_key");
          if($columnName !== FALSE){
              if($isNewSheet === TRUE){
                  $this->phpExcel->getActiveSheet()->setCellValue($columnName."1", "primary_key");
                  $this->phpExcel->getActiveSheet()->getStyle($columnName."1")->getFont()->setBold(TRUE);
              }
              $cellName = $columnName . $rowName;
              $this->phpExcel->getActiveSheet()->setCellValue($cellName, $this->odkInstance."_".$rowIndex); //instance of the ODK file plus the id of the row eg dgea2_1
              $this->phpExcel->getActiveSheet()->getColumnDimension($columnName)->setAutoSize(true);
          }else {
            //echo 'column name for Parent_Cell not found<br/>';
            $this->logHandler->log(2, $this->TAG, 'column name for primary_key not found '.print_r($this->allColumnNames[$parentKey],TRUE));
            //print_r($this->allColumnNames[$parentCellName]);
         }
      }
      
      //set name of parent cell as first/second cell in row if is set
      if ($parentCellName != NULL) {
         if (!in_array("secondary_key", $this->allColumnNames[$parentKey])) {
            $this->logHandler->log(4, $this->TAG, 'pushing secondary_key to allColumnNames array for ' . $parentKey);
            array_push($this->allColumnNames[$parentKey], "secondary_key");
            //$this->allColumnNames[$parentKey][sizeof($this->allColumnNames[$parentKey])]="Parent_Cell";
         }
         $columnName = $this->getColumnName($parentKey, "secondary_key");
         if ($columnName !== FALSE) {//safe to continue
            if($isNewSheet === TRUE){
               $this->phpExcel->getActiveSheet()->setCellValue($columnName."1", "secondary_key");
               $this->phpExcel->getActiveSheet()->getStyle($columnName."1")->getFont()->setBold(TRUE);
            }
            $cellName = $columnName . $rowName;
            $this->phpExcel->getActiveSheet()->setCellValue($cellName, $parentCellName);
            $this->phpExcel->getActiveSheet()->getColumnDimension($columnName)->setAutoSize(true);
            //$this->phpExcel->getActiveSheet()->getStyle($cellName)->getAlignment()->setWrapText(true);
         } else {
            $this->logHandler->log(2, $this->TAG, 'column name for secondary_key not found '.print_r($this->allColumnNames[$parentKey],TRUE));
            //print_r($this->allColumnNames[$parentCellName]);
         }
      }
      
      //add all keys and respective values to row
      if(sizeof($keys) == sizeof($values) && sizeof($values) > 0) {
          
         $this->logHandler->log(4, $this->TAG, 'adding columns from here');
         for($index = 0; $index < sizeof($keys); $index++) {
            //add key to allColumns array
            $columnExisted = TRUE;
            
            //check of columnName ($keys[$index]) existed in allColumnNames[parentKey] add add it if it does not
            if(!in_array($keys[$index], $this->allColumnNames[$parentKey])) {
               $columnExisted = FALSE;
               //echo 'pushing '.$keys[$index].' to allColumnNames array for '.$parentKey.'<br/>';
               $this->logHandler->log(4, $this->TAG, 'pushing '.$keys[$index].' to allColumnNames array for '.$parentKey);
               //array_push($this->allColumnNames[$parentKey], $keys[$index]);
               $this->allColumnNames[$parentKey][sizeof($this->allColumnNames[$parentKey])]=$keys[$index];
               
            }
            
            $columnName = $this->getColumnName($parentKey, $keys[$index]);
            if($columnExisted === FALSE) {
               $this->phpExcel->getActiveSheet()->getColumnDimension($columnName)->setAutoSize(true);
            }
            
            if ($columnName !== FALSE) {
               $cellName = $columnName . $rowName;
               $this->phpExcel->setActiveSheetIndex($this->sheetIndexes[$parentKey]);
               
               //Add title to column
               if($columnExisted === FALSE){
                  if($this->parseType==="viewing"){
                      $this->phpExcel->getActiveSheet()->setCellValue($columnName."1", $this->convertKeyToValue($keys[$index]));
                  }
                  else{
                      $this->phpExcel->getActiveSheet()->setCellValue($columnName."1", $keys[$index]);
                  }
                  
                  $this->phpExcel->getActiveSheet()->getStyle($columnName."1")->getFont()->setBold(TRUE);
               }
          
               //fix for bug in json file were multi select answers are sparated by spaces
               /*if(!is_array($values[$index])){
                  $exploded = explode(" ",$values[$index]);
                  if(sizeof($exploded)>1){
                     $values[$index]=$exploded;
                  }
          }*/
               //end of fix

               if (!is_array($values[$index])) {//values[index] is a string
                  //echo 'value of '.$keys[$index].' is '.$values[$index].'<br/>';
                  $this->logHandler->log(4, $this->TAG, 'value of '.$keys[$index].' is '.$values[$index]);
                  
                  //check if value is a barcode
                  if(strlen($values[$index]) > 0 && in_array($keys[$index], $this->manualBCs) && $this->parseType !== "viewing"){
                      //is a barcode that was entered manually, now get index of the key in manualBCs array
                      $barcodeIndex = array_search($keys[$index], $this->manualBCs, TRUE);
                      
                      //get key for scanned barcode field corresponding to manual barcode field
                      $scannedBCKey = $this->scannedBCs[$barcodeIndex];
                      
                      //get sheet column name corresponding to scannedBCKey
                      $scannedBCColumnName = $this->getColumnName($parentKey, $scannedBCKey);
                      if($scannedBCColumnName!== FALSE){
                          //set value of cell corresponding to scanned barcode to values[index]
                          $scannedBCCellName = $scannedBCColumnName.$rowName;
                          $this->phpExcel->getActiveSheet()->setCellValue($scannedBCCellName, $values[$index]);
                          $values[$index] = "null";
                      }
                  }
                  
                  if(filter_var($values[$index], FILTER_VALIDATE_URL) && $this->dwnldImages === "yes") {
                     //$values[$index] = $this->downloadImage($values[$index]);
                     $this->logHandler->log(4, $this->TAG, 'checking if '.$values[$index].' is image');
                     $values[$index] = $this->gTasks->downloadImage($values[$index], $this->imagesDir);
                  }
                  
                  $values[$index] = $this->formatTime($values[$index]);//format values[index] if it is time
                  
                  if(strlen($values[$index]) === 0) {
                     $values[$index] = "null";
                  }
                  
                  //if output data is meant for easy viewing, convert values[index] to its respective string (parsed from xml file) else just save the code as is
                  if($this->parseType === "viewing"){
                      $this->phpExcel->getActiveSheet()->setCellValue($cellName, $this->convertKeyToValue($values[$index]));
                  }
                  else{
                      $this->phpExcel->getActiveSheet()->setCellValue($cellName, $values[$index]);
                  }
               } 
               else {//if values[index] is an array
                  
                  //check if values[index] is empty. If not continue processing
                  if (sizeof($values[$index]) > 0) {
                     $this->logHandler->log(4, $this->TAG, 'value of '.$keys[$index].' is an array');
                     
                     //check if elements of values[index] are valid json objects
                     $testChild = $values[$index][0];
                     if ($this->gTasks->isJson($testChild) === TRUE) {
                        $this->phpExcel->getActiveSheet()->setCellValue($cellName, "Check " . $keys[$index] . " sheet");
                        foreach ($values[$index] as $childJsonObject) {
                           
                            $this->createSheetRow($childJsonObject, $keys[$index], $this->odkInstance."_".$rowIndex);//NOTE that this is wher the createSheetRow function calls itself
                           
                        }
                     }
                     else{//means that the array contains multiple select values
                        if($this->parseType === "viewing"){
                            $commaSeparatedString = "";
                            foreach ($values[$index] as $childString){
                               if($commaSeparatedString === "") $commaSeparatedString = $this->convertKeyToValue ($childString);
                               else $commaSeparatedString = $commaSeparatedString . ", " . $this->convertKeyToValue ($childString);
                            }
                            $this->phpExcel->getActiveSheet()->setCellValue($cellName, $commaSeparatedString);
                        }
                        else{
                            $this->phpExcel->getActiveSheet()->setCellValue($cellName, "null");
                            foreach($values[$index] as $childString){
                                $columnExisted = TRUE;
                                $newKey = $keys[$index]."-".$childString;
                                if(!in_array($newKey, $this->allColumnNames[$parentKey])){
                                    $columnExisted = FALSE;
                                    
                                    $this->logHandler->log(4, $this->TAG, 'pushing '.$newKey.' to allColumnNames array for '.$parentKey);
                                    array_push($this->allColumnNames[$parentKey], $newKey);
                                }
                                
                                $newColumnName = $this->getColumnName($parentKey, $newKey);
                                if($columnExisted === FALSE){
                                    $this->phpExcel->getActiveSheet()->getColumnDimension($newColumnName)->setAutoSize(true);
                                    $this->phpExcel->getActiveSheet()->setCellValue($newColumnName."1", $newKey);
                                    $this->phpExcel->getActiveSheet()->getStyle($newColumnName."1")->getFont()->setBold(TRUE);
                                }
                                $newCellName = $newColumnName.$rowName;
                                $this->phpExcel->getActiveSheet()->setCellValue($newCellName, "1");
                            }
                        }
                        
                     }
                        
                  }
                  else {
                     //echo 'value of '.$keys[$index].' is an array but is empty<br/>';
                     $this->logHandler->log(2, $this->TAG, 'value of '.$keys[$index].' is an array but is empty');
                     $this->phpExcel->getActiveSheet()->setCellValue($cellName, "null");
                  }
               }
               //$this->phpExcel->getActiveSheet()->getStyle($cellName)->getAlignment()->setWrapText(true);
            }
            else {
               //echo 'column name for '.$keys[$index].' not found<br/>';
               $this->logHandler->log(2, $this->TAG, 'column name for '.$keys[$index].' not found '.print_r($this->allColumnNames[$parentKey],TRUE));
            }
            
         }
      }
      else {
         //means the jsonObject is just a string and not a json object. This is bad
         $this->logHandler->log(1, $this->TAG, 'jsonobject provided to createSheetRow is a string ('.$jsonObject.'). This should not have happened. Killing process');
         exit(0);
      }
      $this->nextRowName[$parentKey]++;
   }
   
   private function insertCSVCell($cellString, $rowIndex, $columnHeading, $parentSheetName){
       $sheetNames = array_keys($this->sheets);

        if (!in_array($parentSheetName, $sheetNames)) {
            $this->sheets[$parentSheetName] = array();
            $this->logHandler->log(4, $this->TAG, "First time encountering " . $parentSheetName);
            if (sizeof($sheetNames) > 0) {
                $this->phpExcel->createSheet();
            }
            $this->sheetIndexes[$parentSheetName] = sizeof($sheetNames);

            $this->phpExcel->setActiveSheetIndex($this->sheetIndexes[$parentSheetName]);
            $this->phpExcel->getActiveSheet()->setTitle($parentSheetName);
        } else {
            $this->phpExcel->setActiveSheetIndex($this->sheetIndexes[$parentSheetName]);
        }

        if (!in_array($columnHeading, $this->sheets[$parentSheetName])) {
            array_push($this->sheets[$parentSheetName], $columnHeading);
        }
        $columnIndex = array_search($columnHeading, $this->sheets[$parentSheetName]);

        //check if string is non image url
        $rowID = $rowIndex + 1;
        $cellID = $this->getColumnName(NULL, NULL, $columnIndex) . $rowID;
        if (filter_var($cellString, FILTER_VALIDATE_URL) && $this->isImage($cellString) === FALSE) {
            $this->phpExcel->getActiveSheet()->setCellValue($cellID, "Check " . $this->cells[0][$columnIndex] . " sheet");
            $this->parseHTMLTable($cellString, $this->cells[0][$columnIndex], $rowIndex);
        }
        else if(filter_var($cellString, FILTER_VALIDATE_URL) &&  $this->isImage($cellString) ===TRUE && $this->dwnldImages === "yes"){ //is image
            $this->logHandler->log(4, $this->TAG, 'checking if '.$values[$index].' is image');
            $cellString = $this->gTasks->downloadImage($cellString, $this->imagesDir);
        }
        else {
            if (!in_array("-" . $this->idPrefix . "-" . $columnHeading, $this->selectIDs)) {//not a multiple select question         
        
                //check if value is a barcode
                if(strlen($cellString) > 0 && in_array($this->getAltHeadingName($columnHeading), $this->manualBCs) && $this->parseType !== "viewing" && $rowIndex!==0){
                    //is a barcode that was entered manually, now get index of the key in manualBCs array
                    $barcodeIndex = array_search($this->getAltHeadingName($columnHeading), $this->manualBCs, TRUE);
                      
                    //get key for scanned barcode field corresponding to manual barcode field
                    $scannedBCKey = $this->scannedBCs[$barcodeIndex];
                    
                    //since $scannedBCKey is not the full key rather just the last part
                    $headings = $this->sheets[$parentSheetName];
                    $scannedBCColumnName = FALSE;
                    for($i = 0; $i<sizeof($headings); $i++){
                        if(strpos($headings[$i], $scannedBCKey) !== FALSE){//$scannedBCKey is in $headings[$i]
                            $scannedBCColumnName = $this->getColumnName(NULL, NULL, $i);
                            break;
                        }
                    }
                      
                    //get sheet column name corresponding to scannedBCKey
                    if($scannedBCColumnName!== FALSE){
                        //set value of cell corresponding to scanned barcode to values[index]
                        $scannedBCCellName = $scannedBCColumnName.$rowID;
                        $this->phpExcel->getActiveSheet()->setCellValue($scannedBCCellName, $cellString);
                        $cellString = "null";
                    }
                }
                
                if (strlen($cellString) === 0) {
                    $cellString = "null";
                }
                
                $cellString = $this->formatTime($cellString);
                if($rowIndex!==0 && $this->parseType === "viewing"){
                    $cellString = $this->convertKeyToValue($cellString);
                }
                else if($rowIndex === 0){
                    $headings = explode("-", $cellString);
                    $cellString = "";
                    foreach ($headings as $currHeading){
                        if(strlen($cellString) === 0){
                            $cellString = $this->convertKeyToValue($currHeading);
                        }
                        else{
                            $cellString = $cellString." :: ".$this->convertKeyToValue($currHeading);
                        }
                    }
                }
                
                $this->phpExcel->getActiveSheet()->setCellValue($cellID, $cellString);
                if ($rowIndex === 0) {
                    $this->phpExcel->getActiveSheet()->getStyle($cellID)->getFont()->setBold(TRUE);
                    $this->phpExcel->getActiveSheet()->getColumnDimension($this->getColumnName(NULL, NULL, $columnIndex))->setAutoSize(true);
                }
            } else if ($rowIndex > 0) {//is a multiple select question
                if($this->parseType === "viewing"){
                    $selectAns = explode(" ", $cellString);
                    
                    $cellString = "";
                    foreach ($selectAns as $currSelectAns){
                        if(strlen($cellString) === 0){
                            $cellString = $this->convertKeyToValue($currSelectAns);
                        }
                        else{
                            $cellString = $cellString.", ".$this->convertKeyToValue($currSelectAns);
                        }
                    }
                    
                    if(strlen($cellString) === 0){
                        $cellString = "null";
                    }
                    
                    $this->phpExcel->getActiveSheet()->setCellValue($cellID, $cellString);
                }
                else{//excel is for analysis purposes
                    $this->phpExcel->getActiveSheet()->setCellValue($cellID, "null");
                    $selectAns = explode(" ", $cellString);
                    foreach ($selectAns as $currAns) {
                        if (strlen($currAns) > 0) {
                            $currAnsColmnIndex = 0;
                            if (!in_array($columnHeading . "-" . $currAns, $this->sheets[$parentSheetName])) {
                                array_push($this->sheets[$parentSheetName], $columnHeading . "-" . $currAns);

                                $currAnsColmnIndex = array_search($columnHeading . "-" . $currAns, $this->sheets[$parentSheetName]);
                                $currHeadingCellID = $this->getColumnName(NULL, NULL, $currAnsColmnIndex) . "1";

                                $this->phpExcel->getActiveSheet()->setCellValue($currHeadingCellID, $columnHeading . "-" . $currAns);
                                $this->phpExcel->getActiveSheet()->getStyle($currHeadingCellID)->getFont()->setBold(TRUE);
                                $this->phpExcel->getActiveSheet()->getColumnDimension($this->getColumnName(NULL, NULL, $currAnsColmnIndex))->setAutoSize(true);
                            }

                            $currAnsColmnIndex = array_search($columnHeading . "-" . $currAns, $this->sheets[$parentSheetName]);

                            $currAnsCellID = $this->getColumnName(NULL, NULL, $currAnsColmnIndex) . $rowID;
                            $this->phpExcel->getActiveSheet()->setCellValue($currAnsCellID, "1");
                        }
                    }
                }
            } else {//is a multiple select question heading
                $this->phpExcel->getActiveSheet()->setCellValue($cellID, $cellString);
                $this->phpExcel->getActiveSheet()->getStyle($cellID)->getFont()->setBold(TRUE);
                $this->phpExcel->getActiveSheet()->getColumnDimension($this->getColumnName(NULL, NULL, $columnIndex))->setAutoSize(true);
            }
        }
    }
    
    private function getAltHeadingName($heading){
        $headingParts = explode("-",$heading);
        return $headingParts[sizeof($headingParts)-1];
    }


    private function parseHTMLTable($url, $sheetName, $secondaryKey) {
        $encodedURL = urlencode($url);
        $authURL = $this->authURL.$encodedURL;
        $sheetNames = array_keys($this->nextRowName);
        if(!in_array($sheetName, $sheetNames)){
            $this->nextRowName[$sheetName] = 0;
        }

        if(file_exists($this->authCookies) === FALSE){
            touch($this->authCookies);
            chmod($this->authCookies, 0777);
            $this->logHandler->log(3, $this->TAG, 'Authenticating '.$this->aUsername.' on the Aggregate server');
            $authCh = curl_init($authURL);
            curl_setopt($authCh, CURLOPT_USERAGENT, $this->userAgent);
            curl_setopt($authCh, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($authCh, CURLOPT_FOLLOWLOCATION, TRUE);
            curl_setopt($authCh, CURLOPT_CONNECTTIMEOUT, TRUE);
            curl_setopt($authCh, CURLOPT_COOKIEJAR, $this->authCookies);
            curl_setopt($authCh, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
            curl_setopt($authCh, CURLOPT_USERPWD, $this->aUsername.":".$this->aPassword);

            curl_exec($authCh);
            curl_close($authCh);
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, TRUE); 
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->authCookies);

        $html = curl_exec($ch);
        curl_close($ch);

        $htmlTables = array();

        preg_match_all("/<table(.*\n*.*)<\/table>/",$html,$htmlTables);

        //check the number of tables in page (should be one)
        if(sizeof($htmlTables[1])===1) {
            $dataTable = $htmlTables[1][0];
            //print_r($htmlTables);
            //get the table headers <th>
            $th = array();
            $uncleanTHs = explode('</th>', $dataTable);
            $cleanTHCount = 0;
            foreach($uncleanTHs as $currTH){
               if(strpos($currTH, '<th>') !== FALSE){
                  $splitTH = explode('<th>',$currTH);
                  $th[$cleanTHCount] = $splitTH[1];
                  $cleanTHCount++;
               }
            }
            $headings = $th;//table headings gotten

            for($i=0; $i<sizeof($headings); $i++){
                $headings[$i] = $sheetName."-".$headings[$i];
            }

            array_unshift($headings, "secondary_key");

            $sheetNames = array_keys($this->sheets);
            if(!in_array($sheetName, $sheetNames)){
               for($hIndex = 0; $hIndex < sizeof($headings); $hIndex++){
                   $this->insertCSVCell($headings[$hIndex], $this->nextRowName[$sheetName], $headings[$hIndex], $sheetName);
               }
               $this->nextRowName[$sheetName]++;
            }


            $tr = array();
            preg_match_all("/<\/th>\s*(<tr>.*<\/tr>$)/",$dataTable,$tr);
            $tr = $tr[1];

            if(sizeof($tr) === 1){
                $rows = explode("</tr>", $tr[0]);
                for($rowIndex = 0; $rowIndex < sizeof($rows); $rowIndex++){
                    //check if row is actual table row
                    if(strpos($rows[$rowIndex],'<tr>') !== false){
                        $rows[$rowIndex] = str_replace("<tr>","",$rows[$rowIndex]);
                        $rowColumns = explode("</td>", $rows[$rowIndex]);

                        array_unshift($rowColumns, $this->odkInstance."_".$secondaryKey);

                        if((sizeof($headings)+1) === sizeof($rowColumns)){
                            for($columnIndex = 0; $columnIndex < sizeof($rowColumns); $columnIndex++){
                                if($columnIndex<sizeof($headings)){
                                   $rowColumns[$columnIndex] = str_replace("<td>","", $rowColumns[$columnIndex]);
                                   $this->insertCSVCell($rowColumns[$columnIndex], $this->nextRowName[$sheetName], $headings[$columnIndex], $sheetName);
                                }
                            }
                        }
                        else{
                            $this->logHandler->log(4, $this->TAG, 'Badly parsed tables look like this: '.sizeof($headings).' '.sizeof($rowColumns).' '.$html );
                            $this->logHandler->log(1, $this->TAG, 'it appears the rows in html table were parsed badly, exiting');
                            exit();
                        }
                        $this->nextRowName[$sheetName]++;
                    }
                }
            }
        }
        else{
            $this->logHandler->log(4, $this->TAG, 'Badly parsed html looks like this: '.$html);
            $this->logHandler->log(1, $this->TAG, 'HTML appears to have been parsed badly, exiting');
            exit();
        }

        //print_r($htmlTables);
    }

    /**
    * This method checks all cells in the sheet specified to see if they are blank and inserts a 0 if blank found
    * 
    * @param    string      $sheetName      The name of the sheet eg main_sheet
    */
   private function cleanSheet($sheetName){
       $this->logHandler->log(3, $this->TAG, 'cleaning '.$sheetName.' sheet ');
       $this->phpExcel->setActiveSheetIndex($this->sheetIndexes[$sheetName]);
       foreach($this->phpExcel->getActiveSheet()->getRowIterator() as $row){
           $rowIndex = $row->getRowIndex();
           //get array of all column headings in sheet
           if(sizeof($this->allColumnNames)>0)//means we are parsing json
               $sheetRowHeadings = $this->allColumnNames[$sheetName];
           else if(sizeof($this->sheets)>0)//means we are parsing csv
               $sheetRowHeadings = $this->sheets[$sheetName];
           foreach($sheetRowHeadings as $columnHeading){
               //get column name/id corresponding to columnHeading
               if(sizeof($this->allColumnNames)>0)//means we are parsing json
                   $columnName = $this->getColumnName($sheetName, $columnHeading);
               else if(sizeof($this->sheets)>0)//means we are parsing csv
                   $columnName = $this->getColumnName(NULL, NULL, array_search($columnHeading, $sheetRowHeadings));
               $cellName = $columnName.$rowIndex;
               $cell = $this->phpExcel->getActiveSheet()->getCell($cellName);
               if(strlen($cell->getValue()) === 0){
                   $this->logHandler->log(4, $this->TAG, 'cell '.$cellName.' in '.$sheetName.' is empty, inserting 0 into it');
                   //setting cell value to 0 if cell is blank
                   $this->phpExcel->getActiveSheet()->setCellValue($cellName, "0");
               }
           }
       }
   }


   /**
    * This function gets the string corresponding to a string code used in ODK eg if mlk is passed to this function and mlk = Milk according to the xml file
    * then Milk will be returned
    * 
    * @param    string      $key    The key which a corresponding values is to be found
    *         
    * @return   string      Returns the string value corresponding to key according to the xml file or returns $key if on corresponding values is found
    */
   private function convertKeyToValue($key) {
      //get the default language
      $defaultLangIndex = 0;
      for($i = 0; $i < sizeof($this->languageCodes); $i++){
          if(preg_match("/eng.*/i", $this->languageCodes[$i]) === 1){ //preg_match returns 1 if pattern matches and 0 if it doesnt
              $defaultLangIndex = $i;
          }
      }
      
      //get the default language's string value of string code (key)
      if((is_string($key) ||  is_numeric($key)) && array_key_exists($key, $this->xmlValues)) {
         return $this->xmlValues[$key][$defaultLangIndex];
      }
      else {
         return $key;
      }
   }
   
   /**
    * This function formats time that are in the form yyyy:MM:ddThh:mm:ss:ms+TZ into hh:mm:ss dd-MM-yyyy +TZ (GMT)
    * 
    * @param    string      $timeString     The time to be formated
    * 
    * @return   string      Returns the formated time if input time ($timeString) is of specified form or $timeString if not
    */
   private function formatTime($timeString){
       //check if string is time
       if(preg_match("/([0-9]{4})-([0-9]{2})-([0-9]{2})T([0-9]{2}):([0-9]{2}):([0-9]{2})\.([0-9]{3})(\+[0-9]{2})/i", $timeString) === 1){
           $timeFragments = array();
           preg_match_all("/([0-9]{4})-([0-9]{2})-([0-9]{2})T([0-9]{2}):([0-9]{2}):([0-9]{2})\.([0-9]{3})(\+[0-9]{2})/i", $timeString, $timeFragments);
           $formatedTime = $timeFragments[4][0].":".$timeFragments[5][0].":".$timeFragments[6][0]." ".$timeFragments[3][0]."-".$timeFragments[2][0]."-".$timeFragments[1][0]." ".$timeFragments[8][0]." GMT";
           return $formatedTime;
       }
       else{
           return $timeString;
       }
   }
   
   /**
    * This function parses the json string in post into a jsonObject/associative arry
    */
   private function parseJson() {
      $this->logHandler->log(3, $this->TAG, 'parsing json string obtained from post');
      $jsonString = $_POST['jsonString'];
      $this->jsonObject = json_decode($jsonString, TRUE);
      $this->logHandler->log(4, $this->TAG, 'json object is '.print_r($this->jsonObject,TRUE));
      if($this->jsonObject === NULL){
         $this->logHandler->log(1, $this->TAG, 'unable to parse the json object in post request, exiting' . json_last_error());
         die();
      }
   }
   
   private function parseCSV() {
       $rows = explode("\"\n\"", $_POST['csvString']);
       $index = 0;
       while($index < sizeof($rows)){
           $columns = explode("\",\"", $rows[$index]);
            
            //remove the double quotes still remaining in the first and last elements of the columns array
           $columns[0] = str_replace("\"","",$columns[0]);
           $columns[sizeof($columns) - 1] = str_replace("\"", "", $columns[sizeof($columns) - 1]);
           
           if($index===0){
               array_unshift($columns,"primary_key");
           }
           else{
               array_unshift($columns,$this->odkInstance."_".$index);
           }
           
           $this->cells[$index] = $columns;
           $index++;
       }
   }
   
   /**
    * This function parses the xml file in the post request to obtain:
    *   - The ODK language codes specified in the xml string
    *   - The ODK instance id for the form being parsed
    *   - The fields in the jsonObject in the post request that hold scanned barcodes and manually entered barcodes
    *   - The string codes and their corresponding string values specified as part of the ODK UI
    */
   private function loadXML() {
      $this->logHandler->log(3, $this->TAG, 'parsing xml obtained from post');
      //$this->xmlString = file_get_contents($this->ROOT . "animals.xml");
      $tmpXMLString = $_POST['xmlString'];
      
      //replace all the ascii codes with the real sh*t
      $tmpXMLString = str_replace("&lt;", "<", $tmpXMLString);
      $tmpXMLString = str_replace("&gt;", ">", $tmpXMLString);
      $tmpXMLString = str_replace("&#61;", "=", $tmpXMLString);
      $this->xmlString = $tmpXMLString;
      
      $matches = array();//temp var for inserting regex matches. Using one variable to save mem
      
      //get all the language codes for languages specifed as translations in xml
      preg_match_all("/\s+<translation\s+lang=[\"'](.+)[\"']>/", $this->xmlString, $matches);
      $this->languageCodes = $matches[1];
      
      //get the odk instance id for the form
      preg_match_all("/<instance>[\s\n]*<([a-z0-9_\-]+)\s+id=[\"']([a-z0-9_]+)[\"']\s*>/i", $this->xmlString, $matches);
      $this->idPrefix = $matches[1][0];
      $tempODKInstance = $matches[2][0];//assuming that the first instance tag in xml file is what we are looking for
      
      //check if the last part of the instance contains a version number
      /*if(preg_match("/.+_[v][ersion_\-]*[0-9]$/i", $tempODKInstance) === 1){
         $this->logHandler->log(4, $this->TAG, $tempODKInstance." contains a version number, removing it");
         preg_match_all("/(.+)_[v][ersion_\-]*[0-9]$/i", $tempODKInstance, $matches);
         $tempODKInstance = $matches[1][0];
      }*/
      $this->odkInstance = $tempODKInstance;
      
      //associate all the barcode inputs in xml with their respective manual barcode inputs
      preg_match_all("/<bind\s+nodeset\s*=\s*[\"'](.+)[\"']\s+type\s*=\s*[\"']barcode[\"']/i", $this->xmlString, $matches);
      $barcodeURLS = $matches[1];
      $barcodeManualURLS = array();
      for($i = 0; $i < sizeof($barcodeURLS); $i++){
          //get respective manual input corresponding to barcode input
          preg_match_all("/<bind\s+nodeset\s*=\s*[\"'](.+)[\"']\s+type\s*=\s*[\"']string[\"'].+relevant\s*=\s*[\"']\s*".str_replace("/", "\/", $barcodeURLS[$i])."\s*=\s*[\"']{2}/i", $this->xmlString, $matches);
          $barcodeManualURLS[$i] = $matches[1][0];
      }
      
      $this->scannedBCs = array();
      $this->manualBCs = array();
      //get just the id of the barcode input urls eg bsr in /dgea2/bsr
      for($i = 0; $i < sizeof($barcodeURLS); $i++){
          preg_match_all("/.+\/([a-z0-9_\-]+)$/i",$barcodeURLS[$i],$matches);
          $this->scannedBCs[$i] = $matches[1][0];
      }
      for($i = 0; $i < sizeof($barcodeManualURLS); $i++){
          preg_match_all("/.+\/([a-z0-9_\-]+)$/i",$barcodeManualURLS[$i],$matches);
          $this->manualBCs[$i] = $matches[1][0];
      }
      
      //get all the string codes codes
      preg_match_all("/\s+?<text\s+id=[\"'](.+)[\"']\s*>\s*<value>.+<\/value>\s*<\/text>/", $this->xmlString, $matches);
      
      //add all unique codes found into new array
      $codes = array();
      foreach ($matches[1] as $currCode) {
         if(!in_array($currCode, $codes)){
            array_push($codes, $currCode);
         }
      }
      
      $this->xmlValues = array();
      //get all values for each unique code
      foreach ($codes as $currCode){
          preg_match_all("/\s+?<text\s+id=[\"']".$currCode."[\"']\s*>\s*<value>(.+)<\/value>\s*<\/text>/", $this->xmlString, $matches);
          $this->xmlValues[$currCode] = $matches[1];
      }
      
      //print_r($this->xmlValues);
      $this->logHandler->log(4, $this->TAG, 'strings obtained from xml file are'.print_r($this->xmlValues,TRUE));
      
      preg_match_all("/<bind\s+nodeset\s*=\s*[\"']([a-z_0-9\-\/]+)[\"'].*type\s*=\s*[\"']select[\"'].*(?=\/>)/",$this->xmlString,$matches);
      $matches = $matches[1];
      for($i=0; $i<sizeof($matches); $i++){
          $matches[$i] = str_replace("/","-",$matches[$i]);
      }
       
      $this->selectIDs = $matches;
   }
   
   /**
    * This file sends a email to the requester with a link to the provided zip file for downloading
    * 
    * @param    string      $zipName        The name of the zip file whose url is to be sent
    */
   private function sendZipURL($zipName) {
      $this->logHandler->log(3, $this->TAG, 'sending email to '.$_POST['email']);
      $url = "http://".$_SERVER['HTTP_HOST'].$this->rootDirURI.$zipName;
      $this->logHandler->log(3, $this->TAG, 'url to zip file is  '.$url);
      $emailSubject = "ODK Parser finished generating ".$_POST['fileName'];
      $message = "Hi ".$_POST['creator'].",\nODK Parser has finished generating ".$_POST['fileName'].".xlsx. You can download the file along with its companion images as a zip file from the following link ".$url." . This is an auto-generated email, please do not reply to it.";
      //$headers = "From: noreply@cgiar.org";
      //mail($_POST['email'], $emailSubject, $message, $headers);
      
      shell_exec('echo "'.$message.'"|'.$this->settings['mutt_bin'].' -F '.$this->settings['mutt_config'].' -s "'.$emailSubject.'" -- '.$_POST['email']);
   }
   
   private function isImage($url) {
       $contentType = get_headers($url, 1);
       $contentType = $contentType["Content-Type"];
       if (!is_array($contentType) && strpos($contentType, 'image') !== NULL) {
           return TRUE;
       } 
       else {
            return FALSE;
       }
   }

}

$obj = new Parser();
?>
