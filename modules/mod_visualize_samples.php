<?php
class VisualizeSamples {
   private $Dbase;
   
   public function __construct($Dbase) {
      /*require_once 'repository_config';
      $logSettings = Config::$logSettings;
      $logSettings['workingDir'] = "../";
      
      require_once OPTIONS_COMMON_FOLDER_PATH . 'dbmodules/mod_objectbased_dbase_v1.0.php';
      require_once OPTIONS_COMMON_FOLDER_PATH . 'mod_general_v0.6.php';

      $this->Dbase = new DBase('mysql');
      $this->Dbase->InitializeConnection();

      if($this->Dbase->dbcon->connect_error || (isset($this->Dbase->dbcon->errno) && $this->Dbase->dbcon->errno!=0)) {

         die('Something wicked happened when connecting to the dbase.');
      }

      $this->Dbase->InitializeLogs($logSettings);
      
      Config::$config['user'] = Config::$config['rw_user']; Config::$config['pass'] = Config::$config['rw_pass'];
      
      $this->Dbase->InitializeConnection();
      
      define(OPTIONS_REQUESTED_SUB_MODULE, $_GET['do']);
      define(OPTIONS_REQUESTED_ACTION, $_GET['action']);*/
      global $Repository;
      $this->Dbase = $Dbase;
      $this->Dbase->CreateLogEntry("Trying to reinitialize db connection", "info");
      Config::$config['user'] = Config::$config['rw_user']; Config::$config['pass'] = Config::$config['rw_pass'];
      
      $this->Dbase->InitializeConnection(Config::$config);
      if(is_null($this->Dbase->dbcon)) {
         ob_start();
         $Repository->LoginPage(OPTIONS_MSSG_DB_CON_ERROR);
         $Repository->errorPage = ob_get_contents();
         ob_end_clean();
         return;
      }
   }
   
   
   public function trafficController(){
      if(OPTIONS_REQUESTED_SUB_MODULE == 'ajax'){
         if(OPTIONS_REQUESTED_ACTION == 'all_sample_data'){
            $this->getAllSamples();
         }
         else if(OPTIONS_REQUESTED_ACTION == 'all_project_data'){
            $this->getAllProjects();
         }
         else if(OPTIONS_REQUESTED_ACTION == 'all_organism_data'){
            $this->getAllOrganisms();
         }
         else if(OPTIONS_REQUESTED_ACTION == 'all_sample_type_data'){
            $this->getAllSampleTypes();
         }
         else if(OPTIONS_REQUESTED_ACTION == 'all_origin_data'){
            $this->getAllOrigins();
         }
         else if(OPTIONS_REQUESTED_ACTION == 'send_sample_data'){
            $this->sendSampleData();
         }
      }
      else if(OPTIONS_REQUESTED_SUB_MODULE == '' || OPTIONS_REQUESTED_SUB_MODULE == 'home'){
         $this->home();
      }
   }
   
   private function getAllSamples(){
      $cacheFile = "/tmp/sample_data.json";
      
      if(file_exists($cacheFile)){//check if file exists
         $this->Dbase->CreateLogEntry("Samples cache file exists","info");
         //check when the file was last modified
         if(filemtime($cacheFile) !== false && (time() - filemtime($cacheFile)) < 7200){//file created less than 1 hour ago
            $this->Dbase->CreateLogEntry("Cache file is ".(time() - filemtime($cacheFile))." seconds old. Using it","info");
            $json = file_get_contents($cacheFile);
            echo $json;
            return;
         }
         else {//make sure file is deleted
            $this->Dbase->CreateLogEntry("Cache file is too old ".(time() - filemtime($cacheFile))." seconds. Rolling back to using MySQL","info");
            unlink($cacheFile);
         }
      }
      else {
         $this->Dbase->CreateLogEntry("Cache file for sample data does not exist. Fetching data from MySQL","info");
      }
      
      //if we've come this far, we have to get the data from MySQl
      
      $query = "SELECT count, label, date_created, sample_type, origin, org, box_id, VisitDate, Longitude, Latitude, Elisa_Results, Project"
               . " FROM ".Config::$config['azizi_db'].".samples"
               . " WHERE Longitude IS NOT null AND Longitude != '' AND Latitude IS NOT null AND Latitude != ''";

       $samples = $this->Dbase->ExecuteQuery($query);

       $query = "SELECT count(`count`) AS orphans"
               . " FROM ".Config::$config['azizi_db'].".samples"
               . " WHERE Longitude IS null OR Longitude = '' OR Latitude IS null OR Latitude = ''";
       $tmp = $this->Dbase->ExecuteQuery($query);

       $data = array();
       if($samples != 1 && $tmp != 1){
          $data['samples'] = $samples;
          $data['orphans'] = $tmp[0]['orphans'];
          $data['error'] = 0;
       }
       else{
          $data['samples'] = array();
          $data['orphans'] = 0;
          $data['error'] = 1;
       }
       
       $query = "SELECT a.sample_id, b.label as test,c.option_name as result"
               . " FROM ".Config::$config['azizi_db'].".processes as a"
               . " INNER JOIN ".Config::$config['azizi_db'].".process_type_def as b ON a.process_type = b.count"
               . " INNER JOIN ".Config::$config['azizi_db'].".modules_options as c on a.status = c.option_id";
       
       $unformattedTests = $this->Dbase->ExecuteQuery($query);
       $allTests = array();
       foreach($unformattedTests as $currTest){
          if(!isset($allTests[$currTest['sample_id']])){
             $allTests[$currTest['sample_id']] = array();
          }
          
          $sID = $currTest['sample_id'];
          unset($currTest['sample_id']);
          array_push($allTests[$sID], $currTest);
       }
       
       $testTypes = array();//array holding the different types of tests done
       $resultTypes = array();
       for($index = 0; $index < count($data['samples']); $index++){
          //clean longitude and latitude
          $data['samples'][$index]['Longitude'] = $this->convertLongitude($data['samples'][$index]['Longitude']);
          $data['samples'][$index]['Latitude'] = $this->convertLatitude($data['samples'][$index]['Latitude']);
          
          //clean the elisa results column and get the relevant data
          /*$test = "";
          $result = "";
          if($data['samples'][$index]['Elisa_Results'] != null && strlen($data['samples'][$index]['Elisa_Results']) > 0){
             $tests = array();
             preg_match_all("/name\s*=\s*([^,]*)\s*,\s*status\s*=\s*([a-z0-9]*)/i", $data['samples'][$index]['Elisa_Results'], $tests);
             
             if(count($tests) == 3){
                if(isset($tests[1][0])) {
                   $test = $tests[1][0];
                   if(array_search($test, $testTypes) === FALSE){
                      array_push($testTypes, $test);
                   }
                }
                
                if(isset($tests[2][0])) {
                   $result = $tests[2][0];
                   //$this->Dbase->CreateLogEntry($tests[2][0], "info");
                   if(array_search($result, $resultTypes) === FALSE){
                      array_push($resultTypes, $result);
                   }
                }
             }
          }*/
          
          $sID = $data['samples'][$index]['count'];
          if(isset($allTests[$sID])){
             
             $sampleTests = $allTests[$sID];
             foreach($sampleTests as $currTest){
                if(array_search($currTest['test'], $testTypes) === false){
                   array_push($testTypes, $currTest['test']);
                }
                
                if(array_search($currTest['result'], $resultTypes) === false){
                   array_push($resultTypes, $currTest['result']);
                }
             }
             
             $data['samples'][$index]['tests'] = $sampleTests;
          }
          else {
             $data['samples'][$index]['tests'] = array();
          }
          
          
          //if($index%1000 === 0) $this->Dbase->CreateLogEntry( $index,"fatal");
       }
       
       $data['tests'] = array('types' => $testTypes, 'results' => $resultTypes);
       $cacheJson = json_encode($data);
       
       file_put_contents($cacheFile, $cacheJson);
       
       echo $cacheJson;
   }
   
   private function getAllProjects() {
      $query = "select b.* from ".Config::$config['azizi_db'].".samples as a"
              . " inner join ".Config::$config['azizi_db'].".modules_custom_values as b on a.Project = b.val_id"
              . " where a.Longitude is not null and a.Longitude != '' and a.Latitude is not null and a.Latitude != ''"
              . " group by a.Project";
      $projects = $this->Dbase->ExecuteQuery($query);
      
      $data = array();
      if($projects != 1){
         
         for($index=0; $index < count($projects); $index++){
            $projects[$index]['value'] = preg_replace("/[^a-z0-9\s]/i", "", $projects[$index]['value']);
         }
         
         $data['projects'] = $projects;
         $data['error'] = 0;
      }
      else {
         $data['projects'] = array();
         $data['error'] = 1;
      }
      
      echo json_encode($data);
   }
   
   private function getAllSampleTypes() {
      $query = "select b.*"
              . " from ".Config::$config['azizi_db'].".samples as a"
              . " inner join ".Config::$config['azizi_db'].".sample_types_def as b on a.sample_type = b.count"
              . " where a.Longitude is not null and a.Longitude != '' and a.Latitude is not null and a.Latitude != ''"
              . " group by a.sample_type";
      $sampleTypes = $this->Dbase->ExecuteQuery($query);
      
      $data = array();
      if($sampleTypes != 1){
         $data['sample_types'] = $sampleTypes;
         $data['error'] = 0;
      }
      else {
         $data['sample_types'] = array();
         $data['error'] = 1;
      }
      
      echo json_encode($data);
   }
   
   private function getAllOrganisms() {
      $query = "select b.* from ".Config::$config['azizi_db'].".samples as a"
              . " inner join ".Config::$config['azizi_db'].".organisms as b on a.org = b.org_id"
              . " where a.Longitude is not null and a.Longitude != '' and a.Latitude is not null and a.Latitude != ''"
              . " group by a.org";
      $organisms = $this->Dbase->ExecuteQuery($query);
      
      $data = array();
      if($organisms != 1) {
         $data['organisms'] = $organisms;
         $data['error'] = 0;
      }
      else {
         $data['organisms'] = array();
         $data['error'] = 1;
      }
      
      echo json_encode($data);
   }
   
   private function getAllOrigins() {
      $query = "select origin from ".Config::$config['azizi_db'].".samples group by origin";
      $origins = $this->Dbase->ExecuteQuery($query);
      
      $data = array();
      if($origins != 1){
         $data['origins'] = $origins;
         $data['error'] = 0;
      }
      else {
         $data['origins'] = array();
         $data['error'] = 1;
      }
      
      echo json_encode($data);
   }
   
   private function sendSampleData() {
      $this->Dbase->CreateLogEntry("sendSampleData called", "fatal");
      
      $sampleIDs = $_POST['sampleIDs'];
      
      if(count($sampleIDs) < 50000){//do not allow downloading of data greater than 50000 samples
         $implodedSIDs = implode(",", $sampleIDs);
      
         //$this->Dbase->CreateLogEntry($implodedSIDs, "fatal");

         $email = $_POST['email'];

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

         require_once OPTIONS_COMMON_FOLDER_PATH.'bower/PHPExcel/Classes/PHPExcel.php';

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

         $filename = $tmpDir ."/". time() . ".xlsx";

         $this->Dbase->CreateLogEntry("Saving xls file", "fatal");
         $objWriter = new PHPExcel_Writer_Excel2007($phpExcel);
         $objWriter->save($filename);
         $this->Dbase->CreateLogEntry("Done creating excel file, about to send it to the user", "fatal");

         $this->sendEmail($email, $filename);
      }
   }
   
   private function sendEmail($email, $filename){
      $this->Dbase->CreateLogEntry('sending email to '.$email ." with the attachment ".$filename, "fatal");
      
      $emailSubject = "ILRI Bio-Repository Samples";
      $message = "The data on samples you requested for is attached to this email. To get more information on these and more samples, feel free to contact ILRI's Bio-Repository Manager";
      
      shell_exec('echo "'.$message.'"|'.Config::$config['mutt_bin'].' -a '.$filename.' -F '.Config::$config['mutt_config'].' -s "'.$emailSubject.'" -- '.$email);
   }
   
   private function convertLongitude($longitude) {
      
      if(strlen($longitude) > 0) {
         $hemInt = 0;//default to 0 so that if not E or W you will end up with 0 longitude
         if(preg_match("/[a-z]/i", $longitude) == 1){
            
            $hem = strtoupper(substr($longitude, -1));
            
            if($hem =="E" ) {
               $hemInt = 1;
            }
            else if($hem == "W") {
               $hemInt = -1;
            }
         }
         else {
            $hemInt = 1;
         }
         
         if($hemInt != 0) {
            $longitudeFloat = floatval(str_replace($hem, "", $longitude));

            return $hemInt * $longitudeFloat;
         }
         else {
            $this->Dbase->CreateLogEntry("longitude with problems = ".$longitude, "info");
         }
      }
      
      $this->Dbase->CreateLogEntry("longitude with problems = ".$longitude, "info");
      return null;
   }
   
   private function convertLatitude($latitude) {
      return floatval($latitude);
   }
   
   private function home(){
?>
<link rel="stylesheet" href="<?php echo OPTIONS_COMMON_FOLDER_PATH;?>leaflet/leaflet.css" />
<script src="<?php echo OPTIONS_COMMON_FOLDER_PATH;?>leaflet/leaflet.js"></script>
<script type='text/javascript' src='<?php echo OPTIONS_COMMON_FOLDER_PATH;?>bower/jquery/jquery.min.js' /></script>
<script src='js/visualize_samples.js'></script>
<script src='<?php echo OPTIONS_COMMON_FOLDER_PATH;?>leaflet/ol.js'></script>
<script src="<?php echo OPTIONS_COMMON_FOLDER_PATH;?>bower/dygraphs/dygraph-combined.js"></script>
<link href="css/repository.css" rel="stylesheet" type="text/css" />
<link href='http://fonts.googleapis.com/css?family=Roboto' rel='stylesheet' type='text/css' />
<link rel='stylesheet' type='text/css' href='<?php echo OPTIONS_COMMON_FOLDER_PATH ?>bower/jquery-ui/themes/smoothness/jquery-ui.min.css' />
<script type='text/javascript' src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>bower/jquery-ui/jquery-ui.min.js" /></script>
<script src='<?php echo OPTIONS_COMMON_FOLDER_PATH;?>leaflet/leaflet-heat.js'></script>
<div id="map"></div>
<div id="samples_timeline"></div>
<div id="loading_box">Loading</div>
<div id="play_button"></div>
<div id="stop_button"></div>
<div id="play_slider"></div>
<div id="samples_heads_up"><div id="sample_count"></div><div id="samples_download_btn"></div></div>
<div id="project_container" class="filter_container">
   <div id="project_label" class="filter_label">Projects</div>
   <div id="project_toggle" class="filter_toggle"></div>
   <div style="display: none;"><input id="project_sel_all" type="checkbox" checked/>Select all<br /></div>
   <div id="project_list" class="filter_list"></div>   
</div>
<div id="organism_container" class="filter_container">
   <div id="organism_label" class="filter_label">Organisms</div>
   <div id="organism_toggle" class="filter_toggle"></div>
   <div style="display: none;"><input id="organism_sel_all" type="checkbox" checked/>Select all<br /></div>
   <div id="organism_list" class="filter_list"></div>   
</div>
<div id="sample_types_container" class="filter_container">
   <div id="sample_types_label" class="filter_label">Sample Types</div>
   <div id="sample_types_toggle" class="filter_toggle"></div>
   <div style="display: none;"><input id="sample_types_sel_all" type="checkbox" checked/>Select all<br /></div>
   <div id="sample_types_list" class="filter_list"></div>   
</div>
<div id="test_container" class="filter_container">
   <div id="test_label" class="filter_label">Tests done</div>
   <div id="test_toggle" class="filter_toggle"></div>
   <div style="display: none;"><input id="test_sel_all" type="checkbox"/>Select all<br /></div>
   <div id="test_list" class="filter_list"></div>   
</div>
<div id="result_container" class="filter_container">
   <div id="result_label" class="filter_label">Test Results</div>
   <div id="result_toggle" class="filter_toggle"></div>
   <div style="display: none;"><input id="result_sel_all" type="checkbox"/>Select all<br /></div>
   <div id="result_list" class="filter_list"></div>   
</div>
<div id="email_dialog">
   <div id="email_dialog_toggle"></div>
   <p style="margin-top: 1rem;">The data about to be sent to you is protected by the <a href="http://www.cgiar.org/resources/open/" target="_blank">CGIAR's Open Access Policy</a>. You ought to have read this policy before clicking 'Send'. If you want the samples moved to your Organisation, fill a <a href="#" id="mta_link">Material Transfer Agreement</a> instead.</p>
   <input type="email" id="user_email" placeholder="Enter your email address" /> <button id="send_button">Send</button>
</div>
<div id="mta_dialog">
   <div id="mta_dialog_toggle"></div>
   <div style="margin-bottom: 15px; font-size: 20px;">Material Transfer Agreement</div>
   <div class="form-group" style="margin-bottom:10px; width:auto;">
      <div style="width: 200px; margin-left:50px; display:inline-block;"><label for="mta_pi_name">Principal Investigator</label></div>
      <div style="width: 100px; display:inline-block;"><input id="mta_pi_name" type="text" /></div>
   </div>
   <div class="form-group" style="margin-bottom:10px; width:auto;">
      <div style="width: 200px; margin-left:50px; display:inline-block;"><label for="mta_pi_email">PI's Email Address</label></div>
      <div style="width: 100px; display:inline-block;"><input id="mta_pi_email" type="text" /></div>
   </div>
   <div class="form-group" style="margin-bottom:10px; width:auto;">
      <div style="width: 200px; margin-left:50px; display:inline-block;"><label for="mta_research_title">Research Title</label></div>
      <div style="width: 100px; display:inline-block;"><input id="mta_research_title" type="text" /></div>
   </div>
   <div class="form-group" style="margin-bottom:10px; width:auto;">
      <div style="width: 200px; margin-left:50px; display:inline-block;"><label for="mta_org">Organisation</label></div>
      <div style="width: 100px; display:inline-block;"><input id="mta_org" type="text" /></div>
   </div>
   <div class="form-group" style="margin-bottom:10px; width:auto;">
      <div style="width: 200px; margin-left:50px; display:inline-block;"><label for="mta_material">Sample Material</label></div>
      <div style="width: 100px; display:inline-block;"><input id="mta_material" type="text" placeholder="e.g serum" /></div>
   </div>
   <div class="form-group" style="margin-bottom:10px; width:auto;">
      <div style="width: 200px; margin-left:50px; display:inline-block;"><label for="mta_format">Format</label></div>
      <div style="width: 100px; display:inline-block;"><input id="mta_format" type="text" /></div>
   </div>
   <div class="form-group" style="margin-bottom:10px; width:auto;">
      <div style="width: 200px; margin-left:50px; display:inline-block;"><label for="mta_storage_safety">Handling, Storage & Safety</label></div>
      <div style="width: 100px; display:inline-block;"><input id="mta_storage_safety" type="text" /></div>
   </div>
   <div class="form-group" style="margin-bottom:10px; width:auto;">
      <div style="width: 200px; margin-left:50px; display:inline-block;"><label for="mta_assoc_data">Accompanying data</label></div>
      <div style="width: 100px; display:inline-block;"><input id="mta_assoc_data" type="text" value="associated metadata" /></div>
   </div>
   <div class="center">
      <button id="mta_submit_btn">Send Request</button>
   </div>
</div>
<div id="instructions_dialog" style="z-index: 4;">
   <div id="instructions_dialog_toggle"></div>
   <div>
      You can download data for up to 50,000 samples. Filter down the number of samples you want 50,000 or less then click the download button on the top right corner. You can also fill in a Material Transfer Agreement if you want the samples transfered to your organisation.
   </div>
</div>
<div id="cloak" style="display: none; position: absolute; background: #3d3d3d; opacity: 0.5; z-index: 3; width: 100%; height: 100%;">
</div>
<script>
   var visSamples = new VisualizeSamples();
</script>
<?php
   }
}
?>

