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
      global $Repository;
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
       *    - recharge_space
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
       *       - print_added_boxes
       *       - recharge_details
       *       - download_recharge_file
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
      elseif (OPTIONS_REQUESTED_SUB_MODULE == 'recharge_space') $this->rechargeSpace(); //recharge storage space in the tanks
      elseif (OPTIONS_REQUESTED_SUB_MODULE == 'delete_box') $this->deleteBox (); // delete box from database (with or without it's metadata)
      elseif (OPTIONS_REQUESTED_SUB_MODULE == 'ajax' && OPTIONS_REQUESTED_ACTION == "get_tank_details") $this->getTankDetails ();
      elseif (OPTIONS_REQUESTED_SUB_MODULE == 'ajax' && OPTIONS_REQUESTED_ACTION == "fetch_boxes") $this->fetchBoxes ();
      elseif (OPTIONS_REQUESTED_SUB_MODULE == 'ajax' && OPTIONS_REQUESTED_ACTION == "search_boxes") $this->searchBoxes ();
      elseif(OPTIONS_REQUESTED_SUB_MODULE == 'ajax' && OPTIONS_REQUESTED_ACTION === "fetch_removed_boxes") $this->fetchRemovedBoxes ();
      else if(OPTIONS_REQUESTED_SUB_MODULE == 'ajax' && OPTIONS_REQUESTED_ACTION === "fetch_deleted_boxes") $this->fetchDeletedBoxes ();
      else if(OPTIONS_REQUESTED_SUB_MODULE == 'ajax' && OPTIONS_REQUESTED_ACTION === "submit_return_request") die($this->submitReturnRequest(TRUE));
      else if(OPTIONS_REQUESTED_SUB_MODULE == 'ajax' && OPTIONS_REQUESTED_ACTION === "print_added_boxes") die($this->printAddedBoxes());
      else if(OPTIONS_REQUESTED_SUB_MODULE == 'ajax' && OPTIONS_REQUESTED_ACTION === "submit_update_request"){
         //lets re-open the db connection with the right credentials
         Config::$config['user'] = Config::$config['rw_user']; Config::$config['pass'] = Config::$config['rw_pass'];
         $this->Dbase->InitializeConnection();
         if(is_null($this->Dbase->dbcon)) {
            ob_start();
            $Repository->LoginPage(OPTIONS_MSSG_DB_CON_ERROR);
            $Repository->errorPage = ob_get_contents();
            ob_end_clean();
            return;
         }
         die($this->updateBox());
      }
      else if(OPTIONS_REQUESTED_SUB_MODULE == 'ajax' && OPTIONS_REQUESTED_ACTION === "submit_delete_request") die($this->submitDeleteRequest(TRUE));
      else if(OPTIONS_REQUESTED_SUB_MODULE == 'ajax' && OPTIONS_REQUESTED_ACTION === "fetch_sample_types") $this->fetchSampleTypes ();
      else if(OPTIONS_REQUESTED_SUB_MODULE == 'ajax' && OPTIONS_REQUESTED_ACTION === "recharge_details") $this->getRechargeDetails();
      else if(OPTIONS_REQUESTED_SUB_MODULE == 'ajax' && OPTIONS_REQUESTED_ACTION === "download_recharge_file") $this->downloadRechargeFile();
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
         <!--li><a href="?page=box_storage&do=recharge_space">Recharge Storage Space</a></li-->
         <li><a href='?page=box_storage&do=delete_box'>Delete a box</a></li>
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
    * This function renders the Add Box page in this module. Also calls relevant functions to handle POST requests if any
    *
    * @param type    $addInfo Any notification info you want displayed to the user when page renders
    * @return null   Return called to initialize the render
    */
   private function addBox($addInfo = ''){
      Repository::jqGridFiles();//Really important if you want jqx to load
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
         <div class="form-group left-align" style="width: 220px;"><label for="features">Description (contents)</label><input type="text" name="features" id="features" /></div>
         <div class='left-align' style="width: 120px;">
            <label>Box Size</label>
            <div class="radio-inline"><label><input type="radio" name="box_size" id="size_81" value="81">9x9</label></div>
            <div class="radio-inline"><label><input type="radio" name="box_size" id="size_100" value="100">10x10</label></div>
         </div>
         <div class='form-group left-align' style="width: 100px;"><label for="no_samples">No. Samples</label><input type="number" name="no_samples" id="no_samples" style="width: 80px; height: 25px;"/></div>
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
      <div class="center" id="submit_button_div">
         <button type="submit" class="btn btn-success" style="margin-top: 20px;">Add Box</button>
         <button id="print_btn" type="button" class="btn btn-primary" style="margin-top: 20px;">Print Added Boxes</button>
      </div>
   </form>
</div>
<div id="project_print" style="position: absolute; display: none; background: white; z-index: 3; width: 20%; left: 40%; top: 40%; padding: 10px; border:0; border-radius:1px; box-shadow:0 1px 2px #aaa;">
   <label for="project_print_list">Select a Project: </label><select id="project_print_list"></select>
   <button id="print_btn_2" type="button" class="btn btn-primary" style="margin-top: 20px; float: right;">Print</button>
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
            //$("#project").prop('disabled', false);
         }
         else{
            $("#owner").prop('disabled', false);
            //$("#project").prop('disabled', 'disabled');
         }
      });
      $("#cancelAnchor").click(function (){
         $("#rack_spec_div").hide();
         $("#rack_div").show();
      });
      $("#print_btn").click(function(){
         //go though all the print boxes and select the unique projects
         
         var selectHTML = "";
         var projectIDs = new Array();
         for(var bIndex = 0; bIndex < Main.printBoxes.length; bIndex++){
            console.log(Main.printBoxes[bIndex].id);
            //if(jQuery.inArray(Main.printBoxes.data[bIndex].project_id, projectIDs) == -1){//not in array
            //   projectIDs.push(Main.printBoxes.data[bIndex].project_id);
            selectHTML = selectHTML + "<option value='"+Main.printBoxes[bIndex].id+"'>"+Main.printBoxes[bIndex].value+"</option>";
            //}
         }
         
         $("#project_print_list").empty();
         $("#project_print_list").html(selectHTML);
         
         $("#project_print").show();
         //BoxStorage.printBoxesBtnClicked();
      });
      $("#print_btn_2").click(function(){
         //get the selected project
         projectID = $("#project_print_list").val();
         
         BoxStorage.printBoxesBtnClicked(projectID);
         $("#project_print").hide();
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
      //check if box already preset
      
      //Repository::jqGridFiles();//load requisite jqGrid javascript files
?>
<!--script type="text/javascript" src="<?php //echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.pager.js"></script>
<script type="text/javascript" src="<?php //echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.columnsresize.js"></script>
<script type="text/javascript" src="<?php //echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxdropdownlist.js"></script-->
<script type='text/javascript' src="<?php echo OPTIONS_COMMON_FOLDER_PATH ?>jquery/jquery.ui/js/jquery-ui.min.js" /></script> <!-- used by autocomplete for the boxes label text field -->
<link rel='stylesheet' type='text/css' href='<?php echo OPTIONS_COMMON_FOLDER_PATH ?>jquery.ui/css/smoothness/jquery-ui.css' />
<?php
      if(OPTIONS_REQUESTED_ACTION === "submit_request"){
         $addInfo = $addInfo.$this->submitRemoveRequest();
      }
      
      if(isset($_GET['id']) && strlen($_GET['id']) > 0){
         $tmpBoxDetails = $this->getBoxDetails($_GET['id']);
         if(count($tmpBoxDetails) == 1){
            $boxDetails = $tmpBoxDetails[0];
         }
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
            <label for="box_label">Box label</label><input type="text" id="box_label" /><input type="hidden" id="box_id" name="box_id" />
         </div>
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
   <!--div id="retrieved_boxes"></div-->
</div>
<?php
      if(isset($boxDetails)){
?>
<script type="text/javascript">
   var positionData = jQuery.parseJSON('<?php echo json_encode($boxDetails);?>');
   $("#box_label").val(positionData.box_name);
   $("#box_id").val(positionData.box_id);
   BoxStorage.loadTankData(false, 1, positionData);
</script>
<?php
      }
      else{
?>
<script type="text/javascript">
   BoxStorage.loadTankData(false, 1);
</script>
<?php
      }
?>
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

      BoxStorage.setRetrievedBoxSuggestions();
      //BoxStorage.initiateRetrievedBoxesGrid();
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
<!--script type="text/javascript" src="<?php //echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.pager.js"></script>
<script type="text/javascript" src="<?php //echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxdropdownlist.js"></script>
<script type="text/javascript" src="<?php //echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.columnsresize.js"></script-->
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

   <!--div id="returned_boxes"></div-->
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
      //BoxStorage.initiateReturnedBoxesGrid();
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
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.sort.js"></script>
<script type='text/javascript' src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jquery.ui/js/jquery-ui.min.js" /></script> <!-- used by autocomplete for the boxes label text field -->
<link rel='stylesheet' type='text/css' href='<?php echo OPTIONS_COMMON_FOLDER_PATH ?>jquery.ui/css/smoothness/jquery-ui.css' />
<div id="box_storage">
   <h3 class="center">Search for a Box</h3>
   <div id="search_div">
      <!--legend>Box Information</legend-->
      <input type="text" id="search" /><button type="button" id="submitButton" class="btn btn-primary" style="margin-left: 20px;">Search</button><a href="#" id="advanced_search_a" style="margin-left: 30px;">Toggle Advanced search</a><a href="#" id="clear_a" style="margin-left: 30px;">Clear search</a>
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
      <div class="center" id="submit_button_div">
         <button type="button" class="btn btn-danger" id="cancel_button" style="margin-right: 20px;">Cancel</button>
         <button type="button" class="btn btn-success" id="edit_button" style="margin-right: 20px;">Update</button>
         <button type="button" class="btn btn-primary" id="retrieve_button" style="margin-right: 20px;">Retrieve Box</button>
         <button type="button" class="btn btn-primary" id="delete_button" style="">Delete Box</button>
      </div>
   </div>
</div>
<script type="text/javascript">
   $(document).ready(function(){
      BoxStorage.setSearchBoxSuggestions();
      BoxStorage.loadTankData(true);

      $('#submitButton').click(function(){
         BoxStorage.searchForBox();
      });
      
      Main.searchTimoutID = 0;
      $("#search").keyup( function(event) {
         window.clearTimeout(Main.searchTimoutID);
         if(event.which === 13){//enter pressed, search immediately
            BoxStorage.searchForBox();
         }
         else if(event.which === 8){//backspace pressed, wait for 500 milliseconds then search
            Main.searchTimoutID = window.setTimeout(BoxStorage.searchForBox, 500);
         }
         else{//any other key pressed
            if($("#search").val().length > 2){//length of query is more than 2, wait for 500 milliseconds then search
               Main.searchTimoutID = window.setTimeout(BoxStorage.searchForBox, 500);
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
      
      $('#clear_a').click(function (){
         BoxStorage.clearSearch();
      });

      BoxStorage.initiateSearchBoxesGrid();

      $('#cancel_button').click(function (){
         BoxStorage.toggleSearchModes();
      });
      $('#edit_button').click(function (){
         BoxStorage.submitBoxUpdate();
      });
      $('#retrieve_button').click(function(){
         BoxStorage.routeToRetrievePage();
      });
      $('#delete_button').click(function(){
         BoxStorage.routeToDeletePage();
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
    * This function displays the recharge space page
    */
   private function rechargeSpace(){
      Repository::jqGridFiles();//Really important if you want jqx to load
      
      $query = "select min(a.rc_period_ending) as min_period_ending, max(a.rc_period_ending) as max_period_ending, b.val_id, b.value, count(*) as box_number"
              . " from ".Config::$config['azizi_db'].".boxes_def as c"
              . " left join ".Config::$config['dbase'].".lcmod_boxes_def as a on c.box_id = a.box_id"//left joining with boxes in lims so that we can know boxes that dont have projects
              . " left join ".Config::$config['azizi_db'].".modules_custom_values as b on a.project=b.val_id"
              . " group by a.project";
      $projects = $this->Dbase->ExecuteQuery($query);
      
      $numOrphans = 0;
      for($i = 0; $i < count($projects); $i++){
         if($projects[$i]['val_id'] == null){
            $numOrphans = $numOrphans + $projects[$i]['box_number'];
            array_splice($projects, $i, 1);
         }
      }
      
      $query = "SELECT * FROM ".Config::$config['dbase'].".ln2_chargecodes";
      $dbCCodes = $this->Dbase->ExecuteQuery($query);
      if($dbCCodes == 1){
         $this->RepositoryHomePage("There was an error while fetching data from the database.");
         return;
      }
      $activityCodes = array();
      $chargeCodes = array();
      foreach ($dbCCodes as $currentP) {
         $activityCodes[] = $currentP['charge_code'];
         $chargeCodes[$currentP['charge_code']] = $currentP['name'];
      }
      if($numOrphans > 0){
         echo "<div class='center'>You have ".$numOrphans." boxes without projects</div>";
      }
?>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.pager.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxdropdownlist.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.columnsresize.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.sort.js"></script>
<script type='text/javascript' src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jquery.ui/js/jquery-ui.min.js" /></script> <!-- used by autocomplete for the boxes label text field -->
<link rel='stylesheet' type='text/css' href='<?php echo OPTIONS_COMMON_FOLDER_PATH ?>jquery.ui/css/smoothness/jquery-ui.css' />
<div id="box_storage">
   <h3 class="center">Recharge Storage Space</h3>
   <div id="recharge_div">
      <!--legend>Box Information</legend-->
      <div class="form-group left-align">
         <label for="project">Project</label>
         <select id="project" name="project" class="input-large">
            <option value=""></option>
            <?php
               foreach ($projects as $currProject) echo '<option value="' . $currProject['val_id'] . '">' . $currProject['value'] . " project</option>\n";
            ?>
         </select>
      </div>
      <div class="form-group left-align">
         <label for="period_starting">Period starting</label>
         <input id="period_starting" name="period_starting" disabled="disabled" class="input-large" />
      </div>
      <div class="form-group left-align">
         <label for="period_ending">Period ending</label>
         <input id="period_ending" name="period_ending" class="input-large" />
      </div>
      <div class="form-group left-align">
         <label for="price">Price per box per year (USD)</label>
         <input id="price" name="price" class="input-large" />
      </div>
      <div class="form-group left-align">
         <label for="charge_code">Charge code</label>
         <input id="charge_code" name="charge_code" disabled="disabled" class="input-large" />
      </div>
      <div class="form-group left-align">
         <label for="activity_code">Activity code</label>
         <input id="activity_code" name="activity_code" class="input-large" />
      </div>
   </div>
   <div class="center" id="submit_button_div">
      <button type="button" class="btn btn-primary" id="download_btn">Download File</button>
   </div>
   <div id="recharge_details_div" style="margin-top: 20px;"></div>
</div>
<script type="text/javascript">
   $(document).ready(function(){
      $( "#period_ending" ).datepicker({dateFormat: 'yy-mm-dd'});

      Main.rechargeProjects = <?php echo json_encode($projects); ?>;

      var activityCodes = <?php echo json_encode($activityCodes); ?>;
      var chargeCodes = <?php echo json_encode($chargeCodes); ?>;

      for(var i = 0; i < activityCodes.length; i++) {
         if(activityCodes[i] === null) {
            activityCodes.splice(i, 1);
            i--;
         }
      }

      $("#project").change(function(){
         //get the last period_ending and using as our period_starting
         var projectID = $("#project").val();
         for(var i = 0; i < Main.rechargeProjects.length; i++){
            if(Main.rechargeProjects[i].val_id == projectID){
               $("#period_starting").val(Main.rechargeProjects[i].min_period_ending);
               $("#period_ending").val("");
               $("#period_ending").datepicker('destroy');
               $("#period_ending" ).datepicker({dateFormat: 'yy-mm-dd', minDate: new Date(Main.rechargeProjects[i].max_period_ending)});
            }
         }

         BoxStorage.updateRechargeGrid();
      });
      $("#activity_code").autocomplete({
         source: activityCodes,
         minLength: 2,
         select: function (event, ui) {
            var value = ui.item.value;
            $("#charge_code").val(chargeCodes[value]);
         }
      });
      $("#period_ending").change(function(){
         BoxStorage.updateRechargeGrid();
      });
      $("#price").change(function(){
         BoxStorage.updateRechargeGrid();
      });
      
      $("#download_btn").click(function(){
         BoxStorage.downloadRechargingFile();
      });

      BoxStorage.initiateRechargeGrid();
      $('#whoisme .back').html('<a href=\'?page=home\'>Home</a> | <a href=\'?page=box_storage\'>Back</a>');//back link
   });
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
      
      if(isset($_GET['id'])){
         $tmpBoxData = $this->getBoxDetails($_GET['id']);
         
         if(count($tmpBoxData) == 1){
            $boxData = $tmpBoxData[0];
         }
      }
      
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
      BoxStorage.setDeleteBoxSuggestions(false);

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
<?php
   if(isset($boxData)){
?>
      var boxData = jQuery.parseJSON('<?php echo json_encode($boxData);?>');
      console.log(boxData);
      $("#box_label").val(boxData.box_name);
      $("#box_id").val(boxData.box_id);
      $("#tank").val(boxData.tank_name);
      $("#sector").val(boxData.sector_name);
      $("#rack").val(boxData.rack);
      $("#position").val(boxData.position);
<?php
   }
?>   
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
      if(strlen($_POST['box_label']) > 0
              && strlen($_POST['box_size']) > 0
              && strlen($_POST['no_samples']) > 0
              && strlen($_POST['status']) > 0
              && strlen($_POST['project']) > 0){
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

            /*$project = NULL;
            if($_POST['status'] === 'temporary')*/
               $project = $_POST['project'];
            $insertQuery = 'insert into '. Config::$config['dbase'] .'.lcmod_boxes_def(box_id, status, date_added, added_by, project, no_samples, rc_period_starting, rc_period_ending, rc_timestamp) values(:box_id, :status, :date_added, :added_by, :project, :no_samples, NOW(), NOW(), NOW())';
            $columns = array('box_id' => $boxId, 'status' => $_POST['status'], 'date_added' => $now, 'added_by' => $addedBy, 'project' => $project, 'no_samples' => $_POST['no_samples']);
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
      else {
         return "User provided incomplete data. Box not added to database";
      }
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
      
      //$_POST['status'], 'date_added' => $now, 'added_by' => $addedBy, 'project' => $project, 'box_id' => $_POST['box_id'], 'no_samples' => $_POST['no_samples']
      if(strlen($_POST['status']) > 0
              && strlen($_POST['project']) > 0
              && strlen($_POST['box_id']) > 0
              && strlen($_POST['no_samples']) > 0){
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

            /*$project = NULL;
            if($_POST['status'] === "temporary")*/
               $project = $_POST['project'];

            $updateQuery = 'insert into '. Config::$config['dbase'] .'.lcmod_boxes_def(box_id, status, date_added, added_by, project, no_samples, rc_period_starting, rc_period_ending, rc_timestamp) '.
                    'values(:box_id, :status, :date_added, :added_by, :project, :no_samples, NOW(), NOW(), NOW()) '.
                    'on duplicate key update status=values(status), date_added=values(date_added), added_by=values(added_by), project=values(project), no_samples=values(no_samples)';
            $columns = array('status' => $_POST['status'], 'date_added' => $now, 'added_by' => $addedBy, 'project' => $project, 'box_id' => $_POST['box_id'], 'no_samples' => $_POST['no_samples']);
            //$columnValues = array($boxId, $_POST['status'], $_POST['features'], $_POST['sample_types'], $now, $addedBy);
            $this->Dbase->CreateLogEntry('Update query = '.$updateQuery, 'debug');

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
      else {
         return json_encode(array("error" => 1, "message" => "User provided incomplete data. Box details not updated"));
      }
   }

   /**
    * This function handles POST requests from Retrieve a Box page. It records the data in the database
    *
    * @return string    Results for handling the remove box action in the database. Can be either positive or negative
    */
   private function submitRemoveRequest(){
      $message = "";
      
      if(strlen($_POST['for_who']) > 0
              && strlen($_POST['purpose']) > 0){
         
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
      else {
         return "User provided incomplete data. Retrieve not recorded";
      }
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
      
      if(strlen($_POST['remove_id']) > 0){
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
      else {
         if($fromAjaxRequest){
            $jsonArray = array();

            return json_encode(array("data" => $jsonArray, "error_message" => "User provided incomplete data. Return not recorded"));
         }
         else return "User provided incomplete data. Return not recorded";
      }
   }

   /**
    * This function handles POST request from Delete a Box page
    *
    * @param type $fromAjaxRequest  Set to TRUE if request is comming from Javascripts AJAX
    *
    * @return string Results for handling the delete box action in the database. Can be either positive or negative
    */
   private function submitDeleteRequest($fromAjaxRequest = false){
      if(strlen($_POST['box_id']) > 0){
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
      else {
         if($fromAjaxRequest){
            $jsonArray = array();
            return json_encode(array("data" => $jsonArray, "error_message" => "User provided incomplete data. Box not deleted"));
         }
         else {
            return "User provided incomplete data. Box not deleted";
         }
      }
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
      //fetch boxes added during this session
      $query = "SELECT * FROM ".  Config::$config['dbase'] . ".sessions WHERE session_id =:session_id";
      $result = $this->Dbase->ExecuteQuery($query, array('session_id' => session_id()));
      if(is_array($result) && count($result) === 1){//should fetch only data for one session
         $startTime = $result[0]['updated_at'];
         $query = "SELECT * FROM ".  Config::$config['dbase'] . ".sessions WHERE session_id = ";
         $fromRow = $_POST['pagenum'] * $_POST['pagesize'];
         $pageSize = $_POST['pagesize'];
         $this->Dbase->CreateLogEntry('mod_box_storage: start time = '.$startTime, 'debug');
         $query = 'select SQL_CALC_FOUND_ROWS a.box_id, a.status, date(a.date_added) as date_added, b.box_name, concat(c.facility, " >> ", b.rack, " >> ", b.rack_position) as position, login as added_by, e.value as project, e.val_id as project_id '.
                 'from '. Config::$config['dbase'] .'.lcmod_boxes_def as a '.
                 'inner join '. Config::$config['azizi_db'] .'.boxes_def as b on a.box_id = b.box_id '.
                 'inner join '. Config::$config['azizi_db'] .'.boxes_local_def as c on b.location = c.id '.
                 'inner join '. Config::$config['dbase'] .'.users as d on a.added_by = d.id '.
                 'left join '. Config::$config['dbase'] .'.modules_custom_values as e on a.project=e.val_id '.
                 'where a.date_added >= "' . date( 'Y-m-d', $_SERVER['REQUEST_TIME']) . '" './/fetch boxes inserted today
                 'limit '.$fromRow.','.$pageSize;

         $this->Dbase->CreateLogEntry('mod_box_storage: fetch boxes query = '.$query, 'debug');

         $result = $this->Dbase->ExecuteQuery($query, array("start_time" => $startTime));
         if($result == 1)  die(json_decode(array('data' => $this->Dbase->lastError)));

         $query = "SELECT FOUND_ROWS() AS found_rows";
         $foundRows = $this->Dbase->ExecuteQuery($query);
         $totalRowCount = $foundRows[0]['found_rows'];

         if(count($result) > 0){
            $result[0]['total_row_count'] = $totalRowCount;
         }
         $query = "select a.project as id,b.value from ".Config::$config['dbase'].".lcmod_boxes_def as a inner join ".Config::$config['azizi_db'].".modules_custom_values as b on a.project=b.val_id where date(date_added) = date(now()) group by project";
         $projects = $this->Dbase->ExecuteQuery($query);

         header("Content-type: application/json");
         die('{"data":'. json_encode($result).',"projects":'.json_encode($projects).'}');
      }
      else{
         die('{"data":'. json_encode(array()) .'}');
      }

   }

   /**
    * This function searches for boxes using certain criteria
    */
   private function searchBoxes() {
      
      $fromRow = "&start=".$_POST['pagenum'] * $_POST['pagesize'];
      $pageSize = "&count=".$_POST['pagesize'];
      
      $sortColumn = $_POST['sort_column'];
      if($sortColumn == "status"){
         $sortColumn = "box_status";
      }
      
      $sort = $sortColumn." ".$_POST['sort_direction'];
      if($sort == " "){//user did not define any sort column
         $sort = "";
      }
      else {
         $sort = "&sort=".$sort;
      }
      
      $qURL = Config::$config['solr_boxes']."/select?wt=json".$fromRow.$pageSize.$sort."&q=";
      //begin building solr query string
      
      $sQuery = "";
      
      // 1. box name
      $concat = "";
      if(strlen($sQuery) > 0) $concat = " AND";
      if($_POST['boxes_wo_names'] == "true"){   
         $sQuery .= $concat."box_name:'NULL'";
      }
      else if($_POST['boxes_wo_names'] === "false"){
         //remove whitespaces from search
         $search = $_POST['search'];
         
         $search = preg_replace("/[^a-zA-Z0-9]/", "", $search);
         
         if(strlen($search) > 0){
            $sQuery .= $concat."(box_name:*".$search."*";//the stars are wildcards
            $sQuery .= " OR box_name:".$search."~1)";//performs fuzzy search with, the number after the tilde is the similarity with 1 being most similar and 0 not similar
         }
      }

      

      // 2. box status
      $concat = "";
      if(strlen($sQuery) > 0) $concat = " AND ";
      if(strlen($_POST['status']) > 0){
         $sQuery .= $concat."box_status:".$_POST['status'];
      }
      
      // 3. location
      $concat = "";
      if(strlen($sQuery) > 0) $concat = " AND ";
      if($_POST['location'] == "wo_location"){
         $sQuery .= $concat."sector_name:'NULL'";
      }
      else if($_POST['location'] == "wo_rack"){
         $sQuery .= $concat."rack:'NULL'";
      }
      else if($_POST['location'] == "wo_rack_pos"){
         $sQuery .= $concat."rack_position:'NULL'";
      }
      
      // 4. owner
      $concat = "";
      if(strlen($sQuery) > 0) $concat = " AND ";
      if(strlen($_POST['keeper'])>0){
         $sQuery .= $concat."owner_id:".$_POST['keeper'];
      }
      
      
      // 5. project (get that from the solr samples core)
      $concat = "";
      if(strlen($sQuery) > 0) $concat = " AND ";
      if($_POST['project'] == "-2"){//user wants boxes not associated with any project
         $sQuery .= $concat."no_projects:0";
      }
      else if($_POST['project'] == "-1"){//user wants boxes associated with more than one project
         $sQuery .= $concat."no_projects:[2 TO *]";
      }
      else if(strlen($_POST['project']) > 0){//user wants boxes associated to a particular project
         $sQuery .= $concat."no_projects:1 AND project_id:".$_POST['project'];
      }
      
      // 6. no samples (get that from the solr samples core)
      $concat = "";
      if(strlen($sQuery) > 0) $concat = " AND ";
      if($_POST['samples'] == "ex_samples"){//user wants boxes with excess samples
         $sQuery .= $concat."((box_size:100 AND no_samples:[101 TO *]) OR (box_size:81 AND no_samples:[82 TO *]))";
      }
      else if($_POST['samples'] == "wo_samples"){
         $sQuery .= $concat."no_samples:0";
      }
      
      //if query still not set, get all boxes
      if(strlen($sQuery) == 0) $sQuery = "*:*";
      
      //get the tings from the solr
      $ch = curl_init();
      
      $fullQURL = str_replace(" ", "+", urldecode($qURL.$sQuery));
      curl_setopt($ch, CURLOPT_URL, $fullQURL);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_USERAGENT, "Codular Sample cURL Request");
      
      $curlResult = curl_exec($ch);
      
      $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);
      
      $data = array();
      $data['data'] = array();
      
      $this->Dbase->CreateLogEntry("URL = ".$fullQURL, "fatal");
      
      if($http_status == 200){
         $solrResponse = json_decode($curlResult, true);
         
         //$this->Dbase->CreateLogEntry(print_r($solrResponse['response'], true), "fatal");
         $totalResults = $solrResponse["response"]['numFound'];
         
         $resIndex = 0;
         for($index = 0; $index < count($solrResponse["response"]["docs"]); $index++){
            $data['data'][$resIndex] = $solrResponse["response"]["docs"][$index];
            $data['data'][$resIndex]['keeper'] = $solrResponse["response"]["docs"][$index]['owner_id'];
            if(strlen($solrResponse["response"]["docs"][$index]['rack']) > 0){
               $data['data'][$resIndex]['position'] = $solrResponse["response"]["docs"][$index]['sector_name']." >> ".$solrResponse["response"]["docs"][$index]['rack'];
               if(strlen($solrResponse["response"]["docs"][$index]['rack_position']) > 0){
                  $data['data'][$resIndex]['position'] .= " >> ".$solrResponse["response"]["docs"][$index]['rack_position'];
               }
            }
            //$data['data'][$index]['position'] = $solrResponse["response"]["docs"][$index]['sector_name']." >> ".$solrResponse["response"]["docs"][$index]['rack']." >> ".$solrResponse["response"]["docs"][$index]['rack_position'];
            $data['data'][$resIndex]['size'] = $solrResponse["response"]["docs"][$index]['box_size'];
            $data['data'][$resIndex]['status'] = $solrResponse["response"]["docs"][$index]['box_status'];
            
            $data['data'][$resIndex]['date_added'] = str_replace("Z", "", str_replace("T", " ", $solrResponse["response"]["docs"][$index]['date_added']));
          
            $data['data'][$resIndex]['total_row_count'] = $totalResults;
            
            $resIndex++;
         }
      }
      else {
         $this->Dbase->CreateLogEntry("Something went wrong when trying to access the solr server", "fatal");
         $this->Dbase->CreateLogEntry("URL = ".$fullQURL, "fatal");
         $this->Dbase->CreateLogEntry("result = ".$curlResult, "fatal");
         $this->Dbase->CreateLogEntry("http status = ".$http_status, "fatal");
      }
      header("Content-type: application/json");
      die(json_encode($data));
   }
   
   /*private function processBoxSamples($boxID, $boxSize, $action){
      $sURL = Config::$config['solr_samples']."/select?wt=json&q=box_id:".$boxID;
      $ch = curl_init();
      
      $fullQURL = str_replace(" ", "+", urldecode($sURL));
      curl_setopt($ch, CURLOPT_URL, $fullQURL);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_USERAGENT, "Codular Sample cURL Request");
      
      $curlResult = curl_exec($ch);
      
      $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);
      
      if($http_status == 200){
         $boxSamples = json_decode($curlResult, true);
         $noSamples = $boxSamples["response"]['numFound'];
         
         
         //$this->Dbase->CreateLogEntry("no found = ".$noSamples, "fatal");
         //$this->Dbase->CreateLogEntry(print_r($boxSamples, true), "fatal");
//         /$this->Dbase->CreateLogEntry($fullQURL, "fatal");
         
         if($action == "ex_samples"){//user wants to know if box has excess samples
            if($noSamples > $boxSize){
               $this->Dbase->CreateLogEntry("box size = ".$boxSize, "fatal");
               $this->Dbase->CreateLogEntry("no samples = ".$noSamples, "fatal");
               return true;
            }
            else {
               $this->Dbase->CreateLogEntry("box has a good number of samples", "fatal");
               $this->Dbase->CreateLogEntry("box size = ".$boxSize, "fatal");
               $this->Dbase->CreateLogEntry("no samples = ".$noSamples, "fatal");
            }
         }
         
         if($action == "wo_samples"){//user wants to know if box has no samples
            if($noSamples == 0){
               return true;
            }
         }
      }
      else{
         $this->Dbase->CreateLogEntry("Something went wrong when trying to access the solr server", "fatal");
         $this->Dbase->CreateLogEntry("URL = ".$fullQURL, "fatal");
         $this->Dbase->CreateLogEntry("result = ".$curlResult, "fatal");
         $this->Dbase->CreateLogEntry("http status = ".$http_status, "fatal");
      }
      
      return false;
   }
   
   private function processBoxProjects($boxID, $action){
      $sURL = Config::$config['solr_samples']."/select?wt=json&q=box_id:".$boxID;
      $ch = curl_init();
      
      $fullQURL = str_replace(" ", "+", urldecode($sURL));
      curl_setopt($ch, CURLOPT_URL, $fullQURL);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_USERAGENT, "Codular Sample cURL Request");
      
      $curlResult = curl_exec($ch);
      
      $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);
      
      if($http_status == 200){
         $boxSamples = json_decode($curlResult, true);
         $samples = $boxSamples["response"]['docs'];
         $noSamples = $boxSamples["response"]['numFound'];
         if($action == "-2"){//user wants boxes not associated with any project
            if($noSamples == 0){
               return true;
            }
         }
         else if($action == "-1"){//user wants boxes associated with more than one project
            $projects = array();
            
            for($sIndex = 0; $sIndex < count($samples); $sIndex++){
               if(array_search($samples[$sIndex]['project_id']) === false){//sample's project not in array
                  array_push($projects, $samples[$sIndex]['project_id']);
                  if(count($projects) > 1){//box already associated with more than one project
                     return true;
                  }
               }
            }
         }
      }
      else{
         $this->Dbase->CreateLogEntry("Something went wrong when trying to access the solr server", "fatal");
         $this->Dbase->CreateLogEntry("URL = ".$fullQURL, "fatal");
         $this->Dbase->CreateLogEntry("result = ".$curlResult, "fatal");
         $this->Dbase->CreateLogEntry("http status = ".$http_status, "fatal");
      }
      
      return false;
   }*/

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
   
   private function printAddedBoxes(){
      $projectID = $_GET['projectID'];
      $query = "select box_id from ".Config::$config['dbase'].".lcmod_boxes_def where project = :project and date(date_added) = date(now())";//get all boxes added today for the selected project
      $boxIDs = $this->Dbase->ExecuteQuery($query, array("project" => $projectID));
      
      $this->Dbase->CreateLogEntry(print_r($boxIDs, true), "fatal");
      
      $boxData = array();
      
      foreach ($boxIDs as $currBox){
          $query = 'select a.box_id, b.box_name, a.no_samples, c.facility, concat(b.rack, " >> ", b.rack_position) as position, b.box_features '.
                 'from '. Config::$config['dbase'] .'.lcmod_boxes_def as a '.
                 'inner join '. Config::$config['azizi_db'] .'.boxes_def as b on a.box_id = b.box_id '.
                 'inner join '. Config::$config['azizi_db'] .'.boxes_local_def as c on b.location = c.id '.
                 'where a.box_id = :id';
          $result = $this->Dbase->ExecuteQuery($query, array("id" => $currBox['box_id']));
          
          if(is_array($result)){
             preg_match("/.*Tank([0-9]+).+/i", $result[0]['facility'], $tank);
             if(is_array($tank) && isset($tank[1])){
                $result[0]['position'] = "T" . $tank[1] . " >> " . $result[0]['position'];
             }
             else {
                $this->Dbase->CreateLogEntry("An error occurred while trying to get the tank name".preg_last_error(), "fatal");
             }
             
             array_push($boxData, $result[0]);
          }
          else {
             $this->Dbase->CreateLogEntry("Problem occurred while trying to get box data for printing", "fatal");
          }
      }
      
      $today = date('d M Y');
      $time = date('h:i A');
      $fullName = $_SESSION['onames'] . " " . $_SESSION['surname'];
      $hash = md5($today.$time.$_SESSION['username']);
      $pageName = $hash.".php";
      
      $boxHTML = "";
      foreach($boxData as $currBox){
         $boxHTML .= "<tr style='background-color: #e0e1ec;'>";
         $boxHTML .= "<td style='padding-left: 35px;' height='30'>".$currBox['box_name']."</td><td style='text-align: center;'>".$currBox['no_samples']."</td><td style='text-align: center;'>".$currBox['position']."</td><td style='text-align: center;'>".$currBox['box_features']."</td>";
         $boxHTML .= "</tr>";
      }
      $this->Dbase->CreateLogEntry($boxHTML, "fatal");
      
      $template = "<!DOCTYPE HTML PUBLIC '-//W3C//DTD HTML 4.01 Transitional//EN'>
<html style='color: #333333;'>
   <head>
      <title>Added Boxes</title>
      <link href='http://fonts.googleapis.com/css?family=Open+Sans:400,700' rel='stylesheet' type='text/css'>
      <style type='text/css'>
         .invoiceTable td, .invoiceTable th {
            border: 1px solid #333333;
         }
      </style>
   </head>
   <body style='font-family:Open Sans,sans-serif'>
      <div style='position: absolute; top: 10px; left: 20px; width: 50px'>
         <img src='../images/WTPlogo.jpg' style='width: 100px; height: 100px;'/>
      </div>
      <div style='position: absolute; top: 10px; left: 220px; width: 480px'>
         <h1 style='position: absolute; top: 20px; left: 120px;'>Azizi Biorepository</h1>
      </div>
      <div style='position: absolute; top: 95px; left: 340px; width 600px;'>
         <p style='font-size: 11px; font-style: italic;'>Ensuring proper sample storage with high quality metadata</p>
      </div>
      <div style='position: absolute; top: 40px; left: 700px; text-align: right; width: 300px;'>
         <p style='font-size: 16px;'>Boxes Received<br/>
         ".$today.", ".$time."</p>
      </div>

      <div style='position: absolute; top: 180px; left: 100px; width: 800px;'>
         <table cellpadding='1' style='border: 1px solid #333333; border-collapse: collapse;' class='invoiceTable'>
            <tr style='background-color: #b0b6f1;'>
               <th width='180' height='40' style='text-align: left; padding-left: 20px;'>Box name</th><th width='180'>No. Samples</th><th width='300'>Storage Position</th><th width='550'>Description</th>
            </tr>". 
            $boxHTML."</table>
         <div style='margin-top:20px; left: 340px; margin-left:40px;'>
            <div style='height: 15px; width: auto; line-height: 15px; margin-top: 20px; margin-right: 10px;'>Received By: ".$fullName."</div>
            <div style='height: 15px; width: auto; line-height: 15px; margin-top: 20px; margin-right: 10px;'>Received Date: ".$today.", ".$time."</div>
            <div style='height: 15px; width: auto; line-height: 15px; margin-top: 20px; margin-right: 10px;'>Delivered By (Name): </div>
            <div style='height: 15px; width: auto; line-height: 15px; margin-top: 20px; margin-right: 10px;'>Delivered By (Signature): </div>
            <div style='height: 15px; width: auto; line-height: 15px; margin-top: 20px; margin-right: 10px;'>Remarks: </div>
         <div>
      </div>
   </body>
</html>";
      
      if(!file_exists('./generated_pages')){
         mkdir('./generated_pages', 0777, true);
      }
      file_put_contents("./generated_pages/" . $pageName, $template);
      $pdfName = "Received Samples ".$today." - ".$time;
      
      shell_exec(Config::$xvfb ." ". Config::$wkhtmltopdf . " http://" . $_SERVER['HTTP_HOST'] . Config::$baseURI . "generated_pages/" . $pageName . " '/tmp/" . $pdfName . ".pdf'");
      //unlink("./generated_pages/" . $pageName);
      $this->Dbase->CreateLogEntry("./generated_pages/" . $pageName, "fatal");
      
      /*header('Content-type: application/pdf');
      header('Content-Disposition: attachment; filename="' . basename(" '/tmp/" . $pdfName . ".pdf'") . '"');
      header('Content-Transfer-Encoding: binary');
      readfile(" '/tmp/" . $pdfName . ".pdf'");*/
      
      $this->Dbase->CreateLogEntry("pdf size : ".filesize("/tmp/" . $pdfName . ".pdf"), "fatal");
      
      header('Content-type: application/pdf');
      header('Content-Disposition: attachment; filename='. $pdfName . ".pdf");
      header("Expires: 0"); 
      header("Cache-Control: must-revalidate, post-check=0, pre-check=0"); 
      header("Content-length: " . filesize("/tmp/" . $pdfName . ".pdf"));
      header('Content-Transfer-Encoding: binary');
      readfile("/tmp/" . $pdfName . ".pdf");
      unlink("/tmp/" . $pdfName . ".pdf");
   }
   
   /**
    * This function gets details for a box using it's id
    */
   private function getBoxDetails($boxID){
      /*
       * Select rack position, sector and tank id
       */
      
      $query = 'select b.box_id, b.box_name, b.rack, b.rack_position as position, c.id as sector, c.facility as sector_name, c.facility_id as tank, d.name as tank_name '.
              'from '. Config::$config['azizi_db'] .'.boxes_def as b './/optimization: use inner join to fetch only boxes in LN2 tanks and not the freezers etc
              'left join '. Config::$config['azizi_db'] .'.boxes_local_def as c on b.location = c.id './/fetch all boxes regardless of wether sector (boxes_local_def) is defined or not
              'left join '. Config::$config['azizi_db'] .'.storage_facilities as d on c.facility_id = d.id '.
              'where b.box_id = :boxID';//select boxes which are not associated to any sector and those in LN2 tanks
      $result = $this->Dbase->ExecuteQuery($query, array("boxID" => $boxID));
      
      if($result == 1){
         $this->Dbase->CreateLogEntry("Error occurred while trying to get box details", "fatal");
         $result = array();
      }
      
      return $result;
   }
   
   /**
    * This function gets recharging details for a particular project
    */
   private function getRechargeDetails(){
      $projectID = $_POST['project'];
      $priceBoxDay = $_POST['price']/365;//price per box per day
      $periodEnding = $_POST['period_ending'];//date in format yyyy-mm-dd

      $query = "select a.rc_period_ending as last_period, b.value as project, count(*) as no_boxes"
              . " from ".Config::$config['dbase'].".lcmod_boxes_def as a"
              . " left join ".Config::$config['azizi_db'].".modules_custom_values as b on a.project=b.val_id"
              . " where a.project = :project"
              . " group by a.rc_period_ending";
      $result = $this->Dbase->ExecuteQuery($query, array("project" => $projectID));
      
      if($result == 1){
         $this->Dbase->CreateLogEntry("An error occurred while trying to get recharge details from the database. Sending user nothing","fatal");
         $result = array();
      }
      
      for($i = 0; $i < count($result); $i++){
         //calculate the number of days between last charged date and current recharge end date
         //charge nothing if last charged date not set
         $start = strtotime($result[$i]['last_period']);
         $end = strtotime($periodEnding);
         if($start != false && $end != false && $result[$i]['last_period'] != "0000-00-00"){
            //get number of days
            $duration = ($end - $start)/86400;
            $result[$i]['duration'] = $duration;
         }
         else {
            $result[$i]['duration'] = 0;
         }
         
         //calculate the total recharge price
         $result[$i]['total_price'] = round($result[$i]['duration'] * $priceBoxDay * $result[$i]['no_boxes'], 2);
      }
      
      $json = array('data'=>$result);
      die(json_encode($json));
   }
   
   /**
    * This function generates a csv file containing data for space recharges to the specified (in get request) project
    */
   private function downloadRechargeFile(){
      $projectID= $_REQUEST['project'];
      $periodEnding = $_REQUEST['period_ending'];
      $pricePerBoxPerDay = $_REQUEST['price']/365;
      $chargeCode = $_REQUEST['charge_code'];
      if(strlen($chargeCode) == 0){
         $chargeCode = $_REQUEST['activity_code'];
      }
      
      if(strlen($projectID) > 0 && strlen($periodEnding) > 0 && strlen($pricePerBoxPerDay) > 0 && strlen($chargeCode) > 0){
//         $query = "select count(*) as no_boxes, c.value as project, d.facility as sector, a.rc_period_ending as start_date"
//                 . " from ".Config::$config['dbase'].".lcmod_boxes_def as a"
//                 . " inner join ".Config::$config['azizi_db'].".boxes_def as b on a.box_id=b.box_id"
//                 . " inner join ".Config::$config['dbase'].".modules_custom_values as c on a.project=c.val_id"
//                 . " inner join ".Config::$config['dbase'].".boxes_local_def as d on b.location=d.id"
//                 . " where a.project = :project"
//                 . " group by b.location, a.rc_period_ending";
         $query = "select b.value as project, count(*) as no_boxes, d.facility as sector, group_concat(c.box_id) as box_ids, a.rc_period_ending as start_date"
                 . " from ".Config::$config['azizi_db'].".boxes_def as c"
                 . " left join ".Config::$config['dbase'].".lcmod_boxes_def as a on c.box_id = a.box_id"
                 . " left join ".Config::$config['azizi_db'].".modules_custom_values as b on a.project=b.val_id"
                 . " left join ".Config::$config['azizi_db'].".boxes_local_def as d on c.location = d.id"
                 . " where a.project = :project and a.rc_period_ending != '0000-00-00'"
                 . " group by a.rc_period_ending, d.id";
         
         $this->Dbase->CreateLogEntry($query, "info");
         $result = $this->Dbase->ExecuteQuery($query, array("project" => $projectID));
         
         if($result == 1){
            $this->Dbase->CreateLogEntry("Unable to get details of boxes for recharging from the database. Sending empty file", "fatal");
            $result = array();
         }
         else {
            $allBoxIDs = array();
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
               $result[$i]['end_date'] = $_REQUEST['period_ending'];
               $result[$i]['price_per_box'] = $_REQUEST['price'];
               $result[$i]['total'] = $total;
               $result[$i]['charge_code'] = $chargeCode;
               
               $allBoxIDs[] = $result[$i]['box_ids'];//list of box ids seperated using commas
            }
            
            $this->Dbase->CreateLogEntry(print_r($result,true), "info");
            
            //headings should be in the order of respective items in associative array
            $headings = array(
                "project" => "Project",
                "no_boxes" => "No. Boxes",
                "sector" => "Sector",
                "box_ids" => "Box IDs",
                "start_date" => "Period Starting",
                "duration" => "Duration (days)",
                "end_date" => "Period Ending",
                "price_per_box" => "Price per Box",
                "total" => "Total Cost (USD)",
                "charge_code" => "Charge Code");
            $csv = $this->generateCSV(array_merge(array($headings), $result), false);
            
            if(count($result) > 0){
               $fileName = "space_recharge_".$result[0]['project']."_".$result[0]['end_date'].".csv";
               
               $emailSubject = "Storage space recharge for ".$result[0]['project'];
               $emailBody = "Find attached a csv file containing data for storage space recharge to ".$result[0]['project']." for the period ending ".$result[0]['end_date'].".";
            }
            else {
               $fileName = "no_data.csv";
               
               $query = "select value from ".Config::$config['azizi_db'].".modules_custom_values where val_id = :project_id";
               $projectName = $this->Dbase->ExecuteQuery($query, array("project_id" => $projectID));
               
               $emailSubject = "Storage space recharge for ".$projectName[0]['value'];
               $emailBody = "Could not file boxes owned by ".$projectName[0]['value']." for storage recharging for the period ending ".$periodEnding.". This might mean the column sc_period_ending for all the boxes associated to this project are null or set to '0000-00-00'. Make sure you record the last date of storage recharge for all the boxes, or you'll end up losing money ;) .";
            }
            
            //update all the boxes 
            for($i = 0; $i < count($allBoxIDs); $i++){
               $query = "update ".Config::$config['dbase'].".lcmod_boxes_def"
                       . " set rc_timestamp = now(), rc_period_starting = rc_period_ending, rc_period_ending = :ending, rc_price = :price, rc_charge_code = :charge_code"
                       . " where box_id in(:box_ids)";
               $this->Dbase->ExecuteQuery($query, array("ending" => $_REQUEST['period_ending'], "price" => $_REQUEST['price'], "charge_code" => $chargeCode, "box_ids" => $allBoxIDs[$i]));
               $this->Dbase->CreateLogEntry("updated box ids = ".$allBoxIDs[$i], "info");
            }
            
            file_put_contents("/tmp/".$fileName, $csv);
            header('Content-type: document');
            header('Content-Disposition: attachment; filename='. $fileName);
            header("Expires: 0"); 
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0"); 
            header("Content-length: " . filesize("/tmp/".$fileName));
            header('Content-Transfer-Encoding: binary');
            readfile("/tmp/" . $fileName);
            
            $this->sendRechargeEmail(Config::$managerEmail, $emailSubject, $emailBody, "/tmp/".$fileName);
            
            $this->Dbase->CreateLogEntry("Recharging file at /tmp/".$fileName, "info");
            unlink("/tmp/" . $fileName);
            
            //return json_encode(array("error" => false, "error_message" => ""));
         }
         //return json_encode(array("error" => true, "error_message" => "Problem getting data from the database"));
      }
      
      $this->Dbase->CreateLogEntry("Problem with the data provided by user".print_r($_REQUEST, true), "fatal");
      
      //return json_encode(array("error" => true, "error_message" => "Problem with the data you provided"));
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
   private function sendRechargeEmail($address, $subject, $message, $file = null){
      if($file != null){
         shell_exec('echo "'.$message.'"|'.Config::$muttBinary.' -F '.Config::$muttConfig.' -s "'.$subject.'" -a '.$file.' -- '.$address);
      }
      else {
         shell_exec('echo "'.$message.'"|'.Config::$muttBinary.' -F '.Config::$muttConfig.' -s "'.$subject.'" -- '.$address);
      }
   }
}
?>
