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
         if(OPTIONS_REQUESTED_ACTION == '') $this->addHome();
         else if(OPTIONS_REQUESTED_ACTION == 'save') $this->saveAnimal();
      }
      else if(OPTIONS_REQUESTED_SUB_MODULE == 'inventory'){
         if(OPTIONS_REQUESTED_ACTION == '') $this->inventoryHome();
         else if(OPTIONS_REQUESTED_ACTION == 'list' && $_POST['field'] == 'events') $this->animalEventsList();
         else if(OPTIONS_REQUESTED_ACTION == 'list') $this->inventoryList();
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
         else if(OPTIONS_REQUESTED_ACTION == 'save') $this->saveAnimalOwners();
      }
      else if(OPTIONS_REQUESTED_SUB_MODULE == 'events'){
         if(OPTIONS_REQUESTED_ACTION == '') $this->animalEvents();
         else if(OPTIONS_REQUESTED_ACTION == 'list' && $_POST['field'] == 'animal_events') $this->eventsList();
         else if(OPTIONS_REQUESTED_ACTION == 'list' && $_POST['field'] == 'sub_events') $this->eventsSubList();
         else if(OPTIONS_REQUESTED_ACTION == 'list') $this->newEventsData ();
         else if(OPTIONS_REQUESTED_ACTION == 'save') $this->saveAnimalEvents ();
      }
      else if(OPTIONS_REQUESTED_SUB_MODULE == 'experiments'){
         if(OPTIONS_REQUESTED_ACTION == '') $this->experimentsHome();
         else if(OPTIONS_REQUESTED_ACTION == 'list') $this->experimentsData();
         else if(OPTIONS_REQUESTED_ACTION == 'save_exp') $this->saveNewExperiment();
         else if(OPTIONS_REQUESTED_ACTION == 'save') $this->saveExperimentAnimals();      // An ambigous action name....
      }
   }

   /**
    * Create links for the different sub modules of this module
    */
   private function homePage($addInfo = ''){
      $addInfo = ($addInfo != '') ? "<div id='addinfo'>$addInfo</div>" : '';
      ?>
<div id='home'>
   <?php echo $addInfo?>
   <h3 class="center">Farm animals management</h3>
   <div class="user_options">
      <ul>
         <li><a href="?page=farm_animals&do=inventory">Animal inventory</a></li>
         <li><a href="?page=farm_animals&do=add">Add an animal</a></li>
         <li><a href="?page=farm_animals&do=ownership">Animal ownership</a></li>
         <li><a href="?page=farm_animals&do=pens">Farm location & animals</a></li>
         <li><a href="?page=farm_animals&do=pen_animals">Animals in location</a></li>
         <li><a href="?page=farm_animals&do=move_animals">Move animals between locations</a></li>
         <li><a href="?page=farm_animals&do=events">Animal Events</a></li>
         <li><a href="?page=farm_animals&do=experiments">Experiments</a></li>
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
<link rel="stylesheet" href="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/styles/jqx.base.css" type="text/css" />
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxcore.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxdata.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxbuttons.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxscrollbar.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxmenu.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxcheckbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxlistbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxdropdownlist.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxgrid.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxgrid.sort.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxgrid.pager.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxgrid.selection.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxgrid.filter.js"></script>
<div id="main">
   <div id="inventory"></div>
</div>
<!-- div id="links" class="center">
   <button type="button" id="save" class='btn btn-primary'>Save</button>
   <button type="button" id="cancel" class='btn btn-primary cancel'>Cancel</button>
</div -->
<script type="text/javascript">
   $('#whoisme .back').html('<a href=\'?page=farm_animals\'>Back</a>');       //back link
   var animals = new Animals();
   animals.initiateAnimalsGrid();
</script>
<?php
   }

   /**
    * Get a list of all the animals currently in the farm
    */
   private function inventoryList(){
      $query = 'select a.*, b.name as species, if(dob = 0, "", dob) as dob, a.current_owner, d.exp_name as experiment, concat(e.level1, " >> ", e.level2) as location, f.breed '
              . 'from '. Config::$farm_db .'.farm_animals as a inner join '. Config::$farm_db .'.farm_species as b on a.species_id=b.id '
              . 'left join '. Config::$farm_db .'.experiments as d on a.current_exp=d.id '
              . 'left join '. Config::$farm_db .'.farm_locations as e on a.current_location=e.id '
              . 'left join (select animal_id, group_concat(breed_name SEPARATOR ", ") as breed from '. Config::$farm_db .'.animal_breeds as a inner join '. Config::$farm_db .'.breeds as b on a.breed_id=b.id group by a.animal_id) as f on a.id=f.animal_id';
      $res = $this->Dbase->ExecuteQuery($query);
      if($res == 1){
         $this->Dbase->CreateLogEntry($this->Dbase->lastError, 'fatal');
         die(json_encode(array('error' => true, 'message' => 'There was an error while fetching data from the database. Contact the system administrator')));
      }
      $owners = $this->getAllOwners(PDO::FETCH_KEY_PAIR);
      if(is_string($owners)) die(json_encode(array('error' => true, 'message' => $owners)));

      foreach($res as $id => $animal){
         $res[$id]['owner'] = $owners[$animal['current_owner']];
      }
      die(json_encode($res));
   }

   /**
    * Get a list of the events done for this animal
    */
   private function animalEventsList(){
      // get all the events for this animal
      $query = 'select a.id as event_id, b.event_name, a.event_date, a.record_date, a.comments '
              . 'from '. Config::$farm_db .'.farm_animal_events as a inner join '. Config::$farm_db .'.farm_events as b on a.event_type_id=b.id '
              . 'where a.animal_id = :animal_id order by a.event_date, a.record_date';
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
<link rel="stylesheet" href="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/styles/jqx.base.css" type="text/css" />
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxcore.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxnotification.js"></script>
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
            <button type="button" id="save" class='btn btn-primary'>Save</button>
            <button type="button" id="cancel" class='btn btn-primary cancel'>Cancel</button>
         </div>
      </fieldset>
   </form>
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
   $('#animal_id').blur(animals.confirmId);
   $('#animal_id').focus();
</script>
<?php
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
<link rel="stylesheet" href="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/styles/jqx.base.css" type="text/css" />
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxcore.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxbuttons.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxbuttongroup.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxscrollbar.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxlistbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxcheckbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxnotification.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxwindow.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxpanel.js"></script>
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
   private function getAnimalLocations($withAnimals = false){
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
      foreach($res as $loc){
         // loop thru all level1 locations and get level2 locations
         $res1 = $this->Dbase->ExecuteQuery($query, array('level1' => $loc['level1']));
         if($res1 == 1) return $this->Dbase->lastError;
         $level2Locations[$loc['level1']] = $res1;

         // get the animals in this locations if need be
         if($withAnimals){
            $animalsQuery = 'select b.id, b.animal_id `name` from '. Config::$farm_db .'.farm_animal_locations as a inner join '. Config::$farm_db .'.farm_animals as b on a.animal_id = b.id where a.location_id = :id and a.end_date is null';
            $this->Dbase->CreateLogEntry('Getting the animals per location', 'info');
            foreach($res1 as $subLoc){
               $res2 = $this->Dbase->ExecuteQuery($animalsQuery, array('id' => $subLoc['id']));
               if($res2 == 1) return $this->Dbase->lastError;
               $animalLocations[$subLoc['id']] = $res2;
            }

            // get the unattached animals
            $unattachedAnimalsQuery = 'select id, animal_id as `name` from '. Config::$farm_db .'.farm_animals as a where a.id not in (select animal_id from '. Config::$farm_db .'.farm_animal_locations where end_date is null)';
            $res3 = $this->Dbase->ExecuteQuery($unattachedAnimalsQuery);
            if($res3 == 1) return $this->Dbase->lastError;
            $animalLocations['floating'] = $res3;
         }
         $level1Locations[] = array('id' => $i, 'name' => $loc['level1']);
         $i++;
      }

      return array('level1' => $level1Locations, 'level2' => $level2Locations, 'animals' => $animalLocations);
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
         if(is_string($owners)){ die(json_encode(array('error' => 'true', 'mssg' => $owners))); }
      }

      $ownerQuery = 'select id, animal_id as `name` from '. Config::$farm_db .'.farm_animals where current_owner = :owner_id';

      $animalByOwners = array();
      foreach($owners as $owner){
         $res = $this->Dbase->ExecuteQuery($ownerQuery, array('owner_id' => $owner['id']));
         if($res == 1) return $this->Dbase->lastError;
         else $animalByOwners[$owner['id']] = $res;
      }

      // get the animals without owners
      $ownerlessAnimalsQuery = 'select id, animal_id as `name` from '. Config::$farm_db .'.farm_animals where id not in (SELECT animal_id FROM '. Config::$farm_db .'.farm_animal_owners where end_date is null)';
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
         if(is_string($experiments)) { die(json_encode(array('error' => 'true', 'mssg' => $experiments))); }
      }

      $query = 'select id, animal_id as name from '. Config::$farm_db .'.farm_animals where current_exp = :exp_id';
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
      if($res == 1) die(json_encode(array('error' => 'true', 'mssg' => $this->Dbase->lastError)));
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
      $cols = 'animal_id, species_id, sex';
      $colrefs = ':animal_id, :species_id, :sex';
      $colvals = array('animal_id' => $_POST['animal_id'], 'species_id' => $_POST['species'], 'sex' => $_POST['sex']);
      if($_POST['dob'] !== '') { $cols .= ', dob';  $colrefs .= ', :dob'; $colvals[''] = $_POST['dob']; }
      if($_POST['other_id'] != '') { $cols .= ', other_id';  $colrefs .= ', :other_id'; $colvals[''] = $_POST['other_id']; }
      if($_POST['origin'] != '') { $cols .= ', origin';  $colrefs .= ', :origin'; $colvals[''] = $_POST['origin']; }
      if($_POST['experiment'] != '') { $cols .= ', experiment';  $colrefs .= ', :experiment'; $colvals[''] = $_POST['experiment']; }
      if($_POST['comments'] != '') { $cols .= ', comments';  $colrefs .= ', :comments'; $colvals['comments'] = $_POST['comments']; }
      if($_POST['dam'] != '') { $cols .= ', dam';  $colrefs .= ', :dam'; $colvals['dam'] = $_POST['dam']; }
      if($_POST['sire'] != '') { $cols .= ', sire';  $colrefs .= ', :sire'; $colvals['sire'] = $_POST['sire']; }

      $this->Dbase->StartTrans();
      $query = "insert into '. Config::$farm_db .'.farm_animals($cols) values($colrefs)";
      $res = $this->Dbase->ExecuteQuery($query, $colvals);
      if($res == 1){
         $this->Dbase->RollBackTrans();
         die(json_encode(array('error' => 'true', 'mssg' => $this->Dbase->lastError)));
      }
      $animalId = $this->Dbase->dbcon->lastInsertId();

      // now lets add the breeds if any
      $breedQuery = 'insert into '. Config::$farm_db .'.animal_breeds(animal_id, breed_id) values(:animal_id, :breed_id)';
      if($_POST['breed'] !== '') {
         $cols .= ', ';  $colrefs .= ', :'; $colvals[''] = $_POST['sire'];
         $res1 = $this->Dbase->ExecuteQuery($breedQuery, array('animal_id' => $animalId, 'breed_id' => $_POST['breed']));
         if($res1 == 1){
            $this->Dbase->RollBackTrans();
            die(json_encode(array('error' => 'true', 'mssg' => $this->Dbase->lastError)));
         }
      }
      // seem all is ok, lets commit the transaction and go back
      $this->Dbase->CommitTrans();
      die(json_encode(array('error' => 'false', 'mssg' => 'The animal has been successful saved.')));
   }

   /**
    * Create a home page for showing the animal owners
    */
   private function animalOwnersHome(){
?>
<div id="messageNotification"><div class="">&nbsp;&nbsp;</div></div>
<script type="text/javascript" src="js/farm_animals.js"></script>
<link rel="stylesheet" href="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/styles/jqx.base.css" type="text/css" />
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxcore.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxdata.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxbuttons.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxscrollbar.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxmenu.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxcheckbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxlistbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxdropdownlist.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxgrid.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxgrid.sort.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxgrid.pager.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxgrid.selection.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxnotification.js"></script>
<div id="ownership">
   <div id="owners_list">&nbsp;</div>
   <div id="links" class="center">
      <button type="button" id="add" class='btn btn-primary'>Add Ownership</button>
   </div>
</div>

<script type='text/javascript'>
   $('#whoisme .back').html('<a href=\'?page=farm_animals\'>Back</a>');       //back link
   var animals = new Animals('ownership');
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
         $query = 'select a.id, a.owner_id, c.animal_id animal, start_date, end_date, a.comments '
             . 'from '. Config::$farm_db .'.farm_animal_owners as a inner join '. Config::$farm_db .'.farm_animals as c on a.animal_id=c.id '
             . 'order by a.animal_id, start_date';
         $ownership = $this->Dbase->ExecuteQuery($query);
         if($ownership == 1) die(json_encode(array('error' => 'true', 'mssg' => $this->Dbase->lastError)));

         // get all the owners
         $owners = $this->getAllOwners(PDO::FETCH_KEY_PAIR);
         if(is_string($owners)) die(json_encode(array('error' => 'true', 'mssg' => $owners)));
         foreach($ownership as $id => $owner){
            $ownership[$id]['owner'] = $owners[$owner['owner_id']];
         }
         die(json_encode($ownership));
      }

      if(in_array('owners', $fields)){
         // get the animals belonging to these owners
         $res = $this->groupAnimalsByOwners();
         if(is_string($res)) { die(json_encode(array('error' => 'true', 'mssg' => $res))); }

         $owners = $this->getAllOwners();
         if(is_string($owners)) { die(json_encode(array('error' => 'true', 'mssg' => $owners))); }

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
    * Saves a new ownership of an animal
    */
   private function saveAnimalOwners(){
      $animals = json_decode($_POST['animals']);
      $addQuery = 'insert into '. Config::$farm_db .'.farm_animal_owners(owner_id, animal_id, start_date) values(:owner_id, :animal_id, :start_date)';
      $updateQuery = 'update '. Config::$farm_db .'.farm_animal_owners set end_date = :end_date where owner_id = :owner_id and animal_id = :animal_id and end_date is null';
      $updateOwnerQuery = 'update '. Config::$farm_db .'.farm_animals set current_owner = :current_owner where id = :animal_id';
      $this->Dbase->StartTrans();
      foreach($animals as $id => $name){
         if($_POST['from'] != 'floating'){
            $res = $this->Dbase->ExecuteQuery($updateQuery, array('owner_id' => $_POST['from'], 'animal_id' => $id, 'end_date' => date('Y-m-d')));
            if($res == 1){
               $this->Dbase->RollBackTrans();
               die(json_encode(array('error' => 'true', 'mssg' => $this->Dbase->lastError)));
            }
         }

         $res = $this->Dbase->ExecuteQuery($addQuery, array('owner_id' => $_POST['to'], 'animal_id' => $id, 'start_date' => date('Y-m-d')));
         if($res == 1){
            $this->Dbase->RollBackTrans();
            die(json_encode(array('error' => 'true', 'mssg' => $this->Dbase->lastError)));
         }

         // update the redudant current owner in animals table
         $res = $this->Dbase->ExecuteQuery($updateOwnerQuery, array('current_owner' => $_POST['to'], 'animal_id' => $id));
         if($res == 1){
            $this->Dbase->RollBackTrans();
            die(json_encode(array('error' => 'true', 'mssg' => $this->Dbase->lastError)));
         }
      }
      $this->Dbase->CommitTrans();
      die(json_encode(array('error' => 'false', 'mssg' => 'The new ownwership has been saved successfully.')));
   }

   /**
    * Move animals between pens
    */
   private function moveAnimals(){
      $animalLocations = $this->getAnimalLocations(true);
?>
<script type="text/javascript" src="js/farm_animals.js"></script>
<link rel="stylesheet" href="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/styles/jqx.base.css" type="text/css" />
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxcore.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxbuttons.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxbuttongroup.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxscrollbar.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxlistbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxcheckbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxnotification.js"></script>
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

   // bind the click functions of the buttons
   $("#reset, #remove, #add, #add_all").live('click', function(sender){ animals.moveAnimals(sender); });
   $("#save").live('click', function(){ animals.saveChanges(); });
   animals.movedAnimals = {};
   animals.initiateFiltersnLists('movement');
</script>
<?php
   }

   /**
    * Saves the movement of animals from one paddock to another
    */
   private function saveAnimalMovement(){
      // lets save the animal new locations
      $animals = json_decode($_POST['animals']);
      $mvmntQuery = 'insert into '. Config::$farm_db .'.farm_animal_locations(location_id, animal_id, start_date) values(:location_id, :animal_id, :start_date)';
      $updateQuery = 'update '. Config::$farm_db .'.farm_animal_locations set end_date = :edate where location_id = :location_id and animal_id = :animal_id and end_date is null';
      $updateAnimalLocation = 'update '. Config::$farm_db .'.farm_animals set current_location = :current_loc where id = :animal_id';
      $this->Dbase->StartTrans();
      foreach($animals as $id => $name){
         // update the from locations
         if(!in_array($_POST['from'], array('floating', 'all', 0)) ){
            $upcols = array('edate' => date('Y-m-d'), 'location_id' => $_POST['from'], 'animal_id' => $id);
            $this->Dbase->CreateLogEntry('updating the locations for ... '. implode(', ', $upcols),  'fatal');
            $res1 = $this->Dbase->ExecuteQuery($updateQuery, $upcols);
            if($res1 == 1){ $this->Dbase->RollBackTrans(); die(json_encode(array('error' => 'true', 'mssg' => $this->Dbase->lastError))); }
         }
         $colvals = array('location_id' => $_POST['to'], 'animal_id' => $id, 'start_date' => date('Y-m-d'));
         $res = $this->Dbase->ExecuteQuery($mvmntQuery, $colvals);
         if($res == 1){
            $this->Dbase->RollBackTrans();
            die(json_encode(array('error' => 'true', 'mssg' => $this->Dbase->lastError)));
         }

         // update the redundant current animal location
         $colvals = array('current_loc' => $_POST['to'], 'animal_id' => $id);
         $res = $this->Dbase->ExecuteQuery($updateAnimalLocation, $colvals);
         if($res == 1){
            $this->Dbase->RollBackTrans();
            die(json_encode(array('error' => 'true', 'mssg' => $this->Dbase->lastError)));
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
?>
<script type="text/javascript" src="js/farm_animals.js"></script>
<link rel="stylesheet" href="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/styles/jqx.base.css" type="text/css" />
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxcore.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxdata.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxbuttons.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxscrollbar.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxmenu.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxcheckbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxlistbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxdropdownlist.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxgrid.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxgrid.sort.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxgrid.pager.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxgrid.selection.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxnotification.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxdatetimeinput.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxcalendar.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/globalization/globalize.js"></script>
<div id="events">
   <div id="events_grid"></div>
   <div id="actions">
      <button style="padding:4px 16px;" id="new">Add Events</button>
   </div>
</div>
<div id="messageNotification"><div></div></div>

<script type="text/javascript">
   $('#whoisme .back').html('<a href=\'?page=farm_animals\'>Back</a>');       //back link
   $("#new").jqxButton({ width: '150'});
   var animals = new Animals();
   animals.initiateAnimalsEventsGrid();
   // bind the click functions of the buttons
   $("#new").live('click', function(){ animals.newEvent(); });
</script>
<?php
   }

   /**
    * Get a list of all animal events
    */
   private function eventsList(){
      $eventsQuery = 'select a.event_type_id, c.event_name, a.event_date, record_date as time_recorded, recorded_by, performed_by as performed_by_id, performed_by, count(*) as no_animals '
          . 'from '. Config::$farm_db .'.farm_animal_events as a inner join '. Config::$farm_db .'.farm_animals as b on a.animal_id=b.id '
          . 'inner join '. Config::$farm_db .'.farm_events as c on a.event_type_id=c.id '
          . 'group by a.event_type_id, a.event_date, performed_by';
      $events = $this->Dbase->ExecuteQuery($eventsQuery);
      if($events == 1) { die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastError))); }
      $owners = $this->getAllOwners(PDO::FETCH_KEY_PAIR);
      if(is_string($owners)) { die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastError))); }

      foreach($events as $id => $ev){
         $events[$id]['performed_by'] = $owners[$ev['performed_by']];
         $events[$id]['recorded_by'] = $owners[$ev['recorded_by']];
      }
      die(json_encode(array('error' => false, 'data' => $events)));
   }

   /**
    * Gets the sub list of a particular group
    */
   private function eventsSubList(){
      $eventsQuery = 'select b.animal_id, b.sex, record_date as time_recorded, concat(d.surname, " ", d.first_name) as owner '
          . 'from '. Config::$farm_db .'.farm_animal_events as a inner join '. Config::$farm_db .'.farm_animals as b on a.animal_id=b.id '
          . 'left join '. Config::$farm_db .'.farm_people as d on b.current_owner=d.id '
          . 'left join '. Config::$farm_db .'.experiments as e on b.current_exp=e.id '
          . 'where a.event_type_id = :event_type_id and a.event_date = :event_date and performed_by = :performed_by';
      $events = $this->Dbase->ExecuteQuery($eventsQuery, array('event_type_id' => $_POST['event_type_id'], 'event_date' => $_POST['event_date'], 'performed_by' => $_POST['performed_by']));
      if($events == 1) { die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastQuery))); }
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

      // return the minimum days allowed for adding events
      $toReturn['eventMinDays'] = Config::$min_days_for_events;

      // get animal groupings
      // by owners
      if(in_array('byOwners', $fields)){
         $res = $this->groupAnimalsByOwners();
         if(is_string($res)) { die(json_encode(array('error' => 'true', 'mssg' => $res))); }
         $toReturn['byOwners'] = $res['byOwners'];
         $toReturn['allOwners'] = $res['owners'];
      }

      // by locations
      if(in_array('byLocations', $fields)){
         $animalsByLocations = $this->getAnimalLocations(true);
         if(is_string($animalsByLocations)) { die(json_encode(array('error' => 'true', 'mssg' => $animalsByLocations))); }
         $toReturn['byLocations'] = $animalsByLocations;
      }

      // get all owners
      if(in_array('allOwners', $fields)){
         $allOwners = $this->getAllOwners();
         if(is_string($allOwners)) { die(json_encode(array('error' => 'true', 'mssg' => $allOwners))); }
         $toReturn['allOwners'] = $allOwners;
      }

      // get animals by experiments
      if(in_array('byExperiments', $fields)){
         $experiments = $this->getAllExperiments();
         if(is_string($experiments)) { die(json_encode(array('error' => 'true', 'mssg' => $experiments))); }

         $exp = $this->groupAnimalsByExperiments($experiments);
         if(is_string($exp)) { die(json_encode(array('error' => 'true', 'mssg' => $exp))); }
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
      if(!is_numeric($_POST['to'])){
         // we have a new event name, so lets add it
         $eventId = $this->saveNewEventName($_POST['to']);
         if(!is_numeric($eventId)){
            $this->Dbase->RollBackTrans();
            die(json_encode(array('error' => 'true', 'mssg' => $eventId)));
         }
      }
      else $eventId = $_POST['to'];

      // so lets save the events
      $addQuery = 'insert into '. Config::$farm_db .'.farm_animal_events(animal_id, event_type_id, event_date, performed_by, recorded_by, comments) '
            . 'values(:animal_id, :event_type_id, :event_date, :performed_by, :recorded_by, :comments)';

      $date = date_create_from_format('d/m/Y', $extras['eventDate']);
      $vals = array('event_type_id' => $eventId, 'event_date' => date_format($date, 'Y-m-d'), 'performed_by' => $extras['performedBy'], 'recorded_by' => $_SESSION['user_id'], 'comments' => $extras['comments']);

      foreach($animals as $animalId => $animal){
         $colvals = $vals;
         $colvals['animal_id'] = $animalId;
         $res = $this->Dbase->ExecuteQuery($addQuery, $colvals);
         if($res == 1){
            $this->Dbase->RollBackTrans();
            die(json_encode(array('error' => 'true', 'mssg' => $this->Dbase->lastError)));
         }
      }
      // we are all good, lets return
      $this->Dbase->CommitTrans();
      die(json_encode(array('error' => 'false', 'mssg' => 'The event has been saved successfully.')));
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
<link rel="stylesheet" href="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/mssg_box.css" />
<link rel="stylesheet" href="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/styles/jqx.base.css" type="text/css" />
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxcore.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxdata.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxbuttons.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxscrollbar.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxmenu.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxcheckbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxlistbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxdropdownlist.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxgrid.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxgrid.sort.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxgrid.pager.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxgrid.selection.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/jqxnotification.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/customMessageBox.js"></script>
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
    * Get the data to be used for creating a new experiment
    */
   private function experimentsData(){
      $fields = json_decode($_POST['fields']);
      if($_POST['field'] == 'experiments'){
         $res = $this->experimentsList();
         if($res == 1) die(json_encode(array('error' => 'true', 'mssg' => $res)));
         die(json_encode(array('error' => 'false', 'data' => $res)));
      }
      else if($_POST['field'] == 'pis'){
         // get the list of owners
         $res = $this->getAllOwners();
         if(is_string($res)) die(json_encode(array('error' => 'true', 'mssg' => $res)));
         die(json_encode(array('error' => 'false', 'data' => $res)));
      }
      else if(in_array('byOwners', $fields)){
         $query = 'select id, exp_name as name from '. Config::$farm_db .'.experiments order by exp_name';
         $res = $this->Dbase->ExecuteQuery($query);
         if($res == 1) die(json_encode(array('error' => 'true', 'mssg' => $res)));

         $res1 = $this->groupAnimalsByOwners();
         if(is_string($res1)) die(json_encode(array('error' => 'true', 'mssg' => $res1)));

         $res2 = $this->groupAnimalsByExperiments();
         if(is_string($res1)) die(json_encode(array('error' => 'true', 'mssg' => $res1)));

         die(json_encode(array('error' => 'false', 'data' => array('byOwners' => $res1, 'experiments' => $res, 'byExperiments' => $res2))));
      }
   }

   /**
    * Get a list of all the experiments
    * @return  boolean|array  Returns a string in case there is an error, else returns an array with the defined experiments
    */
   private function experimentsList(){
      $query = 'select a.exp_name, a.start_date, a.end_date, a.pi_id from '. Config::$farm_db .'.experiments as a';
      $exps = $this->Dbase->ExecuteQuery($query);
      if($exps == 1) return $this->Dbase->lastError;
      // get the list of owners
      $res1 = $this->getAllOwners(PDO::FETCH_KEY_PAIR);
      if(is_string($res1))  return  $res1;
      foreach ($exps as $id => $exp){
         $exps[$id]['pi_name'] = $res1[$exp['pi_id']];
      }
      return $exps;
   }

   /**
    * Saves a new experiment
    */
   private function saveNewExperiment(){
      $addQuery = 'insert into '. Config::$farm_db .'.experiments(exp_name, pi_id, start_date) values(:exp_name, :pi_id, :start_date)';
      $start_date = date_create_from_format('d-m-Y', $_POST['start_date']);
      $res = $this->Dbase->ExecuteQuery($addQuery, array('exp_name' => $_POST['experiment'], 'pi_id' => $_POST['pis'], 'start_date' => date_format($start_date, 'Y-m-d')));
      if($res == 1) die(json_encode(array('error' => 'true', 'mssg' => $this->Dbase->lastError)));
      die(json_encode(array('error' => true, 'mssg' => 'The experiment have been saved successfully!')));
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
            die(json_encode(array('error' => 'true', 'mssg' => $this->Dbase->lastError)));
         }

         $colvals = $vals;
         $colvals['animal_id'] = $animalId;
         $res = $this->Dbase->ExecuteQuery($addQuery, $colvals);
         if($res == 1){
            $this->Dbase->RollBackTrans();
            die(json_encode(array('error' => 'true', 'mssg' => $this->Dbase->lastError)));
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
      $allUsersQuery = 'SELECT a.id, concat(sname, " ", onames) as `name` '
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
}
