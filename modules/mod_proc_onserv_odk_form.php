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
   
   public function __construct($Dbase){
      $this->Dbase = $Dbase;
      
      $this->Dbase->CreateLogEntry("great success .... :)", "fatal");
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
      
      $this->tableURL = $_SERVER['HTTP_ORIGIN']."/repository/tmp/".$this->sessionID . "/tables";
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
         $this->Dbase->CreateLogEntry("submission IDs = ".print_r($this->submissionIDs, true), "fatal");
         
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
            return true;
         }
         else{
            $this->Dbase->CreateLogEntry("Something went wrong while trying to get form xml. http status = ".$http_status." & curl results = ".$curlResult, "fatal");
            return false;
         }
      }
      
   }
   
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
            //$this->Dbase->CreateLogEntry(print_r($listXObject->idList->id,true), "fatal");
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
         //$this->Dbase->CreateLogEntry($curlResult, "fatal");
         //$this->Dbase->CreateLogEntry($curlResult, "fatal");
         $submissionXObject = simplexml_load_string($curlResult);
         /*if(strlen($this->json) === 0)
            $this->json = "[".json_encode($submissionXObject->data);
         else
            $this->json = $this->json.",".json_encode($submissionXObject->data);*/
         //$this->Dbase->CreateLogEntry($curlResult, "fatal");
         //$this->submissionXObjects[] = $submissionXObject->xpath("/submission/data/".$this->topElement."[@id=\"".$this->instanceID."\"");
         $submissionChildren = (array) $submissionXObject->data->children();
         $this->submissionXObjects[] = $submissionChildren[$this->topElement];
         //$this->Dbase->CreateLogEntry("this should work ".print_r($submissionChildren[$this->topElement], true), "fatal");
      }
      else
         $this->Dbase->CreateLogEntry(" Unable to get data for submission with id = ".$submissionID." . http status = ".$http_status." & result = ".$curlResult, "fatal");
   }
   
   private function processRows(){
      preg_match_all("/<repeat\s+nodeset\s*=\s*[\"'](.*)[\"']/", $this->xmlString, $repeats);
      preg_match_all("/<bind\s+nodeset\s*=\s*[\"']([a-z0-9_\-\.\/]+)[\"'].*type\s*=\s*[\"']geopoint[\"']/i", $this->xmlString, $geopoints);
      if(isset($repeats[1]))
         $repeats = $repeats[1];
      else
         $repeats = array();
      
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
      
      $this->Dbase->CreateLogEntry("Geopoints are ".print_r($geopoints, true), "fatal");
      
      $this->geopointHeadings = $geopoints;
      $this->repeatHeadings = $repeats;
      
      for($rowIndex = 0; $rowIndex < count($this->submissionXObjects); $rowIndex++){
         $this->Dbase->CreateLogEntry("currently at ".$rowIndex, "fatal");
         $currRow = (array) $this->submissionXObjects[$rowIndex];
         //$this->Dbase->CreateLogEntry(print_r($currRow, true),"fatal");
         
         $this->processRow($currRow, "main_sheet", array(), $rowIndex);
         
      }
      
      //$this->Dbase->CreateLogEntry(print_r($this->csvRows, true), "fatal");
      //$this->Dbase->CreateLogEntry("csv files are ".print_r($this->headingRows, true), "fatal");
      $this->Dbase->CreateLogEntry(print_r($_SERVER, true), "fatal");
   }
   
   private function processRow($row, $parentSheet, $parents, $rowIndex = -1, $parentLink = -1, $parentIndex = -1){
      
      $rowKeys = array_keys($row);
      $rowValues = array_values($row);
      
      //$this->Dbase->CreateLogEntry("row keys ".print_r($rowKeys, true), "fatal");
      //$this->Dbase->CreateLogEntry("row values ".print_r($rowValues, true), "fatal");
      
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
            
            //$this->Dbase->CreateLogEntry($currHeading, "fatal");
            
            if(!isset($this->headingRows[$parentSheet]))
               $this->headingRows[$parentSheet] = array();
            
            //check if current element value is a simplexmlelement object and convert it to an array if it is
            if(is_a($rowValues[$elementIndex], "SimpleXMLElement")){
               $rowValues[$elementIndex] = (array) $rowValues[$elementIndex];
            }
            
            if(is_array($rowValues[$elementIndex])){
               $newParentSheet = $parentSheet;
               if(!is_numeric($rowKeys[$elementIndex]))
                  $newParents  = array_merge($parents, array($rowKeys[$elementIndex]));
               else
                  $newParents = $parents;
               $newRowIndex = $rowIndex;
               $link = $parentLink;
               $newParentIndex = $parentIndex;
               
               //check if current heading is associated to a repeating group
               if(array_search($currHeading, $this->repeatHeadings) !== false){//means that this particular element is a repeating response
                  $csvElementIndex = array_search($currHeading, $this->headingRows[$parentSheet]);
                  if($csvElementIndex === false){//element heading does not exist in array
                     $csvElementIndex = array_push($this->headingRows[$parentSheet], $currHeading) - 1;
                  }
                  
                  //$this->Dbase->CreateLogEntry($currHeading." is repeating", "fatal");
                  //$newParentSheet = $rowKeys[$elementIndex];
                  $newParentSheet = join("_", $newParents);
                  
                  if(!isset($this->setLinks[$parentSheet][$newParentSheet][$rowIndex])){
                     $link = $newParentSheet.mt_rand().".html";//a link should be unique for each repeat question answered
                     $this->setLinks[$parentSheet][$newParentSheet][$rowIndex] = $link;
                     $this->linkParentSheets[$link] = $currHeading;
                  }
                  else{
                     $link = $this->setLinks[$parentSheet][$newParentSheet][$rowIndex];
                  }
                  
                  $this->csvRows[$parentSheet][$rowIndex][$csvElementIndex] = $this->tableURL . "/" .$link;
                  
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
                  $csvElementIndex = array_search($currHeading, $this->headingRows[$parentSheet]);
                  if($csvElementIndex === false){//element heading does not exist in array
                     $csvElementIndex = array_push($this->headingRows[$parentSheet], $currHeading) - 1;
                  }

                  $this->csvRows[$parentSheet][$rowIndex][$csvElementIndex] = $rowValues[$elementIndex];
                  if($parentLink != -1){
                     if(!isset($this->tableLinks[$parentLink])){
                        $this->tableLinks[$parentLink] = array();
                        $linkIndex = 0;
                     }
                     else{
                        if($elementIndex == 0)
                           $linkIndex = count($this->tableLinks[$parentLink]);
                        else {
                           $linkIndex = count($this->tableLinks[$parentLink]) - 1;
                        }
                     }

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
      $this->Dbase->CreateLogEntry($this->tmpDir."/outputcsv.csv", "fatal");
   }
   
   private function constructLinks(){
      $links = array_keys($this->tableLinks);
      $linkIndex = 0;
      foreach($this->tableLinks as $currTable){
         $linkParentSheet = $this->linkParentSheets[$links[$linkIndex]];
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
         
         if(count($unProcessedHeadings) > count($headings))
            $headings = $unProcessedHeadings;
         
         $html = $html . "<table><tr>";
         foreach ($headings as $currHeading){
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
      $postData = array("creator" => $this->creator, "email" => $this->email, "fileName" => $this->fileName, "csvString" => urldecode($this->csvString), "jsonString" => $this->json, "xmlString" => $this->xmlString, "parseType" => $this->parseType, "dwnldImages" => $this->dwnldImages, "fromWithin" => "yes");
      $ch = curl_init($_SERVER['HTTP_ORIGIN']."/repository/modules/mod_parse_odk_backend.php");
      
      curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, TRUE); 
      curl_setopt($ch, CURLOPT_COOKIEFILE, $this->authCookies);
      curl_setopt($ch, CURLOPT_POST, 1 );
      curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
      
      curl_exec($ch);
      $http_status = curl_getinfo($ch);
      curl_close($ch);
      
      $this->Dbase->CreateLogEntry("http_status = ".print_r($http_status, true), "fatal");
      $this->Dbase->CreateLogEntry("http_status = ".$http_status, "fatal");
   }
}
?>

