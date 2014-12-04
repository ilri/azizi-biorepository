<?php

/* 
 * This module is responsible for handling recharges for the following items:
 *    - inventory (including cryoboxes)
 *    - liquid nitrogen
 *    - storage space
 *    - labels
 */
class Recharges{
   
   private $Dbase;
   
   public function __construct($Dbase){
      $this->Dbase = $Dbase;
   }
   
   /**
    * This function determines what user wants to do
    */
   public function trafficController(){
      
      if(OPTIONS_REQUESTED_SUB_MODULE == '' || OPTIONS_REQUESTED_SUB_MODULE == 'home'){
         $this->showHomePage();
      }
      else if(OPTIONS_REQUESTED_SUB_MODULE == 'space'){
         if(OPTIONS_REQUESTED_ACTION == ''){
            $this->showStorageSpacePage();
         }
         else if(OPTIONS_REQUESTED_ACTION == 'get_recharges') {
            $this->getPendingSpaceRecharges();
         }
         else if(OPTIONS_REQUESTED_ACTION == 'submit_recharge'){
            $this->submitSpaceRecharge();
         }
      }
      else if(OPTIONS_REQUESTED_SUB_MODULE == 'inventory'){
         if(OPTIONS_REQUESTED_ACTION == ''){
            $this->showInventoryPage();
         }
         else if(OPTIONS_REQUESTED_ACTION == 'get_recharges') {
            $this->getPendingInventoryRecharges();
         }
         else if(OPTIONS_REQUESTED_ACTION == 'submit_recharge'){
            $this->submitInventoryRecharge();
         }
      }
      else if(OPTIONS_REQUESTED_SUB_MODULE == 'ln2'){
         if(OPTIONS_REQUESTED_ACTION == ''){
            $this->showLN2Page();
         }
         else if(OPTIONS_REQUESTED_ACTION == 'get_recharges'){
            $this->getPendingLN2Recharges();
         }
         else if(OPTIONS_REQUESTED_ACTION == 'submit_recharge'){
            $this->submitLN2Recharge();
         }
      }
      else if(OPTIONS_REQUESTED_SUB_MODULE == 'labels'){
         if(OPTIONS_REQUESTED_ACTION == ''){
            $this->showLabelsPage();
         }
         else if(OPTIONS_REQUESTED_ACTION == 'get_recharges'){
            $this->getPendingLabelsRecharges();
         }
         else if(OPTIONS_REQUESTED_ACTION == 'submit_recharge'){
            $this->submitLabelsRecharge();
         }
      }
   }
   
   /**
    * This function renders the home page
    * 
    * @param type $addInfo Info you want displayed to the user
    */
   public function showHomePage($addInfo = ''){
      $addInfo = ($addInfo != '') ? "<div id='addinfo'>$addInfo</div>" : '';
      ?>
<div id='home'>
   <?php echo $addInfo?>
   <h3 class="center">Recharging</h3>
   <div class="user_options">
      <ul>
         <li><a href="?page=recharges&do=inventory">Biorepository Resources</a></li>
         <li><a href='?page=recharges&do=ln2'>Liquid Nitrogen</a></li>
         <li><a href="?page=recharges&do=labels">Printed Labels</a></li>
         <li><a href='?page=recharges&do=space'>Storage Space</a></li>
      </ul>
   </div>
</div>
<script>
   $('#whoisme .back').html('<a href=\'?page=home\'>Back</a>');//back link
   $(document).ready( function() {
      BoxStorage.getTankData(false);//get tank data from the server and cache to cookie
   });
</script>
      <?php
   }
   
   /**
    * This function renders the Recharge Storage space page to the user
    * 
    * @param type $addInfo Info you want displayed to the user
    */
   public function showStorageSpacePage($addInfo = ''){
      Repository::jqGridFiles();//Really important if you want jqx to load
      
      $periodStarting = $this->getSSpaceNextPeriodStarting();
      $psTimestamp = 0;
      if($periodStarting != null){
         $psTimestamp = $periodStarting->getTimestamp();//gets timesamp in seconds, you'll need to convert this to milliseconds if javascript were to use it
         $psTimestamp = $psTimestamp * 1000;//converted
      }
?>
<script type="text/javascript" src="js/recharges.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.pager.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxdropdownlist.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.columnsresize.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.sort.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxcheckbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.edit.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.aggregates.js"></script>
<script type='text/javascript' src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jquery.ui/js/jquery-ui.min.js" /></script> <!-- used by autocomplete for the boxes label text field -->
<link rel='stylesheet' type='text/css' href='<?php echo OPTIONS_COMMON_FOLDER_PATH ?>jquery.ui/css/smoothness/jquery-ui.css' />
<div id="box_storage">
   <h3 class="center">Recharge Storage Space</h3>
   <div id="recharge_div">
      <!--legend>Box Information</legend-->
      <div class="form-group left-align">
         <label for="period_starting">Period starting</label>
         <input id="period_starting" name="period_starting" disabled="disabled" class="input-large" />
      </div>
      <div class="form-group left-align">
         <label for="period_ending">Period ending</label>
         <select id="period_ending" name="period_ending" class="input-large"></select>
      </div>
      <div class="form-group left-align">
         <label for="price">Price per box per year (USD)</label>
         <input id="price" name="price" class="input-large" />
      </div>
   </div>
   <div id="space_recharge_table" style="margin-top: 20px;margin-left: 8px;margin-bottom: 20px;"></div>
   <div class="center"><button type="button" class="btn btn-primary" id="recharge_btn">Recharge</button></div>
</div>
<div id="recharge_dialog" class="repo_dialog">
   <div id="recharge_dialog_close" class="repo_dialog_close"></div>
   <div>Are you sure you want to complete the recharge? Once done, your changes will be hard to undo.</div>
   <div class="center"><button type="button" class="btn btn-danger" id="confirm_recharge_btn">Recharge</button></div>
</div>
<script type="text/javascript">
   $(document).ready(function(){
      var recharges = new Recharges(MODE_STORAGE);
      recharges.setStorageRechargePeriods(<?php echo $psTimestamp; ?>);
      
      $('#whoisme .back').html('<a href=\'?page=home\'>Home</a> | <a href=\'?page=recharges\'>Back</a>');//back link
   });
</script>
      <?php
   }
   
   public function showInventoryPage($addInfo = ''){
      
      Repository::jqGridFiles();//Really important if you want jqx to load
?>
<script type="text/javascript" src="js/recharges.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.pager.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxdropdownlist.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.columnsresize.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.sort.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxcheckbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.edit.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.aggregates.js"></script>
<script type='text/javascript' src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jquery.ui/js/jquery-ui.min.js" /></script> <!-- used by autocomplete for the boxes label text field -->
<link rel='stylesheet' type='text/css' href='<?php echo OPTIONS_COMMON_FOLDER_PATH ?>jquery.ui/css/smoothness/jquery-ui.css' />
<div id="inventory">
   <h3 class="center">Recharge Repository Resources</h3>
   <div id="inventory_recharge_table" style="margin-top: 20px;margin-left: 8px;margin-bottom: 20px;"></div>
   <div class="center"><button type="button" class="btn btn-primary" id="recharge_btn">Recharge</button></div>
</div>
<div id="recharge_dialog" class="repo_dialog">
   <div id="recharge_dialog_close" class="repo_dialog_close"></div>
   <div>Are you sure you want to complete the recharge? Once done, your changes will be hard to undo.</div>
   <div class="center"><button type="button" class="btn btn-danger" id="confirm_recharge_btn">Recharge</button></div>
</div>
<script type="text/javascript">
   $(document).ready(function(){
      var recharges = new Recharges(MODE_INVENTORY);
      
      $('#whoisme .back').html('<a href=\'?page=home\'>Home</a> | <a href=\'?page=recharges\'>Back</a>');//back link
   });
</script>
      <?php
   }
   
   public function showLN2page($addInfo = ''){
      Repository::jqGridFiles();//Really important if you want jqx to load
?>
<script type="text/javascript" src="js/recharges.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.pager.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxdropdownlist.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.columnsresize.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.sort.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxcheckbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.edit.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.aggregates.js"></script>
<script type='text/javascript' src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jquery.ui/js/jquery-ui.min.js" /></script> <!-- used by autocomplete for the boxes label text field -->
<link rel='stylesheet' type='text/css' href='<?php echo OPTIONS_COMMON_FOLDER_PATH ?>jquery.ui/css/smoothness/jquery-ui.css' />
<div id="ln2">
   <h3 class="center">Recharge Liquid Nitrogen</h3>
   <div id="ln2_recharge_table" style="margin-top: 20px;margin-left: 8px;margin-bottom: 20px;"></div>
   <div class="center"><button type="button" class="btn btn-primary" id="recharge_btn">Recharge</button></div>
</div>
<div id="recharge_dialog" class="repo_dialog">
   <div id="recharge_dialog_close" class="repo_dialog_close"></div>
   <div>Are you sure you want to complete the recharge? Once done, your changes will be hard to undo.</div>
   <div class="center"><button type="button" class="btn btn-danger" id="confirm_recharge_btn">Recharge</button></div>
</div>
<script type="text/javascript">
   $(document).ready(function(){
      var recharges = new Recharges(MODE_LN2);
      
      $('#whoisme .back').html('<a href=\'?page=home\'>Home</a> | <a href=\'?page=recharges\'>Back</a>');//back link
   });
</script>
      <?php
   }
   
   private function showLabelsPage($addInfo = ''){
      Repository::jqGridFiles();//Really important if you want jqx to load
?>
<script type="text/javascript" src="js/recharges.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.pager.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxdropdownlist.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.columnsresize.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.sort.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxcheckbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.edit.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.aggregates.js"></script>
<script type='text/javascript' src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jquery.ui/js/jquery-ui.min.js" /></script> <!-- used by autocomplete for the boxes label text field -->
<link rel='stylesheet' type='text/css' href='<?php echo OPTIONS_COMMON_FOLDER_PATH ?>jquery.ui/css/smoothness/jquery-ui.css' />
<div id="labels">
   <h3 class="center">Recharge Barcode Labels</h3>
   <div id="labels_recharge_table" style="margin-top: 20px;margin-left: 8px;margin-bottom: 20px;"></div>
   <div class="center"><button type="button" class="btn btn-primary" id="recharge_btn">Recharge</button></div>
</div>
<div id="recharge_dialog" class="repo_dialog">
   <div id="recharge_dialog_close" class="repo_dialog_close"></div>
   <div>Are you sure you want to complete the recharge? Once done, your changes will be hard to undo.</div>
   <div class="center"><button type="button" class="btn btn-danger" id="confirm_recharge_btn">Recharge</button></div>
</div>
<script type="text/javascript">
   $(document).ready(function(){
      var recharges = new Recharges(MODE_LABELS);
      
      $('#whoisme .back').html('<a href=\'?page=home\'>Home</a> | <a href=\'?page=recharges\'>Back</a>');//back link
   });
</script>
      <?php
   }
   
   /**
    * This function returns the date to be used as the next period starting
    */
   private function getSSpaceNextPeriodStarting(){
      $query = "select rc_period_ending"
              . " from lcmod_boxes_def"
              . " group by rc_period_ending";//gets the period ending from the last recharges made
      
      $dates = $this->Dbase->ExecuteQuery($query);
      
      if($dates == 1){
         $this->Dbase->CreateLogEntry("An error occurred while trying to fetch period ending dates from the last storage space recharges made. Wont be able to proceed", "fatal");
         return null;
      }
      
      //get the oldest date that is not 0000-00-00
      $oldest = null;
      foreach($dates as $currDateString){
         if($currDateString['rc_period_ending'] != '0000-00-00' && $currDateString['rc_period_ending'] != null){
            $this->Dbase->CreateLogEntry($currDateString['rc_period_ending'], "fatal");
            $currDate = new DateTime($currDateString['rc_period_ending']);//date from mysql already converted to int
            
            if($oldest == null || $currDate->getTimestamp() < $oldest->getTimestamp()){
               $oldest = $currDate;
            }
         }
      }
      
      return $oldest;
   }
   
   /**
    * This function returns pending space recharges grouped by projects
    */
   private function getPendingSpaceRecharges(){
      $priceBoxDay = $_POST['price']/365;//price per box per day
      $periodEnding = $_POST['period_ending'];//date in format yyyy-mm-dd

      $query = "select a.rc_period_ending as last_period, b.value as project, a.project as project_id, count(*) as no_boxes"
              . " from ".Config::$config['dbase'].".lcmod_boxes_def as a"
              . " inner join ".Config::$config['azizi_db'].".boxes_def as c on a.box_id = c.box_id"//the box should also be in lims
              . " left join ".Config::$config['azizi_db'].".modules_custom_values as b on a.project=b.val_id"
              . " where a.rc_period_ending < :newPeriodEnding"
              . " group by a.rc_period_ending, a.project";
      $result = $this->Dbase->ExecuteQuery($query, array("newPeriodEnding" => $periodEnding));
      $this->Dbase->CreateLogEntry("query for fetching recharge boxes = ".$query, "info");
      
      if($result == 1){
         $this->Dbase->CreateLogEntry("An error occurred while trying to get recharge details from the database. Sending user nothing","fatal");
         $result = array();
      }
      
      $total = 0;
      for($i = 0; $i < count($result); $i++){
         //calculate the number of days between last charged date and current recharge end date
         //charge nothing if last charged date not set
         $start = strtotime($result[$i]['last_period']);
         $end = strtotime($periodEnding);
         if($start != false && $end != false && $result[$i]['last_period'] != "0000-00-00"){
            //get number of days
            $duration = ($end - $start)/86400;
            $result[$i]['duration'] = $duration;
            
            $result[$i]['last_period'] = date('d-m-Y', $start);
         }
         else {
            $result[$i]['duration'] = 0;
         }
         
         
         $result[$i]['recharge'] = 1;
         if($result[$i]['project'] == ''){
            $result[$i]['project'] = "Unknown";
            $result[$i]['project_id'] = -1;
            $result[$i]['recharge'] = 0;
         }
         
         //calculate the total recharge price
         $result[$i]['box_price'] = $_POST['price'];
         $result[$i]['total_price'] = round($result[$i]['duration'] * $priceBoxDay * $result[$i]['no_boxes'], 2);
         
         $total = $total + $result[$i]['total_price'];
      }
      
      $json = array('data'=>$result, 'grand_total' => $total);
      die(json_encode($json));
   }
   
   /**
    * This function returns pending inventory recharges
    */
   private function getPendingInventoryRecharges(){
      $query = "select 1 as recharge, a.id, a.item, a.issued_by, a.issued_to, a.date_issued, b.name as charge_code, a.alt_ccode, a.pp_unit, a.quantity"
               . " from inventory as a"
               . " left join ln2_chargecodes as b on a.chargecode_id=b.id"
               . " where item_borrowed = 0 and rc_timestamp is null"
              .  " order by a.id desc";
      $result = $this->Dbase->ExecuteQuery($query);
      if(is_array($result)){
         for($i = 0 ; $i < count($result); $i++){
            if($result[$i]['charge_code'] == null){
               $result[$i]['charge_code'] = $result[$i]['alt_ccode'];
            }
            unset($result[$i]['alt_ccode']);
            
            $result[$i]['total'] = 0;
            if(is_numeric($result[$i]['pp_unit']) && is_numeric($result[$i]['quantity'])){
               $result[$i]['total'] = $result[$i]['pp_unit'] * $result[$i]['quantity'];
            }
         }
      }
      else {
         $result = array();
      }
      
      $json = array('data'=>$result);
      die(json_encode($json));
   }
   
   private function getPendingLN2Recharges(){
       $query = "select '1' as recharge, a.id, b.name as charge_code, a.alt_ccode, a.added_by, a.apprvd_by, a.amount_appr, a.date as date_requested"
              . " from ln2_acquisitions as a"
              . " left join ln2_chargecodes as b on a.project_id = b.id"
              . " where a.amount_appr is not null and a.rc_timestamp is null"
              . " order by a.id desc";

       $result = $this->Dbase->ExecuteQuery($query);
       $price = $this->getNitrogenPrice();
       if(is_array($result)){
         for($i = 0; $i < count($result); $i++){
            $result[$i]['price'] = $price;
            $result[$i]['cost'] = $price * $result[$i]['amount_appr'];

            if($result[$i]['charge_code'] == null){
               $result[$i]['charge_code'] = $result[$i]['alt_ccode'];
            }
            unset($result[$i]['alt_ccode']);
         }
      }
      
      $json = array('data' => $result);
      die(json_encode($json));
   }
   
   private function getPendingLabelsRecharges(){
      $query = "select '1' as recharge, a.id, a.requester, b.project_name, b.charge_code, a.type, c.label_type, a.date as date_printed, a.total as labels_printed, a.copies"
              . " from labels_printed as a"
              . " inner join lcmod_projects as b on a.project = b.id"
              . " inner join labels_settings as c on a.type = c.id"
              . " where rc_timestamp is null"
              . " order by a.id desc";
      $result = $this->Dbase->ExecuteQuery($query);
      
      if($result == 1){
         $this->Dbase->CreateLogEntry("An error occurred while trying to get labels for recharging", "fatal");
         $result = array();
      }
      
      for($index = 0; $index < count($result); $index++){
         $price = $this->getLabelsPrice($result[$index]['type']);
         $result[$index]['price'] = $price;
         $result[$index]['labels_printed'] = $result[$index]['labels_printed'] * $result[$index]['copies'];
         $result[$index]['total'] = $result[$index]['labels_printed'] * $price;
      }
      $this->Dbase->CreateLogEntry("number of printing entries to be recharged = ".count($result), "fatal");
      $json = array('data' => $result);
      die(json_encode($json));
   }
   
   private function getLabelsPrice($type){
      $query = "select price "
              . "from labels_prices "
              . "where label_type = :type and `start_date` <= curdate()";
      $result = $this->Dbase->ExecuteQuery($query, array("type" => $type));
      
      if($result == 1){
         $this->Dbase->CreateLogEntry("An error occurred while trying to fetch the labels prices", "fatal");   
         return 0;
      }
      else if(count($result) == 1){
         return $result[0]['price'];
      }
      else if(count($result) > 1){
         $this->Dbase->CreateLogEntry("More than one price gotten for label type with id = $type. Returning 0 as label price", "fatal");   
         return 0;
      }
      else{
         $this->Dbase->CreateLogEntry("No price gotten for label type with id = $type. Returning 0 as label price", "warnings");   
         return 0;
      }
   }
   
   /**
    * Gets the price of nitrogen from the database that is valid for today
    *
    * @return  float    Returns -1 if an error occures or the price of nitrogen
    */
   private function getNitrogenPrice() {
      $query = "SELECT price FROM `ln2_prices` WHERE `start_date` <= CURDATE()";
      $result = $this->Dbase->ExecuteQuery($query);
      if($result!==1) {
         if(sizeof($result)===1){
            return $result[0]['price'];
         }
         else {
            return -1;
         }
      }
      else {
         return -1;
      }
   }
   
   private function submitSpaceRecharge(){
      //get all project ids
      $projects = $_REQUEST['projects'];
      
      if(count($projects) > 0){
         //get all boxes for the projects
         //$projectID, $periodEnding, $pricePerBoxPerDay, $chargeCode
         $result = array();
         $summary = array();
         foreach($projects as $currProject){
            if(is_numeric($currProject['project_id']) && is_numeric($currProject['box_price'])){
               //get chargecode for project
               $query = "select b.name"
                       . " from ".Config::$config['dbase'].".lcmod_modules_custom_values as a"
                       . " inner join ".Config::$config['dbase'].".ln2_chargecodes as b on a.chargecode_id = b.id"
                       . " where val_id = :projectID";
               $chargeCodes = $this->Dbase->ExecuteQuery($query, array("projectID" => $currProject['project_id']));

               $chargeCode = "NOT SET";

               if(is_array($chargeCodes) && count($chargeCodes) == 1){
                  $chargeCode = $chargeCodes[0]['name'];
               }
               $currResult = $this->getSpaceBoxes($currProject['project_id'], $_REQUEST['period_ending'], $currProject['box_price'], $chargeCode);
               $result = array_merge($result, $currResult['breakdown']);
               $summary = array_merge($summary, array($currResult['summary']));
            }
            else {
               $this->Dbase->CreateLogEntry("Current project from web client doesnt have a numeric id ({$currProject['project_id']})","fatal");
            }
         }
         
         //headings should be in the order of respective items in associative array
         $headings = array(
             "project" => "Project",
             "charge_code" => "Charge Code",
             "sector" => "Sector",
             "no_boxes" => "No. Boxes",
             "box_ids" => "Box IDs",
             "start_date" => "Period Starting",
             "duration" => "Duration (Days)",
             "end_date" => "Period Ending",
             "price_per_box" => "Price per Box (USD)",
             "total" => "Total Cost (USD)"
         );
         
         $summaryHeadings = array(
             'project' => 'Project',
             'charge_code' => 'Charge Code',
             'end_date' => 'Period Ending',
             'no_boxes' => 'Number of Boxes',
             'price_per_box' => "Price per Box (USD)",
             'total' => 'Total Cost for Period (USD)',
         );
         
         $fileName = null;
         
         if(count($result) > 0){
            $phpExcel = $this->initExcelSheet("Storage Space Recharge");
            $phpExcel = $this->addSheetToExcel($phpExcel, 0, "Summary", $summaryHeadings, $summary);
            $phpExcel = $this->addSheetToExcel($phpExcel, 1, "Breakdown", $headings, $result);

            $objWriter = new PHPExcel_Writer_Excel2007($phpExcel);
            $fileName = "/tmp/space_recharge_".$result[0]['end_date']."-".time().".xlsx";
            $objWriter->save($fileName);

            $emailSubject = "Storage space recharges";
            $emailBody = "Find attached a csv file containing data for storage space recharged for the period ending ".$result[0]['end_date'].".";
         }
         else {

            $query = "select value from ".Config::$config['azizi_db'].".modules_custom_values where val_id = :project_id";
            $projectName = $this->Dbase->ExecuteQuery($query, array("project_id" => $projectID));

            $emailSubject = "Storage space recharges";
            $emailBody = "Could not file boxes for storage recharging for the period ending ".$periodEnding.". This might mean the column sc_period_ending for all the boxes associated to this project are null or set to '0000-00-00'. Make sure you record the last date of storage recharge for all the boxes, or you'll end up losing money ;) .";
         }

         //send the file back
         /*header('Content-type: document');
         header('Content-Disposition: attachment; filename='. $fileName);
         header("Expires: 0"); 
         header("Cache-Control: must-revalidate, post-check=0, pre-check=0"); 
         header("Content-length: " . filesize("/tmp/".$fileName));
         header('Content-Transfer-Encoding: binary');
         readfile("/tmp/" . $fileName);*/

         $this->sendRechargeEmail($emailSubject, $emailBody, $fileName);

         $this->Dbase->CreateLogEntry("Recharging file at ".$fileName, "info");
         if($fileName != null){
            unlink($fileName);
         }
         die(json_encode(array("error" => false, "error_message" => "")));
      }
      else {
         $this->Dbase->CreateLogEntry("No project ids provided for storage space recharge", "fatal");
      }
      
      die(json_encode(array("error" => true, "error_message" => "Something unexpected happened while trying to recharge storage space")));
   }
   
   private function submitInventoryRecharge(){
      $return = array("error" => false, "error_message" => "");
      $this->Dbase->CreateLogEntry(print_r($_REQUEST, true), "fatal");
      $items = $_REQUEST['items'];
      
      $this->Dbase->CreateLogEntry(print_r($items, true), "fatal");
      
      /*
       * What can be updated in an item:
       *  - pp_unit (and hence total price)
       *  - charge_code
       *  - quantity
       *  - item
       */
      $ids = array();
      foreach($items as $currItem){
         $query = "update inventory"
                 . " set pp_unit = :pp_unit, rc_charge_code = :charge_code, quantity = :quantity, rc_timestamp = now(), item = :item"
                 . " where id = :id";
         $this->Dbase->ExecuteQuery($query, array("pp_unit" => $currItem['pp_unit'], "charge_code" => $currItem['charge_code'], "quantity" => $currItem['quantity'], "item" => $currItem['item'], "id"=> $currItem['id']));
         $ids[] = $currItem['id'];
      }
      
      $query = "select a.id, a.item, a.issued_by, a.issued_to, a.date_issued, a.rc_charge_code as charge_code, a.pp_unit, a.quantity"
                 . " from inventory as a"
                 . " where a.id in (".  implode(",", $ids).")";
       
       $this->Dbase->CreateLogEntry($query, "fatal");
       $result = $this->Dbase->ExecuteQuery($query);
       if($result == 1){
          $this->Dbase->CreateLogEntry("An error occurred while trying to get recharged items from the database", "fatal");
          $return = array("error" => true, "error_message" => "An error occurred while running one of the queries in the database");
          $result = array();
       }
       
       $query = "select a.item, a.rc_charge_code as charge_code, sum(a.quantity) as quantity, sum(a.pp_unit * a.quantity) as total"
               . " from inventory as a"
               . " where a.id in (".  implode(",", $ids).")"
               . " group by a.rc_charge_code, a.item";
       $summary = $this->Dbase->ExecuteQuery($query);
       
       $headings = array(
           "id" => "Item ID",
           "item" => "Item",
           "issued_by" => "Issued By",
           "issued_to" => "Issued To",
           "date_issued" => "Date Issued",
           "charge_code" => "Charge Code",
           "pp_unit" => "Price per Unit (USD)",
           "quantity" => "Quantity",
           "total" => "Total Price (USD)"
       );
       
       $summaryHeadings = array(
           "charge_code" => "Charge Code",
           "item" => "Item",
           "quantity" => "Quantity",
           "total" => "Total Cost (USD)"
       );
       
       for($index = 0; $index < count($result); $index++){
          $result[$index]['total'] = $result[$index]['quantity'] * $result[$index]['pp_unit'];
       }
       
       $fileName = null;
       
       if(count($result) > 0){
          $phpExcel = $this->initExcelSheet("Item Recharge");
          $phpExcel = $this->addSheetToExcel($phpExcel, 0, "Summary", $summaryHeadings, $summary);
          $phpExcel = $this->addSheetToExcel($phpExcel, 1, "Breakdown", $headings, $result);
          
          $fileName = "/tmp/item_recharge_".date('Y_m_d')."-".time().".xlsx";
          $emailSubject = "Item Recharge";
          $emailBody = "Find attached an Excel spreadsheet containing data for item recharges.";
          $objWriter = new PHPExcel_Writer_Excel2007($phpExcel);
          $objWriter->save($fileName);
          
          $this->sendRechargeEmail($emailSubject, $emailBody, $fileName);
          unlink($fileName);
       } 
       else {
          $emailSubject = "Item Recharge";
          $emailBody = "No items found that can be recharged.";
          $this->sendRechargeEmail($emailSubject, $emailBody);
       }
       
       die(json_encode($return));
   }
   
   private function submitLN2Recharge(){
      $return = array("error" => false, "error_message" => "");
      
      $items = $_REQUEST['items'];
      
      $ids = array();
      foreach($items as $currItem){
         //id: rowData.id, amount_appr: rowData.amount_appr, charge_code: rowData.charge_code, price: rowData.price
         $query = "update ln2_acquisitions"
                 . " set amount_appr = :amount_appr, rc_timestamp = now(), rc_charge_code = :charge_code, rc_price = :price"
                 . " where id = :id";
         $this->Dbase->ExecuteQuery($query, array("amount_appr" => $currItem['amount_appr'], "charge_code" => $currItem['charge_code'], "price" => $currItem['price'], "id" => $currItem['id']));
         
         $ids[] = $currItem['id'];
      }
      
      /*$query = "select b.name as charge_code, a.alt_ccode, count(*) as number_requests, group_concat(a.id) as request_ids, group_concat(distinct a.added_by) as requesters, sum(a.amount_appr) as total_ln2_requested"
            . " from ln2_acquisitions as a"
            . " left join ln2_chargecodes as b on a.project_id = b.id"
            . " where a.amount_appr is not null and a.rc_timestamp is null"
            . " group by a.project_id, a.alt_ccode";*/
      $query = "select a.id, a.rc_charge_code as charge_code, a.added_by, a.date as request_date, a.apprvd_by, a.amount_appr, a.rc_price as price"
              . " from ln2_acquisitions as a"
              . " where a.id in (".  implode(",", $ids).")";
      
      $headings = array(
          "id" => "Request ID", 
          "charge_code" => "Charge Code",
          "added_by" => "Requested By",
          "request_date" => "Request Date",
          "apprvd_by" => "Approved By",
          "amount_appr" => "Amount Approved (Litres)",
          "price" => "Price Per Litre (USD)",
          "total" => "Total Cost (USD)"
      );
      
      $result = $this->Dbase->ExecuteQuery($query);
      
      $query = "select a.rc_charge_code as charge_code, sum(a.amount_appr) quantity, sum(a.rc_price * a.amount_appr) as total"
              . " from ln2_acquisitions as a"
              . " where a.id in (".  implode(",", $ids).")"
              . " group by a.rc_charge_code";
      
      $summary = $this->Dbase->ExecuteQuery($query);
      
      $summaryHeadings = array(
          "charge_code" => "Charge Code",
          "quantity" => "Quantity of Liquid Nitrogen (Litres)",
          "total" => "Total Cost (USD)"
      );
      
      for($index = 0; $index < count($result); $index++){
         $result[$index]['total'] = $result[$index]['price'] * $result[$index]['amount_appr'];
      }
      
      $fileName = null;
      
      if(count($result) > 0){
         $fileName = "/tmp/ln2_recharge_".date('Y_m_d')."-".time().".xlsx";
         
         $emailSubject = "Liquid Nitrogen Recharge";
         $emailBody = "Find attached an Excel spreadsheet containing data for liquid nitrogen recharges.";
         
         $phpExcel = $this->initExcelSheet("Liquid Nitrogen Recharge");
         $phpExcel = $this->addSheetToExcel($phpExcel, 0, "Summary", $summaryHeadings, $summary);
         $phpExcel = $this->addSheetToExcel($phpExcel, 1, "Breakdown", $headings, $result);
         $objWriter = new PHPExcel_Writer_Excel2007($phpExcel);
         $objWriter->save($fileName);
         
         $this->sendRechargeEmail($emailSubject, $emailBody, $fileName);
         unlink($fileName);
      } 
      else {
         $emailSubject = "Liquid Nitrogen Recharge";
         $emailBody = "No items found that can be recharged.";
         $this->sendRechargeEmail($emailSubject, $emailBody);
      }
      
      die(json_encode($return));
   }
   
   private function submitLabelsRecharge(){
      //id: rowData.id, charge_code: rowData.charge_code, price: rowData.price
      $return = array("error" => false, "error_message" => "");
      
      $items = $_REQUEST['items'];
      $ids = array();
      foreach($items as $currItem){
         $query = "update labels_printed"
                 . " set rc_charge_code = :charge_code, rc_price = :price, rc_timestamp = now()"
                 . " where id = :id";
         $this->Dbase->ExecuteQuery($query, array("charge_code" => $currItem['charge_code'], "price" => $currItem['price'], "id" => $currItem['id']));
         
         $ids[] = $currItem['id'];
      }
      
      $query = "select a.id, a.requester, b.project_name, a.rc_charge_code, c.label_type, a.date as date_printed, a.total as labels_printed, a.copies, a.rc_price"
              . " from labels_printed as a"
              . " inner join lcmod_projects as b on a.project = b.id"
              . " inner join labels_settings as c on a.type = c.id"
              . " where a.id in (".  implode(",", $ids).")";
      $result = $this->Dbase->ExecuteQuery($query);
      
      $query = "select a.rc_charge_code, c.label_type, sum(a.total + a.copies) as labels_printed, sum(a.rc_price * (a.total + a.copies)) as total"
              . " from labels_printed as a"
              . " inner join lcmod_projects as b on a.project = b.id"
              . " inner join labels_settings as c on a.type = c.id"
              . " where a.id in (".  implode(",", $ids).")"
              . " group by a.rc_charge_code, a.type";
      $summary = $this->Dbase->ExecuteQuery($query);
      
      for($index = 0; $index < count($result); $index++){
         $result[$index]['total'] = $result[$index]['labels_printed'] * $result[$index]['copies'] * $result[$index]['rc_price'];
      }
      
      $headings = array(
          "id" => "Printing ID", 
          "requester" => "Requester",
          "project_name" => "Project",
          "rc_charge_code" => "Charge Code",
          "label_type" => "Label Type",
          "date_printed" => "Date of Printing",
          "labels_printed" => "Number of Labels Printed",
          "copies" => "Number of Copies",
          "rc_price" => "Price per Label (USD)",
          "total" => "Total Price (USD)"
      );
      $summaryHeadings = array(
          "rc_charge_code" => "Charge Code",
          "label_type" => "Label Type",
          "labels_printed" => "Number of Labels Printed",
          "total" => "Total Cost (USD)"
      );
      
      $fileName = null;
      
      if(count($result) > 0){    
         $fileName = "/tmp/labels_recharge_".date('Y_m_d')."-".time().".xlsx";
         
         $phpExcel = $this->initExcelSheet("Barcode Labels Recharge");
         $phpExcel = $this->addSheetToExcel($phpExcel, 0, "Summary", $summaryHeadings, $summary);
         $phpExcel = $this->addSheetToExcel($phpExcel, 1, "Breakdown", $headings, $result);
         $objWriter = new PHPExcel_Writer_Excel2007($phpExcel);
         $objWriter->save($fileName);
         
         $emailSubject = "Barcode Labels Recharge";
         $emailBody = "Find attached a csv file containing data for barcode label recharges.";
         $this->sendRechargeEmail($emailSubject, $emailBody, $fileName);
         unlink($fileName);
      } 
      else {
         $emailSubject = "Barcode Labels Recharge";
         $emailBody = "No items found that can be recharged.";
         $this->sendRechargeEmail($emailSubject, $emailBody);
      }
      
      die(json_encode($return));
   }
   
   /**
    * This function generates a csv file containing data for space recharges to the specified (in get request) project
    */
   private function getSpaceBoxes($projectID, $periodEnding, $pricePerBox, $chargeCode){
      
      $pricePerBoxPerDay = 0;
      if(is_numeric($pricePerBox)){
         $pricePerBoxPerDay = $pricePerBox/256;
      }
      
      if(strlen($projectID) > 0 && strlen($periodEnding) > 0 && strlen($pricePerBoxPerDay) > 0 && strlen($chargeCode) > 0){
         $query = "select b.value as project, count(*) as no_boxes, d.facility as sector, group_concat(c.box_id) as box_ids, a.rc_period_ending as start_date"
                 . " from ".Config::$config['azizi_db'].".boxes_def as c"
                 . " inner join ".Config::$config['dbase'].".lcmod_boxes_def as a on c.box_id = a.box_id"//the box should also have extra repository info attached to it
                 . " inner join ".Config::$config['azizi_db'].".modules_custom_values as b on a.project=b.val_id"//make sure the box has a valid project joined to it
                 . " left join ".Config::$config['azizi_db'].".boxes_local_def as d on c.location = d.id"
                 . " where a.project = :project and a.rc_period_ending != '0000-00-00'"
                 . " group by a.rc_period_ending, d.id";
         
         $this->Dbase->CreateLogEntry("query for fetching boxes to be updated with recharges ".$query, "info");
         $result = $this->Dbase->ExecuteQuery($query, array("project" => $projectID));
         
         if($result == 1){
            $this->Dbase->CreateLogEntry("Unable to get details of boxes for recharging from the database. Sending empty file", "fatal");
            $result = array();
         }
         else {
            $allBoxIDs = array();
            $summary = null;
            for($i = 0; $i < count($result); $i++){
               //calculate days between current period ending and last period ending
               $from = strtotime($result[$i]['start_date']);
               $to = strtotime($periodEnding);
               $duration = 0;
               if($to != false && $from != false && $result[$i]['start_date'] != "0000-00-00"){
                  $duration = ($to - $from)/86400;
               }
               
               $total = round($pricePerBoxPerDay * $duration, 2);
               $result[$i]['duration'] = $duration;
               $result[$i]['end_date'] = $periodEnding;
               $result[$i]['price_per_box'] = $pricePerBox;
               $result[$i]['total'] = $total;
               $result[$i]['charge_code'] = $chargeCode;
               $result[$i]['box_ids'] = " ".$result[$i]['box_ids'];//so that the excel sheet looks more presentable
               
               if($summary == null){
                  $summary = array();
                  $summary['charge_code'] = $chargeCode;
                  $summary['project'] = $result[$i]['project'];
                  $summary['no_boxes'] = $result[$i]['no_boxes'];
                  $summary['price_per_box'] = $pricePerBox;
                  $summary['end_date'] = $periodEnding;
                  $summary['total'] = $total;
               }
               else {
                  $summary['no_boxes'] = $summary['no_boxes'] + $result[$i]['no_boxes'];
                  $summary['total'] = $summary['total'] + $total;
               }
               
               $allBoxIDs[] = $result[$i]['box_ids'];//list of box ids seperated using commas
            }
            
            //update boxes
            foreach($allBoxIDs as $currBoxIDs){
                $query = "update ".Config::$config['dbase'].".lcmod_boxes_def"
                        . " set rc_timestamp = now(), rc_period_starting = rc_period_ending, rc_period_ending = :ending, rc_price = :price, rc_charge_code = :charge_code"
                        . " where box_id in(".$currBoxIDs.")";
                $this->Dbase->ExecuteQuery($query, array("ending" => $periodEnding, "price" => $pricePerBox, "charge_code" => $chargeCode));
                $this->Dbase->CreateLogEntry("query for updating boxes = ".$query, "info");
                $this->Dbase->CreateLogEntry("updated box ids = ".$currBoxIDs, "info");
            }
            
            $this->Dbase->CreateLogEntry("for project with id = $projectID boxes to be recharged ".print_r($result,true), "info");
            
            return array("summary" => $summary, "breakdown" => $result);
         }
      }
      
      $this->Dbase->CreateLogEntry("Problem with the data provided by user".print_r($_REQUEST, true), "fatal");
      
      return array("summary" => array(), "breakdown" => array());
   }
   
   /**
    * This function generates a CSV string from a two dimensional array.
    * Make sure each of the second level associative arrays the same size.
    * The following array will not be parsed correctly:
    *  [
    *    [0,1,2]
    *    [0,1]
    *    [0,1,2]
    *  ]
    * 
    * @param type $array
    * @param type $headingsFromKeys
    */
   private function generateCSV($array, $headingsFromKeys = true){
      $csv = "";
      if(count($array) > 0){
         $colNum = count($array[0]);
         
         if($headingsFromKeys === true){
            $keys = array_keys($array[0]);
            $csv .= "\"".implode("\",\"", $keys)."\"\n";
         }
         
         foreach($array as $currRow){
            $csv .= "\"".implode("\",\"", $currRow)."\"\n";
         }
      }
      
      return $csv;
   }
   
   /**
    * This function sends emails using the biorepository's email address. Duh
    * 
    * @param type $address Email address of the recipient
    * @param type $subject Email's subject
    * @param type $message Email's body/message
    * @param type $file    Attachements for the email. Set to null if none
    */
   private function sendRechargeEmail($subject, $message, $file = null){
      $address = $this->Dbase->getEmailAddress($_SESSION['username']);
      $cc = null;
      if($address == 0){
         $address = Config::$managerEmail;
      }
      else if(strtolower($address) !== strtolower(Config::$managerEmail)){
         $cc = Config::$managerEmail;
      }
      
      if($file != null){
         if($cc == null) shell_exec('echo "'.$message.'"|'.Config::$muttBinary.' -F '.Config::$muttConfig.' -s "'.$subject.'" -a '.$file.' -- '.$address);
         else shell_exec('echo "'.$message.'"|'.Config::$muttBinary.' -F '.Config::$muttConfig.' -s "'.$subject.'" '.' -c '.$cc.' -a '.$file.' -- '.$address);
      }
      else {
         if($cc == null) shell_exec('echo "'.$message.'"|'.Config::$muttBinary.' -F '.Config::$muttConfig.' -s "'.$subject.'" -- '.$address);
         else shell_exec('echo "'.$message.'"|'.Config::$muttBinary.' -F '.Config::$muttConfig.' -s "'.$subject.'" '.' -c '.$cc.' -a '.$file.' -- '.$address);
      }
   }
   
   private function addSheetToExcel($phpExcel, $sheetIndex, $sheetLabel, $headings, $rows){
      if($sheetIndex != 0){
         $phpExcel->createSheet($sheetIndex);
      }
      $phpExcel->setActiveSheetIndex($sheetIndex);
      $phpExcel->getActiveSheet()->setTitle($sheetLabel);

      $headingKeys = array_keys($headings);
      for($index = 0; $index < count($headings); $index++){
         $cIndex = PHPExcel_Cell::stringFromColumnIndex($index);
         $columnName = $headings[$headingKeys[$index]];
         $phpExcel->getActiveSheet()->setCellValue($cIndex."1", $columnName);

         $phpExcel->getActiveSheet()->getStyle($cIndex."1")->getFont()->setBold(TRUE);
         $phpExcel->getActiveSheet()->getColumnDimension($cIndex)->setAutoSize(true);
         for($sIndex = 0; $sIndex < count($rows); $sIndex++){
            $rIndex = $sIndex + 2;
            $phpExcel->getActiveSheet()->setCellValue($cIndex.$rIndex, $rows[$sIndex][$headingKeys[$index]]);
         }
      }
      $phpExcel->setActiveSheetIndex(0);
      return $phpExcel;
   }
   
   private function initExcelSheet($title){
      require_once OPTIONS_COMMON_FOLDER_PATH.'PHPExcel/Classes/PHPExcel.php';
      $phpExcel = new PHPExcel();
      $phpExcel->getProperties()->setCreator("Azizi Biorepository");
      $phpExcel->getProperties()->setLastModifiedBy("Azizi Biorepository");
      $phpExcel->getProperties()->setTitle($title);
      $phpExcel->getProperties()->setSubject("Created using Azizi Biorepository's Software Systems");
      $phpExcel->getProperties()->setDescription("This Excel file has been generated using Azizi Biorepository's Software Systems that utilize the PHPExcel library on PHP. These Software Systems were created by Absolomon Kihara (a.kihara@cgiar.org) and Jason Rogena (j.rogena@cgiar.org)");
      return $phpExcel;
   }
   
   
}
?>