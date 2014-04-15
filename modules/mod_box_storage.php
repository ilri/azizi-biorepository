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

class BoxStorage extends Repository{

   public $Dbase;

   public function __construct($Dbase) {
      $this->Dbase = $Dbase;
   }

   public function TrafficController() {

      /*
       * Hierarchical GET requests handled by this file (box_storage)
       * - box_storage (page)
       *    - add_box (do)
       *       - insert_box (action)
       *    - remove_box
       *       - submit_request
       *    - return_box
       *       - submit_return
       *    - search_box
       *    - delete_box
       *    - ajax
       *       - get_tank_details
       *       - fetch_boxes
       *       - search_boxes
       *       - fetch_removed_boxes
       *       - submit_return_request
       *       - submit_update_request
       *       - submit_delete_request
       *       - fetch_deleted_boxes
       *       - fetch_sample_types
       * 
       */
      if(OPTIONS_REQUEST_TYPE == 'normal'){
         echo "<script type='text/javascript' src='js/box_storage.js'></script>";
      }

      if (OPTIONS_REQUESTED_SUB_MODULE == '') $this->homePage();

      if (OPTIONS_REQUESTED_SUB_MODULE == 'add_box') $this->addBox ();
      elseif (OPTIONS_REQUESTED_SUB_MODULE == 'remove_box') $this->retrieveBox (); // retrieve a box temporarily from the LN2 tanks
      elseif (OPTIONS_REQUESTED_SUB_MODULE == 'return_box') $this->returnBox (); // return a box that had been removed/borrowed
      elseif (OPTIONS_REQUESTED_SUB_MODULE == 'search_box') $this->searchBox (); // search for a box in the system
      elseif (OPTIONS_REQUESTED_SUB_MODULE == 'delete_box') $this->deleteBox (); // delete box from database (with or without it's metadata)
      elseif (OPTIONS_REQUESTED_SUB_MODULE == 'ajax' && OPTIONS_REQUESTED_ACTION == "get_tank_details") $this->getTankDetails ();
      elseif (OPTIONS_REQUESTED_SUB_MODULE == 'ajax' && OPTIONS_REQUESTED_ACTION == "fetch_boxes") $this->fetchBoxes ();
      elseif (OPTIONS_REQUESTED_SUB_MODULE == 'ajax' && OPTIONS_REQUESTED_ACTION == "search_boxes") $this->searchBoxes ();
      elseif(OPTIONS_REQUESTED_SUB_MODULE == 'ajax' && OPTIONS_REQUESTED_ACTION === "fetch_removed_boxes") $this->fetchRemovedBoxes ();
      else if(OPTIONS_REQUESTED_SUB_MODULE == 'ajax' && OPTIONS_REQUESTED_ACTION === "fetch_deleted_boxes") $this->fetchDeletedBoxes ();
      else if(OPTIONS_REQUESTED_SUB_MODULE == 'ajax' && OPTIONS_REQUESTED_ACTION === "submit_return_request") die($this->submitReturnRequest(TRUE));
      else if(OPTIONS_REQUESTED_SUB_MODULE == 'ajax' && OPTIONS_REQUESTED_ACTION === "submit_update_request") die($this->updateBox());
      else if(OPTIONS_REQUESTED_SUB_MODULE == 'ajax' && OPTIONS_REQUESTED_ACTION === "submit_delete_request") die($this->submitDeleteRequest(TRUE));
      else if(OPTIONS_REQUESTED_SUB_MODULE == 'ajax' && OPTIONS_REQUESTED_ACTION === "fetch_sample_types") $this->fetchSampleTypes ();
      //TODO: check if you need another sub module for viewing boxes
   }

   /**
    * Create the home page for generating the labels
    */
   private function homePage($addInfo = '') {
      $addInfo = ($addInfo != '') ? "<div id='addinfo'>$addInfo</div>" : '';
      ?>
<div id='home'>
   <?php echo $addInfo?>
   <h3 class="center">Box Storage</h3>
   <div class="user_options">
      <ul>
         <li><a href='?page=box_storage&do=add_box'>Add a box</a></li>
         <li><a href='?page=box_storage&do=remove_box'>Retrieve a box</a></li>
         <li><a href="?page=box_storage&do=return_box">Return a borrowed box</a></li>
         <li><a href="?page=box_storage&do=search_box">Search a box</a></li>
         <li><a href='?page=box_storage&do=delete_box'>Delete a box</a></li>
      </ul>
   </div>
</div>
<script>
   $('#whoisme .back').html('<a href=\'?page=home\'>Back</a>');//back link
   $(document).ready( function() {
      BoxStorage.getTankData(true);//get tank data from the server and cache to cookie
   });
</script>
      <?php
   }

   /**
    * This function renders the Add Box page in this module. Also calls relevant functions to handle POST requests if any
    * 
    * @param type    $addInfo Any notification info you want displayed to the user when page renders
    * @return null   Return called to initialize the render   
    */
   private function addBox($addInfo = ''){
      Repository::jqGridFiles();
?>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.pager.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.columnsresize.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxdropdownlist.js"></script>
<?php
      if(OPTIONS_REQUESTED_ACTION === "insert_box"){
         //re-open the db connection using a profile with rw permissions
         Config::$config['user'] = Config::$config['rw_user']; Config::$config['pass'] = Config::$config['rw_pass'];
         
         $this->Dbase->InitializeConnection();
         if(is_null($this->Dbase->dbcon)) {
            ob_start();
            Repository::LoginPage(OPTIONS_MSSG_DB_CON_ERROR);
            Repository::$errorPage = ob_get_contents();
            ob_end_clean();
            return;
         }
         $addInfo .= $this->insertBox();
      }
      $addInfo = ($addInfo != '') ? "<div id='addinfo'>$addInfo</div>" : '';
      echo $addInfo;
      //get the list of sample keepers
      $query = "SELECT count, name FROM ".Config::$config['azizi_db'].".contacts WHERE name != ''";
      $keepers = $this->Dbase->ExecuteQuery($query);//TODO: link this to users in lims db
      if($keepers == 1){
         $this->RepositoryHomePage($this->Dbase->lastError);
         return;
      }
      /*$query = "SELECT count, description FROM ".Config::$config['azizi_db'].".sample_types_def WHERE description != ''";
      $sampleTypes = $this->Dbase->ExecuteQuery($query);
      if($sampleTypes == 1){
         $this->RepositoryHomePage($this->Dbase->lastError);
         return;
      }*/
      $query = "SELECT val_id, value FROM " . Config::$config['azizi_db'] . ".modules_custom_values";
      $projects = $this->Dbase->ExecuteQuery($query);

?>
<div id="box_storage">
   <h3 class="center">New Box</h3>
   <form enctype="multipart/form-data" name="upload" role='form' class="form-horizontal odk_parser" method="POST" action="index.php?page=box_storage&do=add_box&action=insert_box" onsubmit="return BoxStorage.submitInsertRequest();" >
      <div id="box_details">
         <div class="form-group left-align"><label for="box_label">Box Label</label><input class='input-medium' type="text" name="box_label" id="box_label" /></div>
         <div class="form-group left-align" style="width: 220px;"><label for="features">Features</label><input type="text" name="features" id="features" /></div>
         <div class='left-align' style="width: 120px;">
            <label>Box Size</label>
            <div class="radio-inline"><label><input type="radio" name="box_size" id="size_81" value="81">9x9</label></div>
            <div class="radio-inline"><label><input type="radio" name="box_size" id="size_100" value="100">10x10</label></div>
         </div>
         <div class='left-align' style="width: 180px;">
            <label for="owner">Sample Keeper</label>
            <select name="owner" id="owner" class='form-control'>
            <?php
                  echo '<option value="Select Box Owner"></option>';//add the first blank option
                  foreach($keepers as $contact) echo '<option value="'. $contact['count'] .'">'. $contact['name'] ."</option>\n";
              ?>
            </select>
         </div>
      </div>

      <div id="box_location">
         <div class="form-group left-align loc_divs">
            <label for="tank">Tank</label>
            <select id="tank" class="input-large">
               <option value=""></option>
            </select>
         </div>
         <div class="form-group left-align loc_divs">
            <label for="sector">Sector</label>
            <select id="sector" name="sector" disabled="disabled" class="input-large">
               <!--Disabled until parent select is selected-->
            </select>
         </div>
         <div id="rack_div" class="form-group left-align loc_divs">
            <label for="rack">Rack</label>
            <select type="text" name="rack" id="rack" disabled="disabled" class="input-large">
               <!--Disabled until parent select is selected-->
            </select>
         </div>
         <div id="rack_spec_div" class="form-group left-align hidden loc_divs" style="width: 160px; display: none;">
            <label for="rack">Rack</label>
            <input type="text" id="rack_spec" name="rack_spec" class="input-large" /><a href="#" id="cancelAnchor" ><img src='images/close.png' /></a>
         </div>
         <div class="form-group left-align loc_divs">
            <label for="position">Position in Rack</label>
            <select type="text" name="position" id="position" disabled="disabled" class="input-large"><!--Disabled until parent select is selected-->
            </select>
         </div>
         <div class="form-group left-align loc_divs">
            <label for="status">Status</label>
            <select type="text" name="status" id="status" class="input-large">
               <option value=""></option><!--NULL option-->
               <option value="temporary">Temporary</option>
               <option value="permanent">Permanent</option>
            </select>
         </div>
         <div class="form-group left-align loc_divs">
            <label for="status">Project</label>
            <select id="project" name="project" class="input-large">
               <option value=""></option>
               <?php
                  foreach ($projects as $currProject) echo '<option value="' . $currProject['val_id'] . '">' . $currProject['value'] . " project</option>\n";
               ?>
            </select>
         </div>
       </div>
      <div class="center" id="submit_button_div"><button type="submit" class="btn btn-success">Save</button></div>
   </form>
</div>
<div id="tank_boxes"></div>

<script type="text/javascript">
   $(document).ready( function() {
      BoxStorage.loadTankData(true);
      BoxStorage.initiateAddBoxesGrid();
      
      $("#status").change(function(){
         if($('#status').val() === "temporary"){
            //if user sets position to temporary set owner to biorepository manager
            $("#owner").prop('disabled', 'disabled');
            $("#project").prop('disabled', false);
         }
         else{
            $("#owner").prop('disabled', false);
            $("#project").prop('disabled', 'disabled');
         }
      });
      $("#cancelAnchor").click(function (){
         $("#rack_spec_div").hide();
         $("#rack_div").show();
      });
   });
   $('#whoisme .back').html('<a href=\'?page=home\'>Home</a> | <a href=\'?page=box_storage\'>Back</a>');//back link
</script>
      <?php
   }

   /**
    * This function displays the Remove Box screen. Submissions handled using webserver requests i.e POST and GET
    *
    * @param type $addInfo    Any notification information you want displayed to the user when page loads
    */ 
   private function retrieveBox($addInfo = ''){
      Repository::jqGridFiles();//load requisite jqGrid javascript files
?>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.pager.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.columnsresize.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxdropdownlist.js"></script>
<?php
      if(OPTIONS_REQUESTED_ACTION === "submit_request"){
         $addInfo = $addInfo.$this->submitRemoveRequest();
      }

      $addInfo = ($addInfo != '') ? "<div id='addinfo'>$addInfo</div>" : '';
      ?>
   <?php echo $addInfo?>
<div id="box_storage">
   <h3 class="center">Retrieve a Box</h3>
   <form enctype="multipart/form-data" name="upload" class="form-horizontal odk_parser" method="POST" action="index.php?page=box_storage&do=remove_box&action=submit_request" onsubmit="return BoxStorage.submitRemoveRequest();" >
      <div id="location_div">
         <!--legend>Box Location</legend-->
         <!--div-->
         <div class="form-group left-align">
               <label for="tank">Tank</label>
               <select id="tank">
                  <option value=""></option><!--NULL option-->
               </select>
         </div>
         <div class="form-group left-align">
            <label for="sector">Sector</label>
            <select id="sector" disabled="disabled"><!--Disabled until parent select is selected-->
            </select>
         </div>
         <div id="rack_div" class="form-group left-align">
            <label for="rack">Rack</label>
            <select type="text" name="rack" id="rack" disabled="disabled"><!--Disabled until parent select is selected-->
            </select>
         </div>

         <div class="form-group left-align">
            <label for="position">Position in Rack</label>
            <select type="text" name="position" id="position" disabled="disabled"><!--Disabled until parent select is selected-->
            </select>
         </div>
         <div class="form-group left-align">
            <label for="box_label">Box label</label><input type="text" id="box_label" disabled="disabled" /><input type="hidden" id="box_id" name="box_id" />
         </div>
         <!--/div-->
      </div>
      <div id="purpose_div">
         <!--legend>Purpose</legend-->
         <!--div-->
         <div class="form-group left-align" style="width: 200px;"><label for="removed_by">Retrieved by</label><input type="text" id="removed_by" style="width: 190px;" disabled="disabled" value="<?php echo $_SESSION['onames']." ".$_SESSION['surname'];?>" /></div>
         <div class="form-group left-align" style="width: 200px;"><label for="for_who">For Who</label><input type="text" name="for_who" id="for_who" style="width: 180px;" /></div>
         <div class="form-group left-align">
            <label for="purpose">Intended purpose</label>
            <select name="purpose" id="purpose">
               <option value=""></option>
               <option value="analysis_on_campus">Analysis on campus</option>
               <option value="analysis_off_campus">Analysis off campus</option>
               <option value="shipment">Shipment</option>
            </select>
         </div>
         <div id="analysis_type_div" hidden="true" class="form-group left-align">
            <label for="analysis_type">Specify analysis to be done</label>
            <textarea cols="8" rows="3" id="analysis_type" name="analysis_type" ></textarea>
         </div>
            <!--<div><label for="sampling_loc">Sampling Location</label><input type="text" name="sampling_loc" id="sampling_loc" /></div>-->
         <!--/div-->
      </div>
      <div class="center" id="submit_button_div"><input type="submit" value="Retrieve" name="submitButton" id="submitButton" class="btn btn-success" /></div>
   </form>
   <div id="retrieved_boxes"></div>
</div>

<script type="text/javascript">
   $(document).ready( function() {
      BoxStorage.loadTankData(false, 1);//show boxes that are still in the tanks (and not borrowed/removed)
      $("#purpose").change(function (){
         if($("#purpose").val()=== "analysis_on_campus" || $("#purpose").val()=== "analysis_off_campus"){
            $("#analysis_type_div").show();
         }
         else{
            $("#analysis_type_div").hide();
         }
      });
      
      BoxStorage.initiateRetrievedBoxesGrid();
   });
   $('#whoisme .back').html('<a href=\'?page=home\'>Home</a> | <a href=\'?page=box_storage\'>Back</a>');//back link
</script>
      <?php
   }

   /**
    * This function displays the Return Box screen. Submissions handled using Javascript AJAX requests
    *
    * @param type $addInfo    Any notification information you want displayed to the user when page loads
    */
   private function returnBox(){
       Repository::jqGridFiles();//load requisite jqGrid javascript files
?>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.pager.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxdropdownlist.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.columnsresize.js"></script>
<script type='text/javascript' src="<?php echo OPTIONS_COMMON_FOLDER_PATH ?>jquery/jquery.ui/js/jquery-ui.min.js" /></script> <!-- used by autocomplete for the boxes label text field -->
<link rel='stylesheet' type='text/css' href='<?php echo OPTIONS_COMMON_FOLDER_PATH ?>jquery.ui/css/smoothness/jquery-ui.css' />

<div id="box_storage">
   <h3 class="center">Return Box</h3>
   <div id="return_div">
      <!--legend>Box Information</legend-->
      <div class="form-group left-align"><label for="box_label">Box Label</label><input type="text" id="box_label" /><input type="hidden" id="remove_id"/></div>
      <div class="form-group left-align"><label for="return_comment">Comment</label><textarea cols="100" rows="3" id="return_comment"></textarea></div>
   </div>
   <div id="location_div">
      <!--legend>Location Information</legend-->
      <!--div-->
      <div class="form-group left-align">
         <label for="tank">Tank</label>
         <input id="tank" disabled="disabled" />
      </div>
      <div class="form-group left-align">
         <label for="sector">Sector</label>
         <input id="sector" disabled="disabled" />
      </div>
      <div class="form-group left-align">
         <label for="rack">Rack</label>
         <input id="rack" disabled="disabled" />
      </div>
      <div class="form-group left-align">
         <label for="position">Position in Rack</label>
         <input id="position" disabled="disabled" />
      </div>
      <!--/div-->
   </div>
   <div class="center" id="submit_button_div"><button type="button" id="submitButton" class="btn btn-success">Return</button></div>

   <div id="returned_boxes"></div>
</div>
<script type="text/javascript">
   $(document).ready(function(){
      BoxStorage.setRemovedBoxSuggestions();

      $('#submitButton').click(function(){
         BoxStorage.submitReturnRequest();
      });

      //clear the value the remove_id input when box_label input is changed
      $('#box_label').change(function(){
         console.log("remove_id cleared");
         BoxStorage.resetReturnInput(false);
      });
      BoxStorage.initiateReturnedBoxesGrid();
   });
   $('#whoisme .back').html('<a href=\'?page=home\'>Home</a> | <a href=\'?page=box_storage\'>Back</a>');//back link
</script>
      <?php
   }
   
   /**
    * This function displays the search page
    */
   private function searchBox() {
      Repository::jqGridFiles();//load requisite jqGrid javascript files
      
      $query = "SELECT val_id, value FROM " . Config::$config['azizi_db'] . ".modules_custom_values";
      $projects = $this->Dbase->ExecuteQuery($query);
      $query = "SELECT count, name FROM ".Config::$config['azizi_db'].".contacts WHERE name != ''";//TODO: link this to users from lims db
      $keepers = $this->Dbase->ExecuteQuery($query);
      /*$query = "SELECT count, description FROM ".Config::$config['azizi_db'].".sample_types_def WHERE description != ''";
      $sampleTypes = $this->Dbase->ExecuteQuery($query);*/
      ?>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.pager.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxdropdownlist.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.columnsresize.js"></script>
<script type='text/javascript' src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jquery.ui/js/jquery-ui.min.js" /></script> <!-- used by autocomplete for the boxes label text field -->
<link rel='stylesheet' type='text/css' href='<?php echo OPTIONS_COMMON_FOLDER_PATH ?>jquery.ui/css/smoothness/jquery-ui.css' />
<div id="box_storage">
   <h3 class="center">Search for a Box</h3>
   <div id="search_div">
      <!--legend>Box Information</legend-->
      <input type="text" id="search" /><button type="button" id="submitButton" class="btn btn-primary" style="margin-left: 20px;">Search</button><a href="#" id="advanced_search_a" style="margin-left: 30px;">Toggle Advanced search</a>
      <div id="advanced_search_div" style="display: none;">
         <div class="search_criteria">
            <label for="search_project">Project</label>
            <select id="search_project">
               <option value=""></option>
               <option value="-2">Boxes without projects</option>
               <option value="-1">Boxes linked to multiple projects</option>
               <?php
                  foreach ($projects as $currProject) echo '<option value="' . $currProject['val_id'] . '">With samples from ' . $currProject['value'] . " project</option>\n";
               ?>
            </select>
         </div>
         <div class="search_criteria">
            <label for="search_status">Status</label>
            <select id="search_status">
               <option value=""></option>
               <option value="temporary">Temporary</option>
               <option value="permanent">Permanent</option>
            </select>
         </div>
         <div class="search_criteria">
            <label for="search_location">Location</label>
            <select id="search_location">
               <option value=""></option>
               <option value="wo_location">With sector not specified</option>
               <option value="wo_rack">With rack not specified</option>
               <option value="wo_rack_pos">With rack position not specified</option>
            </select>
         </div>
         <div class="search_criteria">
            <label for="search_keeper">Sample keeper</label>
            <select id="search_keeper">
               <option value=""></option>
               <?php
                  foreach ($keepers as $currKeeper) echo '<option value="' . $currKeeper['count'] . '">' . $currKeeper['name'] . "</option>\n";
               ?>
            </select>
         </div>
         <div class="search_criteria">
            <label for="samples">Samples</label>
            <select id="samples">
               <option value=""></option>
               <option value="wo_samples">Boxes without samples</option>
               <option value="ex_samples">Boxes with excess samples</option>
            </select>
         </div>
         <div class="search_criteria">
            <label for="boxes_wo_names" style="margin-right: 5px; display: inline;">Boxes without names</label>
            <input type="checkbox" id="boxes_wo_names" style="margin-bottom: 10px;"/>
         </div>
      </div>
   </div>
   <div id="searched_boxes"></div>
   <div id="edit_div" style="display: none;">
      <div id="box_details">
         <div class="form-group left-align"><label for="box_label">Box Label</label><input class='input-medium' type="text" name="box_label" id="box_label" /><input type="hidden" id="box_id" /></div>
         <div class="form-group left-align" style="width: 220px;"><label for="features">Features</label><input type="text" name="features" id="features" /></div>
         <div class='left-align' style="width: 140px;">
            <label>Box Size</label>
            <div class="radio-inline" style="width: 70px;"><label><input type="radio" name="box_size" id="size_81" value="81">9x9</label></div>
            <div class="radio-inline" style="width: 70px;"><label><input type="radio" name="box_size" id="size_100" value="100">10x10</label></div>
         </div>
         <div class='left-align' style="width: 180px;">
            <label for="owner">Sample Keeper</label>
            <select name="owner" id="owner" class='form-control'>
            <?php
                  echo '<option value="Select Box Owner"></option>';//add the first blank option
                  foreach($keepers as $contact) echo '<option value="'. $contact['count'] .'">'. $contact['name'] ."</option>\n";
              ?>
            </select>
         </div>
      </div>

      <div id="box_location">
         <div class="form-group left-align">
            <label for="tank">Tank</label>
            <select id="tank" class="input-large">
               <option value=""></option>
            </select>
         </div>
         <div class="form-group left-align">
            <label for="sector">Sector</label>
            <select id="sector" name="sector" disabled="disabled" class="input-large">
               <!--Disabled until parent select is selected-->
            </select>
         </div>
         <div id="rack_div" class="form-group left-align">
            <label for="rack">Rack</label>
            <select type="text" name="rack" id="rack" disabled="disabled" class="input-large">
               <!--Disabled until parent select is selected-->
            </select>
         </div>
         <div id="rack_spec_div" class="form-group left-align hidden" style="width: 160px; display: none;">
            <label for="rack">Rack</label>
            <input type="text" id="rack_spec" name="rack_spec" class="input-large" /><a href="#" id="cancelAnchor" ><img src='images/close.png' /></a>
         </div>
         <div class="form-group left-align">
            <label for="position">Position in Rack</label>
            <select type="text" name="position" id="position" disabled="disabled" class="input-large"><!--Disabled until parent select is selected-->
            </select>
         </div>
         <div class="form-group left-align">
            <label for="status">Status</label>
            <select type="text" name="status" id="status" class="input-large">
               <option value=""></option><!--NULL option-->
               <option value="temporary">Temporary</option>
               <option value="permanent">Permanent</option>
            </select>
         </div>
         <div class="form-group left-align">
            <label for="status">Project</label>
            <select id="project" name="project">
               <option value=""></option>
               <?php
                  foreach ($projects as $currProject) echo '<option value="' . $currProject['val_id'] . '">' . $currProject['value'] . " project</option>\n";
               ?>
            </select>
         </div>
       </div>
      <div class="center" id="submit_button_div"><button type="button" class="btn btn-danger" id="cancel_button" style="margin-right: 10px;">Cancel</button><button type="button" class="btn btn-success" id="edit_button" style="margin-left: 10px;">Edit</button></div>
   </div>
</div>
<script type="text/javascript">
   $(document).ready(function(){
      BoxStorage.setSearchBoxSuggestions();
      BoxStorage.loadTankData(true);

      $('#submitButton').click(function(){
         BoxStorage.searchForBox();
      });
      $("#search").keyup( function(event) {
         if(event.which === 8 || event.which === 13){//backspace or enter pressed
            BoxStorage.searchForBox();
         }
         else{
            if($("#search").val().length > 2){
               BoxStorage.searchForBox();
            }
         }
      });
      $("#search_project").change(function (){
         BoxStorage.searchForBox();
      });
      $("#search_status").change(function (){
         BoxStorage.searchForBox();
      });
      $("#search_location").change(function (){
         BoxStorage.searchForBox();
      });
      $("#search_keeper").change(function (){
         BoxStorage.searchForBox();
      });
      $("#samples").change(function (){
         BoxStorage.searchForBox();
      });
      $("#boxes_wo_names").change(function (){
         BoxStorage.searchForBox();
      });
      
      
      $('#advanced_search_a').click(function (){
         BoxStorage.toggleAdvancedSearch();
      });
      
      BoxStorage.initiateSearchBoxesGrid();
      
      $('#cancel_button').click(function (){
         BoxStorage.toggleSearchModes();
      });
      $('#edit_button').click(function (){
         BoxStorage.submitBoxUpdate();
      });
      $("#cancelAnchor").click(function (){
         $("#rack_spec_div").hide();
         $("#rack_div").show();
      });
      
      $("#status").change(function(){
         if($('#status').val() === "temporary"){
            //if user sets position to temporary set owner to biorepository manager
            $("#owner").prop('disabled', 'disabled');
            $("#project").prop('disabled', false);
         }
         else{
            $("#owner").prop('disabled', false);
            $("#project").prop('disabled', 'disabled');
         }
      });
   });
   $('#whoisme .back').html('<a href=\'?page=home\'>Home</a> | <a href=\'?page=box_storage\'>Back</a>');//back link
</script>
      <?php
   }

   /**
    * This function displays the Delete Box page to the user
    * 
    * @param type $addInfo    Any notification information you want displayed to the user when page loads
    */
   private function deleteBox($addInfo = ''){
      Repository::jqGridFiles();//load requisite jqGrid javascript files
      ?>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.pager.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxdropdownlist.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.columnsresize.js"></script>
<script type='text/javascript' src="<?php echo OPTIONS_COMMON_FOLDER_PATH ?>jquery/jquery.ui/js/jquery-ui.min.js" /></script> <!-- used by autocomplete for the boxes label text field -->
<link rel='stylesheet' type='text/css' href='<?php echo OPTIONS_COMMON_FOLDER_PATH ?>jquery.ui/css/smoothness/jquery-ui.css' />
      <?php
      $addInfo = ($addInfo != '') ? "<div id='addinfo'>$addInfo</div>" : '';
      ?>
<div id="box_storage">
   <h3 class="center">Delete Box</h3>
   <div id="return_div">
      <!--legend>Box Information</legend-->
      <div class="form-group left-align"><label for="box_label">Box Label</label><input type="text" id="box_label" /><input type="hidden" id="box_id"/></div>
      <div class="form-group left-align"><label for="delete_comment">Comment</label><textarea cols="100" rows="3" id="delete_comment"></textarea></div>
   </div>
   <div id="location_div">
      <!--legend>Location Information</legend-->
      <!--div-->
      <div class="form-group left-align">
         <label for="tank">Tank</label>
         <input id="tank" disabled="disabled" />
      </div>
      <div class="form-group left-align">
         <label for="sector">Sector</label>
         <input id="sector" disabled="disabled" />
      </div>
      <div class="form-group left-align">
         <label for="rack">Rack</label>
         <input id="rack" disabled="disabled" />
      </div>
      <div class="form-group left-align">
         <label for="position">Position in Rack</label>
         <input id="position" disabled="disabled" />
      </div>
   </div>
   <div class="center" id="submit_button_div"><button type="button" id="submitButton" class="btn btn-success">Delete</button></div>
   <!--/div-->
   <div id="deleted_boxes"></div>
</div>
<script type="text/javascript">
   $(document).ready(function(){
      BoxStorage.setDeleteBoxSuggestions();

      $('#submitButton').click(function(){
         BoxStorage.submitDeleteRequest();
      });

      //clear the value the remove_id input when box_label input is changed
      $('#box_label').change(function(){
         console.log("box_id cleared");
         BoxStorage.resetDeleteInput(false);
      });
      
      BoxStorage.initiateDeletedBoxesGrid();
   });
   $('#whoisme .back').html('<a href=\'?page=home\'>Home</a> | <a href=\'?page=box_storage\'>Back</a>');//back link
</script>
      <?php
   }

   /**
    * This function handles the POST request for inserting new box data from the Add Box page
    * 
    * @return string    Result of the insert into the database. Can be either positive or negative
    * @todo link owner to users in lims db and not miscdb
    */
   private function insertBox(){
      $message = "";

      //generate box size that lims can understand
      $boxSizeInLIMS = GeneralTasks::NumericSize2LCSize($_POST['box_size']);

      //change keeper to biorepository manger if box is in temp position
      $ownerID = $_POST['owner'];
      if($_POST['status'] === 'temporary'){
         $query = "SELECT count FROM ".Config::$config['azizi_db'].".contacts WHERE email = ?";
         $result = $this->Dbase->ExecuteQuery($query, array(Config::$limsManager));
         if($result !== 1){
            $ownerID = $result[0]['count'];
         }
      }

      //check if user specified the rack manually
      $rack = $_POST['rack'];
      if($rack=== "n£WR@ck") $rack = $_POST['rack_spec'];

      //get the user id for person responsible for adding the box
      $userId = 1;
      if(strlen($_SESSION['username']) > 0){
         $query = 'select id from '. Config::$config['dbase'] .'.users where login = :login';
         $userId = $this->Dbase->ExecuteQuery($query, array('login' => $_SESSION['username']));
      }
      //for some reason session['username'] is not set for some users but surname and onames are set
      else if(strlen($_SESSION['surname']) > 0 && strlen($_SESSION['onames']) > 0){
         $query = 'select id from '. Config::$config['dbase'] .'.users where sname = :sname AND onames = :onames';
         $userId = $this->Dbase->ExecuteQuery($query, array('sname' => $_SESSION['surname'], 'onames' => $_SESSION['onames']));
      }
      if($userId == 1){
         $this->Dbase->CreateLogEntry($this->Dbase->lastError, 'fatal');
         $this->homePage('There was an error while saving the box');
         return;
      }
      $addedBy = $userId[0]['id'];

      $this->Dbase->StartTrans();
      $insertQuery = 'insert into '. Config::$config['azizi_db'] .'.boxes_def(box_name, size, box_type, location, rack, rack_position, keeper, box_features) values(:box_name, :size, :box_type, :location, :rack, :rack_position, :keeper, :features)';
      $columns = array('box_name' => $_POST['box_label'], 'size' => $boxSizeInLIMS, 'box_type' => 'box', 'location' => $_POST['sector'], 'rack' => $rack, 'rack_position' => $_POST['position'], 'keeper' => $ownerID, 'features' => $_POST['features']);
      $columnValues = array($_POST['box_label'], $boxSizeInLIMS, "box", $_POST['sector'], $rack, $_POST['position'], $ownerID);
      $this->Dbase->CreateLogEntry('About to insert the following row of data to boxes table -> '.print_r($columnValues, true), 'debug');

      $result = $this->Dbase->ExecuteQuery($insertQuery, $columns);
      if($result !== 1) {
         $boxId = $this->Dbase->dbcon->lastInsertId();
         //insert extra information in dbase database
         $now = date('Y-m-d H:i:s');
         
         $project = NULL;
         if($_POST['status'] === 'temporary')
            $project = $_POST['project'];
         $insertQuery = 'insert into '. Config::$config['dbase'] .'.lcmod_boxes_def(box_id, status, date_added, added_by, project) values(:box_id, :status, :date_added, :added_by, :project)';
         $columns = array('box_id' => $boxId, 'status' => $_POST['status'], 'date_added' => $now, 'added_by' => $addedBy, 'project' => $project);
         //$columnValues = array($boxId, $_POST['status'], $_POST['features'], $_POST['sample_types'], $now, $addedBy);
         $this->Dbase->CreateLogEntry('About to insert the following row of data to boxes table -> '.print_r($columns, true), 'debug');

         $result = $this->Dbase->ExecuteQuery($insertQuery, $columns);
         if($result === 1){
            $this->Dbase->RollBackTrans();
            $message = "Unable to add some information from the last request";
            $this->Dbase->CreateLogEntry('mod_box_storage: Unable to make the last insertBox request. Last thrown error is '.$this->Dbase->lastError, 'fatal');//used fatal instead of warning because the dbase file seems to only use the fatal log
         }
         else{
            $this->Dbase->CommitTrans();
            $message = "The box '{$_POST['box_label']}' was added successfully";
         }
      }
      else{
         $this->Dbase->RollBackTrans();
         $message = "Unable to add the last request. Try again later";
         $this->Dbase->CreateLogEntry('mod_box_storage: Unable to make the last insertBox request. Last thrown error is '.$this->Dbase->lastError, 'fatal');//used fatal instead of warning because the dbase file seems to only use the fatal log
      }
      return $message;
   }
   
   /**
    * This function performs an update operation for a box.
    * Most likely called from an AJAX request
    * 
    * @return JSON Returns a json object with the result (wheter successful or not) and the message from the server
    * @todo link owner to users in lims db and not miscdb
    */
   private function updateBox(){
      $message = "";
      $error= 0;//set to 1 if error occures

      //generate box size that lims can understand
      $boxSizeInLIMS = GeneralTasks::NumericSize2LCSize($_POST['box_size']);

      //change keeper to biorepository manger if box is in temp position
      $ownerID = $_POST['owner'];
      if($_POST['status'] === 'temporary'){
         $query = "SELECT count FROM ".Config::$config['azizi_db'].".contacts WHERE email = ?";
         $result = $this->Dbase->ExecuteQuery($query, array(Config::$limsManager));
         if($result !== 1){
            $ownerID = $result[0]['count'];
         }
      }

      //check if user specified the rack manually
      $rack = $_POST['rack'];
      if($rack=== "n£WR@ck") $rack = $_POST['rack_spec'];

      //get the user id for person responsible for updating the box
      $userId = 1;
      if(strlen($_SESSION['username']) > 0){
         $query = 'select id from '. Config::$config['dbase'] .'.users where login = :login';
         $userId = $this->Dbase->ExecuteQuery($query, array('login' => $_SESSION['username']));
      }
      //for some reason session['username'] is not set for some users but surname and onames are set
      else if(strlen($_SESSION['surname']) > 0 && strlen($_SESSION['onames']) > 0){
         $query = 'select id from '. Config::$config['dbase'] .'.users where sname = :sname AND onames = :onames';
         $userId = $this->Dbase->ExecuteQuery($query, array('sname' => $_SESSION['surname'], 'onames' => $_SESSION['onames']));
      }
      if($userId == 1){
         $this->Dbase->CreateLogEntry($this->Dbase->lastError, 'fatal');
         $this->homePage('There was an error while saving the box');
         return;
      }
      $addedBy = $userId[0]['id'];

      $this->Dbase->StartTrans();
      $updateQuery = 'update '. Config::$config['azizi_db'] .'.boxes_def set box_name=:box_name, size=:size, box_type=:box_type, location=:location, rack=:rack, rack_position=:rack_position, keeper=:keeper, box_features=:features where box_id=:box_id';
      $columns = array('box_name' => $_POST['box_label'], 'size' => $boxSizeInLIMS, 'box_type' => 'box', 'location' => $_POST['sector'], 'rack' => $rack, 'rack_position' => $_POST['position'], 'keeper' => $ownerID, 'features' => $_POST['features'], 'box_id' => $_POST['box_id']);
      //$columnValues = array($_POST['box_label'], $boxSizeInLIMS, "box", $_POST['sector'], $rack, $_POST['position'], $ownerID);
      $this->Dbase->CreateLogEntry('About to insert the following row of data to boxes table -> '.print_r($columns, true), 'debug');

      $result = $this->Dbase->ExecuteQuery($updateQuery, $columns);
      if($result !== 1) {
         $boxId = $this->Dbase->dbcon->lastInsertId();
         //insert extra information in dbase database
         $now = date('Y-m-d H:i:s');
         
         $project = NULL;
         if($_POST['status'] === "temporary")
            $project = $_POST['project'];

         $updateQuery = 'update '. Config::$config['dbase'] .'.lcmod_boxes_def set status=:status, date_added=:date_added, added_by=:added_by, project=:project where box_id=:box_id';
         $columns = array('status' => $_POST['status'], 'date_added' => $now, 'added_by' => $addedBy, 'project' => $project, 'box_id' => $_POST['box_id']);
         //$columnValues = array($boxId, $_POST['status'], $_POST['features'], $_POST['sample_types'], $now, $addedBy);
         $this->Dbase->CreateLogEntry('About to insert the following row of data to boxes table -> '.print_r($columns, true), 'debug');

         $result = $this->Dbase->ExecuteQuery($updateQuery, $columns);
         if($result === 1){
            $this->Dbase->RollBackTrans();
            $message = "Unable to add some information from the last request";
            $error = 1;
            $this->Dbase->CreateLogEntry('mod_box_storage: Unable to make the last insertBox request. Last thrown error is '.$this->Dbase->lastError, 'fatal');//used fatal instead of warning because the dbase file seems to only use the fatal log
         }
         else{
            $this->Dbase->CommitTrans();
            $message = "The box '{$_POST['box_label']}' was updated successfully";
         }
      }
      else{
         $this->Dbase->RollBackTrans();
         $message = "Unable to add the last request. Try again later";
         $error = 1;
         $this->Dbase->CreateLogEntry('mod_box_storage: Unable to make the last insertBox request. Last thrown error is '.$this->Dbase->lastError, 'fatal');//used fatal instead of warning because the dbase file seems to only use the fatal log
      }
      $json = array();
      $json["message"] = $message;
      $json["error"] = $error;
      return json_encode($json);
   }

   /**
    * This function handles POST requests from Retrieve a Box page. It records the data in the database
    * 
    * @return string    Results for handling the remove box action in the database. Can be either positive or negative
    */
   private function submitRemoveRequest(){
      $message = "";

      $userId = 1;
      if(strlen($_SESSION['username']) > 0){
         $query = 'select id from '. Config::$config['dbase'] .'.users where login = :login';
         $userId = $this->Dbase->ExecuteQuery($query, array('login' => $_SESSION['username']));
      }
      //for some reason session['username'] is not set for some users but surname and onames are set
      else if(strlen($_SESSION['surname']) > 0 && strlen($_SESSION['onames']) > 0){
         $query = 'select id from '. Config::$config['dbase'] .'.users where sname = :sname AND onames = :onames';
         $userId = $this->Dbase->ExecuteQuery($query, array('sname' => $_SESSION['surname'], 'onames' => $_SESSION['onames']));
      }
      if($userId == 1){
         $this->Dbase->CreateLogEntry($this->Dbase->lastError, 'fatal');
         $this->homePage('There was an error while saving the box');
         return;
      }
      $removedBy = $userId[0]['id'];
      
      $now = date('Y-m-d H:i:s');
      $columns = array("box_def", "removed_by", "removed_for", "purpose", "date_removed");

      $colVals = array($_POST['box_id'], $removedBy, $_POST['for_who'], $_POST['purpose'], $now);

      if(isset($_POST['analysis_type']) && strlen($_POST['analysis_type']) > 0 ){//use strlen insead of comparison to empty string. Later not always correctly captured
         array_push($columns, "analysis");
         array_push($colVals, $_POST['analysis_type']);
      }
      $result = $this->Dbase->InsertOnDuplicateUpdate(Config::$config['dbase'].".lcmod_retrieved_boxes", $columns, $colVals);

      if($result === 0){
         $message = "Unable to remove box for the system.";
         $this->Dbase->CreateLogEntry('mod_box_storage: Unable to remove box from system. Last thrown error is '.$this->Dbase->lastError, 'fatal');
      }
      else{
         $message = "Box successfully removed from the system.";
         $this->Dbase->CreateLogEntry('mod_box_storage: Box successfully retrieved from system by '.$_SESSION['username'], 'debug');
      }

      return $message;
   }

   /**
    * This function handles POST request from the Return a Box page
    * 
    * @param Boolean $fromAjaxRequest  Set to TRUE if POST request is comming for javascripts AJAX
    * 
    * @return string Results for handling the return box action in the database. Can be either positive or negative
    */
   private function submitReturnRequest($fromAjaxRequest = false) {
      $message = "";
      
      $userId = 1;
      if(strlen($_SESSION['username']) > 0){
         $query = 'select id from '. Config::$config['dbase'] .'.users where login = :login';
         $userId = $this->Dbase->ExecuteQuery($query, array('login' => $_SESSION['username']));
      }
      //for some reason session['username'] is not set for some users but surname and onames are set
      else if(strlen($_SESSION['surname']) > 0 && strlen($_SESSION['onames']) > 0){
         $query = 'select id from '. Config::$config['dbase'] .'.users where sname = :sname AND onames = :onames';
         $userId = $this->Dbase->ExecuteQuery($query, array('sname' => $_SESSION['surname'], 'onames' => $_SESSION['onames']));
      }
      if($userId == 1){
         $this->Dbase->CreateLogEntry($this->Dbase->lastError, 'fatal');
         $this->homePage('There was an error while saving the box');
         return;
      }
      $returnedBy = $userId[0]['id'];
      
      //get the last remove recored for the box/box being returned
      $query = "UPDATE ".Config::$config['dbase'].".lcmod_retrieved_boxes SET `date_returned` = ?, `returned_by` = ?, `return_comment` = ? WHERE id = ?";
      $now = date('Y-m-d H:i:s');

      $result = $this->Dbase->ExecuteQuery($query, array($now, $returnedBy, $_POST['return_comment'], $_POST['remove_id']));
      if($result === 0){
         $message = "Unable to return box back into the system";
         $this->Dbase->CreateLogEntry('mod_box_storage: Unable to return box back into system. Last thrown error is '.$this->Dbase->lastError, 'fatal');
      }

      if($fromAjaxRequest) {
         $jsonArray = array();

         if(is_array($result)) $jsonArray = $result;

         return json_encode(array("data" => $jsonArray, "error_message" => $message));
      }
      else return $message;
   }

   /**
    * This function handles POST request from Delete a Box page
    * 
    * @param type $fromAjaxRequest  Set to TRUE if request is comming from Javascripts AJAX
    * 
    * @return string Results for handling the delete box action in the database. Can be either positive or negative
    */
   private function submitDeleteRequest($fromAjaxRequest = false){
      $message = "";
      $query = "SELECT id FROM ".Config::$config['azizi_db'].".boxes_local_def WHERE facility = ?";
      $result = $this->Dbase->ExecuteQuery($query, array(Config::$deletedBoxesLoc));
      if($result !== 1 && count($result) === 1){
         $deletedBoxesLocId = $result[0]['id'];
         $this->Dbase->CreateLogEntry('mod_box_storage: deletedBoxesLocId = '.$deletedBoxesLocId, 'debug');
         $query = "UPDATE ".Config::$config['azizi_db'].".boxes_def SET location = ? WHERE box_id = ?";
         $result = $this->Dbase->ExecuteQuery($query, array($deletedBoxesLocId, $_POST['box_id']));
         if($result === 1){
            $message = "Unable to delete the box";
            $this->Dbase->CreateLogEntry('mod_box_storage: Unable to delete box (move it to the EmptiesBox) in lims database '.$this->Dbase->lastError, 'fatal');
         }
         else{
            $this->Dbase->CreateLogEntry('mod_box_storage: Updating database to show box with id = '.$_POST['box_id']." as deleted", 'debug');
            
            $userId = 1;
            if(strlen($_SESSION['username']) > 0){
               $query = 'select id from '. Config::$config['dbase'] .'.users where login = :login';
               $userId = $this->Dbase->ExecuteQuery($query, array('login' => $_SESSION['username']));
            }
            //for some reason session['username'] is not set for some users but surname and onames are set
            else if(strlen($_SESSION['surname']) > 0 && strlen($_SESSION['onames']) > 0){
               $query = 'select id from '. Config::$config['dbase'] .'.users where sname = :sname AND onames = :onames';
               $userId = $this->Dbase->ExecuteQuery($query, array('sname' => $_SESSION['surname'], 'onames' => $_SESSION['onames']));
            }
            if($userId == 1){
               $this->Dbase->CreateLogEntry($this->Dbase->lastError, 'fatal');
               $this->homePage('There was an error while saving the box');
               return;
            }
            $deletedBy = $userId[0]['id'];
            
            $query = "UPDATE ".Config::$config['dbase'].".lcmod_boxes_def SET date_deleted = ?, deleted_by = ?, delete_comment = ? WHERE box_id = ?";
            $now = date('Y-m-d H:i:s');

            $result = $this->Dbase->ExecuteQuery($query, array($now, $deletedBy, $_POST['delete_comment'], $_POST['box_id']));
            if($result === 1){
               $message = "Unable to record extra information on the delete";
               $this->Dbase->CreateLogEntry('mod_box_storage: Unable to record details of the last box delete. Last error is '.$this->Dbase->lastError, 'fatal');
            }
         }
      }
      else{
         $message = "Unable to delete the box";
         $this->Dbase->CreateLogEntry('mod_box_storage: Unable to delete box (move it to the EmptiesBox) in lims database '.$this->Dbase->lastError, 'fatal');
      }

      if($fromAjaxRequest) {
         $jsonArray = array();

         if(is_array($result)) $jsonArray = $result;

         return json_encode(array("data" => $jsonArray, "error_message" => $message));
      }
      else return $message;
   }

   /**
    * This function gets tank details form the database and constructs a json object of the data with the following hierarchies
    *    - tank
    *       - sector
    *          - rack
    *             -box 
    */
   private function getTankDetails() {
      //get tank details from azizi_lims
      $query = "SELECT b.id, b.name" .
              " FROM " . Config::$config['dbase'] . ".lcmod_storage_facilities AS a" .
              " INNER JOIN " . Config::$config['azizi_db'] . ".storage_facilities  AS b ON a.id = b.id" .
              " WHERE a.is_tank = 1";
      $result = $this->Dbase->ExecuteQuery($query);
      for ($tankIndex = 0; $tankIndex < count($result); $tankIndex++) {
         $result[$tankIndex]['sectors'] = array();
         $query = "SELECT id, facility, racks_nbr, rack_pos, facility_id FROM " . Config::$config['azizi_db'] . ".boxes_local_def WHERE facility_id = " . $result[$tankIndex]['id'];
         $tempResult = $this->Dbase->ExecuteQuery($query);
         if ($tempResult !== 1) {
            $result[$tankIndex]['sectors'] = $tempResult;
            for ($sectorIndex = 0; $sectorIndex < count($result[$tankIndex]['sectors']); $sectorIndex++) {
               //get all boxes in that sector
               $query = "SELECT a.*" .
                       " FROM " . Config::$config['azizi_db'] . ".boxes_def AS a" .
                       " INNER JOIN " . Config::$config['dbase'] . ".lcmod_boxes_def AS b ON a.box_id = b.box_id" .
                       " WHERE a.location = " . $result[$tankIndex]['sectors'][$sectorIndex]['id'] .
                       " AND b.date_deleted IS NULL";
               $tempResult = $this->Dbase->ExecuteQuery($query);

               //get all unique racks in this sector
               if ($tempResult !== 1) {
                  $racks = array();
                  for ($boxIndex = 0; $boxIndex < count($tempResult); $boxIndex++) {
                     //create array of boxes inside rack if it doesnt exist
                     if (strlen($tempResult[$boxIndex]['rack']) > 0 && strlen($tempResult[$boxIndex]['rack_position']) > 0) {
                        if (!isset($racks[$tempResult[$boxIndex]['rack']])) {
                           $racks[$tempResult[$boxIndex]['rack']] = array();
                           $racks[$tempResult[$boxIndex]['rack']]['name'] = $tempResult[$boxIndex]['rack'];
                           $racks[$tempResult[$boxIndex]['rack']]['size'] = $result[$tankIndex]['sectors'][$sectorIndex]['rack_pos']; //assuming here that you will not find a box out of range specified in boxes_local_def
                           $racks[$tempResult[$boxIndex]['rack']]['boxes'] = array();
                        }

                        //get extra data for box in boxes_def table in dbase database
                        $query = "SELECT * FROM " . Config::$config['dbase'] . ".lcmod_boxes_def WHERE box_id = " . $tempResult[$boxIndex]['box_id'];
                        $extraData = $this->Dbase->ExecuteQuery($query);
                        if (count($extraData) === 1) {
                           $tempResult[$boxIndex] = array_merge($tempResult[$boxIndex], $extraData[0]);
                        }

                        //get retrieves on the box
                        $query = "SELECT * FROM " . Config::$config['dbase'] . ".lcmod_retrieved_boxes WHERE box_def = " . $tempResult[$boxIndex]['box_id'];
                        $tempResult[$boxIndex]['retrieves'] = $this->Dbase->ExecuteQuery($query);
                        if ($tempResult[$boxIndex]['retrieves'] === 1) {
                           $tempResult[$boxIndex]['retrieves'] = array();
                           $message = $this->Dbase->lastError;
                        }

                        //push box into parent rack
                        array_push($racks[$tempResult[$boxIndex]['rack']]['boxes'], $tempResult[$boxIndex]);
                     } else
                        $this->Dbase->CreateLogEntry('box_storage: Unable to add box with box_id = ' . $tempResult[$boxIndex]['box_id'] . " because its rack or position on rack has not been specified", 'warnings');
                  }

                  //change racks array from associative to index
                  $newRackIndex = 0;
                  $convertedRacks = array();
                  foreach ($racks as $currRack) {
                     $convertedRacks[$newRackIndex] = $currRack;
                     $newRackIndex++;
                  }

                  $result[$tankIndex]['sectors'][$sectorIndex]['racks'] = $convertedRacks;
               } else
                  $message = $this->Dbase->lastError;
            }
         } else
            $message = $this->Dbase->lastError;
      }

      $jsonArray = array();
      $jsonArray['error'] = $message;

      if ($result === 1) {
         $result = array();
      }
      $jsonArray['data'] = $result;
      //$this->Dbase->CreateLogEntry('bod_box_storage: json for tank information -> '.print_r($result, true), 'debug');
      //setcookie("tankData", json_encode($jsonArray), 0, "/");
      echo json_encode($jsonArray);
   }

   /**
    * This function fetched boxes added to the system from the "Add a Box" page and returns a json object with this info
    * 
    */
   private function fetchBoxes() {
      $fromRow = $_POST['pagenum'] * $_POST['pagesize'];
      $pageSize = $_POST['pagesize'];
      $query = 'select SQL_CALC_FOUND_ROWS a.box_id, a.status, date(a.date_added) as date_added, b.box_name, concat(c.facility, " >> ", b.rack, " >> ", b.rack_position) as position, login as added_by '.
              'from '. Config::$config['dbase'] .'.lcmod_boxes_def as a '.
              'inner join '. Config::$config['azizi_db'] .'.boxes_def as b on a.box_id = b.box_id '.
              'inner join '. Config::$config['azizi_db'] .'.boxes_local_def as c on b.location = c.id '.
              'inner join '. Config::$config['dbase'] .'.users as d on a.added_by = d.id '.
              'limit '.$fromRow.','.$pageSize;
      
      $this->Dbase->CreateLogEntry('mod_box_storage: fetch boxes query = '.$query, 'debug');
      
      $result = $this->Dbase->ExecuteQuery($query);
      if($result == 1)  die(json_decode(array('data' => $this->Dbase->lastError)));
      
      $query = "SELECT FOUND_ROWS() AS found_rows";
      $foundRows = $this->Dbase->ExecuteQuery($query);
      $totalRowCount = $foundRows[0]['found_rows'];
      
      if(count($result) > 0){
         $result[0]['total_row_count'] = $totalRowCount;
      }
      
      header("Content-type: application/json");
      die('{"data":'. json_encode($result) .'}');
   }

   /**
    * This function searches for boxes using certain criteria
    */
   private function searchBoxes() {
      $fromRow = $_POST['pagenum'] * $_POST['pagesize'];
      $pageSize = $_POST['pagesize'];
      $query = 'select SQL_CALC_FOUND_ROWS a.box_id, a.status, date(a.date_added) as date_added, a.status, a.project, b.box_features, b.box_name, b.keeper, b.size, b.rack, b.rack_position, c.id as sector_id, c.facility_id as tank_id , concat(c.facility, " >> ", b.rack, " >> ", b.rack_position) as position '.
              'from '. Config::$config['dbase'] .'.lcmod_boxes_def as a '.
              'inner join '. Config::$config['azizi_db'] .'.boxes_def as b on a.box_id = b.box_id './/optimization: use inner join to fetch only boxes in LN2 tanks and not the freezers etc
              'left join '. Config::$config['azizi_db'] .'.boxes_local_def as c on b.location = c.id ';//fetch all boxes regardless of wether sector (boxes_local_def) is defined or not
      
      /*
       * Optimization:
       *  - no need to fetch data on the actual tank (storage_facility). That can be obtained from the cached tank info on the client side
       *  - fetching samples from the database in a join is a NO-NO. There are about 200000 samples in the db and growing
       *    Fetch the sample data in a another query and append no_samples there
       */

      
      if($_POST['boxes_wo_names'] === "true"){
         $query = $query . " WHERE (b.box_name IS NULL OR b.box_name = '') AND b.box_features LIKE '%".$_POST['search']."%'";
      }
      else if($_POST['boxes_wo_names'] === "false"){
         $query = $query . " WHERE (b.box_name LIKE '%".$_POST['search']."%' OR b.box_features LIKE '%".$_POST['search']."%')";
      }
      
      if(strlen($_POST['status']) > 0){
         $query = $query . " AND a.status = '".$_POST['status']."'";
      }

      /*
       * <option value="wo_location">With location not specified</option>
       * <option value="wo_rack">With rack not specified</option>
       * <option value="wo_rack_pos">With rack position not specified</option>
       */
      if($_POST['location'] == "wo_location"){
         $query = $query . " AND b.location IS NULL";
      }
      else if($_POST['location'] == "wo_rack"){
         $query = $query . " AND b.rack IS NULL";
      }
      else if($_POST['location'] == "wo_rack_pos"){
         $query = $query . " AND b.rack_position IS NULL";
      }
      if(strlen($_POST['keeper'])>0){
         $query = $query . " AND b.keeper = ".$_POST['keeper'];
      }
      
      $query = $query . ' limit '.$fromRow.','.$pageSize;
      $this->Dbase->CreateLogEntry('mod_box_storage: Search query = '.$query, 'debug');
      
      $result = $this->Dbase->ExecuteQuery($query);
      
      $query = "SELECT FOUND_ROWS() AS found_rows";
      $foundRows = $this->Dbase->ExecuteQuery($query);
      $totalRowCount = $foundRows[0]['found_rows'];
      
      $query = "select boxes_def.box_id, boxes_def.size, samples.project as project_id, count(distinct(samples.project)) as no_projects, count(samples.box_id) as no_samples".
              " from boxes_def inner join samples on boxes_def.box_id = samples.box_id group by boxes_def.box_id";
      $allBoxes = $this->Dbase->ExecuteQuery($query);
      
      $this->Dbase->CreateLogEntry('mod_box_storage: box count = '.count($result), 'debug');
      for($resultIndex = 0; $resultIndex < count($result); $resultIndex++){
         $indexInAB = -1;
         
         //search for the box in all the boxes
         for($boxIndex = 0; $boxIndex < count($allBoxes); $boxIndex++){
            if($allBoxes[$boxIndex]['box']['box_id'] === $result[$resultIndex]['box_id']){
               $indexInAB = $boxIndex;
            }
         }
         
         if($indexInAB !== -1){
            $this->Dbase->CreateLogEntry('mod_box_storage: Box has samples '.$resultIndex.' box count = '.count($result), 'debug');
            if($_POST['project'] === "-1"){//user wants boxes associated with multiple projects
               if($allBoxes[$indexInAB]['no_projects']<=1 || $result[$resultIndex]['status'] === 'temporary'){
                  //remove this box, we only need boxes associated with multiple projects. There is no way a temporary box can be associated with multiple projects
                  array_splice($result, $resultIndex, 1);
                  array_splice($allBoxes, $indexInAB, 1);
                  $resultIndex--;
                  $totalRowCount--;
                  continue;
               }
            }
            else if($_POST['project'] === "-2"){//user wants boxes not associated with any projects
               if(($result[$resultIndex]['status'] === 'temporary' && $result[$resultIndex]['project'] !== NULL) || $allBoxes[$indexInAB]['no_projects']>0){
                  //remove this box, we only need boxes not associated with projects
                  array_splice($result, $resultIndex, 1);
                  array_splice($allBoxes, $indexInAB, 1);
                  $resultIndex--;
                  $totalRowCount--;
                  continue;
               }
            }
            else if(strlen($_POST['project']) > 0){//user wants boxes associated with a particular box. If we have reached this point, the box is associated to only one project
               if(($result[$resultIndex]['status'] === 'temporary' && $result[$resultIndex]['project'] !== $_POST['project']) || $allBoxes[$indexInAB]['project_id'] !== $_POST['project']){//box not from the user specified project
                  //box is either (in a teporary location not associated with the specified project) or none of its samples associated with the specified project
                  array_splice($result, $resultIndex, 1);
                  array_splice($allBoxes, $indexInAB, 1);
                  $resultIndex--;
                  $totalRowCount--;
                  continue;
               }
            }
            
            if($_POST['samples'] === "ex_samples"){
               $boxSize = GeneralTasks::LCSize2NumericSize($allBoxes[$indexInAB]['size']);
               if($boxSize > $allBoxes[$indexInAB]['no_samples']){//box does not have excess samples
                  array_splice($result, $resultIndex, 1);
                  array_splice($allBoxes, $indexInAB, 1);
                  $resultIndex--;
                  $totalRowCount--;
                  continue;
               }
            }
            else if($_POST['samples'] === "wo_samples"){
               if($allBoxes[$indexInAB]['no_samples'] > 0){//box has samples in it
                  array_splice($result, $resultIndex, 1);
                  array_splice($allBoxes, $indexInAB, 1);
                  $resultIndex--;
                  $totalRowCount--;
                  continue;
               }
            }
         }
         else{//box does not have samples
            $this->Dbase->CreateLogEntry('mod_box_storage: Box does not have samples '.$resultIndex.' box count = '.count($result), 'debug');
            if($result[$resultIndex]['status'] === 'temporary'){
               if($_POST['project'] === "-2"){//user wants boxes not associated with any projects
                  if($result[$resultIndex]['project'] !== NULL){//box associated with a project
                     array_splice($result, $resultIndex, 1);
                     $resultIndex--;
                     $totalRowCount--;
                     continue;
                  }
               }
               else if($_POST['project'] === "-1"){//user wants boxes associated with more than one project
                  //this is not possible if box in temporary position
                  array_splice($result, $resultIndex, 1);
                  $resultIndex--;
                  $totalRowCount--;
                  continue;
               }
               else if(strlen($_POST['project'])>0){//user has specified the id of the project he/she wants boxes associated to
                  if($result[$resultIndex]['project'] !== $_POST['project']){//box not associated to the project
                     array_splice($result, $resultIndex, 1);
                     $resultIndex--;
                     $totalRowCount--;
                     continue;
                  }
               }
            }
            else if(strlen($_POST['project']) > 0 && $_POST['project'] !== "-2"){//box in permanent position
               //user wants box related to at least one project. This box (in a permanent location) is not associated to any samples and therefore no project
               $this->Dbase->CreateLogEntry('mod_box_storage: POST"project" = '.$_POST['project'], 'debug');
                array_splice($result, $resultIndex, 1);
                $resultIndex--;
                $totalRowCount--;
                continue;
            }
            if(strlen($_POST['samples']) > 0 && $_POST['samples'] === "ex_samples"){
               $boxSize = GeneralTasks::LCSize2NumericSize($allBoxes[$indexInAB]['size']);
               array_splice($result, $resultIndex, 1);
                $resultIndex--;
                $totalRowCount--;
                continue;
            }
         }
      }
      
      if(count($result)> 0){
         $result[0]['total_row_count'] = $totalRowCount;
      }
      if($result == 1)  die(json_decode(array('data' => $this->Dbase->lastError)));

      header("Content-type: application/json");
      die('{"data":'. json_encode($result) .'}');
   }

   /**
    * This function gets data for boxes retireved from the system using the Retrieve a Box page and constructs a json object with this data
    */
   private function fetchRemovedBoxes() {
      $fromRow = $_POST['pagenum'] * $_POST['pagesize'];
      $pageSize = $_POST['pagesize'];
      
      $query = "SELECT SQL_CALC_FOUND_ROWS a.id, CONCAT(d.onames, ' ', d.sname) AS removed_by, a.removed_for, a.purpose, a.analysis, date(a.date_removed) AS date_removed, date(a.date_returned) AS date_returned, a.return_comment, CONCAT(e.onames, ' ', e.sname) AS returned_by, b.box_name, concat(c.facility, ' >> ', b.rack, ' >> ', b.rack_position) as position" .
              " FROM " . Config::$config['dbase'] . ".lcmod_retrieved_boxes AS a" .
              " INNER JOIN " . Config::$config['azizi_db'] . ".boxes_def AS b ON a.box_def = b.box_id" .
              " INNER JOIN " . Config::$config['azizi_db'] . ".boxes_local_def AS c ON b.location = c.id".
              " LEFT JOIN " . Config::$config['dbase'] . ".users AS d ON a.removed_by = d.id".
              " LEFT JOIN " . Config::$config['dbase'] . ".users AS e ON a.returned_by = e.id".
              ' LIMIT '.$fromRow.','.$pageSize;
      
      $result = $this->Dbase->ExecuteQuery($query);
      if($result === 1) die(json_decode(array('data' => $this->Dbase->lastError)));
      
      $query = "SELECT FOUND_ROWS() AS found_rows";
      $foundRows = $this->Dbase->ExecuteQuery($query);
      $totalRowCount = $foundRows[0]['found_rows'];
      
      if(count($result) > 0){
         $result[0]['total_row_count'] = $totalRowCount;
      }
      
      header("Content-type: application/json");
      $this->Dbase->CreateLogEntry('mod_box_storage: Removed boxes sent via ajax request = '.print_r($result ,true), 'debug');
      $json = array('data'=>$result);
      die(json_encode($json));
   }

   /**
    * This function gets data for boxes deleted from the system using the Delete a Box page and constructs a json object with this data
    */
   private function fetchDeletedBoxes() {
      $fromRow = $_POST['pagenum'] * $_POST['pagesize'];
      $pageSize = $_POST['pagesize'];
      
      $query = "SELECT SQL_CALC_FOUND_ROWS date(a.date_deleted) AS date_deleted, a.delete_comment, b.box_name, CONCAT(c.onames, ' ', c.sname) AS deleted_by" .
              " FROM " . Config::$config['dbase'] . ".lcmod_boxes_def AS a" .
              " INNER JOIN " . Config::$config['azizi_db'] . ".boxes_def AS b ON a.box_id = b.box_id" .
              " LEFT JOIN " . Config::$config['dbase'] . ".users AS c ON a.deleted_by = c.id" .
              " WHERE date_deleted IS NOT NULL".
              ' LIMIT '.$fromRow.','.$pageSize;
      //$this->Dbase->query = $query." LIMIT $startRow, {$_POST['rp']}";
      $result = $this->Dbase->ExecuteQuery($query);
      
      if($result === 1) die(json_decode(array('data' => $this->Dbase->lastError)));
      
      $query = "SELECT FOUND_ROWS() AS found_rows";
      $foundRows = $this->Dbase->ExecuteQuery($query);
      $totalRowCount = $foundRows[0]['found_rows'];
      
      if(count($result) > 0){
         $result[0]['total_row_count'] = $totalRowCount;
      }
      
      header("Content-type: application/json");
      $this->Dbase->CreateLogEntry('mod_box_storage: Deleted boxes sent via ajax request = '.print_r($result ,true), 'debug');
      $json = array('data'=>$result);
      die(json_encode($json));
   }

   /**
    * This function fetches the available sample types from the LIMS database
    */
   private function fetchSampleTypes() {
      $message = "";

      $query = "SELECT * FROM " . Config::$config['azizi_db'] . ".sample_types_def WHERE description != ''"; //only select sample types that have descriptions
      $result = $this->Dbase->ExecuteQuery($query);
      $jsonArray = array();
      if ($result !== 1) {
         $jsonArray['data'] = $result;
         $jsonArray['error_message'] = '';
      } else {
         $jsonArray['data'] = array();
         $jsonArray['error_message'] = "Unable to get available sample types. Please contact the system developers";
      }
   }
}
?>