<?php
/**
 * The main class of the system. All other classes inherit from this main one
 *
 * @package    ODKParser
 * @author     Jason Rogena <j.rogena@cgiar.org>
 * @since      v0.1
 */
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
    * @var array        Associative array containing all column names in all excel sheets in the format [sheet_name][column_index]. Only used when parsing json
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

   private $selectNodesOptions;

   private $primaryKeys;

   private $sendToDMP;

   /**
    * Variable that determines whether to update the submissions or not
    */
   private $updateSubmissions;
   private $dmpUser;
   private $dmpPass;
   private $dmpServer;
   private $dmpSession;
   private $dmpLinkSheets;

   /**
    * The maximum number of retries when fetching data from aggregate before giving up on this
    */
   private $maxRetries = 5;

   /**
    * The number of seconds to pause between failed fetches of data from aggregate
    */
   private $sleepBetweenTries = 1;

   public function __construct() {
      //load settings
      $this->loadSettings();

      // include modules
      include_once $this->ROOT.'modules/mod_log.php';
      $this->logHandler = new LogHandler();

      include_once $this->settings['common_lib_dir'].'azizi-shared-libs/mod_general/mod_general_v0.6.php';
      $this->gTasks = new GeneralTasks();

      $this->logHandler->log(3, $this->TAG, 'initializing the Parser object');

      //init other vars
      $this->idPrefix = null;
      $this->odkInstance = null;
      $this->parseType = $_POST['parseType'];
      $this->sendToDMP = "no";      //default is no
      $this->updateSubmissions = "no";      //default is no
      $this->dmpUser = "";
      $this->dmpPass = "";
      $this->dmpServer = "";
      $this->dmpSession = "";
      $this->odkInstanceId = "";
      $this->dmpLinkSheets = false;
      if(isset($_POST['sendToDMP'])) $this->sendToDMP = $_POST['sendToDMP'];
      if(isset($_POST['updateSubmissions'])) $this->updateSubmissions = $_POST['updateSubmissions'];
      if(isset($_POST['dmpUser'])) $this->dmpUser = $_POST['dmpUser'];
      if(isset($_POST['dmpPass'])) $this->dmpPass = $_POST['dmpPass'];
      if(isset($_POST['dmpServer'])) $this->dmpServer = $_POST['dmpServer'];
      if(isset($_POST['dmpSession'])) $this->dmpSession = $_POST['dmpSession'];
      if(isset($_POST['dmpLinkSheets'])) {
         $this->dmpLinkSheets = $_POST['dmpLinkSheets'];
         if($this->dmpLinkSheets == "yes") $this->dmpLinkSheets = true;
         else $this->dmpLinkSheets = false;
      }

      if($this->dmpSession == '' && ($this->dmpUser != '' && $this->dmpPass != '')){
         // we don't have a valid session, but we have a valid username and password
         $res = $this->authenticateUser();
         if($res == 1){
            $this->logHandler->log(1, $this->TAG, 'There was an error while authenticating the user: '.$this->dmpUser);
            return;
         }
      }
      else{
         $this->logHandler->log(1, $this->TAG, 'Seems all is clear in terms of authentication...');
      }
      $this->logHandler->log(3, $this->TAG, 'requested parse type is '.$this->parseType);
      $this->sheetIndexes = array();
      $this->allColumnNames = array();
      $this->nextRowName = array();
      $this->primaryKeys = array();
      $this->sessionID = session_id();
      if($this->sessionID == NULL || $this->sessionID == "") {
         $this->sessionID = round(microtime(true) * 1000);
      }
      $this->imagesDir = $this->ROOT.'download/'.$this->sessionID.'/images';
      $this->downloadDir = $this->ROOT.'download/'.$this->sessionID;
      $this->xmlValues = array();
      $this->dwnldImages = $_POST['dwnldImages'];

      $this->cells = array();
      if(!file_exists($this->ROOT."download")){
         mkdir($this->ROOT."download", 0755);//everything for owner, read-exec for everybody else
         $this->logHandler->log(3, $this->TAG, 'Created the download directory');
      }
      $this->authCookies = $this->ROOT."download/AUTH".mt_rand();
      $this->sheets = array();
      $this->idPrefix = "";
      $this->authURL = $this->settings['auth_url'];

      //$this->rootDirURI = "/~jason/ilri/ODKParser/";
      $this->rootDirURI = $this->settings['root_uri'];

      // get the multiple select options
      $this->parseXML();

      $this->loadXML();
      include_once $this->settings['common_lib_dir'].'PHPExcel/Classes/PHPExcel.php';

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
          $multiSelectQuestions = array_keys($this->selectNodesOptions);//includes those in main_sheet and other sheets
          for($i=0; $i < count($this->cells[0]); $i++){
             $this->cells[0][$i] = str_replace(":", "-", $this->cells[0][$i]);
          }
          if(count($this->selectNodesOptions) > 0) {
             $this->cells = $this->expandMultiSelectQuestions($this->cells);
          }
          $rowIndex = 0;
          //$cellsLength = sizeof($this->cells);
          while($rowIndex < sizeof($this->cells)){
              if(is_array($this->cells[$rowIndex])){
                  $rowLength = sizeof($this->cells[$rowIndex]);

                  $readableRIndex = $rowIndex+1;
                  $columnIndex = 0;

                  while($columnIndex < $rowLength){
                      $this->insertCSVCell($this->cells[$rowIndex][$columnIndex], $rowIndex, $this->cells[0][$columnIndex], $mainSheetKey);
                      $columnIndex++;
                  }
              }
              $rowIndex++;
         }
         unlink($this->authCookies);
      }

      $this->phpExcel->setActiveSheetIndex($this->sheetIndexes[$mainSheetKey]);

      //save the excel object
      $this->logHandler->log(3, $this->TAG, 'saving the excel file as '.$_POST['fileName'].'.xlsx');
      if(!file_exists($this->downloadDir)){
         mkdir($this->downloadDir,0777,true);
      }
      $dataFileLoc = $this->downloadDir.'/'.$_POST['fileName'].'.xlsx';
      $objWriter = new PHPExcel_Writer_Excel2007($this->phpExcel);
      $objWriter->save($dataFileLoc);

      //create dictionary and save in download dir
      $this->logHandler->log(3, $this->TAG, 'Creating dictionary');
      $dictionary = new PHPExcel();
      $dictionary->getProperties()->setCreator($_POST['creator']);
      $dictionary->getProperties()->setLastModifiedBy($_POST['creator']);
      $dictionary->getProperties()->setTitle("dictionary");
      $dictionary->getProperties()->setSubject("Created using ODK Parser");
      $dictionary->getProperties()->setDescription("This Excel file has been generated using ODK Parser that utilizes the PHPExcel library on PHP. ODK Parse was created by the Azizi Biorepository team (azizibiorepository@cgiar.org)");

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
      if($this->sendToDMP == "yes") {
         $this->logHandler->log(3, $this->TAG, "Creating a new DMP project with parsed files");
         if(strlen($this->dmpServer) > 0 && strlen($this->dmpSession) > 0 && strlen($this->dmpUser) > 0) {
            $authToken = array(
               "server" => $this->dmpServer,
               "user" => $this->dmpUser,
               "session" => $this->dmpSession
            );
            $dataPayload = array(
               "data_file_url" => str_replace($this->ROOT, "", $dataFileLoc),
               "odk_instance" => $this->odkInstance,
               "workflow_name" => $_POST['fileName']
            );

            if($this->updateSubmissions == 'yes') $dmpData = $this->updateDMPRecords($dataPayload, $authToken);
            else $dmpData = $this->sendDataFileToDMP($dataPayload, $authToken);

            if($dmpData != null) {
               if($dmpData['status']['healthy'] == true) {
                  $workflowId = $dmpData['workflow_id'];
                  $dataPayload = array(
                     "workflow_id" => $workflowId,
                     "link_sheets" => $this->dmpLinkSheets
                  );
                  $schemaData = $this->initializeDMPSchema($dataPayload, $authToken);
                  if($schemaData['status']['healthy'] == true){
                     $this->logHandler->log(3, $this->TAG, "Successfully created DMP project with id '$workflowId' for '{$_POST['fileName']}'");
                     $message = "A DMP project for {$_POST['fileName']} has been created successfully. Log in to http://azizi.ilri.org/repository/ to access the project.\n\n"
                     . "In case of any queries, please contact the Azizi team.\n\n"
                     . "Regards\nThe Azizi Team";
                     $this->sendEmail("Successfully created a DMP Project for ".$_POST['fileName']." (".$workflowId.")", $message);
                  }
                  else {
                     $this->logHandler->log(1, $this->TAG, "Unable to create a DMP schema for '{$_POST['fileName']}' ($workflowId). An error occurred.");
                     $this->logHandler->log(1, $this->TAG, print_r($dmpData['status'], true));
                     $this->logHandler->log(1, $this->TAG, 'Errors: '.print_r($dmpData['status']['errors'], true));
                     $message = "There was an error while trying to create a DMP project for {$_POST['fileName']}.\n\n"
                     . "Please contact the system administrators for assistance.\n\n"
                     . "Regards\nThe Azizi Team";
                     $this->sendEmail("Error while creating DMP project - ".$_POST['fileName'], $message);
                  }
                  //TODO: send the images and dictionary
               }
               else {
                  $this->logHandler->log(1, $this->TAG, "Unable to create the DMP project for '{$_POST['fileName']}'. An error occurred while creating the project");
                  $this->logHandler->log(1, $this->TAG, print_r($dmpData['status'], true));
                  $this->logHandler->log(1, $this->TAG, 'Errors: '.print_r($dmpData['status']['errors'], true));
                  $message = "There was an error while creating a DMP project for for {$_POST['fileName']}. An system error occurred while trying to initialize the project.\n\n"
                  . "Please contact the system administrators for assistance.\n\n"
                  . "Regards\nThe Azizi Team";
                  $this->sendEmail("Error while creating DMP project - ".$_POST['fileName'], $message);
               }
            }
         }
         else {
            $this->logHandler->log(1, $this->TAG, "Unable to create the DMP project for '{$_POST['fileName']}'. One or more of the authentication token variables is not set");
            $this->logHandler->log(1, $this->TAG, "server = {$this->dmpServer}, username = {$this->dmpUser}, session = {$this->dmpSession}");
            $this->sendEmail("DMP Error for ".$_POST['fileName'], "Could not create a Data Management Portal project for {$_POST['fileName']}. The authentication token provided to the server is incomplete. Please contact the system administrators for assistance.");
         }
      }
      else {
         //zip parsed files
         $zipName = 'download/'.$_POST['fileName']."_".$this->sessionID.'.zip';
         $downloadFileName = 'download/'.rawurlencode($_POST['fileName'])."_".$this->sessionID.'.zip';
         //$this->zipParsedItems($this->downloadDir, $this->ROOT.$zipName);
         $this->logHandler->log(3, $this->TAG, 'zipping output files into '.$zipName);
         $this->gTasks->zipDir($this->downloadDir, $this->ROOT.$zipName);
         //$this->deleteDir($this->downloadDir);
         $this->logHandler->log(3, $this->TAG, 'deleting temporary dir '.$this->downloadDir);
         $this->gTasks->deleteDir($this->downloadDir);

         //send zip file to specified email
         $this->sendZipURL($downloadFileName);
      }
   }

   /**
    * Authenticate a user
    */
   private function authenticateUser(){
      $this->logHandler->log(4, $this->TAG, 'Authenticating the user...');
      $postData = array(
         "token" => array(
            "server" => $this->dmpServer,
            "user" => $this->dmpUser,
            "auth_mode" => 'local',
            "secret" => $this->dmpPass
         )
      );
      $ch = curl_init("http://".$_SERVER['HTTP_HOST']."/repository/mod_ajax.php?page=odk_workflow&do=auth");

      curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, TRUE);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));

      $result = curl_exec($ch);
      $http_status = curl_getinfo($ch);
      curl_close($ch);

      if($http_status == 400) {//bad request
         $this->logHandler->log(1, $this->TAG, "Could not create DMP project for {$_POST['fileName']}. DMP returned status code 400 - Bad request");
         $this->sendEmail("DMP Error for ".$_POST['fileName'], "Could not create a Data Management Portal project for {$_POST['fileName']}. The data provided to the server is incomplete. Please contact the system administrators for assistance.");
         return 1;
      }
      else if($http_status == 403) {//forbidden
         $this->logHandler->log(1, $this->TAG, "Could not create DMP project for {$_POST['fileName']}. DMP returned status code 403 - Forbidden");
         $this->sendEmail("DMP Error for ".$_POST['fileName'], "Could not create a Data Management Portal project for {$_POST['fileName']}. Your account was denied permission to create the project. Please contact the system administrators for assistance.");
         return 1;
      }
      else if($http_status == 500) {//server error
         $this->logHandler->log(1, $this->TAG, "Could not create DMP project for {$_POST['fileName']}. DMP returned status code 400 - Server error");
         $this->sendEmail("DMP Error for ".$_POST['fileName'], "Could not create a Data Management Portal project for {$_POST['fileName']}. An error occurred while processing the parsed data file. Please contact the system administrators for assistance.");
         return 1;
      }
      else {
         $res = json_decode($result, true);
         if(is_null($res['session'])){
            // something went wrong
            if(count($res['status']['errors']) != 0){
               $errors = implode("\n", $res['status']['errors']);
               $this->logHandler->log(1, $this->TAG, $errors);
               return 1;
            }
         }
         // seems all is clear
         $this->logHandler->log(4, $this->TAG, 'User authenticated successfully');
         $this->dmpSession = $res['session'];
         return 0;
      }
   }

   /**
    * This function does XML parsing of the XML structure file to obtain
    *  - The instance id and id prefix for the form
    *  - Multiple selects in the form
    */
   private function parseXML(){
      $this->logHandler->log(3, $this->TAG, "Getting all mutiselect questions");
      $xml = new DOMDocument();
      $xml->loadHTML($_POST['xmlString']);
      if($xml == FALSE){
         $this->logHandler->log(2, $this->TAG, 'There was an error while parsing the xml using the DOM parser...');
      }
      else {
         $this->logHandler->log(3, $this->TAG, 'Successfully parsed the form\'s XML file');
      }

      $allInstanceNodes = $xml->getElementsByTagName('instance');
      //get the instance id for the form
      /**/

      $optionsFromOldODK = array();//support for older odk forms
      $nodesLength = $allInstanceNodes->length;
      for($i = 0; $i < $nodesLength; $i++) {
         $node = $allInstanceNodes->item($i);
         //check if the instance has no attributes (probably means it holds the from's instance id)
         if($node->attributes->length == 0) {
            if($this->odkInstance == NULL) {//the instance has not been initialized before
               $allChildElements = $node->getElementsByTagName('*');
               foreach($allChildElements as $currChildElement) {
                  $this->idPrefix = $currChildElement->tagName;
                  $this->odkInstance = $currChildElement->getAttribute('id');
                  break;//we are expecting only one child element in such an instance tag
               }
            }
         }

         $instanceId = $node->attributes->item(0)->value;
         //check if instance has <options> child
         $optionsInInstance = $node->getElementsByTagName('options');
         //expecting at most one <options> tag in each instance tag
         $noOptions = $optionsInInstance->length;
         if($noOptions == 1) {
            $rawValues = $optionsInInstance->item(0)->getElementsByTagName('value');
            $values = array();
            for($index = 0; $index < $rawValues->length; $index++) {
               $values[] = $rawValues->item($index)->nodeValue;
            }
            $optionsFromOldODK[$instanceId] = $values;
         }
      }

      $allSelectNodes = $xml->getElementsByTagName('select');
      $nodesLength = $allSelectNodes->length;
      $allNodes = array();

      // traverse all nodes and get the name of the node and the possible options
      for($i = 0; $i < $nodesLength; $i++){
         //$nodeName = $allSelectNodes[$i]->attributes->item(0)->value;
         $node = $allSelectNodes->item($i);
         $nodeName = $node->attributes->item(0)->value;

         // format the nodeName by removing the leading form name before the first / and then replace the rest of the / with '-' to match the column names as per the csv files
         $nodeName = substr($nodeName, strpos($nodeName, '/', 1)+1);
         $nodeName = str_replace('/', '-', $nodeName);
         $allNodes[$nodeName]['options'] = array();
         //check if select created using old way of creating ODK forms or new way
         $itemsets = $node->getElementsByTagName("itemset");
         if($itemsets->length == 0) {//select defined using the new way
            // get the number of select options in this particular node by selecting the elements with the item tag
            $selectNodes = $node->getElementsByTagName('item');
            $allNodes[$nodeName]['noSelects'] = $selectNodes->length;

            $this->logHandler->log(3, $this->TAG, "Extracting selects for $nodeName($i/$nodesLength) with {$selectNodes->length} selects");
            for($j = 0; $j < $selectNodes->length; $j++){
               // get the actual select options
               $allNodes[$nodeName]['options'][] = $selectNodes->item($j)->childNodes->item(2)->childNodes->item(0)->nodeValue;
            }
         }
         else if($itemsets->length == 1) {//select defined using the old way. Expecting only one itemsets tag in a select tag
            //get the instance id for the itemset
            $itemset = $itemsets->item(0);
            $rawInstanceId = $itemset->getAttribute("nodeset");
            $instanceParts = explode("'", $rawInstanceId);
            if(count($instanceParts) == 3) {
               $instanceId = $instanceParts[1];
               if(isset($optionsFromOldODK[$instanceId])) {
                  $allNodes[$nodeName]['noSelects'] = count($optionsFromOldODK[$instanceId]);
                  $allNodes[$nodeName]['options'] = $optionsFromOldODK[$instanceId];
               }
               else {
                  $this->logHandler->log(2, $this->TAG, "Was unable to get options corresponding to option group '$instanceId'");
               }
            }
            else {
               $this->logHandler->log(2, $this->TAG, "Was unable to extract the instance id from a select tag's nodeset");
            }
         }
         else {
            $this->logHandler->log(2, $this->TAG, "Select tag has more than one itemset tag. This is not expected");
         }
      }
      $this->selectNodesOptions = $allNodes;
      $this->logHandler->log(3, $this->TAG, "Done getting all multiselect questions");
      return;
   }

   private function sendDataFileToDMP($dataPayload, $authToken) {
      $postData = array(
         "data" => $dataPayload,
         "token" => $authToken
      );
      $ch = curl_init("http://".$_SERVER['HTTP_HOST']."/repository/mod_ajax.php?page=odk_workflow&do=init_workflow");

      curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, TRUE);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));

      $result = curl_exec($ch);
      $http_status = curl_getinfo($ch);
      curl_close($ch);
      if($http_status == 400) {//bad request
         $this->logHandler->log(1, $this->TAG, "Could not create DMP project for {$_POST['fileName']}. DMP returned status code 400 - Bad request");
         $this->sendEmail("DMP Error for ".$_POST['fileName'], "Could not create a Data Management Portal project for {$_POST['fileName']}. The data provided to the server is incomplete. Please contact the system administrators for assistance.");
         return null;
      }
      else if($http_status == 403) {//forbidden
         $this->logHandler->log(1, $this->TAG, "Could not create DMP project for {$_POST['fileName']}. DMP returned status code 403 - Forbidden");
         $this->sendEmail("DMP Error for ".$_POST['fileName'], "Could not create a Data Management Portal project for {$_POST['fileName']}. Your account was denied permission to create the project. Please contact the system administrators for assistance.");
         return null;
      }
      else if($http_status == 500) {//server error
         $this->logHandler->log(1, $this->TAG, "Could not create DMP project for {$_POST['fileName']}. DMP returned status code 400 - Server error");
         $this->sendEmail("DMP Error for ".$_POST['fileName'], "Could not create a Data Management Portal project for {$_POST['fileName']}. An error occurred while processing the parsed data file. Please contact the system administrators for assistance.");
         return null;
      }
      else {
         return json_decode($result, true);
      }
   }

   private function initializeDMPSchema($dataPayload, $authToken) {
      $postData = array(
         "data" => $dataPayload,
         "token" => $authToken
      );
      $ch = curl_init("http://".$_SERVER['HTTP_HOST']."/repository/mod_ajax.php?page=odk_workflow&do=process_mysql_schema");

      curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, TRUE);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));

      $result = curl_exec($ch);
      $http_status = curl_getinfo($ch);
      curl_close($ch);
      if($http_status == 400) {//bad request
         $this->logHandler->log(1, $this->TAG, "Could not initialize the schema for DMP project corresponding to {$_POST['fileName']}. DMP returned status code 400 - Bad request");
         $this->sendEmail("DMP Error for ".$_POST['fileName'], "Could not initialize the DMP database schema for {$_POST['fileName']}. The data provided to the server is incomplete. Please contact the system administrators for assistance.");
         return null;
      }
      else if($http_status == 403) {//forbidden
         $this->logHandler->log(1, $this->TAG, "Could not create DMP project for {$_POST['fileName']}. DMP returned status code 403 - Forbidden");
         $this->sendEmail("DMP Error for ".$_POST['fileName'], "Could not initialize the DMP database schema for {$_POST['fileName']}. Your account was denied permission to create the project. Please contact the system administrators for assistance.");
         return null;
      }
      else if($http_status == 500) {//server error
         $this->logHandler->log(1, $this->TAG, "Could not create DMP project for {$_POST['fileName']}. DMP returned status code 400 - Server error");
         $this->sendEmail("DMP Error for ".$_POST['fileName'], "Could not initialize the DMP database schema for {$_POST['fileName']}. An error occurred while processing the parsed data file. Please contact the system administrators for assistance.");
         return null;
      }
      else {
         return json_decode($result, true);
      }
   }

   private function addColumnsToRows($multiDimensionArray, $columnIndex, $columnsToInsert) {
      $arraySize = count($multiDimensionArray);

      //if column index is 0 just array_merge the two arrays
      if($columnIndex == 0){
         for($index = 0; $index < $arraySize; $index++) {
            $multiDimensionArray[$index] = array_merge($columnsToInsert[$index], $multiDimensionArray[$index]);
         }
      }
      else if($columnIndex == count($multiDimensionArray[0])){//appending to the end of the array
         for($index = 0; $index < $arraySize; $index++) {
            $multiDimensionArray[$index] = array_merge($multiDimensionArray[$index], $columnsToInsert[$index]);
         }
      }
      else {
         for($index = 0; $index < $arraySize; $index++) {
            $head = array_slice($multiDimensionArray[$index], 0, $columnIndex);
            $tail = array_slice($multiDimensionArray[$index], $columnIndex);
            $multiDimensionArray[$index] = array_merge($head, $columnsToInsert[$index], $tail);
         }
      }
      return $multiDimensionArray;
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
                  if(in_array($this->getAltHeadingName($columnHeading), $this->manualBCs) === TRUE && $rowIndex !== 0 && strlen($cellString) !== 0 && $cellString !=="null" && $this->parseType !== "viewing"){
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
                     $values[$index] = $this->gTasks->downloadImage($values[$index], $this->imagesDir, $this->logHandler);
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

   /**
    * This recursive function creates an excel sheet cell using the vaule of a csv file "cell"
    *
    * @param   string      $cellString       This is the text in the csv file cell
    * @param   int         $rowIndex         This is the index of the row containing the cell. Rows start from 0
    * @param   string      $columnHeading    The heading of the column in which the cell is in e.g primary_key
    * @param   string      $parentSheetName  The name of the excel sheet in which the cell will be inserted eg main_sheet
    *
    * @todo    Find a better way to extract the answers
    */
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
            $this->phpExcel->getActiveSheet()->setTitle($this->shortenSheetName($parentSheetName));
        } else {
            $this->phpExcel->setActiveSheetIndex($this->sheetIndexes[$parentSheetName]);
        }

        // push the column headings to an array
        if (!in_array($columnHeading, $this->sheets[$parentSheetName])) {
            array_push($this->sheets[$parentSheetName], $columnHeading);
        }
        $columnIndex = array_search($columnHeading, $this->sheets[$parentSheetName]);

        //check if string is non image url
        $rowID = $rowIndex + 1;
        $cellID = $this->getColumnName(NULL, NULL, $columnIndex) . $rowID;
        $isCellImage = $this->isImage($cellString);
        if (filter_var($cellString, FILTER_VALIDATE_URL) && $isCellImage === FALSE) {
            // is non-image url. Expecting a table with data... parse the HTML table
            $this->phpExcel->getActiveSheet()->setCellValue($cellID, "Check " . $this->shortenSheetName($columnHeading) . " sheet");
            $this->parseHTMLTable($cellString, $columnHeading, $rowIndex, $parentSheetName);
        }
        else if(filter_var($cellString, FILTER_VALIDATE_URL) &&  $isCellImage ===TRUE && $this->dwnldImages === "yes"){
            //is an image
            $this->logHandler->log(4, $this->TAG, 'checking if '.$values[$index].' is image');
            $cellString = $this->gTasks->downloadImage($cellString, $this->imagesDir, $this->logHandler);
            $this->phpExcel->getActiveSheet()->setCellValue($cellID, $cellString);
        }
        else {
           $selectIDs = array_keys($this->selectNodesOptions);
            if (!in_array($columnHeading, $selectIDs)) { //not a multiple select question

                //check if value is a barcode
                if(strlen($cellString) > 0 && $cellString !== "null" && in_array($this->getAltHeadingName($columnHeading), $this->manualBCs) && $this->parseType !== "viewing" && $rowIndex!==0){
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
                    $cellString = $this->processColumnHeading($cellString);
                }

                $this->phpExcel->getActiveSheet()->setCellValue($cellID, $cellString);
                if ($rowIndex === 0) {
                    $this->phpExcel->getActiveSheet()->getStyle($cellID)->getFont()->setBold(TRUE);
                    $this->phpExcel->getActiveSheet()->getColumnDimension($this->getColumnName(NULL, NULL, $columnIndex))->setAutoSize(true);
                }
            }
            else if ($rowIndex > 0) {
               // having a cell which was a multiple select question
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
                    //$this->phpExcel->getActiveSheet()->setCellValue($cellID, "null");
                   $this->phpExcel->getActiveSheet()->setCellValue($cellID, $cellString);
                }
            }
            else {//is a multiple select question heading
                $cellString = $this->processColumnHeading($cellString);
                $this->phpExcel->getActiveSheet()->setCellValue($cellID, $cellString);
                $this->phpExcel->getActiveSheet()->getStyle($cellID)->getFont()->setBold(TRUE);
                $this->phpExcel->getActiveSheet()->getColumnDimension($this->getColumnName(NULL, NULL, $columnIndex))->setAutoSize(true);
            }
        }
    }

    /**
     * This function trys to shorten the provided sheet name if it's more than
     * 30 characters long
     *
     * @param String $sheetName  The current sheet name
     * @return String   The shortened sheet name
     */
    private function shortenSheetName($sheetName) {
       $sheetName = str_replace(":", "-", $sheetName);
       if(strlen($sheetName) > 30){
          $sheetName = substr($sheetName, -30);//get the last 30 characters of the name
          if(strstr($sheetName, "-") !== false){//there is at least one '-' in the shortened sheet name
             return strstr($sheetName, "-");//return the portion of the heading starting with the first occurrance of -
          }
          else return $sheetName;
       }
       return $sheetName;
    }

    /**
     * This function gets the string (question text) corresponding to each column heading code.
     * e.g if a csv column heading is "sectioncode-questioncode" then this function will determine
     * which jr:itext the question corresponds to. It then adds it to the dictionary (if it does not
     * already exist there)
     *
     * @param  string     $columnHeadingCode    The text that is the csv column heading. This corresponds to the question code eg <select ref="/formid/sectionid/questionid"
     *                                          will have a csv heading that will look like "sectionid-qestionid"
     * @return string                           The text corresponding to a question is returned if found and $columnHeadingCode of none is found
     */
    private function processColumnHeading($columnHeadingCode){
       //$columnHeading code can be sectioncode-questioncode
       // 1. append formid to column heading and replace all '-' with '/' (escaped / of course)
       $formatedCHC = "-".$this->idPrefix . "-" . $columnHeadingCode;
       $formatedCHC = str_replace("-", "\/", $formatedCHC);

       // 2. search code in xml file and get relevant itext
       $result = array();
       preg_match_all("/ref\s*=\s*['\"]".$formatedCHC."['\"]\s*>[\s\n]*<(label|hint)\s+ref[\s]*=[\s]*[\"']jr:itext\([\"']([a-z0-9_\-\.]+)['\"]\)['\"]/i", $this->xmlString, $result);

       if($result !== FALSE && sizeof($result[2]) === 1){
          $textCode = $result[2][0];

          // 3. Add the code for the question to the dictionary and return the code for the question or the Text for the question depending on the purpose of the excel
          for($index = 0; $index < sizeof($this->languageCodes); $index++){
             $this->xmlValues[$columnHeadingCode][$index] = $this->xmlValues[$textCode][$index];
          }
          if($this->parseType === "viewing")
             return $this->convertKeyToValue($columnHeadingCode);
          else
             return $columnHeadingCode;
       }
       else{
          // 3. Check if question is repeat
          preg_match_all("/ref[\s]*=[\s]*[\"']jr:itext\([\"']([a-z0-9_\-\.]+)['\"]\)['\"]\s*\/>[\s\n]*<repeat\s+nodeset\s*=\s*[\"']".$formatedCHC."[\"']/i", $this->xmlString, $result);
          if($result !== FALSE && sizeof($result[1]) ===1){
             $textCode = $result[1][0];

             // 4. Add the code for the question to the dictionary and return the code for the question or the Text for the question depending on the purpose
             for($index = 0; $index < sizeof($this->languageCodes); $index++){
               $this->xmlValues[$columnHeadingCode][$index] = $this->xmlValues[$textCode][$index];
             }
             if($this->parseType === "viewing")
                return $this->convertKeyToValue($columnHeadingCode);
             else
                return $columnHeadingCode;
          }

       }
       $this->logHandler->log(2, $this->TAG, "Unable to get text code for question ".$columnHeadingCode);
       return $columnHeadingCode;
    }

    /**
     * This method returns the last part of the csv column heading.
     * e.g if the heading looks like sectionid-questionid then
     * questioncode is returned
     *
     * @param  string   $heading    The csv file column heading of the form sectionid-questionid
     * @return string               Returns the last part in $heading
     */
    private function getAltHeadingName($heading){
        $headingParts = explode("-",$heading);
        return $headingParts[sizeof($headingParts)-1];
    }

    /**
     * This method Fetches a html file in aggregate server and scrapes data from the page.
     * It is assumed that this page contains a <table></table> tag that contains the data.
     * Authentication against the server is done and stored as a cookie if not previously done.
     * Auth is through the use of digests
     *
     * @param  type  $url           Points to the page in the aggregate server
     * @param  type  $sheetName     Name of the excel sheet where the data from the html file will be dumpd
     * @param  type  $secondaryKey  Key that will associate the data in the html page to the parent data in the main_sheet
     */
    private function parseHTMLTable($url, $sheetName, $secondaryKey, $parentSheetName) {
      //"http://azizi.ilri.cgiar.org/ODKAggregate/local_login.html?redirect="
      //http://azizi.ilri.cgiar.org/ODKAggregate/view/formMultipleValue?formId=LamuChickens-v01%5B%40version%3Dnull+and+%40uiVersion%3Dnull%5D%2Fchicken%5B%40key%3Duuid%3Ae3983629-8faa-4531-b61a-34d756bbcbd9%5D%2Fsamples
      //$splitURL = preg_split("/\/view\//i", $url);
      $authURL = $this->settings['auth_url'];
      $encodedURL = urlencode($url);
      //$authURL = $splitURL[0].$authURI.$encodedURL;
      $authURL = $authURL . $encodedURL;
      $sheetNames = array_keys($this->nextRowName);
      if (!in_array($sheetName, $sheetNames)) {
         $this->nextRowName[$sheetName] = 0;
      }

      if(!isset($this->primaryKeys[$sheetName])){
         $this->primaryKeys[$sheetName] = 1;
      }

      if (file_exists($this->authCookies) === FALSE) {
         touch($this->authCookies);
         chmod($this->authCookies, 0777);
         $this->logHandler->log(3, $this->TAG, 'Authenticating ' . $this->aUsername . ' on the Aggregate server');
         $authCh = curl_init($authURL);
         curl_setopt($authCh, CURLOPT_USERAGENT, $this->userAgent);
         curl_setopt($authCh, CURLOPT_RETURNTRANSFER, TRUE);
         curl_setopt($authCh, CURLOPT_FOLLOWLOCATION, TRUE);
         curl_setopt($authCh, CURLOPT_CONNECTTIMEOUT, TRUE);
         curl_setopt($authCh, CURLOPT_COOKIEJAR, $this->authCookies);
         curl_setopt($authCh, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
         curl_setopt($authCh, CURLOPT_USERPWD, $this->aUsername . ":" . $this->aPassword);

         curl_exec($authCh);
         curl_close($authCh);
      }

      $i = 0;
      while($i < $this->maxRetries){
         $ch = curl_init($url);
         curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
         curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
         curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
         curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, TRUE);
         curl_setopt($ch, CURLOPT_COOKIEFILE, $this->authCookies);

         $html = curl_exec($ch);
         $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
         $contentType = curl_getinfo($ch);
         if($i == $this->maxRetries-1){
            $this->logHandler->log(4, $this->TAG, 'Giving up on this url. Got a response with code 0 for a number of times. '. $url);
            exit();
         }
         else if($contentType['http_code'] != 0){
            curl_close($ch);
            break;
         }
         else{
            $this->logHandler->log(4, $this->TAG, 'A 0 response from the server... Sleeping for a while before trying again. '. print_r($contentType, true));
            $i++;
            sleep($this->sleepBetweenTries);
         }
      }

      $html = str_replace("\n", " ", $html);

      //check the number of tables in page (should be one)
      if($httpStatus == 200 && $html == ''){
            $this->logHandler->log(4, $this->TAG, 'Empty response from the server: '. $url);
            $this->logHandler->log(1, $this->TAG, 'Empty response from the server: '. $url);
            exit();
         }
      else if ($httpStatus < 400) {
         $htmlTables = array();
         preg_match_all("/<table(.*\n*.*)<\/table>/", $html, $htmlTables);

         if (sizeof($htmlTables[1]) === 1) {//allow only http redirects and 200 status codes
            $dataTable = $htmlTables[1][0];
            $th = array();
            $uncleanTHs = explode('</th>', $dataTable);
            $cleanTHCount = 0;
            foreach ($uncleanTHs as $currTH) {
               if (strpos($currTH, '<th>') !== FALSE) {
                  $splitTH = explode('<th>', $currTH);
                  $th[$cleanTHCount] = $splitTH[1];
                  $cleanTHCount++;
               }
            }
            $headings = $th; //table headings gotten
            array_unshift($headings, "primary_key");
            array_unshift($headings, "secondary_key");
            $cleanCSVRows = array();
            $cleanCSVRows[0] = array();
            $headingsCount = sizeof($headings);
            for ($i = 0; $i < $headingsCount; $i++) {
               if ($i > 1) {//current heading is not primary_key or secondary_key
                  $headings[$i] = $sheetName . "-" . $headings[$i];
               }
               $cleanCSVRows[0][$i] = $headings[$i];
            }

            $tr = array();
            preg_match_all("/<\/th>\s*(<tr>.*<\/tr>$)/", $dataTable, $tr);
            $tr = $tr[1];

            if (sizeof($tr) === 1) {
               $rows = explode("</tr>", $tr[0]);
               $rowCount = sizeof($rows);
               for ($rowIndex = 0; $rowIndex < $rowCount; $rowIndex++) {
                  //check if row is actual table row
                  if (strpos($rows[$rowIndex], '<tr>') !== false) {
                     $rows[$rowIndex] = str_replace("<tr>", "", $rows[$rowIndex]);
                     $rowColumns = explode("</td><td>", $rows[$rowIndex]);
                     $rowColumns = str_replace(array('<td>', '</td>'), '', $rowColumns);

                     //array_unshift($rowColumns, $this->odkInstance . "_" . $secondaryKey);
                     array_unshift($rowColumns, $this->odkInstance."_".$sheetName."_" . $this->primaryKeys[$sheetName]);//insert primary key
                     $this->primaryKeys[$sheetName] = $this->primaryKeys[$sheetName] + 1;
                     if($parentSheetName == "main_sheet") $skPrefix = $this->odkInstance;
                     else $skPrefix = $this->odkInstance."_".$parentSheetName;

                     array_unshift($rowColumns, $skPrefix . "_" . $secondaryKey);//insert secondary key

                     $rowColumnsCount = sizeof($rowColumns);
                     if (($headingsCount) === $rowColumnsCount) {
                        for ($columnIndex = 0; $columnIndex < $rowColumnsCount; $columnIndex++) {
                           if ($columnIndex < $headingsCount) {
                              //check if contents of cell is a link (<a></a>) to image
                              if (preg_match('/<a\s*href/i', $rowColumns[$columnIndex]) === 1) {//cell value is a link to image
                                 //using preg_split for extracting the image url instead of preg_match inorder to avoid a gready fetch at '.*' in the regex /<a\s*href\s*=\s*['\"].*/
                                 $linkSegments = preg_split("/<a\s*href\s*=\s*['\"]/i", $rowColumns[$columnIndex]);
                                 if (count($linkSegments) === 2) {
                                    $linkSegments = $linkSegments[1];
                                    $linkSegments = preg_split("/['\"]\s*target\s*=\s*.*$/i", $linkSegments);
                                    if (count($linkSegments) === 2)
                                       $rowColumns[$columnIndex] = $linkSegments[0];
                                    else {
                                       //probably means that we are parsing from an older version of aggregate
                                       $this->logHandler->log(2, $this->TAG, 'Problem occured when extracting image url in cell. Using a different strategy');
                                       $linkSegments = $linkSegments[0];
                                       $linkSegments = preg_split("/['\"]>view<\/a>/i", $linkSegments);
                                       if (count($linkSegments) === 2) {
                                          $rowColumns[$columnIndex] = $linkSegments[0];
                                       } else {
                                          $this->logHandler->log(2, $this->TAG, 'Latter method for extracting image url failed. Something might be wrong with the url');
                                       }
                                    }
                                 } else
                                    $this->logHandler->log(2, $this->TAG, 'Problem occured when extracting image url in cell (removing first part)');
                              }
                              if (!isset($cleanCSVRows[$rowIndex + 1])){
                                 $cleanCSVRows[$rowIndex + 1] = array();
                              }
                              $cleanCSVRows[$rowIndex + 1][$columnIndex] = $rowColumns[$columnIndex];

                              //$this->insertCSVCell($rowColumns[$columnIndex], $this->nextRowName[$sheetName], $headings[$columnIndex], $sheetName);
                           }
                           else{
                              $this->logHandler->log(4, $this->TAG, "Skipping some rows coz of some reasons.. $columnIndex -- $headingsCount");
                           }
                        }
                     }
                     else {
                        $this->logHandler->log(4, $this->TAG, 'Badly parsed tables were fetched from: '. $url);
                        $this->logHandler->log(4, $this->TAG, 'Badly parsed tables look like this: ' . $headingsCount . ' ' . $rowColumnsCount . ' ' . $html);
                        $this->logHandler->log(1, $this->TAG, 'it appears the rows in html table were parsed badly, exiting');
                        exit();
                     }
                     //$this->nextRowName[$sheetName] ++;
                  }
               }
               //clean csv rows
               $cleanCSVRows = $this->expandMultiSelectQuestions($cleanCSVRows);
               $sheetNames = array_keys($this->sheets);
               /*if (in_array($sheetName, $sheetNames)) {//sheet had already been previously created
                  $cleanCSVRows = array_slice($cleanCSVRows, 1); //delete the first row (containing headings)
               }*/
               $cleanCSVRowsCount = count($cleanCSVRows);
               for ($rowIndex = 0; $rowIndex < $cleanCSVRowsCount; $rowIndex++) {
                  if($this->nextRowName[$sheetName] > 0 && $rowIndex == 0){//not the first time inserting into sheet
                     continue;//no need to reinsert headings in excel file
                  }
                  $currRow = $cleanCSVRows[$rowIndex];
                  $currRowCount = count($currRow);
                  for ($columnIndex = 0; $columnIndex < $currRowCount; $columnIndex++) {
                     $this->insertCSVCell($currRow[$columnIndex], $this->nextRowName[$sheetName], $cleanCSVRows[0][$columnIndex], $sheetName);
                  }
                  $this->nextRowName[$sheetName]++;
               }
            }
         } else {
            $this->logHandler->log(4, $this->TAG, 'Badly parsed html was fetched from: '. $url);
            $this->logHandler->log(4, $this->TAG, 'Badly parsed html looks like this: ' . $html);
            $this->logHandler->log(4, $this->TAG, 'curl details: : ' . print_r($contentType, true));
            $this->logHandler->log(1, $this->TAG, 'HTML appears to have been parsed badly, exiting');
            exit();
         }
      } else {
         $this->logHandler->log(2, $this->TAG, 'Got a status code of ' . $httpStatus . ' while trying to get table from ' . $url);
      }

      //print_r($htmlTables);
   }

   private function expandMultiSelectQuestions($originalCSVRows){
       if($this->parseType !== "viewing"){
          $multiSelectQuestions = array_keys($this->selectNodesOptions);//includes those in main_sheet and other sheets

          $headersCount = count($originalCSVRows[0]);
          for ($i = 0; $i < $headersCount; $i++) {
           //check if current cell is a multi select question
           if (array_search($originalCSVRows[0][$i], $multiSelectQuestions) !== false) {//current cell contains the heading of a multi select question

              //get the options in multiselect question
              $toBeInserted = array(); //array containing new columns for all the csv rows
              $options = $this->selectNodesOptions[$originalCSVRows[0][$i]]['options'];

              $countOptions = count($options);
              $noRows = count($originalCSVRows);
              if($countOptions != 0){
                 for ($index = 0; $index < $countOptions; $index++) {
                    //append the question name as the prefix for each of the options
                    $rawValue = $options[$index];
                    $options[$index] = $originalCSVRows[0][$i] . "-" . $options[$index];

                    if (!isset($toBeInserted[0])) $toBeInserted[0] = array();

                    $toBeInserted[0][$index] = $options[$index];
                    for ($rowIndex = 1; $rowIndex < $noRows; $rowIndex++) {
                       if (!isset($toBeInserted[$rowIndex])) $toBeInserted[$rowIndex] = array();

                       $stringValue = $originalCSVRows[$rowIndex][$i];

                       if (strpos($stringValue, $rawValue) !== false) {//for current row, choice was not selected
                          $toBeInserted[$rowIndex][$index] = 1;
                          $this->logHandler->log(4, $this->TAG, $rawValue . " in " . $stringValue);
                       }
                       else {
                          $this->logHandler->log(4, $this->TAG, $rawValue . " not in " . $stringValue);
                          $toBeInserted[$rowIndex][$index] = 0;
                       }
                    }
                 }
                 $toBeInserted[0] = $options;
                 $originalCSVRows = $this->addColumnsToRows($originalCSVRows, $i + 1, $toBeInserted);
              }
              else{
                 $this->logHandler->log(4, $this->TAG, "The current column {$originalCSVRows[0][$i]} is a multi select column but I didn't find any of its options. I will not attempt to expand the columns.");
                 $this->logHandler->log(3, $this->TAG, "The current column {$originalCSVRows[0][$i]} is a multi select column but I didn't find any of its options. I will not attempt to expand the columns.");
                 $this->logHandler->log(1, $this->TAG, "The current column {$originalCSVRows[0][$i]} is a multi select column but I didn't find any of its options. I will not attempt to expand the columns.");
              }
           }
        }
       }
      return $originalCSVRows;
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

   /**
    * Parse the csv row and save the data in cells in a particular row
    */
   private function parseCSV() {
       $rows = explode("\"\n\"", $_POST['csvString']);
       $index = 0;
       $rowCount = count($rows);
       while($index < $rowCount){
           $columns = explode("\",\"", $rows[$index]);
           $columnCount = count($columns);

            //remove the double quotes still remaining in the first and last elements of the columns array
           $columns[0] = str_replace("\"","",$columns[0]);
           $columns[$columnCount - 1] = str_replace("\"", "", $columns[$columnCount - 1]);
           $columns[$columnCount - 1] = trim($columns[$columnCount - 1], "\n\r");//remove new lines from last column

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
    *   - The fields in the jsonObject in the post request that hold scanned barcodes and manually entered barcodes
    *   - The string codes and their corresponding string values specified as part of the ODK UI
    */
   private function loadXML() {
      $this->logHandler->log(3, $this->TAG, 'parsing xml obtained from post');
      $tmpXMLString = $_POST['xmlString'];

      //replace all the ascii codes with the real sh*t
      $this->logHandler->log(4, $this->TAG, 'Replacing the ASCII codes with the real values');
      $tmpXMLString = str_replace("&lt;", "<", $tmpXMLString);
      $tmpXMLString = str_replace("&gt;", ">", $tmpXMLString);
      $tmpXMLString = str_replace("&#61;", "=", $tmpXMLString);
      $this->xmlString = $tmpXMLString;

      $matches = array();//temp var for inserting regex matches. Using one variable to save mem

      //get all the language codes for languages specifed as translations in xml
      $this->logHandler->log(4, $this->TAG, 'Getting the translations');
      preg_match_all("/\s+<translation\s+lang=[\"'](.+)[\"']>/", $this->xmlString, $matches);
      $this->languageCodes = $matches[1];

      //associate all the barcode inputs in xml with their respective manual barcode inputs
      $this->logHandler->log(4, $this->TAG, 'Associate all the barcode inputs in xml with their respective manual barcode inputs');
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
      $this->logHandler->log(4, $this->TAG, 'Fetch all the string codes');
      preg_match_all("/\s+?<text\s+id=[\"'](.+)[\"']\s*>\s*<value>.+<\/value>\s*<\/text>/", $this->xmlString, $matches);

      //add all unique codes found into new array
      $this->logHandler->log(4, $this->TAG, 'Create a unique array with the unique codes from the xml');
      $codes = array();
      $matchCount = count($matches[1]);

      for($i = 0; $i < $matchCount; $i++){
         $currCode = $matches[1][$i];
         if(!in_array($currCode, $codes)){
            array_push($codes, $currCode);
         }
      }

      $this->xmlValues = array();
      //get all values for each unique code
      foreach ($codes as $currCode){
          preg_match_all("/\s+?<text\s+id=[\"']". str_replace("/", "\/", $currCode) ."[\"']\s*>\s*<value>(.+)<\/value>\s*<\/text>/", $this->xmlString, $matches);
          $this->xmlValues[$currCode] = $matches[1];
      }

      //print_r($this->xmlValues);
      $this->logHandler->log(4, $this->TAG, 'strings obtained from xml file are'.print_r($this->xmlValues,TRUE));

      /*preg_match_all("/<bind\s+nodeset\s*=\s*[\"']([a-z_0-9\-\/]+)[\"'].*type\s*=\s*[\"']select[\"'].*(?=\/>)/",$this->xmlString,$matches);
      $matches = $matches[1];
      for($i=0; $i<sizeof($matches); $i++){
          $matches[$i] = str_replace("/","-",$matches[$i]);
      }

      $this->selectIDs = $matches;*/
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

   /**
    * Check if a given url is a link to an image from the aggregate
    *
    * @param   string   $url  The url to use in fetching the data
    * @return  boolean  Returns TRUE if the url points  to a valid image else returns FALSE
    */
   private function isImage($url) {
      if (filter_var($url, FILTER_VALIDATE_URL) === FALSE) {
          return FALSE;
      }
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_HEADER, 1);
      curl_setopt($ch, CURLOPT_NOBODY, 1);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      $res = curl_exec($ch);
      if ($res === false) {
         $this->logHandler->log(4, $this->TAG, "URL in question: $url");
         $this->logHandler->log(4, $this->TAG, 'Error while determining whether it is an image or not. Will assume it is not an image. Errors: '.curl_errno($ch).": ".curl_error($ch));
         return FALSE;
      }
      $contentType = curl_getinfo($ch);
      $contentType = $contentType["content_type"];
       if(strpos($contentType, 'image') === FALSE) {
           return FALSE;
       }
       else {
            return TRUE;
       }
   }

   private function sendEmail($subject, $message) {
      shell_exec('echo "'."Hi {$_POST['creator']},\n".$message.'"|'.$this->settings['mutt_bin'].' -F '.$this->settings['mutt_config'].' -s "'.$subject.'" -- '.$_POST['email']);
   }

   /**
    *
    * @param type $dataPayload
    * @param type $authToken
    * @return type
    */
   private function updateDMPRecords($dataPayload, $authToken) {
      $this->logHandler->log(3, $this->TAG, "Create a request to the workflow api to update the submissions");
      $postData = array(
         "data" => $dataPayload,
         "token" => $authToken
      );
      $ch = curl_init("http://".$_SERVER['HTTP_HOST']."/repository/mod_ajax.php?page=odk_workflow&do=update_workflow");

      curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, TRUE);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));

      $result = curl_exec($ch);
      $http_status = curl_getinfo($ch);
      curl_close($ch);
      if($http_status == 400) {//bad request
         $this->logHandler->log(1, $this->TAG, "Could not create DMP project for {$_POST['fileName']}. DMP returned status code 400 - Bad request");
         $this->sendEmail("DMP Error for ".$_POST['fileName'], "Could not create a Data Management Portal project for {$_POST['fileName']}. The data provided to the server is incomplete. Please contact the system administrators for assistance.");
         return null;
      }
      else if($http_status == 403) {//forbidden
         $this->logHandler->log(1, $this->TAG, "Could not create DMP project for {$_POST['fileName']}. DMP returned status code 403 - Forbidden");
         $this->sendEmail("DMP Error for ".$_POST['fileName'], "Could not create a Data Management Portal project for {$_POST['fileName']}. Your account was denied permission to create the project. Please contact the system administrators for assistance.");
         return null;
      }
      else if($http_status == 500) {//server error
         $this->logHandler->log(1, $this->TAG, "Could not create DMP project for {$_POST['fileName']}. DMP returned status code 400 - Server error");
         $this->sendEmail("DMP Error for ".$_POST['fileName'], "Could not create a Data Management Portal project for {$_POST['fileName']}. An error occurred while processing the parsed data file. Please contact the system administrators for assistance.");
         return null;
      }
      else {
         return json_decode($result, true);
      }
   }
}

$obj = new Parser();
?>
