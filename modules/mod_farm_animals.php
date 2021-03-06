<?php
/**
   Copyright 2015 ILRI

   This file is part of the azizi repository platform.

   The azizi platform is a free software: you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation, either version 3 of the License, or
   (at your option) any later version.

   The azizi platform is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with the azizi platform .  If not, see <http://www.gnu.org/licenses/>.
  */

/**
 * A module for management of ILRI farm animals
 *
 * @author Absolomon Kihara   a.kihara@cgiar.org
 */
class FarmAnimals{

   private $Dbase;

   private $quickEventsDic = array(
      'temp' => 'Temperature',
      'weight' => 'Weighing'
   );

   /**
    *
    * @param type $Dbase   Create a new class for farm animals
    */
   public function __construct($Dbase){
      $this->Dbase = $Dbase;
   }

   /**
    * This function determines what user wants to do
    */
   public function trafficController(){
      if(OPTIONS_REQUESTED_SUB_MODULE == '' || OPTIONS_REQUESTED_SUB_MODULE == 'home'){
         if(OPTIONS_REQUESTED_ACTION == '') $this->homePage();
      }
      else if(OPTIONS_REQUESTED_SUB_MODULE == 'add'){
         if($_GET['query'] != '') $this->searchAnimals();
         else if(OPTIONS_REQUESTED_ACTION == '') $this->addHome();
         else if(OPTIONS_REQUESTED_ACTION == 'save') $this->saveAnimal();
         else if(OPTIONS_REQUESTED_ACTION == 'confirm') $this->confirmAnimal();
      }
      else if(OPTIONS_REQUESTED_SUB_MODULE == 'edit'){
         if($_GET['query'] != '') $this->searchAnimals();
         else if(OPTIONS_REQUESTED_ACTION == 'save') $this->editAnimal();
      }
      else if(OPTIONS_REQUESTED_SUB_MODULE == 'inventory'){
         if(OPTIONS_REQUESTED_ACTION == '') $this->inventoryHome();
         else if(OPTIONS_REQUESTED_ACTION == 'list' && $_POST['field'] == 'events') $this->animalEventsList();
         else if(OPTIONS_REQUESTED_ACTION == 'list') $this->inventoryList();
         else if(OPTIONS_REQUESTED_ACTION == 'info') $this->getAnimalInfo();
      }
      else if(OPTIONS_REQUESTED_SUB_MODULE == 'pen_animals'){
         if(OPTIONS_REQUESTED_ACTION == '') $this->animalLocations(true);
      }
      else if(OPTIONS_REQUESTED_SUB_MODULE == 'pens'){
         if(OPTIONS_REQUESTED_ACTION == '') $this->animalLocations();
         else if(OPTIONS_REQUESTED_ACTION == 'save') $this->saveAnimalLocations();
      }
      else if(OPTIONS_REQUESTED_SUB_MODULE == 'move_animals'){
         if(OPTIONS_REQUESTED_ACTION == '') $this->moveAnimals();
         else if(OPTIONS_REQUESTED_ACTION == 'save') $this->saveAnimalMovement();
      }
      else if(OPTIONS_REQUESTED_SUB_MODULE == 'ownership'){
         if(OPTIONS_REQUESTED_ACTION == '') $this->animalOwnersHome();
         else if(OPTIONS_REQUESTED_ACTION == 'list') $this->animalOwnersList();
         else if(OPTIONS_REQUESTED_ACTION == 'history') $this->animalOwnersHistory();
         else if(OPTIONS_REQUESTED_ACTION == 'save') $this->saveAnimalOwners();
      }
      else if(OPTIONS_REQUESTED_SUB_MODULE == 'events'){
         if(OPTIONS_REQUESTED_ACTION == '') $this->animalEvents();
         else if(OPTIONS_REQUESTED_ACTION == 'list' && $_POST['field'] == 'animal_events') $this->eventsList();
         else if(OPTIONS_REQUESTED_ACTION == 'list' && $_POST['field'] == 'sub_events') $this->eventsSubList();
         else if(OPTIONS_REQUESTED_ACTION == 'list') $this->newEventsData ();
         else if(OPTIONS_REQUESTED_ACTION == 'save') $this->saveAnimalEvents ();
         else if(OPTIONS_REQUESTED_ACTION == 'delete') $this->deleteAnimalEvents ();
         else if(OPTIONS_REQUESTED_ACTION == 'send_email') $this->emailEventsDigest ();
         else if(OPTIONS_REQUESTED_ACTION == 'quick_events') $this->quickEventsHome ();
         else if(OPTIONS_REQUESTED_ACTION == 'quick_events_list') $this->quickEventsList();
         else if(OPTIONS_REQUESTED_ACTION == 'quick_events_save') $this->quickEventsSave();
      }
      else if(OPTIONS_REQUESTED_SUB_MODULE == 'experiments'){
         if($_GET['query'] != '') $this->searchExperiments();
         else if(OPTIONS_REQUESTED_ACTION == '') $this->experimentsHome();
         else if(OPTIONS_REQUESTED_ACTION == 'list') $this->experimentsData();
         else if(OPTIONS_REQUESTED_ACTION == 'save_exp') $this->saveNewExperiment();
         else if(OPTIONS_REQUESTED_ACTION == 'save') $this->saveExperimentAnimals();      // An ambigous action name....
      }
      else if(OPTIONS_REQUESTED_SUB_MODULE == 'graphs'){
         if(OPTIONS_REQUESTED_ACTION == 'weights'){
            if(isset($_POST['animal_id'])) $this->getAnimalWeights();
            else $this->weightGraphsHome();
         }
      }
      else if(OPTIONS_REQUESTED_SUB_MODULE == 'images'){
         if(OPTIONS_REQUESTED_ACTION == '') $this->fileUploadsHome();
         else if(OPTIONS_REQUESTED_ACTION == 'save') $this->processAndSaveUploads();
      }
      else if(OPTIONS_REQUESTED_SUB_MODULE == 'batch_upload'){
         if(OPTIONS_REQUESTED_ACTION == '') $this->batchUploadsHome();
         else if(OPTIONS_REQUESTED_ACTION == 'save') $this->processAndSaveBatchUploads();

      }
   }

   /**
    * Create links for the different sub modules of this module
    */
   private function homePage($addInfo = ''){
      $addInfo = ($addInfo != '') ? "<div id='addinfo'>$addInfo</div>" : '';
      ?>
<div id='home'>
   <?php echo $addInfo; ?>
   <h3 class="center">Farm animals management</h3>
   <div class="user_options">
      <ul>
         <li><a href="?page=farm_animals&do=inventory">Animal inventory</a></li>
<?php
         if(in_array(Config::$farm_module_admin, $_SESSION['user_type'])){
            echo '<li><a href="?page=farm_animals&do=add">Add/Edit an animal</a></li>
               <li><a href="?page=farm_animals&do=ownership">Animal ownership</a></li>
               <li><a href="?page=farm_animals&do=pens">Farm location & animals</a></li>
               <li><a href="?page=farm_animals&do=pen_animals">Animals in location</a></li>
               <li><a href="?page=farm_animals&do=move_animals">Move animals between locations</a></li>
               <li><a href="?page=farm_animals&do=events">Animal Events</a></li>
               <li><a href="?page=farm_animals&do=events&action=quick_events">Add Quick Events</a></li>
               <li><a href="?page=farm_animals&do=experiments">Experiments</a></li>
               <li><a href="?page=farm_animals&do=images">Picture Uploads</a></li>
               <li><a href="?page=farm_animals&do=graphs&action=weights">Weight Graphs</a></li>
               <li><a href="?page=farm_animals&do=batch_upload">Allflex Batch Uploads</a></li>';
         }
?>
      </ul>
   </div>
</div>
<script>
   $('#whoisme .back').html('<a href=\'?page=home\'>Back</a>');//back link
</script>
      <?php
   }

   /**
    * Show the list of animals in the farm
    */
   private function inventoryHome(){
?>
<script type="text/javascript" src="js/farm_animals.js"></script>
<link rel="stylesheet" href="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/styles/jqx.base.css" type="text/css" />
<link rel="stylesheet" href="<?php echo OPTIONS_COMMON_FOLDER_PATH?>lightgallery/light-gallery/css/lightGallery.css" type="text/css" />
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>lightgallery/light-gallery/js/lightGallery.min.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxcore.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxdata.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxdata.export.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxbuttons.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxscrollbar.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxmenu.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxcheckbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxlistbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxdropdownlist.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxgrid.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxgrid.sort.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxgrid.pager.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxgrid.selection.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxgrid.filter.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxgrid.columnsresize.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxwindow.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxtabs.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxgrid.export.js"></script>

<div id="main">
   <div id="inventory"></div>
   <div id="animal_info">
      <div id="windowHeader"><span>Animal Information</span></div>
       <div style="overflow: hidden;" id="windowContent">
         <div id="tab">
            <ul style="margin-left: 30px;">
               <li>Info</li><li>Others</li><li>Picture</li>
            </ul>
            <div class="info"></div>
            <div class="others"></div>
            <div class="pic"></div>
         </div>
      </div>
   </div>
</div>
<!-- div id="links" class="center">
   <button type="button" id="save" class='btn btn-primary'>Save</button>
   <button type="button" id="cancel" class='btn btn-primary cancel'>Cancel</button>
</div -->
<script type="text/javascript">
   $('#whoisme .back').html('<a href=\'?page=farm_animals\'>Back</a>');       //back link
   var animals = new Animals();
   animals.initiateAnimalsGrid();
   $('.anim_id_href').live('click', function(that){ animals.showAnimalDetails(that); } );
   animals.info = {};   // an object for holding the animal information
</script>
<?php
   }

   /**
    * Get a list of all the animals currently in the farm
    */
   private function inventoryList(){
      $showAll = '';
      $query = 'select a.*, b.name as species, if(dob = 0, "", dob) as dob, a.current_owner, d.iacuc as experiment, concat(e.level1, " > ", e.level2) as location, f.breed, a.status, right(rfid, 6) as rfidd, left(sex, 1) as sexd '
         . 'from '. Config::$farm_db .'.farm_animals as a inner join '. Config::$farm_db .'.farm_species as b on a.species_id=b.id '
         . 'left join '. Config::$farm_db .'.experiments as d on a.current_exp=d.id '
         . 'left join '. Config::$farm_db .'.farm_locations as e on a.current_location=e.id '
         . 'left join (select animal_id, group_concat(breed_name SEPARATOR ", ") as breed from '. Config::$farm_db .'.animal_breeds as a inner join '. Config::$farm_db .'.breeds as b on a.breed_id=b.id '
         . 'group by a.animal_id) as f on a.id=f.animal_id '. $showAll .' order by a.animal_id';
      $res = $this->Dbase->ExecuteQuery($query);
      if($res == 1){
         $this->Dbase->CreateLogEntry($this->Dbase->lastError, 'fatal');
         die(json_encode(array('error' => true, 'message' => 'There was an error while fetching data from the database. Contact the system administrator')));
      }
      $owners = $this->getAllOwners(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE);
      if(is_string($owners)) die(json_encode(array('error' => true, 'message' => $owners)));

      foreach($res as $id => $animal){
         $res[$id]['owner'] = $owners[$animal['current_owner']]['name'];
      }
      die(json_encode($res, true));
   }

   /**
    * Get a list of the events done for this animal
    */
   private function animalEventsList(){
      // get all the events for this animal
      $query = 'select a.id as event_id, b.event_name, a.event_value, a.event_date, a.record_date, a.comments, d.file_name, d.path '
              . 'from '. Config::$farm_db .'.farm_animal_events as a inner join '. Config::$farm_db .'.farm_events as b on a.event_type_id=b.id '
              . 'left join '. Config::$farm_db .'.event_files as c on a.id = c.event_id left join '. Config::$farm_db .'.uploaded_files as d on c.file_id = d.id '
              . 'where a.animal_id = :animal_id order by a.event_date desc, a.record_date';

      $res = $this->Dbase->ExecuteQuery($query, array('animal_id' => $_POST['animal_id']));
      if($res == 1){
         $this->Dbase->CreateLogEntry($this->Dbase->lastError, 'fatal');
         die(json_encode(array('error' => true, 'message' => 'There was an error while fetching data from the database. Contact the system administrator')));
      }
      die(json_encode($res));
   }

   /**
    * Creates a home page for adding animals
    */
   private function addHome(){
      global $Repository;
      // get the animal types in the farm
      $query = 'select id, name from '. Config::$farm_db .'.farm_species order by name';
      $farmAnimals = $this->Dbase->ExecuteQuery($query);
      if($farmAnimals == 1){
         $this->homePage($this->Dbase->lastError);
         return;
      }

      // get the breeds
      $query = 'select id, breed_name as name from '. Config::$farm_db .'.breeds order by breed_name';
      $breeds = $this->Dbase->ExecuteQuery($query);
      if($breeds == 1){
         $this->homePage($this->Dbase->lastError);
         return;
      }
     $Repository->DateTimePickerFiles();
?>
<link rel="stylesheet" href="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/styles/jqx.base.css" type="text/css" />
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxcore.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxnotification.js"></script>
<script type="text/javascript" src="js/farm_animals.js"></script>
<div id="add_animals">
   <form class='form-horizontal' id="adding">
      <fieldset id="animals">
         <div id="left_panel">
            <div class="control-group">
               <label class="control-label" for="animal_id">Animal ID&nbsp;&nbsp;<img class='mandatory' src='images/mandatory.gif' alt='Required' /></label>
               <div class="animal_input controls">
                  <input type="text" name="animal_id" id="animal_id" placeholder="Animal ID" class='input-medium form-control' required="true" />
               </div>
            </div>
            <div class="control-group">
               <label class="control-label" for="species">Species&nbsp;&nbsp;<img class='mandatory' src='images/mandatory.gif' alt='Required' /></label>
               <div class="animal_input controls species">
               </div>
            </div>
            <div class="control-group">
               <label class="control-label" for="sex">Sex&nbsp;&nbsp;<img class='mandatory' src='images/mandatory.gif' alt='Required' /></label>
               <div class="animal_input controls">
                  <label class="radio-inline"><input type="radio" name="sex" id="male" class='form-control' value="male" required="true"> Male</label>
                  <label class="radio-inline"><input type="radio" name="sex" id="female" class='form-control' value="female" required="true"> Female</label>
               </div>
            </div>
            <div class="control-group">
               <label class="control-label" for="dob">Date of Birth</label>
               <div class="animal_input controls">
                  <input type="text" name='dob' id="dob" placeholder="DoB" class='input-medium form-control' />
               </div>
            </div>
            <div class="control-group">
               <label class="control-label" for="breed">Breed&nbsp;&nbsp;<img class='mandatory' src='images/mandatory.gif' alt='Required' /></label>
               <div class="animal_input controls breeds">
               </div>
            </div>
         </div>
         <div id="right_panel">
            <div class="control-group">
               <label class="control-label" for="other_id">Other ID</label>
               <div class="animal_input controls">
                  <input type="text" name="other_id" id="other_id" placeholder="Other ID" class='input-medium form-control' />
               </div>
            </div>
            <div class="control-group">
               <label class="control-label" for="origin">Origin</label>
               <div class="animal_input controls">
                  <input type="text" name='origin' id="origin" placeholder="Origin" class='input-medium form-control' />
               </div>
            </div>
            <div class="control-group">
               <label class="control-label" for="sire">Sire</label>
               <div class="animal_input controls">
                  <input type="text" name='sire' id="sire" placeholder="Sire" class='input-medium form-control' />
               </div>
            </div>
            <div class="control-group">
               <label class="control-label" for="dam">Dam</label>
               <div class="animal_input controls">
                  <input type="text" name='dam' id="dam" placeholder="Dam" class='input-medium form-control' />
               </div>
            </div>
            <div class="control-group">
               <label class="control-label" for="experiment">Current Experiment</label>
               <div class="animal_input controls">
                  <input type="text" name='experiment' id="experiment" placeholder="Experiment" class='input-medium form-control' />
               </div>
            </div>
            <div class="control-group">
               <label class="control-label" for="comments">Comments</label>
               <div class="animal_input controls">
                  <textarea name='comments' id="comments" class=' form-control'></textarea>
               </div>
            </div>
         </div>
         </div>
         <div>
         <div id="links" class="center">
            <button type="button" id="save" class='btn btn-primary' value="save">Save</button>
            <button type="button" id="cancel" class='btn btn-primary cancel'>Cancel</button>
         </div>
      </fieldset>
   </form>
   <div id="messageNotification"><div></div></div>
</div>
<script type="text/javascript">
   $('#whoisme .back').html('<a href=\'?page=farm_animals\'>Back</a>');       //back link
   // create the dob widget
   datePickerController.createDatePicker({ formElements:{"dob":"%d-%m-%Y"}, fillGrid: true, constraintSelection:false, maxDate: 0 });
   var animals = new Animals();

   animals.species = <?php echo json_encode($farmAnimals); ?>;
   var settings = {name: 'species', id: 'speciesId', data: animals.species, initValue: 'Select One', required: 'true'};
   var comboString = Common.generateCombo(settings);
   $('.species').html(comboString);

   // breeds
   animals.breeds = <?php echo json_encode($breeds); ?>;
   var settings = {name: 'breed', id: 'breedId', data: animals.breeds, initValue: 'Select One', required: 'true', type: 'multiple'};
   var comboString = Common.generateCombo(settings);
   $('.breeds').html(comboString);

   $('#save').bind('click', animals.saveAnimal);     // bind the save button to the save action
   $('#animal_id').focus();
</script>
<?php
      Repository::autoCompleteFiles();

      $settings = array('inputId' => 'animal_id', 'reqModule' => 'farm_animals', 'reqSubModule' => 'edit', 'selectFunction' => 'animals.confirmId');
      Repository::InitiateAutoComplete($settings);

      $settings = array('inputId' => 'sire', 'reqModule' => 'farm_animals', 'reqSubModule' => 'add', 'selectFunction' => 'function(){}');
      Repository::InitiateAutoComplete($settings);

      $settings = array('inputId' => 'dam', 'reqModule' => 'farm_animals', 'reqSubModule' => 'add', 'selectFunction' => 'function(){}');
      Repository::InitiateAutoComplete($settings);

      $settings = array('inputId' => 'experiment', 'reqModule' => 'farm_animals', 'reqSubModule' => 'experiments', 'selectFunction' => 'function(){}');
      Repository::InitiateAutoComplete($settings);
   }

   /**
    * Confirm whether an animal is in the
    */
   private function confirmAnimal(){
      $animalQuery = 'select a.id, animal_id, other_id, species_id, lower(sex) as sex, origin, date_format(dob, "%d-%m-%Y") as dob, sire, dam, c.exp_name, a.comments '
          . 'from '. Config::$farm_db .'.farm_animals as a left join '. Config::$farm_db .'.experiments as c on a.current_exp=c.id where animal_id = :animal_id';
      $res = $this->Dbase->ExecuteQuery($animalQuery, array('animal_id' => $_POST['animalId']));

      if($res == 1) die(json_encode(array('error' => true, 'data' => $this->Dbase->lastError)));
      if(count($res) == 0){
         die(json_encode(array('error' => false, 'data' => array('isAnimalSaved' => false))));
      }
      else{
         //get the breed composition of this animal
         $breedQuery = 'select breed_id from '. Config::$farm_db .'.animal_breeds where animal_id = :animal_id';
         $res1 = $this->Dbase->ExecuteQuery($breedQuery, array('animal_id' => $res[0]['id']));
         if($res1 == 1) die(json_encode(array('error' => true, 'data' => $this->Dbase->lastError)));
         $breed = array();
         foreach($res1 as $t) $breed[] = $t['breed_id'];
         $res[0]['breed'] = $breed;

         die(json_encode(array('error' => false, 'data' => array('isAnimalSaved' => true, 'animalDetails' => $res[0]))));
      }
   }

   /**
    * Creates an auto suggest list based on the search criteria
    */
   private function searchAnimals(){
      $searchQuery = 'select animal_id, breed_string, sex, status from '. Config::$farm_db .'.farm_animals where animal_id like :animal_id';
      $res = $this->Dbase->ExecuteQuery($searchQuery, array('animal_id' => "%{$_GET['query']}%"));

      if($res == 1) die(json_encode(array('error' => true, 'data' => $this->Dbase->lastError)));
      $suggestions = array();
      foreach($res as $t){
         $suggestions[] = array('value' => $t['animal_id'], 'data' => $t['animal_id']. ', ' . $t['breed_string']. ' ' . $t['sex']. ', ' .$t['status']);
      }
      $data = array('error' => false, 'query' => $_GET['query'], 'suggestions' => $suggestions, 'data' => $data);
      header("Content-type: application/json");
      die(json_encode($data, true));
   }

   /**
    * Creates a page for managing the pens in the farm
    */
   private function animalLocations($withAnimals = false){
      if($withAnimals){ $animalsLocations = "<div id='animalsOnLocation'><div class='label'>Animals in selected locations</div><div id='level3'></div></div>"; $action = 'pensWithAnimals'; }
      else{ $animalsLocations = ''; $action = 'pensWithoutAnimals'; }

      $locations = $this->getAnimalLocations($withAnimals);
?>
<script type="text/javascript" src="js/farm_animals.js"></script>
<link rel="stylesheet" href="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/styles/jqx.base.css" type="text/css" />
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxcore.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxbuttons.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxbuttongroup.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxscrollbar.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxlistbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxcheckbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxnotification.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxwindow.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxpanel.js"></script>
<div id="animal_locations">
   <div id="level1_pl">
      <div class="label">Level1 Locations</div>
      <div id="level1"></div>
   </div>
   <div id="level2_pl">
      <div class="label">Level2 Locations</div>
      <div id="level2"></div>
      <div class="actions">
         <button style="padding:4px 16px;" id="add">Add</button>
         <button style="padding:4px 16px;" id="edit">Edit</button>
         <button style="padding:4px 16px;" id="delete">Delete</button>
      </div>
      <div id="modal_window">
         <div>Add new location</div>
         <div>
            <div id='level1_add'></div>
            <div id='level2_add'>
               <input type='text' id='level2Id' value='' />
               <div style="float: right; margin-top: 15px;">
                  <input type="button" id="ok" value="OK" style="margin-right: 10px" />
                  <input type="button" id="cancel" value="Cancel" />
               </div>
            </div>
         </div>
      </div>
      <div id='animalsOnLocation_pl'>
         <div class="label">Animals in selected location</div>
         <div id='animalsOnLocation'></div>
         <div class="actions" class="hidden">
            <button style="padding:4px 16px;" id="add">Add</button>
            <button style="padding:4px 16px;" id="edit">Edit</button>
            <button style="padding:4px 16px;" id="delete">Delete</button>
         </div>
      </div>
   </div>
   <?php echo $animalsLocations; ?>
   <div id="messageNotification"><div></div></div>
</div>
<script type="text/javascript">
   $('#whoisme .back').html('<a href=\'?page=farm_animals\'>Back</a>');       //back link
   $("#animalsOnLocation_pl .actions").jqxButtonGroup({ mode: 'default' });
   $("#level2_pl .actions").jqxButtonGroup({ mode: 'default' });

   var animals = new Animals();
   animals.level1Locations = <?php echo json_encode($locations['level1']); ?>;
   animals.level2Locations = <?php echo json_encode($locations['level2']); ?>;
   animals.inLocations = <?php echo json_encode($locations['animals']); ?>;
   animals.initiateAnimalLocations('<?php echo $action; ?>');

   $("#level2_pl .actions").on('buttonclick', animals.level2BttnClicked );
   $("#animalsOnLocation_pl .actions").on('buttonclick', animals.animalsBttnClicked );
</script>
<?php
   }

   /**
    *
    *
    * @return  array[]  Returns an array with the animals grouped by locations. In add
    */
   /**
    * Gets the defined locations for the animals
    * @param   boolean        $withAnimals   Whether to get the locations with animals
    * @return  string|array   Returns a string incase of an error, else it returns an array
    */
   private function getAnimalLocations($withAnimals = false, $groupByTopLocations = false, $includeDeadAnimals = true){
      // get all the level1 locations
      $query = 'select level1 from '. Config::$farm_db .'.farm_locations group by level1 order by level1';
      $res = $this->Dbase->ExecuteQuery($query);
      if($res == 1) return $this->Dbase->lastError;

      // get all the level2 locations grouped by level1 locations and format level1 locations well
      $level2Locations = array();
      $level1Locations = array();
      $query = 'select id, level2 as `name` from '. Config::$farm_db .'.farm_locations where level1 = :level1 group by level2 order by level2';
      $i = 2;
      $animalLocations = array();

      // get all animals
      $deadAnimals = ($includeDeadAnimals == true) ? '' : " and b.status='Alive'";
      $animalsQuery = 'select b.id, if(b.status="Alive", b.animal_id, concat(b.animal_id, " (", b.status, ")")) `name` from '. Config::$farm_db .'.farm_animal_locations as a right join '. Config::$farm_db .".farm_animals as b on a.animal_id = b.id where a.end_date is null $deadAnimals order by b.animal_id";
      $allAnimals = $this->Dbase->ExecuteQuery($animalsQuery);
      if($allAnimals == 1) return $this->Dbase->lastError;

      foreach($res as $loc){
         // loop thru all level1 locations and get level2 locations
         $res1 = $this->Dbase->ExecuteQuery($query, array('level1' => $loc['level1']));
         if($res1 == 1) return $this->Dbase->lastError;
         $level2Locations[$loc['level1']] = $res1;

         // get the animals in this locations if need be
         if($withAnimals){
            if($groupByTopLocations){
               $animalsTopLocationQuery = 'select b.id, b.animal_id `name` from '. Config::$farm_db .'.farm_animal_locations as a '
                  . 'inner join '. Config::$farm_db .'.farm_animals as b on a.animal_id = b.id '
                  . 'inner join '. Config::$farm_db .'.farm_locations as c on a.location_id = c.id '
                  . 'where b.status like "Alive" and c.level1 = :level1 and a.end_date is null order by b.animal_id';
               $this->Dbase->CreateLogEntry('Getting the animals per the top locations', 'info');
               $res3 = $this->Dbase->ExecuteQuery($animalsTopLocationQuery, array('level1' => $loc['level1']));
               if($res3 == 1) return $this->Dbase->lastError;
               $animalLocations[$loc['level1']] = $res3;
            }
            $animalsQuery = 'select b.id, b.animal_id `name` from '. Config::$farm_db .'.farm_animal_locations as a inner join '. Config::$farm_db .'.farm_animals as b on a.animal_id = b.id where b.status like "Alive" and a.location_id = :id and a.end_date is null order by b.animal_id';
            $this->Dbase->CreateLogEntry('Getting the animals per location', 'info');
            foreach($res1 as $subLoc){
               $res2 = $this->Dbase->ExecuteQuery($animalsQuery, array('id' => $subLoc['id']));
               if($res2 == 1) return $this->Dbase->lastError;
               $animalLocations[$subLoc['id']] = $res2;
            }

            // get the unattached animals
            $unattachedAnimalsQuery = 'select id, animal_id as `name` from '. Config::$farm_db .'.farm_animals  where current_location is null and status like "Alive"';
            $res3 = $this->Dbase->ExecuteQuery($unattachedAnimalsQuery);
            if($res3 == 1) return $this->Dbase->lastError;
            $animalLocations['floating'] = $res3;
         }
         $level1Locations[] = array('id' => $i, 'name' => $loc['level1']);
         $i++;
      }

      return array('level1' => $level1Locations, 'level2' => $level2Locations, 'animals' => $animalLocations, 'allAnimals' => $allAnimals);
   }

   /**
    * Get the animals grouped by owners
    *
    * @param   array          $owners  An array with the owners list
    * @return  array|string   Returns an array of the animals grouped by owners or a string if an error occurs during query execution
    */
   private function groupAnimalsByOwners($owners = NULL){
      if($owners == NULL){
         $owners = $this->getAllOwners();
         if(is_string($owners)){ die(json_encode(array('error' => true, 'mssg' => $owners))); }
      }

      $ownerQuery = 'select id, animal_id as `name` from '. Config::$farm_db .'.farm_animals where status like "Alive" and current_owner = :owner_id order by animal_id';

      $animalByOwners = array();
      foreach($owners as $owner){
         $res = $this->Dbase->ExecuteQuery($ownerQuery, array('owner_id' => $owner['id']));
         if($res == 1) return $this->Dbase->lastError;
         else $animalByOwners[$owner['id']] = $res;
      }

      // get the animals without owners
      $ownerlessAnimalsQuery = 'select id, animal_id as `name` from '. Config::$farm_db .'.farm_animals where status like "Alive" and id not in (SELECT animal_id FROM '. Config::$farm_db .'.farm_animal_owners where end_date is null) order by animal_id';
      $res = $this->Dbase->ExecuteQuery($ownerlessAnimalsQuery);
      if($res == 1) return $this->Dbase->lastError;
      else $animalByOwners['floating'] = $res;

      return array('byOwners' => $animalByOwners, 'owners' => $owners);
   }

   /**
    * Group animals by the current experiment
    * @param   array          $experiments   The experiments which we are interested in
    * @return  array|string   Returns a string with an error message if there is one, else returns an array with animals grouped by experiments
    */
   private function groupAnimalsByExperiments($experiments = NULL){
      if($experiments == NULL){
         $experiments = $this->getAllExperiments();
         if(is_string($experiments)) { die(json_encode(array('error' => true, 'mssg' => $experiments))); }
      }

      $query = 'select id, animal_id as name from '. Config::$farm_db .'.farm_animals where status like "Alive" and current_exp = :exp_id order by animal_id';
      $animalsByExperiments = array();
      foreach($experiments as $index => $exp){
         $res = $this->Dbase->ExecuteQuery($query, array('exp_id' => $exp['id']));
         if($res == 1) return $this->Dbase->lastError;
         $animalsByExperiments[$exp['id']] = $res;
      }
      return $animalsByExperiments;
   }

   /**
    * Save locations where the animals are kept
    */
   private function saveAnimalLocations(){
      // we have a level2 or a level1 and level2. If level1 is an integer, we have a presaved level1 else save both
      // whether or not we have a level1 selected, we need to save both of them... flauting the db rules
      $level1Query = 'insert into '. Config::$farm_db .'.farm_locations(level1, level2) values(:level1, :level2)';
      $vals = array('level2' => $_POST['level2']);

      $vals['level1'] = ($_POST['level1name'] == '') ? $_POST['level1'] : $_POST['level1name'];
      $res = $this->Dbase->ExecuteQuery($level1Query, $vals);
      if($res == 1) die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastError)));
      else{
         $locations = $this->getAnimalLocations();
         die(json_encode(array('error' => 'false', 'mssg' => 'The new levels have been successfully saved.', 'data' => $locations)));
      }

   }
   /**
    * A function to save a new animal to the database
    */
   private function saveAnimal(){
      // saving a new animal. Mandatory fields are animal_id, species, sex, breed
      $cols = 'animal_id, species_id, sex, status';
      $colrefs = ':animal_id, :species_id, :sex, :status';
      $colvals = array('animal_id' => $_POST['animal_id'], 'species_id' => $_POST['species'], 'sex' => $_POST['sex'], 'status' => 'Alive');
      $dob = date_create_from_format('d-m-Y', $_POST['dob']);
      if($_POST['dob'] !== '') { $cols .= ', dob';  $colrefs .= ', :dob'; $colvals['dob'] = date_format($dob, 'Y-m-d'); }
      if($_POST['other_id'] != '') { $cols .= ', other_id';  $colrefs .= ', :other_id'; $colvals['other_id'] = $_POST['other_id']; }
      if($_POST['origin'] != '') { $cols .= ', origin';  $colrefs .= ', :origin'; $colvals['origin'] = $_POST['origin']; }
      if($_POST['experiment'] != '') {
         // get the experiment as defined here
         $searchQuery = 'select id from '. Config::$farm_db .'.experiments where exp_name = :exp';
         $res = $this->Dbase->ExecuteQuery($searchQuery, array('exp' => $_POST['experiment']));
         if($res == 1) die(json_encode(array('error' => true, 'data' => $this->Dbase->lastError)));
         else if(count($res) != 0){
            $cols .= ', current_exp';
            $colrefs .= ', :current_exp';
            $colvals['current_exp'] = $res[0]['id'];
         }
      }
      if($_POST['comments'] != '') { $cols .= ', comments';  $colrefs .= ', :comments'; $colvals['comments'] = $_POST['comments']; }
      if($_POST['dam'] != '') { $cols .= ', dam';  $colrefs .= ', :dam'; $colvals['dam'] = $_POST['dam']; }
      if($_POST['sire'] != '') { $cols .= ', sire';  $colrefs .= ', :sire'; $colvals['sire'] = $_POST['sire']; }

      $this->Dbase->StartTrans();
      $query = 'insert into '. Config::$farm_db .".farm_animals($cols) values($colrefs)";
      $res = $this->Dbase->ExecuteQuery($query, $colvals);
      if($res == 1){
         $this->Dbase->RollBackTrans();
         die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastError)));
      }
      $animalId = $this->Dbase->dbcon->lastInsertId();

      // now lets add the breeds if any
      $breedQuery = 'insert into '. Config::$farm_db .'.animal_breeds(animal_id, breed_id) values(:animal_id, :breed_id)';
      $breeds = json_decode($_POST['breed']);
      if(!is_array($breeds)) $breeds = array($breeds);
      foreach($breeds as $breed) {
         $res1 = $this->Dbase->ExecuteQuery($breedQuery, array('animal_id' => $animalId, 'breed_id' => $breed));
         if($res1 == 1){
            $this->Dbase->RollBackTrans();
            die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastError)));
         }
      }
      // seem all is ok, lets commit the transaction and go back
      $this->Dbase->CommitTrans();
      die(json_encode(array('error' => false, 'mssg' => 'The animal has been successful saved.')));
   }

   /**
    * Update an animal details
    */
   private function editAnimal(){
      // updating an animal details. Mandatory fields are animal_id, species, sex, breed
      $cols = 'animal_id = :animal_id, species_id = :species_id, sex = :sex, status = :status';
      $colvals = array('animal_id' => $_POST['animal_id'], 'species_id' => $_POST['species'], 'sex' => $_POST['sex'], 'status' => 'Alive', 'id' => $_POST['editedAnimal']);

      $dob = date_create_from_format('d-m-Y', $_POST['dob']);
      if($_POST['dob'] !== '') { $cols .= ', dob = :dob'; $colvals['dob'] = date_format($dob, 'Y-m-d'); }
      if($_POST['other_id'] != '') { $cols .= ', other_id = :other_id'; $colvals['other_id'] = $_POST['other_id']; }
      if($_POST['origin'] != '') { $cols .= ', origin = :origin'; $colvals['origin'] = $_POST['origin']; }
      if($_POST['experiment'] != '') {
         // get the experiment as defined here
         $searchQuery = 'select id from '. Config::$farm_db .'.experiments where exp_name = :exp';
         $res = $this->Dbase->ExecuteQuery($searchQuery, array('exp' => $_POST['experiment']));
         if($res == 1) die(json_encode(array('error' => true, 'data' => $this->Dbase->lastError)));
         else if(count($res) != 0){
            $cols .= ', current_exp = :current_exp';
            $colvals['current_exp'] = $res[0]['id'];
         }
      }
      if($_POST['comments'] != '') { $cols .= ', comments = :comments'; $colvals['comments'] = $_POST['comments']; }
      if($_POST['dam'] != '') { $cols .= ', dam = :dam'; $colvals['dam'] = $_POST['dam']; }
      if($_POST['sire'] != '') { $cols .= ', sire = :sire'; $colvals['sire'] = $_POST['sire']; }

      $this->Dbase->StartTrans();
      $query = 'update '. Config::$farm_db .".farm_animals set $cols where id = :id";
      $res = $this->Dbase->ExecuteQuery($query, $colvals);
      if($res == 1){
         $this->Dbase->RollBackTrans();
         die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastError)));
      }

      // now lets update the breeds if any.
      // first delete all the breeds entries and then add the breeds afresh
      $deleteQuery = 'delete from '. Config::$farm_db .'.animal_breeds where animal_id = :animal_id';
      $res = $this->Dbase->ExecuteQuery($deleteQuery, array('animal_id' => $_POST['editedAnimal']));
      if($res == 1){
         $this->Dbase->RollBackTrans();
         die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastError)));
       }

      // now lets add the breeds afresh
      $breedQuery = 'insert into '. Config::$farm_db .'.animal_breeds(animal_id, breed_id) values(:animal_id, :breed_id)';
      $breeds = json_decode($_POST['breed']);
      if(!is_array($breeds)) $breeds = array($breeds);
      foreach($breeds as $breed) {
         $res1 = $this->Dbase->ExecuteQuery($breedQuery, array('animal_id' => $_POST['editedAnimal'], 'breed_id' => $breed));
         if($res1 == 1){
            $this->Dbase->RollBackTrans();
            die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastError)));
         }
      }

      // seem all is ok, lets commit the transaction and go back
      $this->Dbase->CommitTrans();
      die(json_encode(array('error' => false, 'mssg' => 'The animal has been successful saved.')));
   }

   /**
    * Create a home page for showing the animal owners
    */
   private function animalOwnersHome(){
      global $Repository;
      // include the files for creating the date time picker
      $Repository->DateTimePickerFiles();
?>
<div id="messageNotification"><div class="">&nbsp;&nbsp;</div></div>
<script type="text/javascript" src="js/farm_animals.js"></script>
<link rel="stylesheet" href="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/styles/jqx.base.css" type="text/css" />
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxcore.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxdata.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxbuttons.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxscrollbar.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxmenu.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxcheckbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxlistbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxdropdownlist.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxgrid.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxgrid.sort.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxgrid.pager.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxgrid.selection.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxgrid.filter.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxnotification.js"></script>
<div id="ownership">
   <div id="owners_list">&nbsp;</div>
   <div id="links" class="center">
      <button type="button" id="add" class='btn btn-primary'>Add Ownership</button>
   </div>
   <div id="messageNotification"><div></div></div>
</div>

<script type='text/javascript'>
   $('#whoisme .back').html('<a href=\'?page=farm_animals\'>Back</a>');       //back link
   var animals = new Animals();
   animals.initiateAnimalsOwnersGrid();
   animals.movedAnimals = {};

   $('#add').bind('click', animals.addOwnership);
</script>
<?php
   }

   /**
    * Get the animal owners over a period of time
    */
   private function animalOwnersList(){
      $toReturn = array();
      $fields = json_decode($_POST['fields']);
      if($_POST['field'] == 'grid'){
         $query = 'select a.id, a.owner_id, c.animal_id animal, a.animal_id, start_date, end_date, a.comments '
             . 'from '. Config::$farm_db .'.farm_animal_owners as a inner join '. Config::$farm_db .'.farm_animals as c on a.animal_id=c.id where c.status like "Alive" and end_date is null '
             . 'order by a.animal_id, start_date';
         $ownership = $this->Dbase->ExecuteQuery($query);
         if($ownership == 1) die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastError)));

         // get all the owners
         $owners = $this->getAllOwners(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE);
         if(is_string($owners)) die(json_encode(array('error' => true, 'mssg' => $owners)));
         foreach($ownership as $id => $owner){
            $ownership[$id]['owner'] = $owners[$owner['owner_id']]['name'];
         }
         die(json_encode($ownership));
      }

      if(in_array('owners', $fields)){
         // get the animals belonging to these owners
         $res = $this->groupAnimalsByOwners();
         if(is_string($res)) { die(json_encode(array('error' => true, 'mssg' => $res))); }

         $owners = $this->getAllOwners();
         if(is_string($owners)) { die(json_encode(array('error' => true, 'mssg' => $owners))); }

         $toReturn['owners'] = $owners;
         $toReturn['animalsByOwners'] = $res['byOwners'];
      }

      if(in_array('animals', $fields)){
         $res = $this->getAnimalLocations(true);
         $toReturn['animals'] = $res;
      }
      die(json_encode($toReturn));
   }

   /**
    * Get a history of the animal owners for this particular animal
    */
   private function animalOwnersHistory(){
      $query = 'select a.animal_id, a.owner_id, c.animal_id animal, start_date, end_date, a.comments, concat(d.sname, " ", d.onames) as owner '
         . 'from '. Config::$farm_db .'.farm_animal_owners as a inner join '. Config::$farm_db .'.farm_animals as c on a.animal_id=c.id '
         . 'inner join '. Config::$config['dbase'] .'.users as d on a.owner_id = d.id '
         . 'where a.animal_id = :animal_id '
         . 'order by start_date';
      $ownership = $this->Dbase->ExecuteQuery($query, array('animal_id' => $_POST['animal_id']));
      if($ownership == 1) die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastError)));
      die(json_encode($ownership));
   }

   /**
    * Saves a new ownership of an animal
    */
   private function saveAnimalOwners(){
      $animals = json_decode($_POST['animals']);
      $addQuery = 'insert into '. Config::$farm_db .'.farm_animal_owners(owner_id, animal_id, start_date, added_at, added_by) values(:owner_id, :animal_id, :start_date, :added_at, :added_by)';
      $updateQuery = 'update '. Config::$farm_db .'.farm_animal_owners set end_date = :end_date, updated_at = :updated_at, updated_by = :updated_by where owner_id = :owner_id and animal_id = :animal_id and end_date is null';
      $updateWoOwnerQuery = 'update '. Config::$farm_db .'.farm_animal_owners set end_date = :end_date, updated_at = :updated_at, updated_by = :updated_by where animal_id = :animal_id and end_date is null limit 1';
      $updateOwnerQuery = 'update '. Config::$farm_db .'.farm_animals set current_owner = :current_owner where id = :animal_id';

      // queries to ensure the dates are being added well
      $checkQuery = 'select id from '. Config::$farm_db .'.farm_animal_owners where owner_id = :owner_id and animal_id = :animal_id and end_date is null and start_date > :end_date';
      $checkWoOwnerQuery = 'select id from '. Config::$farm_db .'.farm_animal_owners where animal_id = :animal_id and end_date is null and start_date > :end_date';

      // if we have a start date specified, use it as the end date for the previous location and the start date for the new location
      if(isset($_POST['start_date'])){
         $startDate = DateTime::createFromFormat('d-m-Y', $_POST['start_date']);
         $startDate = $startDate->format('Y-m-d');
      }
      else{
         $startDate = date('Y-m-d');
      }
      $this->Dbase->StartTrans();
      foreach($animals as $id => $name){
         if(!in_array($_POST['from'], array('floating', 'all'))){
            // check that the dates are fine before updating the database
            $checkVars = array('end_date' => $startDate, 'owner_id' => $_POST['from'], 'animal_id' => $id);
            $check = $this->Dbase->ExecuteQuery($checkQuery, $checkVars);
            if($check == 1){ $this->Dbase->RollBackTrans(); die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastError))); }
            else if(count($check) != 0){
               $this->Dbase->RollBackTrans();
               die(json_encode(array('error' => true, 'mssg' => 'The specified end date is less than the start date for this ownership'. print_r($check, true))));
            }

            $res = $this->Dbase->ExecuteQuery($updateQuery, array('owner_id' => $_POST['from'], 'animal_id' => $id, 'end_date' => $startDate, 'updated_at' => date('Y-m-d H:i:s'), 'updated_by' => $_SESSION['user_id']));
            if($res == 1){
               $this->Dbase->RollBackTrans();
               die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastError)));
            }
         }
         // if we are selecting from all, update the previous ownership
         if($_POST['from'] == 'all'){
            // check that the dates are fine before updating the database
            $checkVars = array('end_date' => $startDate, 'animal_id' => $id);
            $check = $this->Dbase->ExecuteQuery($checkWoOwnerQuery, $checkVars);
            if($check == 1){ $this->Dbase->RollBackTrans(); die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastError))); }
            else if(count($check) != 0){
               $this->Dbase->RollBackTrans();
               die(json_encode(array('error' => true, 'mssg' => 'The specified end date is less than the start date for this ownership'. print_r($check, true))));
            }
            $res = $this->Dbase->ExecuteQuery($updateWoOwnerQuery, array('animal_id' => $id, 'end_date' => $startDate, 'updated_at' => date('Y-m-d H:i:s'), 'updated_by' => $_SESSION['user_id']));
            if($res == 1){
               $this->Dbase->RollBackTrans();
               die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastError)));
            }
         }

         $res = $this->Dbase->ExecuteQuery($addQuery, array('owner_id' => $_POST['to'], 'animal_id' => $id, 'start_date' => $startDate, 'added_at' => date('Y-m-d H:i:s'), 'added_by' => $_SESSION['user_id']));
         if($res == 1){
            $this->Dbase->RollBackTrans();
            die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastError)));
         }

         // update the redudant current owner in animals table
         $res = $this->Dbase->ExecuteQuery($updateOwnerQuery, array('current_owner' => $_POST['to'], 'animal_id' => $id));
         if($res == 1){
            $this->Dbase->RollBackTrans();
            die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastError)));
         }
      }
      $this->Dbase->CommitTrans();
      die(json_encode(array('error' => 'false', 'mssg' => 'The new ownwership has been saved successfully.')));
   }

   /**
    * Move animals between pens
    */
   private function moveAnimals(){
      global $Repository;
      $animalLocations = $this->getAnimalLocations(true, true, false);
      // include the files for creating the date time picker
      $Repository->DateTimePickerFiles();
?>
<script type="text/javascript" src="js/farm_animals.js"></script>
<link rel="stylesheet" href="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/styles/jqx.base.css" type="text/css" />
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxcore.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxbuttons.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxbuttongroup.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxscrollbar.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxlistbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxcheckbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxnotification.js"></script>
<div id="move_animals">
   <div id="all_animals">
      <div id='from_filter'></div>
      <div id='from_list'></div>
   </div>
   <div id="actions">
      <button style="padding:4px 16px;" id="add">Add ></button>
      <button style="padding:4px 16px;" id="add_all">Add All >></button>
      <button style="padding:4px 16px;" id="remove">< Remove</button>
      <button style="padding:4px 16px;" id="reset">Reset</button>
   </div>
   <div id="new_locations">
      <div id='to_filter'></div>
      <div id='to_list'></div>
   </div>
   <div id="actions">
      <div class="control-group">
         <label class="control-label" for="dob">Change Date</label>
         <div class="animal_input controls">
            <input type="text" name='change_date' id="change_date" placeholder="Change Date" class='input-medium form-control' />
         </div>
      </div>
      <button style="padding:4px 16px;" id="save">Save</button>
   </div>
</div>
<div id="messageNotification"><div></div></div>
<script type="text/javascript">
   $('#whoisme .back').html('<a href=\'?page=farm_animals\'>Back</a>');       //back link
   $("#add").jqxButton({ width: '150'});
   $("#add_all").jqxButton({ width: '150'});
   $("#remove").jqxButton({ width: '150'});
   $("#reset").jqxButton({ width: '150'});

   var animals = new Animals();
   animals.byLocations = <?php echo json_encode($animalLocations); ?>;
   animals.allAnimals = <?php echo json_encode($animalLocations['allAnimals']); ?>;
   animals.includeTopLevels = true;
   animals.locationOrganiser();

   // create the change date widget
   datePickerController.createDatePicker({ formElements:{"change_date":"%d-%m-%Y"}, fillGrid: true, constraintSelection:false, maxDate: 0 });

   // bind the click functions of the buttons
   $("#reset, #remove, #add, #add_all").live('click', function(sender){ animals.moveAnimals(sender); });
   $("#save").live('click', function(){ animals.saveChanges(); });
   animals.movedAnimals = {};
   animals.initiateFiltersnLists('movement');

   //default to select all animals
   $('#fromId').val('all').change();
</script>
<?php
   }

   /**
    * Saves the movement of animals from one paddock to another
    *
    * @param array   $eventDates    (Optional) The dates to use when saving the event. This is an associative array with an animal id associated with the event date
    * @todo Add a check to ensure that the end date is greater than the start date
    */
   private function saveAnimalMovement($eventDates = NULL){
      // lets save the animal new locations
      $animals = json_decode($_POST['animals'], true);

      // if we have a start date specified, use it as the end date for the previous location and the start date for the new location
      if(isset($_POST['start_date'])){
         $startDate = DateTime::createFromFormat('d-m-Y', $_POST['start_date']);
         $startDate = $startDate->format('Y-m-d');
      }
      else{
         $startDate = date('Y-m-d');
      }

      $mvmntQuery = 'insert into '. Config::$farm_db .'.farm_animal_locations(location_id, animal_id, start_date, added_by, added_at) values(:location_id, :animal_id, :start_date, :added_by, :added_at)';
      $updateQuery = 'update '. Config::$farm_db .'.farm_animal_locations set end_date = :edate, updated_by = :updated_by, updated_at = :updated_at where location_id = :location_id and animal_id = :animal_id and end_date is null';
      $updateWOLocationQuery = 'update '. Config::$farm_db .'.farm_animal_locations set end_date = :edate, updated_by = :updated_by, updated_at = :updated_at where animal_id = :animal_id and end_date is null limit 1';
      $updateAnimalLocation = 'update '. Config::$farm_db .'.farm_animals set current_location = :current_loc where id = :animal_id';

      // queries to ensure the dates are being added well
      $checkQuery = 'select id from '. Config::$farm_db .'.farm_animal_locations where location_id = :location_id and animal_id = :animal_id and end_date is null and start_date > :end_date';
      $checkWOLocationQuery = 'select id from '. Config::$farm_db .'.farm_animal_locations where animal_id = :animal_id and end_date is null and start_date > :end_date';
      $this->Dbase->StartTrans();
      foreach($animals as $id => $name){
         $this->Dbase->CreateLogEntry("$id ==> $name", 'debug');
         // update the from locations
         $end_date = (is_null($eventDates)) ? $startDate : $eventDates[$id];

         if(!in_array($_POST['from'], array('floating', 'all', 0)) ){
            // check that the dates are fine before updating the database
            $checkVars = array('end_date' => $end_date, 'location_id' => $_POST['from'], 'animal_id' => $id);
            $check = $this->Dbase->ExecuteQuery($checkQuery, $checkVars);
            if($check == 1){ $this->Dbase->RollBackTrans(); die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastError))); }
            else if(count($check) != 0){
               $this->Dbase->RollBackTrans();
               die(json_encode(array('error' => true, 'mssg' => 'The specified end date is less than the start date for this dataset'. print_r($check, true))));
            }

            $upcols = array('edate' => $end_date, 'location_id' => $_POST['from'], 'animal_id' => $id, 'updated_at' => date('Y-m-d H:i:s'), 'updated_by' => $_SESSION['user_id']);
            $this->Dbase->CreateLogEntry("updating the end date for animal '$id'",  'info');
            $res1 = $this->Dbase->ExecuteQuery($updateQuery, $upcols);
            if($res1 == 1){ $this->Dbase->RollBackTrans(); die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastError))); }
         }
         // if the origin is from all, then update the end date of the previous record before adding the new record
         if($_POST['from'] == 'all'){
            // check that the dates are fine before updating the database
            $checkVars = array('end_date' => $end_date, 'animal_id' => $id);
            $check = $this->Dbase->ExecuteQuery($checkWOLocationQuery, $checkVars);
            if($check == 1){ $this->Dbase->RollBackTrans(); die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastError))); }
            else if(count($check) != 0){
               $this->Dbase->RollBackTrans();
               die(json_encode(array('error' => true, 'mssg' => 'The specified end date is less than the start date for this dataset'. print_r($check, true))));
            }

            $upcols = array('edate' => $end_date, 'animal_id' => $id, 'updated_at' => date('Y-m-d H:i:s'), 'updated_by' => $_SESSION['user_id']);
            $this->Dbase->CreateLogEntry("updating the end date for animal '$id'",  'info');
            $res1 = $this->Dbase->ExecuteQuery($updateWOLocationQuery, $upcols);
            if($res1 == 1){ $this->Dbase->RollBackTrans(); die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastError))); }
         }
         $colvals = array('location_id' => $_POST['to'], 'animal_id' => $id, 'start_date' => $end_date, 'added_at' => date('Y-m-d H:i:s'), 'added_by' => $_SESSION['user_id']);
         $res = $this->Dbase->ExecuteQuery($mvmntQuery, $colvals);
         if($res == 1){
            $this->Dbase->RollBackTrans();
            die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastError)));
         }

         // update the redundant current animal location
         $colvals = array('current_loc' => $_POST['to'], 'animal_id' => $id);
         $res = $this->Dbase->ExecuteQuery($updateAnimalLocation, $colvals);
         if($res == 1){
            $this->Dbase->RollBackTrans();
            die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastError)));
         }
      }
      $this->Dbase->CommitTrans();
      $animalLocations = $this->getAnimalLocations(true);
      die(json_encode(array('error' => 'false', 'data' => $animalLocations, 'mssg' => 'The movement has been saved successfully')));
   }

   /**
    * Create a new page for animal events
    */
   private function animalEvents(){
      // testing the notification system
//      $this->emailEventsDigest();
?>
<script type="text/javascript" src="js/farm_animals.js"></script>
<link rel="stylesheet" href="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/styles/jqx.base.css" type="text/css" />
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxcore.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxdata.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxbuttons.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxscrollbar.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxmenu.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxcheckbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxlistbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxdropdownlist.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxgrid.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxgrid.sort.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxgrid.pager.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxgrid.selection.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxnotification.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxdatetimeinput.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxcalendar.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxgrid.filter.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxwindow.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/globalization/globalize.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxdata.export.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxgrid.export.js"></script>
<div id="events_home">
   <div id="events_grid"></div>
</div>
<div id="messageNotification"><div></div></div>
<div id="modalWindow" style="display: none;">
   <div id="mWindow_header">Confirm Delete</div>
   <div>
      <span id="mWindow_content">Are you sure you want to delete this event?</span>
      <input type="button" value="Yes" id="button_yes" />
      <input type="button" value="No" id="button_no" />
   </div>
</div>

<script type="text/javascript">
   $('#whoisme .back').html('<a href=\'?page=farm_animals\'>Back</a>');       //back link
   var animals = new Animals();
   animals.initiateAnimalsEventsGrid();
   // bind the click functions of the buttons
   animals.exitVariable = '<?php echo Config::$farm_exit_name; ?>';
   animals.tempVariable = '<?php echo Config::$farm_temperature_name; ?>';
   animals.weightVariable = '<?php echo Config::$farm_weighing_name; ?>';
   animals.seenVariable = '<?php echo Config::$farm_seen_name; ?>';
   animals.pmReportVariable = '<?php echo Config::$farm_pm_report; ?>';
   animals.valueEvents = [animals.tempVariable, animals.weightVariable, animals.seenVariable];
   $('.event_id_href').live('click', function(that){ animals.confirmDeleteEvent(that); } );
</script>
<?php
   }

   /**
    * Get a list of all animal events
    */
   private function eventsList(){
      $eventsQuery = 'select a.event_type_id, a.sub_event_type_id, if(d.id is null, c.event_name, concat(c.event_name, " >> ", d.sub_event_name)) as event_name, a.event_date, record_date as time_recorded, recorded_by, performed_by as performed_by_id, performed_by, count(*) as no_animals '
          . 'from '. Config::$farm_db .'.farm_animal_events as a inner join '. Config::$farm_db .'.farm_animals as b on a.animal_id=b.id '
          . 'inner join '. Config::$farm_db .'.farm_events as c on a.event_type_id=c.id '
          . 'left join '. Config::$farm_db .'.farm_sub_events as d on a.sub_event_type_id=d.id '
          . 'group by a.event_type_id, a.sub_event_type_id, date(a.event_date), performed_by order by event_date desc';
      $events = $this->Dbase->ExecuteQuery($eventsQuery);
      if($events == 1) { die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastError))); }
      $owners = $this->getAllOwners(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE);
      if(is_string($owners)) { die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastError))); }

      foreach($events as $id => $ev){
         $events[$id]['performed_by'] = $owners[$ev['performed_by']]['name'];
         $events[$id]['recorded_by'] = $owners[$ev['recorded_by']]['name'];
      }
      die(json_encode(array('error' => false, 'data' => $events)));
   }

   /**
    * Gets the sub list of a particular group
    */
   private function eventsSubList(){
      // prepare for the query
      $subEvent = (is_numeric($_POST['sub_event_type_id'])) ? $_POST['sub_event_type_id'] : NULL;
      $vars = array('event_type_id' => $_POST['event_type_id'], 'event_date' => $_POST['event_date'], 'performed_by' => $_POST['performed_by']);

      if(is_numeric($_POST['sub_event_type_id'])){
         $vars['sub_event_type_id'] = $_POST['sub_event_type_id'];
         $addQuery = ' and a.sub_event_type_id = :sub_event_type_id ';
         $subEvent = $_POST['sub_event_type_id'];
      }
      else{
         $subEvent = NULL;
      }

      $eventsQuery = 'select a.id as event_id, b.animal_id, b.sex, event_value, record_date as time_recorded, performed_by, recorded_by, a.comments '
          . 'from '. Config::$farm_db .'.farm_animal_events as a inner join '. Config::$farm_db .'.farm_animals as b on a.animal_id=b.id '
          . 'left join '. Config::$farm_db .'.experiments as e on b.current_exp=e.id '
          . "where a.event_type_id = :event_type_id $addQuery and a.event_date = :event_date and performed_by = :performed_by";

      $events = $this->Dbase->ExecuteQuery($eventsQuery, $vars);
      if($events == 1) { die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastQuery))); }

      $owners = $this->getAllOwners(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE);
      if(is_string($owners)) { die(json_encode(array('error' => true, 'mssg' => $owners))); }
      foreach($events as $key => $event){
         $events[$key]['performed_by'] = $owners[$event['performed_by']]['name'];
         $events[$key]['recorded_by'] = $owners[$event['recorded_by']]['name'];
      }

      die(json_encode(array('error' => false, 'data' => $events)));

   }

   /**
    * Fetch data that will be used for creating the interface for adding new events
    */
   private function newEventsData(){
      $fields = json_decode($_POST['fields'], true);
      // get a list of all events
      $eventsQuery = 'select id, event_name as `name` from '. Config::$farm_db .'.farm_events order by event_name';
      $events = $this->Dbase->ExecuteQuery($eventsQuery);
      if($events == 1) { die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastQuery))); }
      $toReturn = array();
      $toReturn['events'] = $events;

      // get the list of sub-events
      $subEventsQuery = 'SELECT id, sub_event_name as name FROM '. Config::$farm_db .'.farm_sub_events';
      $subEvents = $this->Dbase->ExecuteQuery($subEventsQuery);
      if($subEvents == 1) { die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastQuery))); }
      $toReturn['sub_events'] = $subEvents;

      // return the minimum days allowed for adding events
      $toReturn['eventMinDays'] = Config::$min_days_for_events;

      // get animal groupings
      // by owners
      if(in_array('byOwners', $fields)){
         $res = $this->groupAnimalsByOwners();
         if(is_string($res)) { die(json_encode(array('error' => true, 'mssg' => $res))); }
         $toReturn['byOwners'] = $res['byOwners'];
         $toReturn['allOwners'] = $res['owners'];
      }

      // by locations
      if(in_array('byLocations', $fields)){
         $animalsByLocations = $this->getAnimalLocations(true);
         if(is_string($animalsByLocations)) { die(json_encode(array('error' => true, 'mssg' => $animalsByLocations))); }
         $toReturn['byLocations'] = $animalsByLocations;
      }

      // get all owners
      if(in_array('allOwners', $fields)){
         $allOwners = $this->getAllOwners();
         if(is_string($allOwners)) { die(json_encode(array('error' => true, 'mssg' => $allOwners))); }
         $toReturn['allOwners'] = $allOwners;
      }

      // get animals by experiments
      if(in_array('byExperiments', $fields)){
         $experiments = $this->getAllExperiments();
         if(is_string($experiments)) { die(json_encode(array('error' => true, 'mssg' => $experiments))); }

         $exp = $this->groupAnimalsByExperiments($experiments);
         if(is_string($exp)) { die(json_encode(array('error' => true, 'mssg' => $exp))); }
         $toReturn['byExperiments'] = $exp;
         $toReturn['allExperiments'] = $experiments;
      }

      die(json_encode(array('error' => false, 'data' => $toReturn)));
   }

   /**
    * Saves new animal events
    */
   private function saveAnimalEvents(){
      $animals = json_decode($_POST['animals']);
      $extras = json_decode($_POST['extras'], true);
      $this->Dbase->StartTrans();
      $addedFiles = array();
      // if we have uploaded files... save them..
      if(count($_FILES) != 0){
         $files = GeneralTasks::CustomSaveUploads('../farm_uploads/', 'uploads', array('application/pdf'), true);
         $addFileQuery = 'insert into '. Config::$farm_db .'.uploaded_files(file_name, path) values(:file_name, :path)';
         if(is_string($files)) die(json_encode(array('error' => true, 'mssg' => $files)));
         elseif($files == 0) $files = array();
         else{
            // add the file records to the database and move them to the proper places
            foreach($files as $index => $file){
               $fileName = $_FILES['uploads']['name'][$index];
               $res = $this->Dbase->ExecuteQuery($addFileQuery, array('file_name' => $fileName, 'path' => $file));
               if($res == 1){
                  $this->Dbase->RollBackTrans();
                  die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastError)));
               }
               else $addedFiles[] = $this->Dbase->dbcon->lastInsertId();
            }
         }
      }

      if(!is_numeric($_POST['to'])){
         // we have a new event name, so lets add it
         $eventId = $this->saveNewEventName($_POST['to']);
         if(!is_numeric($eventId)){
            $this->Dbase->RollBackTrans();
            die(json_encode(array('error' => true, 'mssg' => $eventId)));
         }
      }
      else $eventId = $_POST['to'];

      $exitVariableQuery = 'select id from '. Config::$farm_db .'.farm_events where event_name = :event_name';
      $exitVariable = $this->Dbase->ExecuteQuery($exitVariableQuery, array('event_name' => Config::$farm_exit_name));
      if($exitVariable == 1) {
         $this->Dbase->RollBackTrans();
         die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastError)));
      }

      // so lets save the events
      $addQuery = 'insert into '. Config::$farm_db .'.farm_animal_events(animal_id, event_type_id, event_date, performed_by, recorded_by, comments, sub_event_type_id, event_value) '
         . 'values(:animal_id, :event_type_id, :event_date, :performed_by, :recorded_by, :comments, :sub_event_type_id, :event_value)';

      $date = date_create_from_format('d/m/Y', $extras['eventDate']);
      $vals = array('event_type_id' => $eventId, 'event_date' => date_format($date, 'Y-m-d'), 'performed_by' => $extras['performedBy'], 'recorded_by' => $_SESSION['user_id'], 'comments' => $extras['comments']);
      $vals['sub_event_type_id'] = (is_numeric($extras['exitType'])) ? $extras['exitType'] : NULL;
      $vals['event_value'] = (isset($extras['eventValue'])) ? $extras['eventValue'] : NULL;

      $updateAnimalStatus = 'update '. Config::$farm_db .'.farm_animals '
         . 'set status = (SELECT concat(event_name, " >> ", sub_event_name) FROM '. Config::$farm_db .'.farm_events as a inner join '. Config::$farm_db .'.farm_sub_events as b on a.id=b.event_id where b.id = :sub_event_id) '
         . 'where id = :id';

      $addEventFileEntry = 'insert into '. Config::$farm_db .'.event_files(event_id, file_id) values(:event_id, :file_id)';

      foreach($animals as $animalId => $animal){
         $colvals = $vals;
         $colvals['animal_id'] = $animalId;

         $res = $this->Dbase->ExecuteQuery($addQuery, $colvals);
         if($res == 1){
            $this->Dbase->RollBackTrans();
            die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastError)));
         }

         $newEventId = $this->Dbase->dbcon->lastInsertId(); // get the system event id for future use ...

         // if its an exit event, there is need to update the status of the animal....
         if($eventId == $exitVariable[0]['id']){
            $updateVals = array('sub_event_id' => $extras['exitType'], 'id' => $animalId);
            $res1 = $this->Dbase->ExecuteQuery($updateAnimalStatus, $updateVals);
            if($res1 == 1){
               $this->Dbase->RollBackTrans();
               die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastError)));
            }
         }

         // if we have uploaded files.. associate their records to this event
         foreach ($addedFiles as $addedFile){
            $res = $this->Dbase->ExecuteQuery($addEventFileEntry, array('event_id' => $newEventId, 'file_id' => $addedFile));
            if($res == 1){
               $this->Dbase->RollBackTrans();
               die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastError)));
            }
         }
      }
      // we are all good, lets return
      $this->Dbase->CommitTrans();
      die(json_encode(array('error' => 'false', 'mssg' => 'The event has been saved successfully.')));
   }

   /**
    * Delete an event
    */
   private function deleteAnimalEvents(){
      // we want to delete an event. Check if its an exit event, if it is, we will need to update the farm animal details
      $eventQuery = 'select event_name, animal_id from '. Config::$farm_db .'.farm_animal_events as a inner join '. Config::$farm_db .'.farm_events as b on a.event_type_id=b.id where a.id = :event_id';
      $eventName = $this->Dbase->ExecuteQuery($eventQuery, array('event_id' => $_POST['event_id']));
      if($eventName == 1) die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastError)));

      $this->Dbase->StartTrans();
      // delete the event details
      $updateQuery = 'delete from '. Config::$farm_db .'.farm_animal_events where id = :event_id';
      $deleteEvent = $this->Dbase->ExecuteQuery($updateQuery, array('event_id' => $_POST['event_id']));
      if($deleteEvent == 1){
         $this->Dbase->RollBackTrans();
         die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastError)));
      }

      // update the animal status if we deleted an exit event
      if($eventName[0]['event_name'] == 'Exit'){
         $updateQuery = 'update '. Config::$farm_db .'.farm_animals set status = :alive where id = :animal_id';
         $updateResult = $this->Dbase->ExecuteQuery($updateQuery, array('animal_id' => $eventName[0]['animal_id'], 'alive' => 'Alive'));
         if($updateResult == 1){
            $this->Dbase->RollBackTrans();
            die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastError)));
         }
      }
      $this->Dbase->CommitTrans();

      die(json_encode(array('error' => false, 'mssg' => 'The event has been deleted successfully')));
   }

   /**
    * Saves a new event name
    *
    * @param   string         $event_name    The name of the event to save
    * @return  string|integer Returns a string incase of error, else it returns the id of the inserted event
    */
   private function saveNewEventName($event_name){
      $insertQuery = 'insert into '. Config::$farm_db .'.farm_events(event_name) values(:event_name)';
      $res = $this->Dbase->ExecuteQuery($insertQuery, array('event_name' => $event_name));
      if($res == 1) return $this->Dbase->lastError;
      else return $this->Dbase->dbcon->lastInsertId();
   }

   /**
    * Creates a new page for managing experiments
    */
   private function experimentsHome(){
      global $Repository;
     $Repository->DateTimePickerFiles();
?>
<script type="text/javascript" src="js/farm_animals.js"></script>
<link rel="stylesheet" href="<?php echo OPTIONS_COMMON_FOLDER_PATH?>azizi-shared-libs/customMessageBox/mssg_box.css" />
<link rel="stylesheet" href="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/styles/jqx.base.css" type="text/css" />
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxcore.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxdata.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxbuttons.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxscrollbar.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxmenu.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxcheckbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxlistbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxdropdownlist.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxgrid.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxgrid.sort.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxgrid.pager.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxgrid.selection.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxnotification.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>azizi-shared-libs/customMessageBox/customMessageBox.js"></script>
<div id="experiments">
   <div id="exp_grid"></div>
   <div id="grid_actions">
      <button style="padding:4px 16px;" id="new_exp">Add an Experiment</button>
      <button style="padding:4px 16px;" id="new_exp_animals">Manage Exp Animals</button>
   </div>
</div>
<div id="messageNotification"><div></div></div>

<script type="text/javascript">
   $('#whoisme .back').html('<a href=\'?page=farm_animals\'>Back</a>');       //back link
   $("#new_exp").jqxButton({ width: '150'});
   $("#new_exp_animals").jqxButton({ width: '200'});
   var animals = new Animals();
   // bind the click functions of the buttons
   $("#new_exp").on('click', function(){ animals.newExperiment(); });
   $("#new_exp_animals").on('click', function(){ animals.newExperimentAnimals(); });
</script>
<?php
   }

   /**
    * Search the experiments database
    */
   private function searchExperiments(){
      $searchQuery = 'select exp_name, iacuc, start_date from '. Config::$farm_db .'.experiments where exp_name like :exp';
      $res = $this->Dbase->ExecuteQuery($searchQuery, array('exp' => "%{$_GET['query']}%"));

      if($res == 1) die(json_encode(array('error' => true, 'data' => $this->Dbase->lastError)));
      $suggestions = array();
      foreach($res as $t){
         $suggestions[] = array('value' => $t['exp_name'], 'data' => $t['exp_name']);
      }
      $data = array('error' => false, 'query' => $_GET['query'], 'suggestions' => $suggestions);
      header("Content-type: application/json");
      die(json_encode($data, true));
   }

   /**
    * Get the data to be used for creating a new experiment
    */
   private function experimentsData(){
      $fields = json_decode($_POST['fields']);
      if($_POST['field'] == 'experiments'){
         $res = $this->experimentsList();
         if($res == 1) die(json_encode(array('error' => true, 'mssg' => $res)));
         die(json_encode(array('error' => 'false', 'data' => $res)));
      }
      else if($_POST['field'] == 'pis'){
         // get the list of owners
         $res = $this->getAllOwners();
         if(is_string($res)) die(json_encode(array('error' => true, 'mssg' => $res)));
         die(json_encode(array('error' => 'false', 'data' => $res)));
      }
      else if(in_array('byOwners', $fields)){
         $query = 'select id, exp_name as name from '. Config::$farm_db .'.experiments order by exp_name';
         $res = $this->Dbase->ExecuteQuery($query);
         if($res == 1) die(json_encode(array('error' => true, 'mssg' => $res)));

         $res1 = $this->groupAnimalsByOwners();
         if(is_string($res1)) die(json_encode(array('error' => true, 'mssg' => $res1)));

         $res2 = $this->groupAnimalsByExperiments();
         if(is_string($res1)) die(json_encode(array('error' => true, 'mssg' => $res1)));

         die(json_encode(array('error' => 'false', 'data' => array('byOwners' => $res1, 'experiments' => $res, 'byExperiments' => $res2))));
      }
   }

   /**
    * Get a list of all the experiments
    * @return  boolean|array  Returns a string in case there is an error, else returns an array with the defined experiments
    */
   private function experimentsList(){
      $query = 'select a.exp_name, a.start_date, a.end_date, a.pi_id, iacuc, a.comments from '. Config::$farm_db .'.experiments as a';
      $exps = $this->Dbase->ExecuteQuery($query);
      if($exps == 1) return $this->Dbase->lastError;
      // get the list of owners
      $res1 = $this->getAllOwners(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE);
      if(is_string($res1))  return  $res1;
      foreach ($exps as $id => $exp){
         $exps[$id]['pi_name'] = $res1[$exp['pi_id']]['name'];
      }
      return $exps;
   }

   /**
    * Saves a new experiment
    */
   private function saveNewExperiment(){
      $addQuery = 'insert into '. Config::$farm_db .'.experiments(exp_name, iacuc, pi_id, start_date) values(:exp_name, :iacuc, :pi_id, :start_date)';
      $start_date = date_create_from_format('d-m-Y', $_POST['start_date']);
      $res = $this->Dbase->ExecuteQuery($addQuery, array('exp_name' => $_POST['experiment'], 'iacuc' => $_POST['iacuc'], 'pi_id' => $_POST['pis'], 'start_date' => date_format($start_date, 'Y-m-d')));
      if($res == 1) die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastError)));
      die(json_encode(array('error' => false, 'mssg' => 'The experiment have been saved successfully!')));
   }

   /**
    * Saves new animal and experiments association
    */
   private function saveExperimentAnimals(){
      $animals = json_decode($_POST['animals']);
      $addQuery = 'insert into '. Config::$farm_db .'.exp_animals(animal_id, exp_id, start_date) values(:animal_id, :exp_id, :start_date)';
      $updateExpQuery = 'update '. Config::$farm_db .'.farm_animals set current_exp = :current_exp where id = :animal_id';
      $vals = array('exp_id' => $_POST['to'], 'start_date' => date('Y-m-d'));
      // start the transacation
      $this->Dbase->StartTrans();
      foreach($animals as $animalId => $animal){
         // update the redundant current animal experiment
         $this->Dbase->CreateLogEntry("$animalId --> ". print_r($animal, true), 'debug');
         $updatevals = array('current_exp' => $vals['exp_id'], 'animal_id' => $animalId);
         $res = $this->Dbase->ExecuteQuery($updateExpQuery, $updatevals);
         if($res == 1){
            $this->Dbase->RollBackTrans();
            die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastError)));
         }

         $colvals = $vals;
         $colvals['animal_id'] = $animalId;
         $res = $this->Dbase->ExecuteQuery($addQuery, $colvals);
         if($res == 1){
            $this->Dbase->RollBackTrans();
            die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastError)));
         }
      }

      //we good so commit trans..
      $this->Dbase->CommitTrans();
      die(json_encode(array('error' => 'false', 'mssg' => 'The animals have been succesfully saved.')));
   }

   /**
    * Gets all the farm users as defined in the system
    *
    * @return  string|array   Returns a string with the error in case of an error, else it returns an array with the defined farm users
    */
   private function getAllOwners($fetchAs = PDO::FETCH_ASSOC){
      $allUsersQuery = 'SELECT a.id, concat(sname, " ", onames) as `name`, email '
            . 'FROM '. Config::$lims_extension .'.users as a inner join '. Config::$lims_extension .'.user_groups as b on a.id=b.user_id '
            . 'inner join '. Config::$lims_extension .'.groups as c on b.group_id=c.id '
            . 'where c.name in (:farm_module_admin, :farm_module_users)';
      $vals = array('farm_module_admin' => Config::$farm_module_admin, 'farm_module_users' => Config::$farm_module_users);

      $res = $this->Dbase->ExecuteQuery($allUsersQuery, $vals, $fetchAs);
      if($res == 1) return $this->Dbase->lastError;
      else return $res;
   }

   /**
    * Gets a list of all experiments as saved in the database
    *
    * @param   integer  $fetchAs    Whether to fetch the data as a proper object or just as an array
    * @return  string|array|object  Returns a string with the error message in case an error occurs, else it returns an array or an object, depending on what was requested
    */
   private function getAllExperiments($fetchAs = PDO::FETCH_ASSOC){
      $query = 'select id, exp_name as name from '. Config::$farm_db .'.experiments order by exp_name';
      $experiments = $this->Dbase->ExecuteQuery($query);
      if($experiments == 1) return $this->Dbase->lastError;
      else return $experiments;
   }

   /**
    * Fetch all information for a particular animal
    */
   private function getAnimalInfo(){
      $fetchQuery = 'select a.*, b.name as species, if(dob = 0, "", dob) as dob, a.current_owner, d.exp_name, d.iacuc, d.start_date as exp_startdate, d.end_date as exp_enddate, d.comments as exp_comments, concat(e.level1, " >> ", e.level2) as location, f.breed '
              . 'from '. Config::$farm_db .'.farm_animals as a inner join '. Config::$farm_db .'.farm_species as b on a.species_id=b.id '
              . 'left join '. Config::$farm_db .'.experiments as d on a.current_exp=d.id '
              . 'left join '. Config::$farm_db .'.farm_locations as e on a.current_location=e.id '
              . 'left join (select animal_id, group_concat(breed_name SEPARATOR ", ") as breed from '. Config::$farm_db .'.animal_breeds as a inner join '. Config::$farm_db .'.breeds as b on a.breed_id=b.id group by a.animal_id) as f on a.id=f.animal_id '
              . 'where a.id = :animal_id';
      $res = $this->Dbase->ExecuteQuery($fetchQuery, array('animal_id' => $_POST['animal_id']));
      if($res == 1) die(json_encode(array('error' => true, 'message' => 'There was an error while fetching data from the database. Contact the system administrator')));

      // get the list of animal images
      $animalId = $res[0]['animal_id'];
      $grepCommand = "/usr/bin/find ". Config::$farmThumbnailsPath ." -iname '$animalId*' ";
      exec($grepCommand, $output);
      $res[0]['imageList'] = array();
      foreach($output as $image){
         $res[0]['imageList'][] = pathinfo($image, PATHINFO_BASENAME);
      }
      die(json_encode(array('error' => false, 'data' => $res[0])));
   }

   /**
    * Compile and send an email to the users of the day's events
    *
    * @todo Define the farm manager email as a setting
    */
   private function emailEventsDigest($email_type){
      global $Repository;
      // load the settings from the main file
      $settings = $Repository->loadSettings();
      $this->Dbase->CreateLogEntry("sending daily digest", 'info');

      // get a list of all the day's events and send them to the concerned user
      if($email_type == 'weekly'){
         $eventsQuery = 'select a.event_type_id, a.sub_event_type_id, if(d.id is null, c.event_name, concat(c.event_name, " >> ", d.sub_event_name)) as event_name, b.current_owner, '
               . 'a.event_date, record_date as time_recorded, recorded_by, performed_by, b.animal_id, b.sex, event_value, a.comments  '
             . 'from '. Config::$farm_db .'.farm_animal_events as a inner join '. Config::$farm_db .'.farm_animals as b on a.animal_id=b.id '
             . 'inner join '. Config::$farm_db .'.farm_events as c on a.event_type_id=c.id '
             . 'left join '. Config::$farm_db .'.farm_sub_events as d on a.sub_event_type_id=d.id where event_date < :end_date and event_date > :start_date';
         $events = $this->Dbase->ExecuteQuery($eventsQuery, array('start_date' => date('Y-m-d', strtotime('-7 days')), 'end_date' => date('Y-m-d', strtotime('+1 days'))));
         $intro = 'Below is a digest of activities carried out on animals assigned to you and <b>RECORDED IN THE LAST 7 DAYS</b><b></b>. If you have any questions, kindly contact the farm manager through '. Config::$farmManagerEmail .'<br /><br />';
      }
      elseif($email_type == 'daily'){
         $eventsQuery = 'select a.event_type_id, a.sub_event_type_id, if(d.id is null, c.event_name, concat(c.event_name, " >> ", d.sub_event_name)) as event_name, b.current_owner, '
               . 'a.event_date, record_date as time_recorded, recorded_by, performed_by, b.animal_id, b.sex, event_value, a.comments  '
             . 'from '. Config::$farm_db .'.farm_animal_events as a inner join '. Config::$farm_db .'.farm_animals as b on a.animal_id=b.id '
             . 'inner join '. Config::$farm_db .'.farm_events as c on a.event_type_id=c.id '
             . 'left join '. Config::$farm_db .'.farm_sub_events as d on a.sub_event_type_id=d.id where event_date = :event_date and c.event_category = :cat';
         $events = $this->Dbase->ExecuteQuery($eventsQuery, array('event_date' => date('Y-m-d'), 'cat' => 'heightened'));
         $intro = "Below are the activities carried out on your animals and were recorded <b>". date('dS M Y') .'</b>. If you have any questions, kindly contact the farm manager through '. Config::$farmManagerEmail .'<br /><br />';
      }

      if($events == 1) { die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastError))); }
      $owners = $this->getAllOwners(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE);
      if(is_string($owners)) { die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastError))); }

      $uniqueOwners = array();
      foreach($events as $id => $ev){
         $events[$id]['performed_by'] = $owners[$ev['performed_by']]['name'];
         $events[$id]['recorded_by'] = $owners[$ev['recorded_by']]['name'];
         $events[$id]['owner'] = $owners[$ev['current_owner']]['name'];
         $uniqueOwners[] = $ev['current_owner'];
      }
      $uniqueOwners = array_unique($uniqueOwners);

      // loop thru all the owners and create a report for them
      foreach ($uniqueOwners as $owner){
         $animalsByOwner = array();
         // loop through all the affected animals and create the report
         $style = '<style type="text/css">';
         $style .= 'table{font-family: verdana,arial,sans-serif; font-size:10px; color:#333333; border-width: 1px; border-color: #666666; border-collapse: collapse; }';
         $style .= 'th { border-width: 1px; padding: 8px; border-style: solid; border-color: #666666; background-color: #dedede; }';
         $style .= 'td { border-width: 1px; padding: 8px; border-style: solid; border-color: #666666; background-color: #ffffff; }';
         $style .= '</style>';
         $content = "Dear {$owners[$owner]['name']}, <br /><br />$intro";
         $content .= "$style";
         $content .= '<table><tr><th>Animal ID</th><th>Event Date</th><th>Event</th><th>Event Value</th><th>Recorded By</th><th>Performed By</th><th>Time Recorded</th><th>Comments</th></tr>';
         foreach($events as $id => $event){
            if($event['current_owner'] == $owner){
               $animalsByOwner[$id] = $event;
               $content .= "<tr><td>{$event['animal_id']} ({$event['sex']})</td><td>{$event['event_date']}</td><td>{$event['event_name']}</td><td>{$event['event_value']}</td><td>{$event['recorded_by']}</td>"
               . "<td>{$event['performed_by']}</td><td>{$event['time_recorded']}</td><td>{$event['comments']}</td></tr>";
            }
         }
         $content .= "</table><br /><br />Regards<br />The Farm team";
         // email this user with the animal report

         $this->Dbase->CreateLogEntry("sending an email to {$owners[$owner]['name']} with the daily digest", 'info');
//         shell_exec("echo '$content' | {$settings['mutt_bin']} -e 'set content_type=text/html' -c 'azizibiorepository@cgiar.org' -c 's.kemp@cgiar.org' -F {$settings['mutt_config']} -s 'Farm animals activities digest' -- ". Config::$farmManagerEmail);
//         shell_exec("echo '$content' | {$settings['mutt_bin']} -e 'set content_type=text/html' -c '".Config::$farmManagerEmail."' -c '".Config::$farmRecordKeeperEmail."' -c '".Config::$farmManagerSupervisorEmail."' -c '".Config::$farmSystemDeveloperEmail."' -F {$settings['mutt_config']} -s 'Farm animals activities digest' -- {$owner['email']}");
         shell_exec("echo '$content' | {$settings['mutt_bin']} -e 'set content_type=text/html' -c '".Config::$farmSystemDeveloperEmail."' -F {$settings['mutt_config']} -s 'Farm animals activities digest' -- a.kihara@cgiar.org");
      }
      die(json_encode(array('error' => false, 'mssg' => 'All processed')));
   }

   /**
    * creates the home page for image uploads
    */
   private function fileUploadsHome(){
?>
<script type="text/javascript" src="js/farm_animals.js"></script>
<link rel="stylesheet" href="<?php echo OPTIONS_COMMON_FOLDER_PATH?>azizi-shared-libs/customMessageBox/mssg_box.css" />
<link rel="stylesheet" href="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/styles/jqx.base.css" type="text/css" />
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxcore.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxdata.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxnotification.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxbuttons.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxfileupload.js"></script>

<div id="images">
   <div id="info">
      Use the placeholder below to upload images of the animals.
      <ol>
         <li>You can only select 1 image at a time</li>
         <li>However, you can upload multiple images at the same time</li>
         <li>The images <b>MUST</b> be jpeg/jpg images</li>
         <li>The name of the images <b>MUST</b> be the animal name. eg. If the image belongs to animal BL005, the image <b>MUST</b> be BL005.jpg or BL005.jpeg</li>
         <li>The system does not discriminate images uploaded multiple times, hence great care should be taken while uploading the images</li>
      </ol>
   </div>
   <div id="upload"></div>
</div>
<div id="messageNotification"><div></div></div>

<script type="text/javascript">
   $('#whoisme .back').html('<a href=\'?page=farm_animals\'>Back</a>');       //back link
   var animals = new Animals();
   animals.initiateImageUploads();
</script>
<?php
   }

   /**
    * Process and saves the uploaded images
    */
   private function processAndSaveUploads(){
      // if we have uploaded files... save them..
      if(count($_FILES) != 0){
         $files = GeneralTasks::CustomSaveUploads('../farmdb_images/', 'images_2_upload', array('image/jpg', 'image/jpeg', 'image/png'), true);
         if(is_string($files)) die(json_encode(array('error' => true, 'mssg' => $files)));
         elseif($files == 0) die(json_encode(array('error' => true, 'mssg' => 'No files were saved.')));
         else{
            // add the file records to the database and move them to the proper places
            foreach($files as $index => $file){
               // create a thumbnail for the image
               $thumbNail = GeneralTasks::CreateThumbnail($file, '../farmdb_images/thumbs/', 200);
               if(is_string($thumbNail)) die(json_encode(array('error' => true, 'mssg' => $thumbNail)));
            }
         }
         die(json_encode(array('error' => false, 'mssg' => 'The images were saved successfully.')));
      }
      else die(json_encode(array('error' => true, 'mssg' => 'No files were uploaded')));
   }

   /**
    * Creates the home page for adding events quickly
    */
   private function quickEventsHome(){
?>
<script type="text/javascript" src="js/farm_animals.js"></script>
<link rel="stylesheet" href="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/styles/jqx.base.css" type="text/css" />
<link rel="stylesheet" href="<?php echo OPTIONS_COMMON_FOLDER_PATH?>lightgallery/light-gallery/css/lightGallery.css" type="text/css" />
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>lightgallery/light-gallery/js/lightGallery.min.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxcore.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxdata.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxdata.export.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxbuttons.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxscrollbar.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxmenu.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxcheckbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxlistbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxdropdownlist.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxgrid.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxgrid.sort.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxgrid.pager.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxgrid.selection.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxgrid.filter.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxwindow.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxtabs.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxgrid.export.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxgrid.edit.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxnotification.js"></script>

<div id="quick_events_ph">
   <div id="addinfo" style="font-weight: normal;">
      This module presents a quick way of adding <b>Weighing</b> and <b>Temperature</b> events which were recorded <b><u>TODAY</u></b> to the database.<br />
      To add a record, search for the animal and then double click the cell for that record. After entering the value, press <b>Enter</b> to save the value.<br />
      <b><i>Please note that it will be recorded that <?php echo "{$_SESSION['surname']} {$_SESSION['onames']}"; ?> took the measurement.</i></b><br />If you need to specify who took the record please use the Animal Events module
   </div>
   <div id="quick_events"></div>
</div>
<div id="messageNotification"><div class="">&nbsp;&nbsp;</div></div>
<script type="text/javascript">
   $('#whoisme .back').html('<a href=\'?page=farm_animals\'>Back</a>');       //back link
   var animals = new Animals();
   animals.initiateQuickEventsGrid();
   animals.info = {};   // an object for holding the animal information
</script>
<?php
   }

   /**
    * Fetch the animal details that will be used in the quick add list
    */
   private function quickEventsList(){
      $query = 'select a.*, b.name as species, if(dob = 0, "", dob) as dob, a.current_owner, d.iacuc as experiment, concat(e.level1, " >> ", e.level2) as location, f.breed, a.status, g.event_value as temp, h.event_value as weight '
         . 'from '. Config::$farm_db .'.farm_animals as a inner join '. Config::$farm_db .'.farm_species as b on a.species_id=b.id '
         . 'left join '. Config::$farm_db .'.experiments as d on a.current_exp=d.id '
         . 'left join '. Config::$farm_db .'.farm_locations as e on a.current_location=e.id '
         . 'left join (select animal_id, group_concat(breed_name SEPARATOR ", ") as breed from '. Config::$farm_db .'.animal_breeds as a inner join '. Config::$farm_db .'.breeds as b on a.breed_id=b.id group by a.animal_id) as f on a.id=f.animal_id '
         . 'left join (select animal_id, event_value from '. Config::$farm_db .'.farm_animal_events as a inner join '. Config::$farm_db .'.farm_events as b on a.event_type_id=b.id where b.event_name = :temp and date(record_date) = :today) as g on a.id=g.animal_id '
         . 'left join (select animal_id, event_value from '. Config::$farm_db .'.farm_animal_events as a inner join '. Config::$farm_db .'.farm_events as b on a.event_type_id=b.id where b.event_name = :weight and date(record_date) = :today) as h on a.id=h.animal_id '
         . 'where a.status not like "%exit%" order by a.animal_id';
      $res = $this->Dbase->ExecuteQuery($query, array('temp' => $this->quickEventsDic['temp'], 'today' => date('Y-m-d'), 'weight' => $this->quickEventsDic['weight']));
      if($res == 1){
         $this->Dbase->CreateLogEntry($this->Dbase->lastError, 'fatal');
         die(json_encode(array('error' => true, 'message' => 'There was an error while fetching data from the database. Contact the system administrator')));
      }
      $owners = $this->getAllOwners(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE);
      if(is_string($owners)) die(json_encode(array('error' => true, 'message' => $owners)));

      foreach($res as $id => $animal){
         $res[$id]['owner'] = $owners[$animal['current_owner']]['name'];
      }
      die(json_encode($res));
   }

   /**
    * Saves the values from adding quick values
    */
   private function quickEventsSave(){
      // get the event id
      $selectQuery = 'select id from '. Config::$farm_db .'.farm_events where event_name = :event_name';
      $res = $this->Dbase->ExecuteQuery($selectQuery, array('event_name' => $this->quickEventsDic[$_POST['type']]));
      if($res == 1) die(json_encode(array('error' => true, 'mssg' => 'There was an error while fetching data from the database. Contact the system administrator')));
      $eventId = $res[0]['id'];

      // so lets save the events
      $addQuery = 'insert into '. Config::$farm_db .'.farm_animal_events(animal_id, event_type_id, event_date, performed_by, recorded_by, event_value) '
         . 'values(:animal_id, :event_type_id, :event_date, :performed_by, :recorded_by, :event_value)';
      $vals = array('animal_id' => $_POST['animal_id'], 'event_type_id' => $eventId, 'event_date' => date('Y-m-d'), 'performed_by' => $_SESSION['user_id'], 'recorded_by' => $_SESSION['user_id'], 'event_value' => $_POST['value']);

      $res1 = $this->Dbase->ExecuteQuery($addQuery, $vals);
      if($res1 == 1) die(json_encode(array('error' => true, 'mssg' => 'There was an error while fetching data from the database. Contact the system administrator')));

      die(json_encode(array('error' => false, 'mssg' => 'The record was succesfully recorded.')));
   }

   /**
    * The home page for visualizing cow weights
    */
   private function weightGraphsHome(){
      $animals = $this->getAnimalsByWeight();
?>
<script type="text/javascript" src="js/farm_animals.js"></script>
<link rel="stylesheet" href="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/styles/jqx.base.css" type="text/css" />
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxcore.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxdata.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxdraw.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxchart.core.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxlistbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxbuttons.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxscrollbar.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxdropdownlist.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxnotification.js"></script>

<div id="weights_graphs">
   <div id="addinfo" style="font-weight: normal;">
      This module presents a quick way of visualizing cow weights. The cows on the left are ordered by the ones losing weight the fastest. Select an animal on the left to show its weight plotted over time.
   </div>
   <div id="cow_details"></div>
   <div id="cow_list"></div>
   <div id="weight_graph"></div>
</div>
<div id="messageNotification"><div class="">&nbsp;&nbsp;</div></div>
<script type="text/javascript">
   $('#whoisme .back').html('<a href=\'?page=farm_animals\'>Back</a>');       //back link
   var animals = new Animals();
   animals.allAnimals = <?php echo json_encode($animals); ?>;
   $("#cow_list").jqxListBox({width: 215, source: animals.allAnimals, displayMember: 'animal_id', valueMember: 'id', height: '400px', filterable: true});
   $("#cow_list").on('select', animals.initiateWeightsChart);
</script>
<?php
   }

   /**
    * Fetch a list of animals sorted by loss of weight
    */
   private function getAnimalsByWeight(){
      $animalQuery = 'select b.id, b.animal_id '
            . 'from '. Config::$farm_db .'.farm_animal_events as a inner join '. Config::$farm_db .'.farm_animals as b on a.animal_id=b.id '
            . 'where event_type_id=13 and b.status="Alive"'
            . 'group by a.animal_id';
      $res = $this->Dbase->ExecuteQuery($animalQuery);
      if($res == 1) return 1;
      else{
         $weights = array();
         foreach($res as $r){
            $weights[] = array('id' => $r['id'], 'animal_id' => $r['animal_id']);
         }
      }
      return $weights;
   }

   /**
    * Fetch the weights of the selected animal
    */
   private function getAnimalWeights(){
      $weighingId = $this->getEventId('Weighing');
      if(!is_numeric($weighingId)) die(json_encode(array('error' => true, 'mssg' => $weighingId)));

      $weightQ = 'select date_format(event_date, "%m/%d/%Y") as eventdate, event_value as weight from '. Config::$farm_db .'.farm_animal_events where event_type_id = :event and animal_id = :animal_id order by event_date';
      $events = $this->Dbase->ExecuteQuery($weightQ, array('event' => $weighingId, 'animal_id' => $_POST['animal_id']));
      if($events == 1) die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastError)));

      header("Content-type: text/csv");
      header("Content-Disposition: attachment; filename=file.csv");
      header("Pragma: no-cache");
      header("Expires: 0");
      foreach($events as $e){
         echo "{$e['eventdate']},{$e['weight']}\n";
      }

   }

   /**
    * Return the id of an event from the database
    *
    * @param   string   $eventName  The name of the event that we want to get its id
    * @return  mixed    Returns the id if successfull else returns the error
    */
   private function getEventId($eventName){
      $weightsEventQ = 'select id from '. Config::$farm_db .'.farm_events where event_name = :event_name';
      $weightsEventName = $this->Dbase->ExecuteQuery($weightsEventQ, array('event_name' => $eventName));
      if($weightsEventName == 1) return $this->Dbase->lastError;
      else return $weightsEventName[0]['id'];
   }

   /**
    * Create a page for batch uploads of scans from the RFID stick
    */
   private function batchUploadsHome(){
      $allOwners = $this->getAllOwners();
      if(is_string($allOwners)){
         $this->homePage($allOwners);
         return;
      }

      // get a list of all the locations
      $locationQuery = 'select id, concat(level1, " >> ", level2) as name from '. Config::$farm_db .'.farm_locations order by level1, level2';
      $locations = $this->Dbase->ExecuteQuery($locationQuery);
      if($locations == 1) {
         $this->homePage($this->Dbase->lastError);
         return;
      }

      // get a list of all events
      $eventsQuery = 'select id, event_name as `name` from '. Config::$farm_db .'.farm_events order by event_name';
      $events = $this->Dbase->ExecuteQuery($eventsQuery);
      if($events == 1) {
         $this->homePage($this->Dbase->lastError);
         return;
      }
      $events[] = array('id' => 'loc', 'name' => 'New Location');
?>
<script type="text/javascript" src="js/farm_animals.js"></script>
<link rel="stylesheet" href="<?php echo OPTIONS_COMMON_FOLDER_PATH?>azizi-shared-libs/customMessageBox/mssg_box.css" />
<link rel="stylesheet" href="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/styles/jqx.base.css" type="text/css" />
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxcore.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxdata.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxnotification.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxbuttons.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxfileupload.js"></script>

<div id="batch_upload">
   <div id="info">
      Use the placeholder below to upload a batch of animals of which a certain event was carried out
      <ol>
         <li>You can only select 1 file at a time. The file should correspond to the format <a href='resources/dummy_allflex_scans.csv'>Sample Allflex Scans</a></li>
      </ol>
   </div>
   <div id="upload"></div>
   <div id="details">
      <div class='control-group'>
         <label class='control-label' for='performed_by'>Event</label>
         <div id="eventsCombo"></div>
      </div>
      <div class="control-group" id='add_ons'>
      </div>
      <div class='control-group'>
         <label class='control-label' for='events'>Performed By</label>
         <div id="performedBy_pl"></div>
      </div>
      <div class='control-group'>
         <label class='control-label' for='comments'>Event Comments</label>
         <div id='comments_pl' class='animal_input controls'><textarea id='event_comments' class='span6' rows='3'></textarea></div>
      </div>
   </div>
</div>
<div id="messageNotification"><div></div></div>

<script type="text/javascript">
   $('#whoisme .back').html('<a href=\'?page=farm_animals\'>Back</a>');       //back link

   var animals = new Animals();
   animals.allEvents = <?php echo json_encode($events, true); ?>;
   animals.allOwners = <?php echo json_encode($allOwners, true); ?>;
   animals.allLocations = <?php echo json_encode($locations, true); ?>;
   animals.initiateBatchFileUpload();
</script>
<?php
   }

   /**
    * Process a file with a batch upload of scans
    */
   private function processAndSaveBatchUploads(){
      $this->Dbase->CreateLogEntry('Processing a batch upload of events', 'info');
      // if we dont have the event and person who did it reject it
      if($_POST['performed_by'] == 0) die(json_encode(array('error' => true, 'mssg' => 'Please specify the person who carried out this event. The update has been cancelled')));
      if($_POST['event'] === 0) die(json_encode(array('error' => true, 'mssg' => 'Please specify the event for this update. The update has been cancelled')));

      // save the file and process it
      $uploaded = GeneralTasks::CustomSaveUploads('tmp/', 'file_2_upload', array('text/csv'), true);
      if(!is_array($uploaded)){
         $this->Dbase->CreateLogEntry($uploaded, 'debug');
         $this->Dbase->CreateLogEntry($uploaded, 'fatal');
         if(is_string($uploaded)) die(json_encode(array('error' => true, 'mssg' => $uploaded)));
         else die(json_encode(array('error' => true, 'mssg' => 'No files were selected for upload.')));
      }

      $fd = fopen($uploaded[0], 'rt');
      if(!$fd) die(json_encode(array('error' => true, 'mssg' => 'There was an error while opening the file for reading')));
      $count = -1;
      $info = '';
      $headers = array();
      $headerCount = 0;
      $data = array();
      $animalData = array();
      $animalDates = array();
      $animalIdQuery = 'select id from '. Config::$farm_db .'.farm_animals where animal_id = :animal_id';
      while($curLine = fgets($fd)){
         $curLine = trim($curLine);
         $parts = explode(',', $curLine);
         if($parts[0] == '') continue;    // empty string don't process it

         if($count == -1){
            $headers = $parts;
            $headerCount = count($headers);
         }
         else $data[] = $parts;

         // if we are dealing with locations get the id of this animal
         if($_POST['event'] == 'loc' && $count > -1){
            $propData = array();
            for($j = 0; $j < $headerCount; $j++){
               $propData[$headers[$j]] = $parts[$j];
            }
            $animalId = $this->Dbase->ExecuteQuery($animalIdQuery, array('animal_id' => $propData['TAG']));
            if($animalId == 1){
               die(json_encode(array('error' => true, 'mssg' => "Error: ". $this->Dbase->lastError)));
            }
            if(count($animalId) == 0){
               $this->Dbase->CreateLogEntry("The animal {$propData['TAG']} wasn't found in the db, skipping it", 'debug');
               $info .= "The animal {$propData['TAG']} wasn't found in the db, skipping it<br />";
            }
            else if(count($animalId) > 1){
               $this->Dbase->CreateLogEntry("The animal {$propData['TAG']} has multiple records in the db, skipping it", 'debug');
               $info .= "The animal {$propData['TAG']} has multiple records in the db, skipping it<br />";
            }
            else{
               $animalData[$animalId[0]['id']] = $propData['TAG'];
               $event_date = DateTime::createFromFormat('n/j/Y h:i A', $propData['Date Time']);
               $this->Dbase->CreateLogEntry("Date string {$propData['Date Time']} -- ", 'debug');
               $this->Dbase->CreateLogEntry("$curLine -- ", 'debug');
               $animalDates[$animalId[0]['id']] = $event_date->format('Y-m-d');
            }
         }
         $count++;
      }
      fclose($fd);
      $this->Dbase->CreateLogEntry("Found $count records in the file, now processing them", 'debug');

      if($_POST['event'] == 'loc'){
         $this->Dbase->CreateLogEntry("Processing batch upload of locations data. A total of $count records found.", 'debug');
         $_POST['animals'] = json_encode($animalData);
         $_POST['to'] = $_POST['new_location'];
         $_POST['from'] = 'all';
         $this->saveAnimalMovement($animalDates);
      }

      $this->Dbase->StartTrans();
      $insertQuery = 'insert into '. Config::$farm_db .'.farm_animal_events(animal_id, event_type_id, event_date, record_date, performed_by, recorded_by, comments) '
          . 'values(:animal_id, :event_type_id, :event_date, :record_date, :performed_by, :recorded_by, :comments)';
      $animalQuery = 'select id, rfid, animal_id from '. Config::$farm_db .'.farm_animals where rfid = :rfid';
      $animalEventQuery = 'select id from '. Config::$farm_db .'.farm_animal_events where animal_id = :animal_id and event_type_id = :event_type_id and event_date = :event_date';

      $headerCount = count($headers);
      $messages = array();
      $count1 = 0; $skipped = 0; $alreadyAdded = 0;
      for($i = 0; $i < $count; $i++){
         $this->Dbase->CreateLogEntry("Adding ". implode(', ', $data[$i]) ." to the database..", 'debug');
         // create a proper hash and then use it
         $propData = array();
         for($j = 0; $j < $headerCount; $j++){
            $propData[$headers[$j]] = $data[$i][$j];
         }

         // get the animal particulars
         $rfid = $this->Dbase->ExecuteQuery($animalQuery, array('rfid' => $propData['EID']));
         if($rfid == 1){
            $this->Dbase->RollBackTrans();
            die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastError)));
         }
         else if(count($rfid) == 0){
            $messages[] = "Couldn't find the animal with RFID '{$propData['EID']}' in the database. Will skip this record.";
            $this->Dbase->CreateLogEntry("Couldn't find the animal with RFID '{$propData['EID']}' in the database. Will skip this record...", 'info');
            $skipped++;
            continue;
         }
         $rfid = $rfid[0];

         // if the TAG is set, confirm that we are dealing with the right animal
         if(isset($propData['TAG']) && $propData['TAG'] != '' && strtolower($propData['TAG']) != strtolower($rfid['animal_id'])){
            $this->Dbase->CreateLogEntry("Error: The animal id with RFID '{$propData['EID']}', {$propData['TAG']} does not match with the database record of {$rfid['rfid']}, {$rfid['animal_id']}", 'fatal');
            $this->Dbase->RollBackTrans();
            die(json_encode(array('error' => true, 'mssg' => "Error: The animal id with RFID {$propData['EID']}, {$propData['TAG']} does not match with the database record of {$rfid['rfid']}, {$rfid['animal_id']}")));
         }

         $comments = date('y-m-d H:i:s'). ': Batch upload from the Allflex stick';
         $comments .= $_POST['comments'];
         $event_date = DateTime::createFromFormat('n/j/y h:i', $propData['Date Time']);

         // get the animal particulars
         $savedEvents = $this->Dbase->ExecuteQuery($animalEventQuery, array('animal_id' => $rfid['id'], 'event_date' => $event_date->format('Y-m-d H:i:s'), 'event_type_id' => $_POST['event']));
         if($savedEvents == 1){
            $this->Dbase->RollBackTrans();
            die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastError)));
         }
         else if(count($savedEvents) == 1){
            $this->Dbase->CreateLogEntry("This event has already been added; ". implode(', ', $data[$i]) ." skipping it...", 'debug');
            $alreadyAdded++;
            continue;
         }

         // all is good so update the database
         $vals = array('animal_id' => $rfid['id'], 'event_type_id' => $_POST['event'], 'event_date' => $event_date->format('Y-m-d H:i:s'), 'record_date' => date('Y-m-d H:i:s'), 'performed_by' => $_POST['performed_by'], 'recorded_by' => $_SESSION['user_id'], 'comments' => $comments);
         $rfid = $this->Dbase->ExecuteQuery($insertQuery, $vals);
         if($rfid == 1){
            $this->Dbase->RollBackTrans();
            die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastError)));
         }
         $count1++;
      }
      $this->Dbase->CommitTrans();
      unlink($uploaded[0]);
      $this->Dbase->CreateLogEntry("Found $count records, $alreadyAdded were already added, added $count1 to the database and skipped $skipped", 'debug');

      $escapers =     array("\\",     "/",   "\"",  "\n",  "\r",  "\t", "\x08", "\x0c");
      $replacements = array("\\\\", "\\/", "\\\"", "\\n", "\\r", "\\t",  "\\f",  "\\b");
      $result = str_replace($escapers, $replacements, implode("---", $messages));

      if(count($messages) == 0) die(json_encode(array('error' => false, 'mssg' => 'Uploaded successfully without any messages.')));
      else die(json_encode(array('error' => false, 'mssg' => 'Messages from the upload: '. $result)));
   }
}
