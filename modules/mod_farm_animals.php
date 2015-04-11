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
         <li><a href="?page=farm_animals&do=pens">Farm pens & animals</a></li>
         <li><a href="?page=farm_animals&do=pen_animals">Animals in pens</a></li>
         <li><a href="?page=farm_animals&do=move_animals">Move animals between pens</a></li>
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
<div id="main">
   <div id="inventory"></div>
</div>
<!-- div id="links" class="center">
   <button type="button" id="save" class='btn btn-primary'>Save</button>
   <button type="button" id="cancel" class='btn btn-primary cancel'>Cancel</button>
</div -->
<script type="text/javascript">
   $('#whoisme .back').html('<a href=\'?page=farm_animals\'>Back</a>');       //back link
   var animals = new Animals('inventory');
</script>
<?php
   }

   /**
    * Get a list of all the animals currently in the farm
    */
   private function inventoryList(){
      $query = 'select a.*, b.name as species, if(dob = 0, "", dob) as dob, concat(d.surname, " ", d.first_name) as owner '
              . 'from farm_animals.farm_animals as a inner join farm_animals.farm_species as b on a.species_id=b.id '
              . 'left join farm_animals.farm_animal_owners as c on a.id=c.animal_id '
              . 'left join farm_animals.farm_people as d on c.owner_id=d.id where c.end_date is null';
      $res = $this->Dbase->ExecuteQuery($query);
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
      $query = 'select id, name from farm_animals.farm_species order by name';
      $farmAnimals = $this->Dbase->ExecuteQuery($query);
      if($farmAnimals == 1){
         $this->homePage($this->Dbase->lastError);
         return;
      }

      // get the breeds
      $query = 'select id, breed_name as name from farm_animals.breeds order by breed_name';
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
      if($withAnimals){ $animalsLocations = "<div id='animalsOnLocation'><div class='label'>Animals in selected locations</div><div id='level2'></div></div>"; $action = 'pensWithAnimals'; }
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
   animals.initiateAnimalLocations('<?php echo $action; ?>');

   $("#level2_pl .actions").on('buttonclick', animals.level2BttnClicked );
   $("#animalsOnLocation_pl .actions").on('buttonclick', animals.animalsBttnClicked );
</script>
<?php
   }

   /**
    * Gets the defined locations for the animals
    *
    * @return  array    Returns an array with the defined locations
    */
   private function getAnimalLocations($withAnimals = false){
      // get all the level1 locations
      $query = 'select level1 from farm_animals.farm_locations group by level1 order by level1';
      $res = $this->Dbase->ExecuteQuery($query);
      if($res == 1) return false;

      // get all the level2 locations grouped by level1 locations and format level1 locations well
      $level2Locations = array();
      $level1Locations = array();
      $query = 'select id, level2 as `name` from farm_animals.farm_locations where level1 = :level1 group by level2 order by level2';
      $i = 2;
      $animalLocations = array();
      foreach($res as $loc){
         // loop thru all level1 locations and get level2 locations
         $res1 = $this->Dbase->ExecuteQuery($query, array('level1' => $loc['level1']));
         if($res1 == 1) return false;
         $level2Locations[$loc['level1']] = $res1;

         // get the animals in this locations if need be
         if($withAnimals){
            $animalsQuery = 'select b.id, b.animal_id `name` from farm_animals.farm_animal_locations as a inner join farm_animals.farm_animals as b on a.animal_id = b.id where a.location_id = :id and a.end_date is null';
            $this->Dbase->CreateLogEntry('Getting the animals per location', 'info');
            foreach($res1 as $subLoc){
               $res2 = $this->Dbase->ExecuteQuery($animalsQuery, array('id' => $subLoc['id']));
               if($res2 == 1) return false;
               $animalLocations[$subLoc['id']] = $res2;
            }

            // get the unattached animals
            $unattachedAnimalsQuery = 'select id, animal_id as `name` from farm_animals.farm_animals as a where a.id not in (select animal_id from farm_animals.farm_animal_locations where end_date is null)';
            $res3 = $this->Dbase->ExecuteQuery($unattachedAnimalsQuery);
            if($res3 == 1) return false;
            $animalLocations['floating'] = $res3;
         }
         $level1Locations[] = array('id' => $i, 'name' => $loc['level1']);
         $i++;
      }

      return array('level1' => $level1Locations, 'level2' => $level2Locations, 'animals' => $animalLocations);
   }

   /**
    * Save locations where the animals are kept
    */
   private function saveAnimalLocations(){
      // we have a level2 or a level1 and level2. If level1 is an integer, we have a presaved level1 else save both
      // whether or not we have a level1 selected, we need to save both of them... flauting the db rules
      $level1Query = 'insert into farm_animals.farm_locations(level1, level2) values(:level1, :level2)';
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
      $query = "insert into farm_animals.farm_animals($cols) values($colrefs)";
      $res = $this->Dbase->ExecuteQuery($query, $colvals);
      if($res == 1){
         $this->Dbase->RollBackTrans();
         die(json_encode(array('error' => 'true', 'mssg' => $this->Dbase->lastError)));
      }
      $animalId = $this->Dbase->dbcon->lastInsertId();

      // now lets add the breeds if any
      $breedQuery = 'insert into farm_animals.animal_breeds(animal_id, breed_id) values(:animal_id, :breed_id)';
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
      global $Repository;
     $Repository->DateTimePickerFiles();
?>
<script type="text/javascript" src="js/farm_animals.js"></script>
<link rel="stylesheet" href="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/jquery/jqwidgets371/styles/jqx.base.css" type="text/css" />
<link rel="stylesheet" href="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/mssg_box.css" />
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
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>/customMessageBox.js"></script>
<div id="main">
   <div id="ownership"></div>
</div>
<div id="links" class="center">
   <button type="button" id="add" class='btn btn-primary'>Add Ownership</button>
</div>
<script type='text/javascript'>
   $('#whoisme .back').html('<a href=\'?page=farm_animals\'>Back</a>');       //back link
   var animals = new Animals('ownership');

   $('#add').bind('click', animals.addOwnership);
</script>
<?php

   }

   /**
    * Get the animal owners over a period of time
    */
   private function animalOwnersList(){
      $toReturn = array();
      if($_POST['field'] == 'grid'){
         $query = 'select a.id, concat(b.surname, " ", b.first_name) as owner, c.animal_id animal, start_date, end_date, a.comments '
                 . 'from farm_animals.farm_animal_owners as a inner join farm_animals.farm_people as b on a.owner_id=b.id inner join farm_animals.farm_animals as c on a.animal_id=c.id';
         $ownership = $this->Dbase->ExecuteQuery($query);
         if($ownership == 1){
            die(json_encode(array('error' => 'true', 'mssg' => $this->Dbase->lastError)));
         }
         die(json_encode($ownership));
      }

      if(in_array('owners', $_POST['fields'])){
         $query = 'select id, concat(surname, " ", first_name) as name from farm_animals.farm_people order by surname';
         $owners = $this->Dbase->ExecuteQuery($query);
         if($owners == 1){
            die(json_encode(array('error' => 'true', 'mssg' => $this->Dbase->lastError)));
         }
         $toReturn['owners'] = $owners;
      }

      if(in_array('animals', $_POST['fields'])){
         $query = 'select id, animal_id as name from farm_animals.farm_animals order by animal_id';
         $animals = $this->Dbase->ExecuteQuery($query);
         if($animals == 1){
            die(json_encode(array('error' => 'true', 'mssg' => $this->Dbase->lastError)));
         }
         $toReturn['animals'] = $animals;
      }
      die(json_encode($toReturn));
   }

   /**
    * Saves a new ownership of an animal
    */
   private function saveAnimalOwners(){
      // check if we have some comments
      $comments = NULL; $comments_ref = ''; $comments_col = '';
      if(isset($_POST['commnents']) && $_POST['comments'] != ''){
         $comments = $_POST['comments']; $comments_ref = ', :comments'; $comments_col = ', comments';
      }
      $query = "insert into farm_animals.farm_animal_owners(owner_id, animal_id, start_date, end_date $comments_col) values(:owner_id, :animal_id, :start_date, :end_date $comments_ref)";

      $start_date = DateTime::createFromFormat('d-m-Y', $_POST['start_date']);
      $end_date = DateTime::createFromFormat('d-m-Y', $_POST['end_date']);
      $colvals = array('owner_id' => $_POST['owners'], 'animal_id' => $_POST['animals'], 'start_date' => $start_date->format('Y-m-d'), 'end_date' => $end_date->format('Y-m-d'));

      $res = $this->Dbase->ExecuteQuery($query, $colvals);
      if($res == 1){
          die(json_encode(array('error' => 'true', 'mssg' => $this->Dbase->lastError)));
      }
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
      <button style="padding:4px 16px;" id="remove_all"><< Remove All</button>
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
   $("#remove_all").jqxButton({ width: '150'});

   var animals = new Animals();
   animals.animalLocations = <?php echo json_encode($animalLocations); ?>;

   // bind the click functions of the buttons
   $("#remove_all, #remove, #add, #add_all").live('click', function(sender){ animals.moveAnimals(sender); });
   $("#save").live('click', function(){ animals.saveMovedAnimals(); });
   animals.movedAnimals = {};
   animals.initiateAnimalMovement();
</script>
<?php
   }

   /**
    * Saves the movement of animals from one paddock to another
    */
   private function saveAnimalMovement(){
      // lets save the animal new locations
      $animals = json_decode($_POST['animals']);
      $mvmntQuery = 'insert into farm_animals.farm_animal_locations(location_id, animal_id, start_date) values(:location_id, :animal_id, :start_date)';
      $updateQuery = 'update farm_animals.farm_animal_locations set end_date = :edate where location_id = :location_id and animal_id = :animal_id and end_date is null';
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
         if($res == 1){ $this->Dbase->RollBackTrans(); die(json_encode(array('error' => 'true', 'mssg' => $this->Dbase->lastError))); }
      }
      $this->Dbase->CommitTrans();
      $animalLocations = $this->getAnimalLocations(true);
      die(json_encode(array('error' => 'false', 'data' => $animalLocations, 'mssg' => 'The movement has been saved successfully')));
   }
}
