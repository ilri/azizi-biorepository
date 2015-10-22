<?php
error_reporting(E_ALL);
class ProcODKForm {
   private $ROOT = "./";
   
   public $Dbase;
   private $creator;
   private $email;
   private $fileName;
   private $formID;
   private $parseType;
   private $dwnldImages;
   private $authCookies;
   private $tmpDir;
   private $sessionID;
   private $userAgent;
   private $xmlString;
   private $lastCursor;
   private $instanceID;
   private $topElement;
   private $submissionIDs;
   private $json;
   private $submissionXObjects;
   private $csvRows;
   private $headingRows;
   private $repeatHeadings;
   private $tableDir;
   private $tableURL;
   private $tableLinks;
   private $setLinks;
   private $csvString;
   private $geopointHeadings;
   private $linkParentSheets;
   private $tableIndexes;
   private $sendToDMP;
   private $dmpUser;
   private $dmpServer;
   private $dmpSession;
   private $dmpLinkSheets;
   public function __construct($Dbase){
      $this->Dbase = $Dbase;
      
      $this->Dbase->CreateLogEntry("great success .... :)", "debug");
      /*
       *  creator: window.parse.name,
         email: window.parse.email,
         fileName: window.parse.fileName,
         formOnServerID: window.parse.formOnServerID,
         parseType: window.parse.parseType,
         dwnldImages: window.parse.dwnldImages,
       */
      $this->creator = $_POST['creator'];
      $this->email = $_POST['email'];
      $this->fileName = $_POST['fileName'];
      $this->formID = $_POST['formOnServerID'];
      $this->parseType = $_POST['parseType'];
      $this->dwnldImages = $_POST['dwnldImages'];
      $this->sendToDMP = "no";//default is no
      $this->dmpUser = "";
      $this->dmpServer = "";
      $this->dmpSession = "";
      $this->dmpLinkSheets = false;
      if(isset($_POST['sendToDMP'])) $this->sendToDMP = $_POST['sendToDMP'];
      if(isset($_POST['dmpUser'])) $this->dmpUser = $_POST['dmpUser'];
      if(isset($_POST['dmpServer'])) $this->dmpServer = $_POST['dmpServer'];
      if(isset($_POST['dmpSession'])) $this->dmpSession = $_POST['dmpSession'];
      if(isset($_POST['dmpLinkSheets'])) $this->dmpLinkSheets = $_POST['dmpLinkSheets'];
      
      $this->sessionID = session_id();
      if($this->sessionID == NULL || $this->sessionID == "") {
         $this->sessionID = round(microtime(true) * 1000);
      }
      
      if(!file_exists($this->ROOT.'tmp')){
         mkdir($this->ROOT.'tmp',0777,true);
      }
      
      $this->tmpDir = $this->ROOT.'tmp/'.$this->sessionID;
      
      if(!file_exists($this->tmpDir)){
         mkdir($this->tmpDir,0777,true);
      }
      $this->tableIndexes = array();
      $this->tableURL = "http://".$_SERVER['HTTP_HOST']."/repository/tmp/".$this->sessionID . "/tables";
      $this->tableDir = $this->tmpDir . "/tables";
      if(!file_exists($this->tableDir)){
         mkdir($this->tableDir,0777,true);
      }
      
      $this->authCookies = $this->tmpDir."/"."AUTH".mt_rand();
      $this->userAgent = "Mozilla/5.0 (X11; Linux x86_64; rv:26.0) Gecko/20100101 Firefox/26.0";
      
      //1. get xml for the form
      $result = $this->getXMLFile();
      if($result === TRUE){
         //2. get submission list
         $this->lastCursor = "";
         $this->submissionIDs = array();
         $this->getSubmissionList();
         $this->Dbase->CreateLogEntry("submission IDs = ".print_r($this->submissionIDs, true), "debug");
         
         //3. download submission data
         $this->json = "";
         $this->submissionXObjects = array();
         foreach($this->submissionIDs as $currSubmissionID){
            $this->downloadSubmissionData($currSubmissionID);
         }
         if(strlen($this->json) !== 0)
            $this->json = $this->json."]";
         
         $this->csvRows = array();
         $this->tableLinks = array();
         $this->setLinks = array();
         $this->linkParentSheets = array();
         $this->processRows();
         $this->construcCSVFile();
         $this->constructLinks();
         $this->sendToODKParser();
         /*if(strlen($this->json) !== 0){
            $this->sendToODKParser();
         }*/
      }
   }
   /**
    * This fuction authenticates the ODK user on Aggregate
    * @return string
    */
   private function authUser(){
      if(file_exists($this->authCookies) === FALSE){
         $authURL = Config::$config['odkAuthURL'];
         touch($this->authCookies);
         chmod($this->authCookies, 0777);
         $authCh = curl_init($authURL);

         curl_setopt($authCh, CURLOPT_USERAGENT, $this->userAgent);
         curl_setopt($authCh, CURLOPT_RETURNTRANSFER, TRUE);
         curl_setopt($authCh, CURLOPT_FOLLOWLOCATION, TRUE);
         curl_setopt($authCh, CURLOPT_CONNECTTIMEOUT, TRUE);
         curl_setopt($authCh, CURLOPT_COOKIEJAR, $this->authCookies);
         curl_setopt($authCh, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
         curl_setopt($authCh, CURLOPT_USERPWD, Config::$config['odkUploadUser'].":".Config::$config['odkUploadPass']);

         $result = curl_exec($authCh);
         $http_status = curl_getinfo($authCh, CURLINFO_HTTP_CODE);
         curl_close($authCh);
         if($http_status == 401){//user not authenticated
            $this->Dbase->CreateLogEntry("auth status = ".$http_status.". ".Config::$config['odkUploadUser']." is not authenticated to upload forms to aggregate. Exiting", "fatal");
            return "The ODK user is not authorised to upload ODK forms";
         }
      }
   }
   
   /**
    * This function fetches the xml form file corresponding to an ODK form
    * 
    * @return boolean   TRUE if able to fetch the xml
    */
   private function getXMLFile(){
      $query = "SELECT instance_id, top_element FROM odk_forms WHERE id = :id";
      $instanceID = $this->Dbase->ExecuteQuery($query, array("id" => $this->formID));
      if(is_array($instanceID) && count($instanceID) == 1){
         $topElement = $instanceID[0]['top_element'];
         $instanceID = $instanceID[0]['instance_id'];
         
         $this->authUser();
         $getXMLURL = "http://azizi.ilri.cgiar.org/aggregate/www/formXml?readable=false&formId=".$instanceID;
         $ch = curl_init($getXMLURL);
         
         curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
         curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
         curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
         curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, TRUE); 
         curl_setopt($ch, CURLOPT_COOKIEFILE, $this->authCookies);
         
         $curlResult = curl_exec($ch);
         $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
         curl_close($ch);
         
         if($http_status == 200){
            $this->instanceID = $instanceID;
            $this->topElement = $topElement;
            
            $this->Dbase->CreateLogEntry("Form xml obtained successfully!","info");
            $this->xmlString = $curlResult;
            file_put_contents($this->tmpDir."/form.xml", $curlResult);
            return true;
         }
         else{
            $this->Dbase->CreateLogEntry("Something went wrong while trying to get form xml. http status = ".$http_status." & curl results = ".$curlResult, "fatal");
            return false;
         }
      }
      
   }
   
   /**
    * This function fetches the list of submissions for the set ODK form on Aggregate
    */
   private function getSubmissionList(){
      $numSubmissionEntries = 100;
      $listURL = "http://azizi.ilri.cgiar.org/aggregate/view/submissionList?formId=".$this->instanceID."&numEntries=".$numSubmissionEntries;
      if(strlen($this->lastCursor) > 0)
         $listURL = $listURL . "&cursor=" . urlencode ($this->lastCursor);
      
      $ch = curl_init($listURL);
      
      curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, TRUE); 
      curl_setopt($ch, CURLOPT_COOKIEFILE, $this->authCookies);

      $curlResult = curl_exec($ch);
      $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);
      
      if($http_status == 200){
         $listXML = $curlResult;
         $listXObject = simplexml_load_string($listXML);
         if(is_object($listXObject->idList)){
            if(isset($listXObject->idList->id[0])){
               //add all submission ids to submissionIDs array
               foreach ($listXObject->idList->id as $currSubmission){
                  $this->submissionIDs[] = "$currSubmission";//convert simplexml array values into normal string
               }
               
               if($this->lastCursor != $listXObject->resumptionCursor){
                  $this->lastCursor = $listXObject->resumptionCursor;
                  $this->getSubmissionList();
               }
               else{
                  $this->Dbase->CreateLogEntry("It appears like all the submission IDs have been obtained from aggregate server", "info");
               }
            }
            else{
               $this->Dbase->CreateLogEntry("No submissions gotten for form".print_r($listXObject, true), "fatal");
               //TODO: send user email
            }
         }
         else
            $this->Dbase->CreateLogEntry("Unable to get idList from xml. XML object looks like this ".print_r($listXObject, true), "fatal");
      }
      else{
         $this->Dbase->CreateLogEntry("There was a problem getting submission list from server. http status = ".$http_status. " & result = ".$curlResult,"fatal");
      }
   }
   
   /**
    * This function downloads submission data for one item in the ODK Submission list (Refer to getSubmissionList)
    * @param type $submissionID
    */
   private function downloadSubmissionData($submissionID){
      $formId = $this->instanceID."[@version=null]/".$this->topElement."[@key=".$submissionID."]";
      $downloadURL = "http://azizi.ilri.cgiar.org/aggregate/view/downloadSubmission?formId=".urlencode($formId);
      
      $ch = curl_init($downloadURL);
      
      curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, TRUE);
      curl_setopt($ch, CURLOPT_COOKIEFILE, $this->authCookies);

      $curlResult = curl_exec($ch);
      $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);
      
      if($http_status == 200){
         $submissionXObject = simplexml_load_string($curlResult);
         $submissionChildren = (array) $submissionXObject->data->children();
         $this->submissionXObjects[] = $submissionChildren[$this->topElement];
      }
      else
         $this->Dbase->CreateLogEntry(" Unable to get data for submission with id = ".$submissionID." . http status = ".$http_status." & result = ".$curlResult, "fatal");
   }
   
   /**
    * This function does the initial cleaning of submissions obtained from Aggregate:
    *    - preprocessing of geopoints
    *    - preprocessing of repeats
    */
   private function processRows(){
      $xml = new DOMDocument();
      $xml->loadHTML($this->xmlString);
      $xmlRepeats = $xml->getElementsByTagName('repeat');
      $repeats = array();
      for($xmlI = 0; $xmlI < $xmlRepeats->length; $xmlI++) {
          $repeats[] = $xmlRepeats->item($xmlI)->getAttribute("nodeset");
      }
      //preg_match_all("/<repeat\s+nodeset\s*=\s*[\"'](.*)[\"']/", $this->xmlString, $repeats);
      preg_match_all("/<bind\s+nodeset\s*=\s*[\"']([a-z0-9_\-\.\/]+)[\"'].*type\s*=\s*[\"']geopoint[\"']/i", $this->xmlString, $geopoints);
      /*if(isset($repeats[1]))
         $repeats = $repeats[1];
      else
         $repeats = array();*/
      
      for($repeatIndex = 0; $repeatIndex<count($repeats); $repeatIndex++){
         $repeats[$repeatIndex] = str_replace("/".$this->topElement."/", "", $repeats[$repeatIndex]);
         $repeats[$repeatIndex] = str_replace("/", ":", $repeats[$repeatIndex]);
      }
      
      if(isset($geopoints[1])){
         $geopoints = $geopoints[1];
      }
      else{
         $geopoints = array();
      }
      
      for($geoIndex = 0; $geoIndex<count($geopoints); $geoIndex++){
         $geopoints[$geoIndex] = str_replace("/".$this->topElement."/", "", $geopoints[$geoIndex]);
         $geopoints[$geoIndex] = str_replace("/", ":", $geopoints[$geoIndex]);
      }
      
      $this->Dbase->CreateLogEntry("Geopoints are ".print_r($geopoints, true), "debug");
      
      $this->geopointHeadings = $geopoints;
      $this->repeatHeadings = $repeats;
      $this->Dbase->CreateLogEntry("Repeats are ".print_r($repeats, true), "debug");
      
      for($rowIndex = 0; $rowIndex < count($this->submissionXObjects); $rowIndex++){
         $this->Dbase->CreateLogEntry("currently at ".$rowIndex, "debug");
         $currRow = (array) $this->submissionXObjects[$rowIndex];
         $this->setLinks = array(); 
         $this->processRow($currRow, "main_sheet", array(), $rowIndex);
         
      }
      $this->Dbase->CreateLogEntry(print_r($_SERVER, true), "debug");
   }
   
   /**
    * This function processes the provided row to make it parseable by the ODK Parser
    * module
    * 
    * @param type $row          SimpleXML representation of a single response for and ODK form
    * @param type $parentSheet  Name of the sheet from where the row is from
    * @param type $parents      An array of the heirarcy of parents that are parents of $parentSheet
    * @param type $rowIndex     $row's index in $parentSheet
    * @param type $parentLink   If $parentSheet will ultimately be converted into a HTML table, the link for the HTML page
    * @param type $parentIndex  
    */
   private function processRow($row, $parentSheet, $parents, $rowIndex = -1, $parentLink = -1, $parentIndex = -1){
      
      $rowKeys = array_keys($row);
      $rowValues = array_values($row);
      
      //get the next index in parent csv array
      if($rowIndex === -1){
         if(isset($this->csvRows[$parentSheet])){
            $rowIndex = count($this->csvRows[$parentSheet]);
         }
         else{
            $rowIndex = 0;//the parent csv does not exist, set row index to 0
         }
         
      }

      if(count($rowKeys) == count($rowValues)){
         for($elementIndex = 0; $elementIndex < count($rowKeys); $elementIndex++){
            //get the index of the current element in the csv matrix
            if(count($parents) > 0)
               $parent_heading = join(":", $parents);
            else
               $parent_heading = "";
            
            if(strlen($parent_heading) === 0)
               $currHeading = $rowKeys[$elementIndex];
            else
               $currHeading = $parent_heading . ":" . $rowKeys[$elementIndex];
            
            if(!isset($this->headingRows[$parentSheet]))
               $this->headingRows[$parentSheet] = array();
            
            //check if current element value is a simplexmlelement object and convert it to an array if it is
            if(is_a($rowValues[$elementIndex], "SimpleXMLElement")){
               $rowValues[$elementIndex] = (array) $rowValues[$elementIndex];
            }
            
            if(is_array($rowValues[$elementIndex])){//means that current element a group or repeat
               $newParentSheet = $parentSheet;//new parent sheet will be a combination of the existing parent sheet and question name
               if(!is_numeric($rowKeys[$elementIndex])){
                  $newParents  = array_merge($parents, array($rowKeys[$elementIndex]));
               }
               else {//probably means that this is that we are at the topmost heirarchy in a repeat element
                   $newParents = $parents;
                   $this->tableIndexes[$parentLink] = $rowKeys[$elementIndex];
               }
                  
               $newRowIndex = $rowIndex;
               $link = $parentLink;
               $newParentIndex = $parentIndex;
               //check if current heading is associated to a repeating group
               if(array_search($currHeading, $this->repeatHeadings) !== false){//means that this particular element is a repeat
                  $csvElementIndex = array_search($currHeading, $this->headingRows[$parentSheet]);
                  if($csvElementIndex === false){//element heading does not exist in array
                     $csvElementIndex = array_push($this->headingRows[$parentSheet], $currHeading) - 1;
                  }
                  
                  $newParentSheet = join("-", $newParents);
                  
                  $link = $newParentSheet.mt_rand().".html";//a link should be unique for each repeat question answered
                  $this->setLinks[$newParentSheet] = $link;
                  $this->linkParentSheets[$link] = $currHeading;
                  
                  $this->csvRows[$parentSheet][$rowIndex][$csvElementIndex] = $this->tableURL . "/" .$link;
                  if($parentLink != -1) $this->tableLinks[$parentLink][$this->tableIndexes[$parentLink]][$currHeading] = $this->tableURL . "/" .$link;
                  if(!isset($this->tableLinks[$link])){
                      $this->tableLinks[$link] = array();
                      $this->tableIndexes[$link] = -1;
                  }
                  $newParentIndex = $newRowIndex;
                  $newRowIndex = -1;
               }
               /*else{
                  $parentHeadingIndex = array_search($currHeading, $this->headingRows[$parentSheet]);
                  unset($this->headingRows[$parentSheet][$parentHeadingIndex]);
               }*/
               
               //processRow($row, $parentSheet, $parents, $rowIndex = -1)
               $this->processRow((array) $rowValues[$elementIndex], $newParentSheet, $newParents, $newRowIndex, $link, $newParentIndex);
            }
            else{//is a string
               
               if(array_search($currHeading, $this->geopointHeadings) !== false){//if is gps
                  if(strlen($rowValues[$elementIndex]) > 0)
                     $gpsParts = explode(" ", $rowValues[$elementIndex]);
                  else
                     $gpsParts = array("","","","");
                  
                  //gps is in 4 parts: latitude, longitude, altidute & accuracy in that order
                  
                  for($gpsPIndex = 0; $gpsPIndex < count($gpsParts); $gpsPIndex++){
                     $gpsName = "";
                     if($gpsPIndex == 0) $gpsName = ":Latitude";
                     else if($gpsPIndex == 1) $gpsName = ":Longitude";
                     else if($gpsPIndex == 2) $gpsName = ":Altitude";
                     else if($gpsPIndex == 3) $gpsName = ":Accuracy";
                     
                     $newCurrHeading = $currHeading . $gpsName;
                     $newCsvElementIndex = array_search($newCurrHeading, $this->headingRows[$parentSheet]);
                     if($newCsvElementIndex === false){
                        array_push($this->headingRows[$parentSheet], $newCurrHeading);
                        $newCsvElementIndex = array_search($newCurrHeading, $this->headingRows[$parentSheet]);
                     }
                     
                     $this->csvRows[$parentSheet][$rowIndex][$newCsvElementIndex] = $gpsParts[$gpsPIndex];
                  }
               }
               else{
                  //check if current cell is an image
                  if(preg_match("/.+\.jpg/", $rowValues[$elementIndex]) === 1){
                   /*cell is an image, we need to disregard whatever is in the cell and construct a url
                    * Refer to https://groups.google.com/forum/#!topic/opendatakit/uzMy9az9eGE
                   */
                   $this->Dbase->CreateLogEntry("** is image ".$rowValues[$elementIndex]." rowIndex = ".($rowIndex)." parentIndex = ".$parentIndex,"debug");
                   $cId = $rowKeys[$elementIndex];
                   
                   if($parentLink == -1){//means we are in the main sheet
                     $submissionID = $this->submissionIDs[$rowIndex];//since the headings also had a rowIndex but submissionIDs started with first response
                     $blobKey = $this->instanceID."[@version=null]/".$this->topElement."[@key=".$submissionID."]/".$cId;
                     $downloadURL = "http://azizi.ilri.cgiar.org/aggregate/view/binaryData?blobKey=".urlencode($blobKey);
                   }
                   else {
                      //get 1 based index of row in current table and use that as ordinal. Each questionnaire response usually has one table
                      if(!isset($this->tableLinks[$parentLink])){//weird because heading should have already been set
                         $ordinal = 0;
                         $this->Dbase->CreateLogEntry("An error occurred while trying to calculate the ordinal using the row index of row with image in html table. Setting ordinal to 0", "fatal");
                      }
                      else {
                         $ordinal = count($this->tableLinks[$parentLink]);//assuming that the first row contains the heading
                      }

                     $submissionID = $this->submissionIDs[$parentIndex];
                     $blobKey = $this->instanceID."[@version=null]/".$this->topElement."[@key=".$submissionID."]/".$parent_heading."[@ordinal=".$ordinal."]/".$cId;
                     $downloadURL = '<a href="'."http://azizi.ilri.cgiar.org/aggregate/view/binaryData?blobKey=".urlencode($blobKey).'" target="_blank">View</a>';
                   }

                   $this->Dbase->CreateLogEntry("** is image url ".$downloadURL, "debug");
                   $rowValues[$elementIndex] = $downloadURL;
                  }
                  else if(preg_match("/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d+\+\d+/", $rowValues[$elementIndex]) === 1) {//check if it's a timestamp
                     $this->Dbase->CreateLogEntry("** timestamp ".$rowValues[$elementIndex]." rowIndex = ".($rowIndex)." parentIndex = ".$parentIndex,"fatal");
                     //change to format 2015-07-30 11:30:43.0
                     $parts = explode("T", $rowValues[$elementIndex]);
                     $this->Dbase->CreateLogEntry("** ".$parts[1],"fatal");
                     $timeParts = explode("+", $parts[1]);
                     $rowValues[$elementIndex] = $parts[0]." ".$timeParts[0];
                  }
                  
                  $csvElementIndex = array_search($currHeading, $this->headingRows[$parentSheet]);
                  if($csvElementIndex === false){//element heading does not exist in array
                     $csvElementIndex = array_push($this->headingRows[$parentSheet], $currHeading) - 1;
                  }

                  $this->csvRows[$parentSheet][$rowIndex][$csvElementIndex] = $rowValues[$elementIndex];
                  if($parentLink != -1){
                     $linkIndex = $this->tableIndexes[$parentLink];
                     $this->tableLinks[$parentLink][$linkIndex][$rowKeys[$elementIndex]] = $rowValues[$elementIndex];
                  }
               }
            }
         }
      }
      else{
         //$this->Dbase->CreateLogEntry();
      }
   }
   
   /**
    * This function converts the processed $this->headingRows and $this->csvRows
    * into $this->csvString
    */
   private function construcCSVFile(){
      $csvString = "";
      $mainSheetNoColumns = count($this->headingRows["main_sheet"]);
      for($msIndex = 0; $msIndex < $mainSheetNoColumns; $msIndex++){
         $this->headingRows["main_sheet"][$msIndex] = str_replace("@attributes", "meta", $this->headingRows["main_sheet"][$msIndex]);
         if(strlen($csvString) === 0)
            $csvString = '"'.$this->headingRows["main_sheet"][$msIndex].'"';
         else
            $csvString = $csvString.',"'.$this->headingRows["main_sheet"][$msIndex].'"';
      }
      
      $csvString = $csvString . "\n";
      
      $msRows = $this->csvRows["main_sheet"];
      foreach ($msRows as $currRow){
         for($msIndex = 0; $msIndex< $mainSheetNoColumns; $msIndex++){
            if(isset($currRow[$msIndex])){
               if($msIndex === 0){
                  $csvString = $csvString . '"' . $currRow[$msIndex] . '"';
               }
               else{
                  $csvString = $csvString . ',"' . $currRow[$msIndex] . '"';
               }
            }
            else{
               if($msIndex === 0){
                  $csvString = $csvString . '""';
               }
               else{
                  $csvString = $csvString . ',""';
               }
            }
         }
         $csvString = $csvString . "\n";
      }
      $this->csvString = $csvString;
      file_put_contents($this->tmpDir."/outputcsv.csv", $csvString);
      $this->Dbase->CreateLogEntry($this->tmpDir."/outputcsv.csv", "debug");
   }
   
   private function constructLinks(){
      $links = array_keys($this->tableLinks);
      $linkIndex = 0;
      foreach($this->tableLinks as $currTable){
         $linkParentSheet = str_replace(":", "-", $this->linkParentSheets[$links[$linkIndex]]);
         $unProcessedHeadings = $this->headingRows[$linkParentSheet];
         for($uIndex = 0; $uIndex < count($unProcessedHeadings); $uIndex++){
            preg_match("/.*:([a-z0-9_\-\.\/]+)/i", $unProcessedHeadings[$uIndex], $matches);
            if(strlen($matches[1])>0)
               $unProcessedHeadings[$uIndex] = $matches[1];
         }
         
         $html = "<html><head></head><body>";
         //get all headings
         $headings = array();
         foreach ($currTable as $currRow){
            $rowHeaders = array_keys($currRow);
            foreach ($rowHeaders as $currRowHeader){
               if(array_search($currRowHeader, $headings)=== false){//heading does not exist in main heading array
                  array_push($headings, $currRowHeader);
               }
            }
         }
         
         //add headings that are missing but that are in the sheet
         foreach($unProcessedHeadings as $currUPHeading){
            $found = false;
            foreach($headings as $currHeading){
               if(strpos($currHeading, $currUPHeading) !== false) {
                  $found = true;
                  break;
               }
            }
            if($found == false) {
               $this->Dbase->CreateLogEntry("Did not find $currUPHeading in ".print_r($headings, true), "debug");
               $headings[] = $currUPHeading;
            }
         }
         
         $html = $html . "<table><tr>";
         foreach ($headings as $currHeading){
            //remove heading prefixes that might have been inserted due to repeats within repeats. Heading prefixes are joined using a colon
            $cleanHeading = explode(":", $currHeading);
            if(count($cleanHeading) > 1) {
               $cleanHeadingPart = count($cleanHeading) - 1;
               $currHeading = $cleanHeading[$cleanHeadingPart];
            }
            $html = $html . "<th>" . $currHeading. "</th>";
         }
         //$html = $html . "</tr>";
         
         foreach($currTable as $currRow){
            $html = $html . "<tr>";
            foreach ($headings as $currHeading){
               if(isset($currRow[$currHeading])){
                  $html = $html . "<td>" . $currRow[$currHeading] . "</td>";
               }
               else{
                  $html = $html . "<td></td>";
               }
            }
            $html = $html . "</tr>";
         }
         $html = $html . "</table></body></html>";
         file_put_contents($this->tableDir . "/" . $links[$linkIndex], $html);
         $linkIndex++;
      }
   }
   
   private function sendToODKParser(){
      $postData = array(
         "creator" => $this->creator,
         "email" => $this->email,
         "fileName" => $this->fileName,
         "csvString" => urldecode($this->csvString),
         "jsonString" => $this->json,
         "xmlString" => $this->xmlString,
         "parseType" => $this->parseType,
         "dwnldImages" => $this->dwnldImages,
         "fromWithin" => "yes",
         "sendToDMP" => $this->sendToDMP,
         "dmpServer" => $this->dmpServer,
         "dmpUser" => $this->dmpUser,
         "dmpSession" => $this->dmpSession,
         "dmpLinkSheets" => $this->dmpLinkSheets
      );
      $ch = curl_init("http://".$_SERVER['HTTP_HOST']."/repository/modules/mod_parse_odk_backend.php");
      
      curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, TRUE); 
      curl_setopt($ch, CURLOPT_COOKIEFILE, $this->authCookies);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
      
      curl_exec($ch);
      $http_status = curl_getinfo($ch);
      curl_close($ch);
      
      $this->Dbase->CreateLogEntry("http_status = ".print_r($http_status, true), "debug");
      $this->Dbase->CreateLogEntry("http_status = ".$http_status, "debug");
   }
}
?>

