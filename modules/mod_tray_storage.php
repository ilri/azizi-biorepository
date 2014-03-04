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
       *     - add_tray (do)
       *       - insert_tray (action)
       *     - remove_tray
       *     - delete_tray
       * 
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
      <?php
   }

   private function addTray($addInfo = ''){
      $addInfo = ($addInfo != '') ? "<div id='addinfo'>$addInfo</div>" : '';
      ?>
<div id="home">
   <?php echo $addInfo?>
   <h3 class="center">Add a tray</h3>
   <form enctype="multipart/form-data" name="upload" class="form-horizontal" method="POST" action="index.php?page=tray_storage&do=add_tray&action=insert_tray" onsubmit="return TrayStorage.submitNewRequest();" >
      <div class="form-group">
         <label for="tray_label" class="control-label">Tray Label</label>
         <div class=""><input type="text" class="form-control" id="tray_label" placeholder="JSON or CSV File"></div>
      </div>
      <div class="form-group">
         <label for="tank" class="control-label">Tank</label>
         <div class="">
            <select id="tank">
               <option value=""></option><!--NULL option-->
               <option value="1">1</option><!--TODO: get tanks from tanks table-->
               <option value="2">2</option>
            </select>
         </div>
      </div>
      <div class="form-group">
         <label for="sector" class="control-label">Sector</label>
         <div class="">
            <select id="sector">
               <option value=""></option><!--NULL option-->
               <option value="a">A</option><!--TODO: get tanks from tanks table-->
               <option value="b">B</option>
               <option value="b">C</option>
               <option value="b">D</option>
               <option value="b">E</option>
               <option value="b">F</option>
            </select>
         </div>
      </div>
      <div class="form-group">
         <label for="tower" class="control-label">Tower</label>
         <div class="">
            <select type="text" class="form-control" id="tower">
               <option value=""></option><!--NULL option-->
               <option value="1">1</option><!--TODO: get tanks from tanks table-->
               <option value="2">2</option>
               <option value="3">3</option>
               <option value="4">4</option>
               <option value="5">5</option>
               <option value="6">6</option>
            </select>
         </div>
      </div>
      <div class="form-group">
         <label for="position" class="control-label">Position in Tower</label>
         <div class="">
            <select type="text" class="form-control" id="position">
               <option value=""></option><!--NULL option-->
               <option value="1">1</option><!--TODO: get tanks from tanks table-->
               <option value="2">2</option>
               <option value="3">3</option>
               <option value="4">4</option>
               <option value="5">5</option>
               <option value="6">6</option>
            </select>
         </div>
      </div>
      <div class="form-group">
         <label for="status" class="control-label">Status</label>
         <div class="">
            <select type="text" class="form-control" id="tower">
               <option value=""></option><!--NULL option-->
               <option value="temporary">Temporary</option>
               <option value="permanent">Permanent</option>
            </select>
         </div>
      </div>
   </form>
</div>
      <?php
   }
   
   private function removeTray($addInfo = ''){
      $addInfo = ($addInfo != '') ? "<div id='addinfo'>$addInfo</div>" : '';
      
   }
   
   private function deleteTray($addInfo = ''){
      $addInfo = ($addInfo != '') ? "<div id='addinfo'>$addInfo</div>" : '';
      
   }
}
?>