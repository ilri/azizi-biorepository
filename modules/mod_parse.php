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
   
   public function __construct() {
      //load settings
      $this->loadSettings();
      
      //include modules
      include_once $this->ROOT.'modules/mod_log.php';
      $this->logHandler = new LogHandler();
      
      $this->logHandler->log(3, $this->TAG, 'initializing the Parser object');
      
      //init other vars
      $this->parseType = $_POST['parse_type'];
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
      
      //$this->rootDirURI = "/~jason/ilri/ODKParser/";
      $this->rootDirURI = $this->settings['root_uri'];
      
      //parse json String
      $this->parseJson();
      $this->loadXML();

      include_once $this->settings['common_lib_dir'].'PHPExcel/PHPExcel.php';
      $jsonWidth = $this->array_depth($this->jsonObject);
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
      
      //process all responses
      $mainSheetKey = "main_sheet";
     
      $currMainSheetItem = 0; 
      foreach($this->jsonObject as $currentJsonObject) {
         $this->logHandler->log(3, $this->TAG, 'Now at main sheet item '.$currMainSheetItem.' of '.count($this->jsonObject));
         if($this->parseType === "viewing"){
             $this->createSheetRow($currentJsonObject, $mainSheetKey);
         }
         else{
             $this->createSheetRow($currentJsonObject, $mainSheetKey, NULL, $currMainSheetItem);
         }
         $currMainSheetItem++;
      }
      
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
      $this->zipParsedItems($this->downloadDir, $this->ROOT.$zipName);
      $this->deleteDir($this->downloadDir);
      
      //send zip file to specified email
      $this->sendZipURL($zipName);
   }
   
   private function loadSettings() {
      $settingsDir = $this->ROOT."config/";
      if(file_exists($settingsDir."main.ini")) {
         $this->settings = parse_ini_file($settingsDir."main.ini");
      }
   }
   
   private function setExcelMetaData() {
      $this->logHandler->log(3, $this->TAG, 'setting excel metadata');
      $this->phpExcel->getProperties()->setCreator($_POST['creator']);
      $this->phpExcel->getProperties()->setLastModifiedBy($_POST['creator']);
      $this->phpExcel->getProperties()->setTitle($_POST['fileName']);
      $this->phpExcel->getProperties()->setSubject("Created using ODK Parser");
      $this->phpExcel->getProperties()->setDescription("This Excel file has been generated using ODK Parser that utilizes the PHPExcel library on PHP. ODK Parse was created by Jason Rogena (j.rogena@cgiar.org)");
   }
   
   private function getColumnName($parentKey, $key){//a maximum of 676 (26*26) columns
      //$this->logHandler->log(3, $this->TAG, 'getting column name corresponding to '.$key);
      $columnNames = array("A","B","C","D","E","F","G","H","I","J","K","L","M","N","O","P","Q","R","S","T","U","V","W","X","Y","Z");
      $indexOfKey = array_search($key, $this->allColumnNames[$parentKey]);
      $x = intval($indexOfKey/26) -1;
      $y = fmod($indexOfKey, 26);
      $z = fmod($indexOfKey, 26*26);
      
      $columnName = "";
      if($x>=0){
         $columnName = $columnNames[$x];
      }
      if($y<26){
         $columnName = $columnName.$columnNames[$y];
         //echo 'returned from getcolumn name '.$columnName.'<br/>';
         $this->logHandler->log(4, $this->TAG, 'returned from getcolumn name '.$columnName);
         return $columnName;
      }
      if($z<26){
          $columnName = $columnName.$columnNames[$z];
      }
      
   }
   
   private function createSheetRow($jsonObject, $parentKey, $parentCellName = NULL, $rowIndex = -1) {
      $this->logHandler->log(3, $this->TAG, 'creating a new sheet row in '.$parentKey);
      //check if sheet for parent key exists
      $sheetArrayKeys = array_keys($this->sheetIndexes);
      $isNewSheet = FALSE;
      if(!in_array($parentKey, $sheetArrayKeys)) {
         $isNewSheet = TRUE;
         //echo 'sheet for '.$parentKey.' does not exist<br/>';
         $this->logHandler->log(2, $this->TAG, 'sheet for '.$parentKey.' does not exist');
         //create sheet for parent key
         //echo 'size of sheet indexes before '.sizeof($this->sheetIndexes)."<br/>";
         $this->logHandler->log(4, $this->TAG, 'size of sheet indexes before '.sizeof($this->sheetIndexes));
         $this->sheetIndexes[$parentKey] = sizeof($this->sheetIndexes);
         //echo 'size of sheet indexes now '.sizeof($this->sheetIndexes)."<br/>";
         $this->logHandler->log(4, $this->TAG, 'size of sheet indexes now '.sizeof($this->sheetIndexes));
         $this->nextRowName[$parentKey] = 2;
         $this->allColumnNames[$parentKey] = array();
         
         if(sizeof($this->sheetIndexes)>1){
            $this->phpExcel->createSheet();
            //echo 'this is not the first sheet, therefore calling createSheet<br/>';
            $this->logHandler->log(4, $this->TAG, 'this is not the first sheet, therefore calling createSheet');
         }
         $this->phpExcel->setActiveSheetIndex($this->sheetIndexes[$parentKey]);
         //echo 'set active sheet index to '.$this->sheetIndexes[$parentKey]."<br/>";
         $this->logHandler->log(4, $this->TAG, 'set active sheet index to '.$this->sheetIndexes[$parentKey]);
         $this->phpExcel->getActiveSheet()->setTitle($parentKey);
      }
      else {
         //set active sheet to that which corresponds to parent key
         $this->phpExcel->setActiveSheetIndex($this->sheetIndexes[$parentKey]);
         //echo 'sheet for '.$parentKey.' already exists<br/>';
         $this->logHandler->log(4, $this->TAG, 'sheet for '.$parentKey.' already exists');
      }
      
      //split keys and values in jsonObject
      //echo 'splitting keys and values in jsonObject<br/>';
      $this->logHandler->log(4, $this->TAG, 'splitting keys and values in jsonObject');
      $keys = array_keys($jsonObject);
      $values = array();
      $index = 0;
      foreach($jsonObject as $value) {
         $values[$index] = $value;
         $index++;
      }
      //echo 'size of values is '.sizeof($values)."<br/>";
      $this->logHandler->log(4, $this->TAG, 'size of values is '.sizeof($values));
      
      //get next row name for parent key
      $rowName = $this->nextRowName[$parentKey];
      //echo 'row name is '.$rowName.'<br/>';
      $this->logHandler->log(4, $this->TAG, 'row name is '.$rowName);
      
      //set Primary_ID as first cell in row if required
      if($rowIndex !== -1){
          if(!in_array("Primary_ID", $this->allColumnNames[$parentKey])){
              $this->logHandler->log(4, $this->TAG, 'pushing Primary_ID to allColumnNames array for ' . $parentKey);
              array_push($this->allColumnNames[$parentKey], "Primary_ID");
          }
          
          $columnName = $this->getColumnName($parentKey, "Primary_ID");
          if($columnName !== FALSE){
              if($isNewSheet === TRUE){
                  $this->phpExcel->getActiveSheet()->setCellValue($columnName."1", "Primary ID");
                  $this->phpExcel->getActiveSheet()->getStyle($columnName."1")->getFont()->setBold(TRUE);
              }
              $cellName = $columnName . $rowName;
              $this->phpExcel->getActiveSheet()->setCellValue($cellName, $this->odkInstance."_".$rowIndex); //instance of the ODK file plus the id of the row eg dgea2_1
              $this->phpExcel->getActiveSheet()->getColumnDimension($columnName)->setAutoSize(true);
          }else {
            //echo 'column name for Parent_Cell not found<br/>';
            $this->logHandler->log(2, $this->TAG, 'column name for Primary_ID not found '.print_r($this->allColumnNames[$parentCellName],TRUE));
            //print_r($this->allColumnNames[$parentCellName]);
         }
      }
      
      //set name of parent cell as first cell in row if is set
      if ($parentCellName != NULL) {
         if (!in_array("Parent_Cell", $this->allColumnNames[$parentKey])) {
            //echo 'pushing Parent_Cell to allColumnNames array for ' . $parentKey . '<br/>';
            $this->logHandler->log(4, $this->TAG, 'pushing Parent_Cell to allColumnNames array for ' . $parentKey);
            array_push($this->allColumnNames[$parentKey], "Parent_Cell");
            //$this->allColumnNames[$parentKey][sizeof($this->allColumnNames[$parentKey])]="Parent_Cell";
         }
         $columnName = $this->getColumnName($parentKey, "Parent_Cell");
         if ($columnName != FALSE) {
            if($isNewSheet === TRUE){
               $this->phpExcel->getActiveSheet()->setCellValue($columnName."1", "Parent cell");
               $this->phpExcel->getActiveSheet()->getStyle($columnName."1")->getFont()->setBold(TRUE);
            }
            $cellName = $columnName . $rowName;
            $this->phpExcel->getActiveSheet()->setCellValue($cellName, $parentCellName);
            $this->phpExcel->getActiveSheet()->getColumnDimension($columnName)->setAutoSize(true);
            //$this->phpExcel->getActiveSheet()->getStyle($cellName)->getAlignment()->setWrapText(true);
         } else {
            //echo 'column name for Parent_Cell not found<br/>';
            $this->logHandler->log(2, $this->TAG, 'column name for Parent_Cell not found '.print_r($this->allColumnNames[$parentCellName],TRUE));
            //print_r($this->allColumnNames[$parentCellName]);
         }
      }
      
      //add all keys and respective values to row
      if(sizeof($keys) == sizeof($values) && sizeof($values) > 0) {
      
         //echo 'adding columns from here<br/>';
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

               if (!is_array($values[$index])) {
                  //echo 'value of '.$keys[$index].' is '.$values[$index].'<br/>';
                  $this->logHandler->log(4, $this->TAG, 'value of '.$keys[$index].' is '.$values[$index]);
                  
                  if(filter_var($values[$index], FILTER_VALIDATE_URL)) {
                     $values[$index] = $this->downloadImage($values[$index]);
                  }
                  
                  if(strlen($values[$index]) === 0) {
                     $values[$index] = "NULL";
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
                     if ($this->isJson($testChild) === TRUE) {
                        $this->phpExcel->getActiveSheet()->setCellValue($cellName, "Check " . $keys[$index] . " sheet");
                        foreach ($values[$index] as $childJsonObject) {
                           
                            $this->createSheetRow($childJsonObject, $keys[$index], $this->odkInstance."_".$rowName);//NOTE that this is wher the createSheetRow function calls itself
                           
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
                            $this->phpExcel->getActiveSheet()->setCellValue($cellName, "NULL");
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
                     $this->phpExcel->getActiveSheet()->setCellValue($cellName, "NULL");
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
         //means the jsonObject is just a string, this is bad, just kill the process
         $this->logHandler->log(1, $this->TAG, 'jsonobject provided to createSheetRow is a string ('.$jsonObject.'). This should not have happened. Killing process');
         exit(0);
      }
      $this->nextRowName[$parentKey]++;
   }
   
   private function downloadImage($url) {
      $this->logHandler->log(3, $this->TAG, 'checking if '.$url.' is image before starting download');
      
      //only supported in PHP 5.4+
      $contentType = get_headers($url, 1);
      $contentType = $contentType["Content-Type"];
      //echo 'content type is '.$contentType."<br/>";
      $this->logHandler->log(4, $this->TAG, 'content type is '.$contentType);
      if(strpos($contentType, 'image')!==NULL) {
         if(!file_exists($this->imagesDir)) {
            mkdir($this->imagesDir,0777,true);
         }
         //echo 'starting downloads'.$this->sessionID.'<br/>';
         $this->logHandler->log(4, $this->TAG, 'starting downloads'.$this->sessionID);
         $timestamp = round(microtime(true) * 1000);
         $name = $timestamp.".".str_replace("image/", "", $contentType);
         $img = $this->imagesDir.'/'.$name;
         file_put_contents($img, file_get_contents($url));
         return $name;
      }
      else {
         return $url;
      }
   }
   
   function zipParsedItems($source, $destination, $include_dir = false) {
      $this->logHandler->log(3, $this->TAG, 'zipping all items in '.$source);
      
      if (!extension_loaded('zip') || !file_exists($source)) {
         return false;
      }

      if (file_exists($destination)) {
         unlink($destination);
      }

      $zip = new ZipArchive();
      if (!$zip->open($destination, ZIPARCHIVE::CREATE)) {
         return false;
      }
      $source = str_replace('\\', '/', realpath($source));

      if (is_dir($source) === true) {

         $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);

         if ($include_dir) {

            $arr = explode("/", $source);
            $maindir = $arr[count($arr) - 1];

            $source = "";
            for ($i = 0; $i < count($arr) - 1; $i++) {
               $source .= '/' . $arr[$i];
            }

            $source = substr($source, 1);

            $zip->addEmptyDir($maindir);
         }

         foreach ($files as $file) {
            $file = str_replace('\\', '/', $file);

            // Ignore "." and ".." folders
            if (in_array(substr($file, strrpos($file, '/') + 1), array('.', '..')))
               continue;

            $file = realpath($file);

            if (is_dir($file) === true) {
               $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
            } else if (is_file($file) === true) {
               $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
            }
         }
      } else if (is_file($source) === true) {
         $zip->addFromString(basename($source), file_get_contents($source));
      }

      return $zip->close();
   }
   
   public function deleteDir($dirPath) {
      $this->logHandler->log(3, $this->TAG, 'deleting '.$dirPath);
      if (!is_dir($dirPath)) {
         throw new InvalidArgumentException("$dirPath must be a directory");
      }
      if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
         $dirPath .= '/';
      }
      $files = glob($dirPath . '*', GLOB_MARK);
      foreach ($files as $file) {
         if (is_dir($file)) {
            self::deleteDir($file);
         } else {
            unlink($file);
         }
      }
      rmdir($dirPath);
   }
   
   private function convertKeyToValue($key) {
      //get the default language
      $defaultLangIndex = 0;
      for($i = 0; $i < sizeof($this->languageCodes); $i++){
          if(preg_match("/eng.*/i", $this->languageCodes[$i]) === 1){ //preg_match returns 1 if pattern matches and 0 if it doesnt
              $defaultLangIndex = $i;
          }
      }
      
      //get the default language's string value of string code (key)
      if(array_key_exists($key, $this->xmlValues)) {
         return $this->xmlValues[$key][$defaultLangIndex];
      }
      else {
         return $key;
      }
   }
   
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
   
   private function loadXML() {
      $this->logHandler->log(3, $this->TAG, 'parsing xml obtained from post');
      //$this->xmlString = file_get_contents($this->ROOT . "animals.xml");
      $tmpXMLString = $_POST['xmlString'];
      
      //replace all the ascii codes with the real sh*t
      $tmpXMLString = str_replace("&lt;", "<", $tmpXMLString);
      $tmpXMLString = str_replace("&gt;", ">", $tmpXMLString);
      $tmpXMLString = str_replace("&#61;", "=", $tmpXMLString);
      $this->xmlString = $tmpXMLString;
      
      $matches = array();//temp var for inserting regex matches
      
      //get all the language codes for languages specifed as translations in xml
      preg_match_all("/\s+<translation\s+lang=[\"'](.+)[\"']>/", $this->xmlString, $matches);
      $this->languageCodes = $matches[1];
      
      //get the odk instance id for the form
      preg_match_all("/<instance>[\s\n]*<[a-z0-9_\s]+id=[\"']([a-z0-9_]+)[\"']\s*>/i", $this->xmlString, $matches);
      $tempODKInstance = $matches[1][0];//assuming that the first instance tag in xml file is what we are looking for
      
      //check if the last part of the instance contains a version number
      if(preg_match("/.+_[v][ersion_\-]*[0-9]$/i", $tempODKInstance) === 1){
         $this->logHandler->log(4, $this->TAG, $tempODKInstance." contains a version number, removing it");
         preg_match_all("/(.+)_[v][ersion_\-]*[0-9]$/i", $tempODKInstance, $matches);
         $tempODKInstance = $matches[1][0];
      }
      $this->odkInstance = $tempODKInstance;
      
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
   }
   
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
   
  private function array_depth($array) {
       $max_depth = 1;
       
       foreach ($array as $value) {
           if (is_array($value)) {
               $depth = $this->array_depth($value) + 1;

               if ($depth > $max_depth) {
                   $max_depth = $depth;
               }
           }
       }
       return $max_depth;
   } 
   
   function isJson($json) {
      $keys = array_keys($json);
      if(sizeof($keys)>0){
         return TRUE;
      }
      else{
         return FALSE;
      }
   }
    
}

$obj = new Parser();
?>
