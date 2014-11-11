<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * This class is responsible for handling The Biorepository's MTAs
 */
class MTA{
   
   private $Dbase;
   
   /**
    * The class's constructor
    */
   public function __construct($Dbase){
      $this->Dbase = $Dbase;
   }
   
   public function trafficController(){
      if(OPTIONS_REQUESTED_SUB_MODULE == "process_mta"){
         $this->processMTA();
      }   
   }
   
   private function processMTA(){
      $return = array("error_message" => "");
      //check if user provided sample ids or filters
      $sampleIDs = array();
      
      if(isset($_REQUEST['sample_ids'])){
         $sampleIDs = explode(",", $_REQUEST['sample_ids']);
      }
      else if(isset($_REQUEST['filters'])){
         $sampleIDs = $this->getSampleIDs();
      }
      
      //check validity of the data
      $result = $this->validateMTAData($sampleIDs);
      
      if($result == true){
         //add mta to database
         $mtaID = $this->addMTAToDb($sampleIDs);

         if($mtaID  !== 0){
            //generate sample spreadsheet
            $samplesDocument = $this->generateSamplesSpreadsheet($sampleIDs);
            
            //generate mta document. Do this last
            $mtaDocument = $this->generateMTADocument($sampleIDs);

            //send documents to parties
            if($samplesDocument != null && $mtaDocument != null){
               $this->sendMTADocuments($mtaID, $mtaDocument, $samplesDocument);
            }
            else {
               $this->removeFromDB($mtaID);
               
               if($samplesDocument == null){
                  $this->Dbase->CreateLogEntry("Unable to generate the samples document. Removing the MTA from the database", "fatal");
                  $return['error'] = true;
                  $return['error_message'] .= "Unable to generate the samples document.";
               }
               if($mtaDocument == null){
                  $this->Dbase->CreateLogEntry("Unable to generate the MTA document. Removing the MTA from the database", "fatal");
                  $return['error'] = true;
                  $return['error_message'] .= "Unable to generate the MTA document.";
               }
            }
            
         }
         else {
            $this->Dbase->CreateLogEntry("Unable to obtain MTA number probably because SQL error occurred while trying to add MTA to database", "fatal");
            $return['error'] = true;
            $return['error_message'] .= "Unable to obtain MTA number probably because SQL error occurred while trying to add MTA to database";
         }
         
      }
      else {
         $this->Dbase->CreateLogEntry("Data validation for MTA failed. Doing nothing", "fatal");
         $return['error'] = true;
         $return['error_message'] .= "Data validation for MTA failed. Doing nothing";
      }
      
      return json_encode($return);
   }
   
   private function getSampleIDs(){
      if(isset($_REQUEST['filters'])){
         $filters = json_decode($_REQUEST['filters'], true);
         
         $projectIDs = implode(",", $filters['projects']);
         $organismIDs = implode(",", $filters['organisms']);
         $sampleTypes = implode(",", $filters['sampleTypes']);
         $tests = implode(",", $filters['tests']);
         $results = implode(",", $filters['results']);
         
         $queryFilter = "";
         if(strlen($projectIDs) > 0){
            if(strlen($queryFilter) == 0){
               $queryFilter .= " a.Project in(".$projectIDs.")";
            }
            else {
               $queryFilter .= " and a.Project in(".$projectIDs.")";
            }
         }
         if(strlen($organismIDs) > 0){
            if(strlen($queryFilter) == 0){
               $queryFilter .= " a.org in(".$organismIDs.")";
            }
            else {
               $queryFilter .= " and a.org in(".$organismIDs.")";
            }
         }
         if(strlen($sampleTypes) > 0){
            if(strlen($queryFilter) == 0){
               $queryFilter .= " a.sample_type in(".$sampleTypes.")";
            }
            else {
               $queryFilter .= " and a.sample_type in(".$sampleTypes.")";
            }
         }
         
         $query = "select a.count"
                 . " from ".Config::$config['azizi_db'].".samples as a";
         if(strlen($queryFilter) > 0){
            $query .= " where ".$queryFilter;
         }
         
         $result = $this->Dbase->ExecuteQuery($query);
         
         $sampleIDs = array();
         foreach ($result as $currResult){
            $sampleIDs[] = $currResult['count'];
         }
         $this->Dbase->CreateLogEntry("Found ".count($sampleIDs)." samples", "info");
         return $sampleIDs;
      }
      return array();
   }
   
   private function validateMTAData($sampleIDs){
      $this->Dbase->CreateLogEntry("About to validate ".print_r($_REQUEST, true), "info");
      
      $piName = $_REQUEST['pi_name'];
      $piEmail = $_REQUEST['pi_email'];
      $researchTitle = $_REQUEST['research_title'];//title of the research being conducted
      $org = $_REQUEST['org'];
      
      $materialRequired = $_REQUEST['material'];
      $format = $_REQUEST['format'];
      $storageSafety = $_REQUEST['storage_safety'];
      $assocData = $_REQUEST['assoc_data'];
      
      //$sampleIDs = $_REQUEST['sample_ids'];
      
      //$this->Dbase->CreateLogEntry("pi_name = ".$piName, "info");
      if(strlen($piName) == 0){
         $this->Dbase->CreateLogEntry("PI Name wrong ".$piName,"fatal");
         return false;
      }
      //$this->Dbase->CreateLogEntry("pi_email = ".$piEmail, "info");
      if(strlen($piEmail) == 0) {
         $this->Dbase->CreateLogEntry("PI Email wrong ".$piEmail,"fatal");
         return false;
      }
      //$this->Dbase->CreateLogEntry("research_title = ".$researchTitle, "info");
      if(strlen($researchTitle) == 0) {
         $this->Dbase->CreateLogEntry("Research Title wrong ".$researchTitle,"fatal");
         return false;
      }
      //$this->Dbase->CreateLogEntry("org = ".$org, "info");
      if(strlen($org) == 0 ) {
         $this->Dbase->CreateLogEntry("Org ".$org,"fatal");
         return false;
      }
      //$this->Dbase->CreateLogEntry("material = ".$materialRequired, "info");
      if(strlen($materialRequired) == 0) {
         $this->Dbase->CreateLogEntry("PI Name wrong ".$materialRequired,"fatal");
         return FALSE;
      }
      //$this->Dbase->CreateLogEntry("format = ".$format, "info");
      if(strlen($format) == 0) {
         $this->Dbase->CreateLogEntry("Format wrong ".$format,"fatal");
         return false;
      }
      //$this->Dbase->CreateLogEntry("assoc_data = ".$assocData, "info");
      if(strlen($assocData) == 0) {
         $this->Dbase->CreateLogEntry("Associated data wrong ".$piName,"fatal");
         return false;
      }
      //$this->Dbase->CreateLogEntry("sample_ids = ".$sampleIDs, "info");
      if(count($sampleIDs) == 0) {
         $this->Dbase->CreateLogEntry("No samples ","fatal");
         return false;
      }
      
      return true;
   }
   
   private function addMTAToDb($sampleIDs){
      $piName = $_REQUEST['pi_name'];
      $piEmail = $_REQUEST['pi_email'];
      $researchTitle = $_REQUEST['research_title'];//title of the research being conducted
      $org = $_REQUEST['org'];
      
      $materialRequired = $_REQUEST['material'];
      $quantity = count($sampleIDs);
      $format = $_REQUEST['format'];
      $storageSafety = $_REQUEST['storage_safety'];
      $assocData = $_REQUEST['assoc_data'];
      
      //$sampleIDs = $_REQUEST['sample_ids'];
      
      //get last inserted mta
      $query = "select id"
              . " from mtas"
              . " order by id desc"
              . " limit 1";
      
      $result = $this->Dbase->ExecuteQuery($query);
      if(is_array($result)){
         $lastID = 0;
         if(count($result) == 1) $lastID = $result[0]['id'];
         
         $mtaID = $lastID + 1;
         $query = "insert into mtas(id, pi_name, pi_email, research_title, organisation, material, quantity, format, storage_safety, assoc_data, ilri_scientist_name, ilri_scientist_title, ilri_scientist_email)"
                  . " values (:id, :piName, :piEmail, :researchTitle, :org, :material, :quantity, :format, :storageSafety, :assocData, :ilriScientistName, :ilriScientistTitle, :ilriScientistEmail)";
          $values = array(
              "id" => $mtaID,
              "piName" => $piName,
              "piEmail" => $piEmail,
              "researchTitle" => $researchTitle,
              "org" => $org,
              "material" => $materialRequired,
              "quantity" => $quantity,
              "format" => $format,
              "storageSafety" => $storageSafety,
              "assocData" => $assocData,
              "ilriScientistName" => "Steve Kemp",
              "ilriScientistTitle" => "Deputy Director General",
              "ilriScientistEmail" => "s.kemp@cgiar.org"
          );
          $result = $this->Dbase->ExecuteQuery($query, $values);
          
          if($result !== 1){
            $sIDs = explode(",", $sampleIDs);
            foreach($sIDs as $currSampleID){
               $query = "insert into mta_samples(sample_id, mta_id)"
                       . " values(:sampleID, :mtaID)";
               $result = $this->Dbase->ExecuteQuery($query, array("sampleID" => $currSampleID, "mtaID" => $mtaID));
               
               if($result === 1){
                  $this->Dbase->CreateLogEntry("Unable add on of the samples (with id = ".$currSampleID.") in the mta to the database. Rolling back (deleting MTA from database)", "fatal");
                  
                  $this->removeFromDB($mtaID);
                  return 0;
               }
            }
            
            return $mtaID;
          }
          else {
             $this->Dbase->CreateLogEntry("Unable to insert MTA to database. Unable to continue", "fatal");
             return 0;
          }
      }
      else {
         return 0;
      }
   }
   
   private function removeFromDB($mtaID){
      $query = "DELETE FROM mta_samples where mta_id = :mtaID";
      $this->Dbase->ExecuteQuery($query, array("mtaID" => $mtaID));
      
      $query = "DELETE FROM mtas where id = :mtaID";
      $this->Dbase->ExecuteQuery($query, array("mtaID" => $mtaID));
   }
   
   private function generateSamplesSpreadsheet($sampleIDs){
      //$sampleIDs = explode(",", $_REQUEST['sample_ids']);
      
      if(count($sampleIDs) < 50000){//do not allow downloading of data greater than 50000 samples
         $implodedSIDs = implode(",", $sampleIDs);
         //get box owners
         
         $query = "SELECT a.box_id, b.name as keeper, b.email"
                 . " FROM ".Config::$config['azizi_db'].".boxes_def AS a"
                 . " INNER JOIN ".Config::$config['azizi_db'].".contacts AS b ON a.keeper = b.count";
         $boxKeepers = $this->Dbase->ExecuteQuery($query);
         //put the box keepers in a better data structure
         $keepers = array();
         foreach ($boxKeepers as $currKeeper){
            $keepers[$currKeeper['box_id']] = array("name" => $currKeeper['keeper'], "email" => $currKeeper['email']);
         }

         $query = "select b.*"
                 . " from ".Config::$config['azizi_db'].".samples as a"
                 . " inner join ".Config::$config['azizi_db'].".sample_types_def as b on a.sample_type = b.count"
                 . " where a.Longitude is not null and a.Longitude != '' and a.Latitude is not null and a.Latitude != ''"
                 . " group by a.sample_type";
         $tmpSTypes = $this->Dbase->ExecuteQuery($query);

         $sampleTypes = array();
         foreach($tmpSTypes as $currType){
            $sampleTypes[$currType['count']] = $currType['sample_type_name'];
         }
         //$this->Dbase->CreateLogEntry(print_r($sampleTypes, true), "fatal");

         $query = "select b.* from ".Config::$config['azizi_db'].".samples as a"
                 . " inner join ".Config::$config['azizi_db'].".modules_custom_values as b on a.Project = b.val_id"
                 . " where a.Longitude is not null and a.Longitude != '' and a.Latitude is not null and a.Latitude != ''"
                 . " group by a.Project";
         $tmpProjects = $this->Dbase->ExecuteQuery($query);

         $projects = array();
         foreach($tmpProjects as $currProject){
            $projects[$currProject['val_id']] = $currProject['value'];
         }
         //$this->Dbase->CreateLogEntry(print_r($projects, true), "fatal");

         $query = "select b.* from ".Config::$config['azizi_db'].".samples as a"
                 . " inner join ".Config::$config['azizi_db'].".organisms as b on a.org = b.org_id"
                 . " where a.Longitude is not null and a.Longitude != '' and a.Latitude is not null and a.Latitude != ''"
                 . " group by a.org";
         $tmpOrg = $this->Dbase->ExecuteQuery($query);
         $organisms = array();
         foreach ($tmpOrg as $currOrg){
            $organisms[$currOrg['org_id']] = $currOrg['org_name'];
         }
         //$this->Dbase->CreateLogEntry(print_r($organisms, true), "fatal");

         $oaQuery = "SELECT count, label, comments, date_created, sample_type, origin, org, box_id, Project, open_access"
                 . " FROM ".Config::$config['azizi_db'].".samples"
                 . " WHERE count in (" . $implodedSIDs . ") AND open_access = 1";

         $oaResult = $this->Dbase->ExecuteQuery($oaQuery);

         //get ids for Open acces samples
         //append projects to all open access samples
         $this->Dbase->CreateLogEntry("About to process open access samples ".  count($oaResult), "fatal");

         $oaIDs = array();
         for($oaIndex = 0; $oaIndex < count($oaResult); $oaIndex++){
            //$this->Dbase->CreateLogEntry("org = ".$oaResult[$oaIndex]['org'], "fatal");
            array_push($oaIDs, $oaResult[$oaIndex]['count']);
            unset($oaResult[$oaIndex]['count']);

            $oaResult[$oaIndex]['keeper'] = $keepers[$oaResult[$oaIndex]['box_id']]['name'];
            $oaResult[$oaIndex]['keeper_email'] = $keepers[$oaResult[$oaIndex]['box_id']]['email'];
            unset($oaResult[$oaIndex]['box_id']);

            $oaResult[$oaIndex]['sample_type'] = $sampleTypes[$oaResult[$oaIndex]['sample_type']];
            $oaResult[$oaIndex]['Project'] = $projects[$oaResult[$oaIndex]['Project']];
            $oaResult[$oaIndex]['org'] = $organisms[$oaResult[$oaIndex]['org']];
            $oaResult[$oaIndex]['open_access'] = "Yes";
         }

         $caQuery = "SELECT label, date_created, sample_type, Project, open_access, box_id, org"
                 . " FROM ".Config::$config['azizi_db'].".samples"
                 . " WHERE count in (" . $implodedSIDs . ") AND open_access = 0";

         $caResult = $this->Dbase->ExecuteQuery($caQuery);

         $this->Dbase->CreateLogEntry("About to process closed access samples ".  count($caResult), "fatal");
         for($caIndex = 0; $caIndex < count($caResult); $caIndex++){
            //$this->Dbase->CreateLogEntry("org = ".$caResult[$caIndex]['org'], "fatal");

            $caResult[$caIndex]['keeper'] = $keepers[$caResult[$caIndex]['box_id']]['name'];
            $caResult[$caIndex]['keeper_email'] = $keepers[$caResult[$caIndex]['box_id']]['email'];
            unset($caResult[$caIndex]['box_id']);

            $caResult[$caIndex]['sample_type'] = $sampleTypes[$caResult[$caIndex]['sample_type']];
            $caResult[$caIndex]['Project'] = $projects[$caResult[$caIndex]['Project']];
            $caResult[$caIndex]['org'] = $organisms[$caResult[$caIndex]['org']];
            $caResult[$caIndex]['open_access'] = "No";
         }

         require_once OPTIONS_COMMON_FOLDER_PATH.'PHPExcel/Classes/PHPExcel.php';

         $phpExcel = new PHPExcel();
         $phpExcel->getProperties()->setCreator($email);
         $phpExcel->getProperties()->setLastModifiedBy($email);
         $phpExcel->getProperties()->setTitle("ILRI Biorepository Samples");
         $phpExcel->getProperties()->setSubject("Created using Azizi Samples Visualizer");
         $phpExcel->getProperties()->setDescription("This Excel file has been generated using Azizi Samples Visualizer that utilizes the PHPExcel library on PHP. Azizi Samples Visualizer was created by Jason Rogena (j.rogena@cgiar.org)");

         //merge the open access and closed access data sets
         $samples = array();
         for($index = 0; $index < count($oaResult); $index++ ){
            $currSample = $oaResult[$index];

            $columns = array_keys($currSample);

            for($cIndex = 0; $cIndex < count($columns); $cIndex++){
               if(!isset($samples[$columns[$cIndex]])){
                  $samples[$columns[$cIndex]] = array();
               }

               $samples[$columns[$cIndex]][$index] = $currSample[$columns[$cIndex]];
            }
         }

         $noOASamples = count($oaResult);

         $this->Dbase->CreateLogEntry("Samples after adding Open Access samples ". count($samples), "fatal");

         for($index = 0; $index < count($caResult); $index++ ){
            $currSample = $caResult[$index];

            $columns = array_keys($currSample);

            for($cIndex = 0; $cIndex < count($columns); $cIndex++){
               if(!isset($samples[$columns[$cIndex]])){
                  $samples[$columns[$cIndex]] = array();
               }

               $samples[$columns[$cIndex]][$index + $noOASamples] = $currSample[$columns[$cIndex]];
            }
         }

         $columnHeadings = array_keys($samples);

         //sort the column headings
         $sorted = array('label', 'open_access', 'sample_type', 'org', 'date_created', 'Project', 'keeper', 'keeper_email', 'origin');
         $commentsExisted = false;
         for($index = 0; $index < count($columnHeadings); $index++){
            if(array_search($columnHeadings[$index], $sorted) === false){
               if($columnHeadings[$index] != 'comments'){
                  array_push($sorted, $columnHeadings[$index]);
               }
               else {
                  $commentsExisted = true;
               }
            }
         }

         /*if($commentsExisted == true){
            array_push($sorted, "comments");
         }*/

         $this->Dbase->CreateLogEntry(print_r($sorted, true), "fatal");
         $this->Dbase->CreateLogEntry(print_r($columnHeadings, true), "fatal");

         for($index = 0; $index < count($sorted); $index++){
            if(array_search($sorted[$index], $columnHeadings) === false){
               unset($sorted[$index]);
               //$index--;
            }
         }

         $columnHeadings = $sorted;

         $trans = array(
             "org" => "Organism",
             "date_created" => "Date Collected",
             "sample_type" => "Sample Type",
             "origin" => "Origin",
             "open_access" => "Open Access",
             "keeper" => "Contact Person",
             "keeper_email" => "C.P Email"
         );

         for($index = 0; $index < count($columnHeadings); $index++){
            $cIndex = PHPExcel_Cell::stringFromColumnIndex($index);
            $phpExcel->getActiveSheet()->setTitle("Samples");
            $this->Dbase->CreateLogEntry($cIndex." " .$columnHeadings[$index], "fatal");

            $columnName = $columnHeadings[$index];
            if(isset($trans[$columnName])) $columnName = $trans[$columnName];

            $phpExcel->getActiveSheet()->setCellValue($cIndex."1", $columnName);

            $phpExcel->getActiveSheet()->getStyle($cIndex."1")->getFont()->setBold(TRUE);
            $phpExcel->getActiveSheet()->getColumnDimension($cIndex)->setAutoSize(true);

            $columnSamples = $samples[$columnHeadings[$index]];
            for($sIndex = 0; $sIndex < count($columnSamples); $sIndex++){
               $rIndex = $sIndex + 2;
               $phpExcel->getActiveSheet()->setCellValue($cIndex.$rIndex, $columnSamples[$sIndex]);
            }
         }

         $this->Dbase->CreateLogEntry("Getting the tests", "fatal");

         $oatQuery = "SELECT a.date as Date, a.sample_id, b.label as Sample, c.option_name as Result, d.label as Test"
                 . " FROM ".Config::$config['azizi_db'].".processes as a"
                 . " INNER JOIN ".Config::$config['azizi_db'].".samples as b on b.count=a.sample_id"
                 . " INNER JOIN ".Config::$config['azizi_db'].".modules_options as c on a.status = c.option_id"
                 . " INNER JOIN ".Config::$config['azizi_db'].".process_type_def as d on a.process_type=d.count";

         $oatResults = $this->Dbase->ExecuteQuery($oatQuery);

         $testHeadings = array();
         $good = 0;
         for($index = 0; $index < count($oatResults); $index++){
            $sampleId = $oatResults[$index]['sample_id'];
            unset($oatResults[$index]['sample_id']);
            if(array_search($sampleId, $oaIDs) === false){
               unset($oatResults[$index]);
               //$index--;
            }
            else {
               $good++;
               if(count($testHeadings) == 0){
                  $testHeadings = $oatResults[$index];
               }
            }
         }

         $this->Dbase->CreateLogEntry("Pruned all the chuff and left with ".$good, "fatal");
         $this->Dbase->CreateLogEntry(print_r($testHeadings, true), "fatal");

         if(count($testHeadings) > 0){//means that there is at least one relevant test left         
            $phpExcel->setActiveSheetIndex(1);
            $phpExcel->getActiveSheet()->setTitle("Tests");

            for($index = 0; $index < count($testHeadings); $index++){
               $cIndex = PHPExcel_Cell::stringFromColumnIndex($index);

               $phpExcel->getActiveSheet()->setCellValue($cIndex."1", $testHeadings[$index]);

               $phpExcel->getActiveSheet()->getStyle($cIndex."1")->getFont()->setBold(TRUE);
               $phpExcel->getActiveSheet()->getColumnDimension($cIndex)->setAutoSize(true);
            }

            $this->Dbase->CreateLogEntry("Done adding headings. now adding results", "fatal");

            for($tIndex = 0; $tIndex < count($oatResults); $tIndex++){
               if(is_array($oatResults[$tIndex])){//done to exclude all unset rows
                  $this->Dbase->CreateLogEntry($tIndex, "fatal");
                  for($index = 0; $index < count($testHeadings); $index++){
                     $rIndex = $tIndex + 2;
                     $cIndex = PHPExcel_Cell::stringFromColumnIndex($index);

                     $phpExcel->getActiveSheet()->setCellValue($cIndex.$rIndex, $oatResults[$tIndex][$testHeadings[$index]]);
                  }
               }
            }
         }

         $tmpDir = "tmp";
         if(!file_exists($tmpDir)){
            mkdir($tmpDir, 0755);//everything for owner, read & exec for everybody else
         }

         $filename = $tmpDir ."/MTA_ILRI_". str_replace(" ", "_", $_REQUEST['org']) . "_samples.xlsx";

         $this->Dbase->CreateLogEntry("Saving xls file", "fatal");
         $objWriter = new PHPExcel_Writer_Excel2007($phpExcel);
         $objWriter->save($filename);
         $this->Dbase->CreateLogEntry("Done creating excel file, about to send it to the user", "fatal");

         return $filename;
      }
      
      return null;
   }
   
   private function generateMTADocument($sampleIDs){
      //TODO: change $_REQUEST to $_POST
      
      $piName = $_REQUEST['pi_name'];
      $piEmail = $_REQUEST['pi_email'];
      $researchTitle = $_REQUEST['research_title'];//title of the research being conducted
      $org = $_REQUEST['org'];
      
      $materialRequired = $_REQUEST['material'];
      $quantity = count($sampleIDs);
      $format = $_REQUEST['format'];
      $storageSafety = $_REQUEST['storage_safety'];
      $assocData = $_REQUEST['assoc_data'];
      
      /*
       * Template variables:
       *    PI_NAME
       *    PI_EMAIL
       *    RESEARCH_TITLE
       *    REQUESTING_ORG
       *    MATERIAL_REQUIRED
       *    MATERIAL_QUNTITY
       *    MATERIAL_FORMAT
       *    STORAGE_SAFETY
       *    ASSOCIATED_DATA
       *    AZIZI_MANAGER_NAME
       *    AZIZI_MANAGER_EMAIL
       *    ILRI_SCIENTIST_NAME
       *    ILRI_SCIENTIST_TITLE
       *    ILRI_SCIENTIST_EMAIL
       *    DATE_GENERATED
       */
      
      $templateLocation = "templates/MTA-ILRI-Template.docx";
      require_once OPTIONS_COMMON_FOLDER_PATH."/PHPWord/PHPWord.php";
      $PHPWord = new PHPWord();
      $mtaTemplate = $PHPWord->loadTemplate($templateLocation);
      
      $mtaTemplate->setValue('PI_NAME', utf8_decode($piName));
      $mtaTemplate->setValue("PI_EMAIL", $piEmail);
      $mtaTemplate->setValue("RESEARCH_TITLE", $researchTitle);
      $mtaTemplate->setValue("REQUESTING_ORG", $org);
      $mtaTemplate->setValue("MATERIAL_REQUIRED", $materialRequired);
      $mtaTemplate->setValue("MATERIAL_QUANTITY", $quantity);
      $mtaTemplate->setValue("MATERIAL_FORMAT", $format);
      $mtaTemplate->setValue("STORAGE_SAFETY", $storageSafety);
      $mtaTemplate->setValue("ASSOCIATED_DATA", $assocData);
      $mtaTemplate->setValue("AZIZI_MANAGER_NAME", "Absolomn Kihara");
      $mtaTemplate->setValue("AZIZI_MANAGER_EMAIL", Config::$limsManager);
      $mtaTemplate->setValue("ILRI_SCIENTIST_NAME", "Steve Kemp");
      $mtaTemplate->setValue("ILRI_SCIENTIST_TITLE", "Deputy Director General");
      $mtaTemplate->setValue("ILRI_SCIENTIST_EMAIL", "s.kemp@cgiar.org");
      $mtaTemplate->setValue("DATE_GENERATED", date('jS') ." day of ". date('F, Y'));//2nd day of March, 2014
      
      if(!file_exists("tmp")){
         mkdir("tmp", 0755);
      }
      
      $fileName = "tmp/MTA_ILRI_".str_replace(" ", "_", $org).".docx";
      $mtaTemplate->save($fileName);
      
      /*header("Content-Disposition: attachment; filename=$fileName");
      header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
      header('Content-Length: ' . filesize("tmp/".$fileName));
      header('Content-Transfer-Encoding: binary');
      header('Cache-Control: must-revalidate');
      header('Pragma: public');
      ob_clean();
      flush();
      readfile("tmp/".$fileName);
      //unlink("tmp/".$fileName);
      die();*/
      
      return $fileName;
   }
   
   private function sendMTADocuments($mtaID, $mtaDocument, $samplesDocument){
      //send email to biorepository manager with MTA and samples
      $emailSubject = "MTA #".$mtaID.": ".$_REQUEST['org']." - ".$_REQUEST['pi_name']." Samples";
      $emailBody = "Find attached the draft MTA between ILRI and ".$_REQUEST['org']." and a spreadsheet with the requested samples. The request was made by ".$_REQUEST['pi_name']." (".$_REQUEST['pi_email'].")";
      
      $repositoryManagerEmail = Config::$managerEmail;
      
      shell_exec('echo "'.$emailBody.'"|'.Config::$config['mutt_bin'].' -a '.$mtaDocument.' '.$samplesDocument.' -F '.Config::$config['mutt_config'].' -s "'.$emailSubject.'" -- '.$repositoryManagerEmail);
      
      //send email to ilri scientist, legal, biorepository manager, pi
      $emailSubject = "MTA between ILRI and ".$_REQUEST['org'];
      $emailBody = "Find attached the draft MTA between ILRI and ".$_REQUEST['org'].". The material request was made by ".$_REQUEST['pi_name']." (CC'd herein). \n"
              . "Please note that the MTA was generated on-the-fly, by a computer. It may therefore contain mistakes. \n"
              . "This MTA has been labeled #".$mtaID." \n\n"
              . "Regards, \n"
              . "Azizi Birepository \n";
      
      $scientistEmail = "j.rogena@cgiar.org";
      $legalEmail = "jasonrogena@gmail.com";
      $cc= array($repositoryManagerEmail, $scientistEmail, $_REQUEST['pi_email']);
      
      shell_exec('echo "'.$emailBody.'"|'.Config::$config['mutt_bin'].' -a '.$mtaDocument.' -c '.implode(",", $cc).' -F '.Config::$config['mutt_config'].' -s "'.$emailSubject.'" -- '.$legalEmail);
   }
}
?>

