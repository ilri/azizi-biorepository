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
       *       - fetch_removed_boxes
       *       - submit_return_request
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
      elseif(OPTIONS_REQUESTED_SUB_MODULE == 'ajax' && OPTIONS_REQUESTED_ACTION === "fetch_removed_boxes") $this->fetchRemovedBoxes ();
      else if(OPTIONS_REQUESTED_SUB_MODULE == 'ajax' && OPTIONS_REQUESTED_ACTION === "fetch_deleted_boxes") $this->fetchDeletedBoxes ();
      else if(OPTIONS_REQUESTED_SUB_MODULE == 'ajax' && OPTIONS_REQUESTED_ACTION === "submit_return_request") die($this->submitReturnRequest(TRUE));
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
      $keepers = $this->Dbase->ExecuteQuery($query);
      if($keepers == 1){
         $this->RepositoryHomePage($this->Dbase->lastError);
         return;
      }
      $query = "SELECT count, description FROM ".Config::$config['azizi_db'].".sample_types_def WHERE description != ''";
      $sampleTypes = $this->Dbase->ExecuteQuery($query);
      if($sampleTypes == 1){
         $this->RepositoryHomePage($this->Dbase->lastError);
         return;
      }
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
         <div class='left-align'>
            <label for="sample_types">Sample Types</label>
            <select name="sample_types" id="sample_types" class='form-control'>
            <?php
                  echo '<option value=""></option>';//add the first blank option
                  foreach($sampleTypes as $sampleType) echo '<option value="'. $sampleType['count'] .'">'. $sampleType['description'] ."</option>\n";
              ?>
            </select>
         </div>
         <div class="left-align">
            <label for="project">Project</label>
            <select id="project" name="project">
               <option value=""></option>
               <?php
                  foreach ($projects as $currProject) echo '<option value="' . $currProject['val_id'] . '">' . $currProject['value'] . "</option>\n";
               ?>
            </select>
         </div>
      </div>

      <div id="box_location">
         <div class="form-group left-align">
            <label for="tank">Tank</label>
            <select id="tank" class="input-medium">
               <option value=""></option>
            </select>
         </div>
         <div class="form-group left-align">
            <label for="sector">Sector</label>
            <select id="sector" name="sector" disabled="disabled">
               <!--Disabled until parent select is selected-->
            </select>
         </div>
         <div id="rack_div" class="form-group left-align">
            <label for="rack">Rack</label>
            <select type="text" name="rack" id="rack" disabled="disabled">
               <!--Disabled until parent select is selected-->
            </select>
         </div>
         <div id="rack_spec_div" class="form-group left-align hidden" style="width: 160px;">
            <label for="rack">Rack</label>
            <input type="text" id="rack_spec" name="rack_spec" /><a href="#" id="cancelAnchor" ><img src='images/close.png' /></a>
         </div>
         <div class="form-group left-align">
            <label for="position">Position in Rack</label>
            <select type="text" name="position" id="position" disabled="disabled"><!--Disabled until parent select is selected-->
            </select>
         </div>
         <div class="form-group left-align">
            <label for="status">Status</label>
            <select type="text" name="status" id="status">
               <option value=""></option><!--NULL option-->
               <option value="Temporary">Temporary</option>
               <option value="Permanent">Permanent</option>
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
   });
   $('#whoisme .back').html('<a href=\'?page=home\'>Home</a> | <a href=\'?page=box_storage\'>Back</a>');//back link

   $("#status").change(function(){
      if($('#status').val() === "temporary"){
         //if user sets position to temporary set owner to biorepository manager
         $("#owner").prop('disabled', 'disabled');
      }
      else{
         $("#owner").prop('disabled', false);
      }
   });
   $("#cancelAnchor").click(function (){
      $("#rack_spec_div").hide();
      $("#rack_div").show();
   });
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
<script type='text/javascript' src="<?php echo OPTIONS_COMMON_FOLDER_PATH ?>jquery/jquery.ui/js/jquery-ui.min.js" /></script> <!-- used by autocomplete for the boxes label text field -->

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
      $query = "SELECT count, name FROM ".Config::$config['azizi_db'].".contacts WHERE name != ''";
      $keepers = $this->Dbase->ExecuteQuery($query);
      ?>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.pager.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxdropdownlist.js"></script>
<script type='text/javascript' src="<?php echo OPTIONS_COMMON_FOLDER_PATH ?>jquery/jquery.ui/js/jquery-ui.min.js" /></script> <!-- used by autocomplete for the boxes label text field -->
<div id="box_storage">
   <h3 class="center">Search for a Box</h3>
   <div id="search_div">
      <!--legend>Box Information</legend-->
      <input type="text" id="search" /><button type="button" id="submitButton" class="btn btn-success" style="margin-left: 20px;">Search</button><a href="#" id="advanced_search_a" style="margin-left: 30px;">Toggle Advanced search</a>
      <div id="advanced_search_div" style="display: none;">
         <div class="search_criteria">
            <label for="projecs">Project</label>
            <select id="project">
               <option value=""></option>
               <option value="-1">Boxes with projects</option>
               <option value="-2">Boxes without projects</option>
               <?php
                  foreach ($projects as $currProject) echo '<option value="' . $currProject['val_id'] . '">From ' . $currProject['value'] . " project</option>\n";
               ?>
            </select>
         </div>
         <div class="search_criteria">
            <label for="status">Status</label>
            <select id="status">
               <option value=""></option>
               <option value="temporary">Temporary</option>
               <option value="">Permanent</option>
            </select>
         </div>
         <div class="search_criteria">
            <label for="location">Location</label>
            <select id="location">
               <option value=""></option>
               <option value="wi_location">With a location</option>
               <option value="wo_location">Without a location</option>
            </select>
         </div>
         <div class="search_criteria">
            <label for="keeper">Sample keeper</label>
            <select id="keeper">
               <option value=""></option>
               <?php
                  foreach ($keepers as $currKeeper) echo '<option value="' . $currKeeper['count'] . '">' . $currKeeper['name'] . "</option>\n";
               ?>
            </select>
         </div>
      </div>
   </div>

   <div id="searched_boxes"></div>
</div>
<script type="text/javascript">
   $(document).ready(function(){
      BoxStorage.setSearchBoxSuggestions();

      $('#submitButton').click(function(){
         BoxStorage.searchForBox();
      });
      
      $('#advanced_search_a').click(function (){
         BoxStorage.toggleAdvancedSearch();
      });
      
      BoxStorage.initiateSearchBoxesGrid();
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
<script type='text/javascript' src="<?php echo OPTIONS_COMMON_FOLDER_PATH ?>jquery/jquery.ui/js/jquery-ui.min.js" /></script> <!-- used by autocomplete for the boxes label text field -->
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

      //get the user id
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

         $insertQuery = 'insert into '. Config::$config['dbase'] .'.lcmod_boxes_def(box_id, status, sample_types, date_added, added_by, project) values(:box_id, :status, :sample_types, :date_added, :added_by, :project)';
         $columns = array('box_id' => $boxId, 'status' => $_POST['status'], 'sample_types' => $_POST['sample_types'], 'date_added' => $now, 'added_by' => $addedBy, 'project' => $_POST['project']);
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
      echo json_encode($jsonArray);
   }

   /**
    * This function fetched boxes added to the system from the "Add a Box" page and returns a json object with this info
    */
   private function fetchBoxes() {
      $query = 'select a.box_id, a.status, date(a.date_added) as date_added, a.project, b.box_features, b.box_name, b.keeper, concat(c.facility, " >> ", b.rack, " >> ", b.rack_position) as position, CONCAT(d.onames, " ", d.sname) as added_by, e.description as sample_type, f.value as project '.
              'from '. Config::$config['dbase'] .'.lcmod_boxes_def as a '.
              'inner join '. Config::$config['azizi_db'] .'.boxes_def as b on a.box_id = b.box_id '.
              'inner join '. Config::$config['azizi_db'] .'.boxes_local_def as c on b.location = c.id '.
              'left join '. Config::$config['dbase'] .'.users as d on a.added_by = d.id '.
              'left join '. Config::$config['azizi_db'] .'.sample_types_def as e on a.sample_types=e.count './/sample type
              'left join '. Config::$config['azizi_db'] .'.modules_custom_values as f on a.project = f.val_id';//associated project
      
      if(isset($_POST['search'])){//check if requester whats a more specific search
         $query = $query . " WHERE box_name LIKE '%".$_POST['search']."%'";
         $query = $query . " AND box_features LIKE '%".$_POST['search']."%'";
         if(count($_POST['project']) > 0){
            //<option value="-1">Boxes with projects</option>
               //<option value="-2">Boxes without projects</option>
            if($_POST['project'] == -1){//boxes associated with projects
               $query = $query . " AND project IS NOT NULL AND project != 0";
            }
            else if($_POST['project'] == -2){//boxes not associated with projects
               $query = $query . " AND project IS NULL OR project = 0";
            }
            else{
               $query = $query . " AND project = ".$_POST['project'];
            }
         }
         if(count($_POST['status']) > 0){
            $query = $query . " AND status = ".$_POST['status']."'";
         }
         if($_POST['location'] == "wi_location"){
            $query = $query . " AND position != ''";//TODO: not sure will work
         }
         else if($_POST['location'] == "wo_location"){
            $query = $query . " AND postion = ''";//TODO: not sure will work
         }
         if(count($_POST['keeper'])>0){
            $query = $query . " AND keeper = ".$_POST['keeper'];
         }
      }
      
      $this->Dbase->CreateLogEntry('mod_box_storage: Search query = '.$query, 'debug');
      
      $result = $this->Dbase->ExecuteQuery($query);
      if($result == 1)  die(json_decode(array('data' => $this->Dbase->lastError)));

      header("Content-type: application/json");
      die('{"data":'. json_encode($result) .'}');
   }

   /**
    * This function gets data for boxes retireved from the system using the Retrieve a Box page and constructs a json object with this data
    */
   private function fetchRemovedBoxes() {
      //TODO: refere to users table
      $query = "SELECT a.id, CONCAT(d.onames, ' ', d.sname) AS removed_by, a.removed_for, a.purpose, a.analysis, date(a.date_removed) AS date_removed, date(a.date_returned) AS date_returned, a.return_comment, CONCAT(e.onames, ' ', e.sname) AS returned_by, b.box_name, concat(c.facility, ' >> ', b.rack, ' >> ', b.rack_position) as position" .
              " FROM " . Config::$config['dbase'] . ".lcmod_retrieved_boxes AS a" .
              " INNER JOIN " . Config::$config['azizi_db'] . ".boxes_def AS b ON a.box_def = b.box_id" .
              " INNER JOIN " . Config::$config['azizi_db'] . ".boxes_local_def AS c ON b.location = c.id".
              " LEFT JOIN " . Config::$config['dbase'] . ".users AS d ON a.removed_by = d.id".
              " LEFT JOIN " . Config::$config['dbase'] . ".users AS e ON a.returned_by = e.id";
      
      $result = $this->Dbase->ExecuteQuery($query);
      if($result === 1) die(json_decode(array('data' => $this->Dbase->lastError)));
      
      header("Content-type: application/json");
      $this->Dbase->CreateLogEntry('mod_box_storage: Removed boxes sent via ajax request = '.print_r($result ,true), 'debug');
      $json = array('data'=>$result);
      die(json_encode($json));
   }

   /**
    * This function gets data for boxes deleted from the system using the Delete a Box page and constructs a json object with this data
    */
   private function fetchDeletedBoxes() {
      $query = "SELECT date(a.date_deleted) AS date_deleted, a.delete_comment, b.box_name, CONCAT(c.onames, ' ', c.sname) AS deleted_by" .
              " FROM " . Config::$config['dbase'] . ".lcmod_boxes_def AS a" .
              " INNER JOIN " . Config::$config['azizi_db'] . ".boxes_def AS b ON a.box_id = b.box_id" .
              " LEFT JOIN " . Config::$config['dbase'] . ".users AS c ON a.deleted_by = c.id" .
              " WHERE date_deleted IS NOT NULL";
      //$this->Dbase->query = $query." LIMIT $startRow, {$_POST['rp']}";
      $result = $this->Dbase->ExecuteQuery($query);
      
      if($result === 1) die(json_decode(array('data' => $this->Dbase->lastError)));
      
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