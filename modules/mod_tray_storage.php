<?php
/**
 * The main class of the system. All other classes inherit from this main one
 *
 * @category   Bio-Repository
 * @package    LN2 Requests
 * @author     Kihara Absolomon <a.kihara@cgiar.org>
 * @author     Jason Rogena <j.rogena@cgiar.org>
 * @since      v0.2
 */

class TrayStorage extends Repository{

   public $Dbase;

   public function __construct($Dbase) {
      $this->Dbase = $Dbase;
   }

   public function TrafficController() {

      /*
       * Hierarchical GET requests handled by this file (tray_storage)
       * - tray_storage (page)
       *    - add_tray (do)
       *       - insert_tray (action)
       *    - remove_tray
       *    - delete_tray
       *    - ajax
       *       - get_tank_details
       */
      if(OPTIONS_REQUEST_TYPE == 'normal'){
         echo "<script type='text/javascript' src='js/tray_storage.js'></script>";
         echo "<script type='text/javascript' src='" . OPTIONS_COMMON_FOLDER_PATH . "jquery.form.js' /></script>";
         echo "<script type='text/javascript' src='" . OPTIONS_COMMON_FOLDER_PATH . "jquery.flexigrid/flexigrid.pack.js' /></script>";
         echo "<script type='text/javascript' src='" . OPTIONS_COMMON_FOLDER_PATH . "jquery.ui/js/jquery-ui.min.js' /></script>";
         echo "<link rel='stylesheet' type='text/css' href='" . OPTIONS_COMMON_FOLDER_PATH . "jquery.flexigrid/css/flexigrid.pack.css' />";
         echo "<link rel='stylesheet' type='text/css' href='" . OPTIONS_COMMON_FOLDER_PATH . "jquery.ui/css//smoothness/jquery-ui.css' />";
      }

      if (OPTIONS_REQUESTED_SUB_MODULE == '') $this->homePage();
      if (OPTIONS_REQUESTED_SUB_MODULE == 'add_tray') $this->addTray ();
      elseif (OPTIONS_REQUESTED_SUB_MODULE == 'remove_tray') $this->removeTray (); // remove a tray temporarily from the LN2 tanks 
      elseif (OPTIONS_REQUESTED_SUB_MODULE == 'delete_tray') $this->deleteTray (); // delete tray from database (with or without it's metadata)
      elseif (OPTIONS_REQUESTED_SUB_MODULE == 'ajax') $this->ajax();
      //TODO: check if you need another sub module for viewing trays
   }

   /**
    * Create the home page for generating the labels
    */
   private function homePage($addInfo = '') {
      $addInfo = ($addInfo != '') ? "<div id='addinfo'>$addInfo</div>" : '';
      ?>
<div id='home'>
   <?php echo $addInfo?>
   <h3 class="center">Tray Storage</h3>
   <hr />
   <div class="user_options">
      <ul>
         <li><a href='?page=tray_storage&do=add_tray'>Add a tray</a></li>
         <li><a href='?page=tray_storage&do=remove_tray'>Remove (borrow) a tray</a></li>
         <li><a href='?page=tray_storage&do=delete_tray'>Delete a tray</a></li>
      </ul>
   </div>
</div>
<script>
   $('#whoisme .back').html('<a href=\'?page=home\'>Back</a>');
</script>
      <?php
   }

   private function addTray($addInfo = ''){
      if(OPTIONS_REQUESTED_ACTION === "insert_tray"){
         $addInfo = $this->insertTray();
      }
      $addInfo = ($addInfo != '') ? "<div id='addinfo'>$addInfo</div>" : '';
      ?>
   <?php echo $addInfo?>
<h3 class="center">Add a tray</h3>
<form enctype="multipart/form-data" name="upload" class="form-horizontal odk_parser" method="POST" action="mod_ajax.php?page=tray_storage&do=add_tray&action=insert_tray" onsubmit="return TrayStorage.submitNewRequest();" >
   <div class="form-group">
      <label for="tray_label" class="control-label">Tray Label</label>
      <div class=""><input type="text" class="form-control" id="tray_label"></div>
   </div>
   <div class="form-group">
      <label for="tank" class="control-label">Tank</label>
      <div class="">
         <select id="tank">
            <option value=""></option><!--NULL option-->
         </select>
      </div>
   </div>
   <div class="form-group">
      <label for="sector" class="control-label">Sector</label>
      <div class="">
         <select id="sector">
            <option value=""></option><!--NULL option-->
         </select>
      </div>
   </div>
   <div class="form-group">
      <label for="rack" class="control-label">Rack</label>
      <div class="">
         <select type="text" class="form-control" id="rack">
            <option value=""></option><!--NULL option-->
         </select>
      </div>
   </div>
   <div class="form-group">
      <label for="position" class="control-label">Position in Rack</label>
      <div class="">
         <select type="text" class="form-control" id="position">
            <option value=""></option><!--NULL option-->
         </select>
      </div>
   </div>
   <div class="form-group">
      <label for="status" class="control-label">Status</label>
      <div class="">
         <select type="text" class="form-control" id="status">
            <option value=""></option><!--NULL option-->
            <option value="temporary">Temporary</option>
            <option value="permanent">Permanent</option>
         </select>
      </div>
   </div>
   <div class="center"><input type="submit" value="Request" name="submitButton" id="submitButton"/></div>
</form>
<script>
   $(document).ready( function() {
      TrayStorage.loadTankData();
   });
   $('#whoisme .back').html('<a href=\'?page=tray_storage\'>Back</a>');
</script>
      <?php
   }
   
   private function removeTray($addInfo = ''){
      $addInfo = ($addInfo != '') ? "<div id='addinfo'>$addInfo</div>" : '';
      
   }
   
   private function deleteTray($addInfo = ''){
      $addInfo = ($addInfo != '') ? "<div id='addinfo'>$addInfo</div>" : '';
      
   }
   
   private function insertTray(){
      $message = "";
   }
   
   /**
    * Handles all ajax requests to this page
    */
   private function ajax(){
      $message = "";
      if(OPTIONS_REQUESTED_ACTION == "get_tank_details"){
         //get tank details from monitoring database
         $query = "SELECT * FROM ".Config::$config['monitoring_db'].".units";
         $result = $this->Dbase->ExecuteQuery($query);
         $this->Dbase->CreateLogEntry('tanks -> '.print_r($result, true), 'fatal');
         if($result !== 1){
            for($tankIndex = 0; $tankIndex < count($result); $tankIndex++){
               $result[$tankIndex]['sectors'] = array();
               $query = "SELECT * FROM tank_sector WHERE tank = ".$result[$tankIndex]['TankID'];
               $tempResult = $this->Dbase->ExecuteQuery($query);
               if($tempResult !== 1){
                  $result[$tankIndex]['sectors'] = $tempResult;
                  for($sectorIndex = 0; $sectorIndex < count($result[$tankIndex]['sectors']); $sectorIndex++){
                     $result[$tankIndex]['sectors'][$sectorIndex]['racks'] = array();
                     $query = "SELECT * FROM rack WHERE tank_sector = ".$result[$tankIndex]['sectors'][$sectorIndex]['id'];
                     $tempResult = $this->Dbase->ExecuteQuery($query);
                     if($tempResult !== 1){
                        $result[$tankIndex]['sectors'][$sectorIndex]['racks'] = $tempResult;
                        for($rackIndex = 0; $rackIndex < count($result[$tankIndex]['sectors'][$sectorIndex]['racks']); $rackIndex++){
                           $result[$tankIndex]['sectors'][$sectorIndex]['racks'][$rackIndex]['boxes'] = array();
                           $query = "SELECT * FROM boxes WHERE rack = ".$result[$tankIndex]['sectors'][$sectorIndex]['racks'][$rackIndex]['id'];
                           $tempResult = $this->Dbase->ExecuteQuery($query);
                           if($tempResult !== 1){
                              $result[$tankIndex]['sectors'][$sectorIndex]['racks'][$rackIndex]['boxes'] = $tempResult;
                           }
                           else $message = $this->Dbase->lastError;
                        }
                     }
                     else $message = $this->Dbase->lastError;
                  }
               }
               else $message = $this->Dbase->lastError;
            }
         }
         else{
            $message = $this->Dbase->lastError;
         }
         
         $jsonArray = array();
         $jsonArray['error'] = $message;
         $this->Dbase->CreateLogEntry('tray storage error message -> '.$message, 'fatal');
         if($result === 1) {
            $result = array();
         }
         $jsonArray['data'] = $result;
         return json_encode($jsonArray);
      }
   }
}
?>