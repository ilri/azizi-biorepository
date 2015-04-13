/**
 * The constructor of the Animals object
 *
 * @param   {string}    sub_module     The current sub module
 * @returns {Animals}   The Animal object which will be used in the farm animals module
 */
function Animals(sub_module){
   window.farm_animals = this;

   // initialize the main variables
   window.farm_animals.sub_module = Common.getVariable('do', document.location.search.substring(1));
   window.farm_animals.module = Common.getVariable('page', document.location.search.substring(1));

   this.serverURL = "./modules/mod_farm_animals.php";
   this.procFormOnServerURL = "mod_ajax.php?page=farm_animals";

   // call the respective function
   if(this.sub_module === 'inventory') this.initiateAnimalsGrid();
   else if(this.sub_module === 'ownership') this.initiateAnimalsOwnersGrid();
   else if(this.sub_module === 'events') this.initiateAnimalsEventsGrid();
   else if(this.sub_module === 'experiments') this.initiateExperimentsGrid();
};

/**
 * A function to initiate the animals grid
 */
Animals.prototype.initiateAnimalsGrid = function(){
   // create the source for the grid
   var source = {
      datatype: 'json', datafields: [ {name: 'animal_id'}, {name: 'species'}, {name: 'sex'}, {name: 'origin'}, {name: 'dob'},
         {name: 'sire'}, {name: 'dam'}, {name: 'owner'}, {name: 'experiment'}],
         id: 'id', root: 'data', async: false, type: 'POST', data: {action: 'list'}, url: 'mod_ajax.php?page=farm_animals&do=inventory'
     };
     var animalsAdapter = new $.jqx.dataAdapter(source);
   // initialize jqxGrid
     if($('#inventory :regex(class, jqx\-grid)').length === 0){
        $("#inventory").jqxGrid({
            width: 910,
            source: source,
            pageable: true,
            autoheight: true,
            sortable: true,
            altrows: true,
            enabletooltips: true,
            columns: [
              { datafield: 'system_id', hidden: true },
              { text: 'Animal ID', datafield: 'animal_id', width: 120 },
              { text: 'Species', datafield: 'species', width: 75 },
              { text: 'Sex', datafield: 'sex', width: 50 },
              { text: 'Origin', datafield: 'origin', width: 100 },
              { text: 'Birth Date', datafield: 'dob', width: 100 },
              { text: 'Sire', datafield: 'sire', width: 100 },
              { text: 'Dam', datafield: 'dam', width: 100 },
              { text: 'Current Owner', datafield: 'owner', width: 100 },
              { text: 'Experiment', datafield: 'experiment', width: 150 }
            ]
        });
     }
     else{
        $("#inventory").jqxGrid({source: animalsAdapter});
     }
};

/**
 * Confirm whether the entered animal id is unique and not used before
 * @returns {void}
 *
 * @todo    Fully implement the functionality
 */
Animals.prototype.confirmId = function(){
   // check that this is a unique id
   var animalId = $('#animal_id').val().trim();
   if(animalId === '') { return; }
   $.ajax({
       type:"POST", url: "mod_ajax.php?page=farm_animals&do=confirm", async: false, dataType:'json', data: {animalId: animalId},
       success: function (data) {
          if(data.error === true){
             alert(data.mssg);
             $('#animal_id').val('').focus();
             return;
          }
      }
   });
};

/**
 * Saves a new animal
 *
 * @todo Handling of multiple breeds
 */
Animals.prototype.saveAnimal = function(){
   // conduct validation
   var formInfo = $('#animals').formToArray(true), missingInfo = false, isSexFound = false;
   $.each(formInfo, function(){
      if(this.required && this.value === '' || this.required && this.value === 0 && this.type === 'select1'){
         // we have a mandatory field with no data...
         $('[name='+this.name+']').css({'aria-invalid': 'invalid'});
         missingInfo = true;
      }
      if(this.name === 'sex'){ isSexFound = true; }
   });
   // check that the sex is set since if its not set it doesnt appear in the formToArray array
   if(isSexFound === false) { missingInfo = true; }
   if(missingInfo){
      alert("Please fill in the missing mandatory information.");
      return;
   }

   // date of birth
   var formSerialized = $('#animals').formSerialize();
   $.ajax({
       type:"POST", url: "mod_ajax.php?page=farm_animals&do=add", async: false, dataType:'json', data: formSerialized + '&action=save',
       success: function (data) {
          if(data.error === true){
              alert(data.mssg);
              $('#animal_id').val('').focus();
              return;
          }
          else{
             // clear all the fields and get ready for saving a new animal
              $('#animals').clearForm();
              $('#animal_id').focus();
          }
      }
   });

};

/**
 * Initiate the grid showing the ownership of the animals over time
 */
Animals.prototype.initiateAnimalsOwnersGrid = function(){
   // create the source for the grid
   var source = {
       datatype: 'json', datafields: [ {name: 'animal'}, {name: 'owner'}, {name: 'start_date'}, {name: 'end_date'}, {name: 'comments'} ],
       id: 'id', root: 'data', async: false, type: 'POST', data: {action: 'list', field: 'grid'}, url: 'mod_ajax.php?page=farm_animals&do=ownership'
     };
     var ownersAdapter = new $.jqx.dataAdapter(source);
   // initialize jqxGrid
     if($('#owners_list :regex(class, jqx\-grid)').length === 0){
        $("#owners_list").jqxGrid({
            width: 910,
            height: 350,
            source: source,
            pageable: true,
            autoheight: true,
            sortable: true,
            altrows: true,
            enabletooltips: true,
            columns: [
              { datafield: 'system_id', hidden: true },
              { text: 'Animal', datafield: 'animal', width: 100 },
              { text: 'Owner', datafield: 'owner', width: 200 },
              { text: 'Start Date', datafield: 'start_date', width: 150 },
              { text: 'End Date', datafield: 'end_date', width: 150 },
              { text: 'Comments', datafield: 'comments', width: 200 }
            ]
         });
     }
     else{
        $("#owners_list").jqxGrid({source: ownersAdapter});
     }
};

/**
 * Add a new ownership of an animal
 */
Animals.prototype.addOwnership = function(){
   // get all the animals and all the people who can be owners
   var userData;
   $.ajax({
       type:"POST", url: "mod_ajax.php?page=farm_animals&do=ownership", async: false, dataType:'json', data: {'action': 'list', 'fields': $.toJSON(['owners','animals'])},
       success: function (data) {
          if(data.error === true){
              animals.showNotification(data.mssg, 'error');
              $('#animal_id').val('').focus();
              return;
          }
          else{
             animals.byLocations = data.animals;
             animals.owners = data.owners;
             animals.byOwners = data.animalsByOwners;
          }
      }
   });

   // change the interface to be able to add new ownership
var mainContent = '\
   <div id="all_animals">\
      <div id="from_filter"></div>\
      <div id="from_list"></div>\
   </div>\n\
   <div id="actions">\n\
      <button style="padding:4px 16px;" id="add">Add ></button>\
      <button style="padding:4px 16px;" id="add_all">Add All >></button>\
      <button style="padding:4px 16px;" id="remove">< Remove</button>\
      <button style="padding:4px 16px;" id="reset">Reset</button>\
   </div>\n\
   <div id="new_locations">\
      <div id="to_filter"></div>\
      <div id="to_list"></div>\
   </div>\n\
   <div id="actions">\n\
      <button style="padding:4px 16px;" id="save">Save</button>\n\
   </div>';

   $('#ownership').html(mainContent);
   $("#save").live('click', function(){ animals.saveChanges(); });

   // now initiate the grids
   $("#add").jqxButton({ width: '150'});
   $("#add_all").jqxButton({ width: '150'});
   $("#remove").jqxButton({ width: '150'});
   $("#reset").jqxButton({ width: '150'});
   $("#reset, #remove, #add, #add_all").on('click', function(sender){ animals.moveAnimals(sender); });

   // initiate the list boxes
   animals.initiateFiltersnLists();
   animals.movedAnimals = {};
};

/**
 * Re-initializes the ownership module
 * @returns {void}
 */
Animals.prototype.reInitializeOwnership = function(){
   var content = '\n\
<div id="ownership">\
   <div id="owners_list">&nbsp;</div>\
   <div id="links" class="center">\
      <button type="button" id="add" class="btn btn-primary">Add Ownership</button>\
   </div>\
</div>';

   $('#ownership').html(content);
   $('#add').bind('click', animals.addOwnership);
   animals.initiateAnimalsOwnersGrid();
};

/**
 * Create a page for managing the animal locations and the animals being held in those locations
 * @param   {string}    action   The action being performed
 * @returns {void}
 */
Animals.prototype.initiateAnimalLocations = function(action){
   $("#level1").jqxListBox({width: 200, source: animals.level1Locations, displayMember: 'name', valueMember: 'id', checkboxes: true, height: 150});
   $("#level1").on('checkChange', function (event) { animals.level1CheckChange(); });
   // create an empty level2 listbox pending selection of a level1 location
   $("#level2").jqxListBox({width: 200, source: [], displayMember: 'name', valueMember: 'id', checkboxes: true, height: 250});
   $("#level2").on('checkChange', function (event) { animals.level2CheckChange(); });

   // update the level2 listbox when the user checks or unchecks the level1 locations
   $("#level1").on('checkChange', function () {
      var items = $("#level1").jqxListBox('getCheckedItems');
      var level2Items = [];

      // traverse through all the checked level1 locations and find their respective level2 locations
      $.each(items, function (index, checkedLevel1) {
         $.each(animals.level2Locations, function(index1, level1){
            if(checkedLevel1 === level1){
               // we have the level2 locations belonging to the current checked level1 location
               level2Items = level2Items.concat(animals.level2Locations[level1]);
            }
         });
      });
      $("#level2").source = level2Items;
   });

   if(action === 'pensWithAnimals'){
      // initiate an empty listbox pending someone to select a pen with animals
      $("#level3").jqxListBox({width: 200, source: [], displayMember: 'name', valueMember: 'id', checkboxes: false,  height: 250});
   }
};

/**
 * A level1 location has been checked/unchecked
 */
Animals.prototype.level1CheckChange = function(){
   // lets update the level2 listbox with the pens
   var items = $("#level1").jqxListBox('getCheckedItems');
   var level2s = {};
   $.each(items, function(i, that){
      // get the level2 locations belonging to this level1 location
      $.each(animals.level2Locations[that.label], function(j, thist){
         level2s[Object.keys(level2s).length] = {name: thist.name, id: thist.id};
      });
   });
   $('#level2').jqxListBox({ source: level2s });
};

Animals.prototype.level2CheckChange = function(){
   var items = $("#level2").jqxListBox('getCheckedItems');
   var cur_animals = {};
   $.each(items, function(i, that){
//       cur_animals += animals.inLocations[that.value];
       $.each(animals.inLocations[that.value], function(i, that){
         cur_animals[Object.keys(cur_animals).length] = {name: that.name, id: that.id};
       });
   });
   $("#level3").jqxListBox({ source: cur_animals });
};

/**
 * Actions when level2 buttons are clicked
 * @param {type} event
 */
Animals.prototype.level2BttnClicked = function(event){
   var clickedButton = event.args.button;
//   alert("Clicked: " + clickedButton[0].id);
   if(clickedButton[0].id === 'add') animals.addLevels();
   else{
      animals.showNotification('Functionality not yet implemented!', 'error');
      return false;
   }
};

/**
 * Add a new level/sub level
 * @returns {void}
 */
Animals.prototype.addLevels = function(){
   // create a new interface for adding location levels
   animals.level1Locations[Object.keys(animals.level1Locations).length] = {id: -1, name: 'Add New'};
   var settings = {name: 'level1', id: 'level1Id', data: animals.level1Locations, initValue: 'Select One', required: 'true'};
   var level1Combo = Common.generateCombo(settings);

   $('#modal_window').jqxWindow({
      minHeight: 150, minWidth: 220, height: 160, width: 230,
      resizable: true, isModal: true, modalOpacity: 0, closeButtonAction: 'hide',
      initContent: function () {
         $('#ok').jqxButton({ width: '65px' });
         $('#cancel').jqxButton({ width: '65px' });
         $('#level2').focus();
       }
   });
   $('#modal_window').css({'display': 'block'});
   $('#level1_add').html(level1Combo);
   // if a new level1 is to be added
   $('#level1Id').live('change', function(){ animals.newLevel(); });

   // add the event listeners
   $('#ok, #cancel').on('click', function (event) { animals.animalsBttnClicked(event); } );
//   $('#modal_window').on('close', function (event) { animals.animalsBttnClicked(event); } );
};

/**
 * Actions when animal locations buttons are clicked
 * @param {type} event
 */
Animals.prototype.animalsBttnClicked = function(event){
   var clickedButton = event.target.id;
   if(clickedButton === 'cancel'){
      // nothing to do ... just close
      $('#modal_window').jqxWindow('close');
      return;
   }
   if(clickedButton === 'ok'){
      // we have a new level, so lets save it bt first
      // check that the level1 and level2 are defined
      var level1 = $('#level1Id').val();
      var level2 = $('#level2Id').val();
      if(level1 === '0' || level1 === ''){
         var mssg;
         if(level1 === '0') mssg = 'Please select a level1 location';
         if(level1 === '') mssg = 'Please enter the name of the level1 location';
         animals.showNotification(mssg, 'error');
         return false;
      }
      if(level2 === ''){
         animals.showNotification('Please enter the name of the level2 location', 'error');
         return false;
      }
      // now we are good, so lets send the data to the server
      var level1name = $('#level1Id option:selected').text();
       $.ajax({
         type:"POST", url: "mod_ajax.php?page=farm_animals&do=pens", async: false, dataType:'json', data: {'action': 'save', 'level1': level1, 'level2': level2, 'level1name': level1name },
         success: function (data) {
            if(data.error === true){
               animals.showNotification(data.mssg, 'error');
               return false;
            }
            else{
               animals.showNotification(data.mssg, 'success');
               $('#modal_window').jqxWindow('close');
               // refresh the list boxes
               $('#level2Id').val('');
               $("#level1").jqxListBox({source: data.data.level1});
            }
        }
      });
   }
};

/**
 * Initiates the interface for moving animals between pens
 *
 * @returns {void}
 */
Animals.prototype.initiateFiltersnLists = function(){
   // initiate the dropdown with the animals by traversing thru level2 object and getting the locations
   if(animals.byLocations !== undefined){
      var allLevels = {};
      $.each(animals.byLocations.level2, function(level1, that){
         $.each(that, function(i, sublevel){
            allLevels[Object.keys(allLevels).length] = {id: sublevel.id, name: level1+ ' >> ' +sublevel.name};
         });
      });
   }

   // to filter
   if(this.sub_module === 'ownership'){
      var settings = {name: 'toCombo', id: 'toComboId', data: animals.owners, initValue: 'Select One', required: 'true'};
      var toCombo = Common.generateCombo(settings);
      $('#to_filter').html(toCombo);

   }
   else if(this.sub_module === 'move_animals'){
      var settings = {name: 'toCombo', id: 'toComboId', data: allLevels, initValue: 'Select One', required: 'true'};
      var toCombo = Common.generateCombo(settings);
      $('#to_filter').html(toCombo);
   }
   else if(this.sub_module === 'events'){
      animals.initiateEventsToFilter();
   }
   else if(this.sub_module === 'experiments'){
      var settings = {name: 'toCombo', id: 'toComboId', data: animals.allExperiments, initValue: 'Select One', required: 'true'};
      var fromCombo = Common.generateCombo(settings);
      $('#to_filter').html(fromCombo);
   }

   // from filter
   if(this.sub_module === 'move_animals'){
      allLevels[Object.keys(allLevels).length] = {id:'floating', name: 'Select unattached'};
      var settings = {name: 'from', id: 'fromId', data: allLevels, initValue: 'Select One', required: 'true'};
      var fromCombo = Common.generateCombo(settings);
      $('#from_filter').html(fromCombo);
   }
   else if(this.sub_module === 'ownership' || this.sub_module === 'experiments'){
      var owners = animals.owners;
      owners[Object.keys(owners).length] = {id:'floating', name: 'Select unattached'};
      var settings = {name: 'from', id: 'fromId', data: owners, initValue: 'Select One', required: 'true'};
      var fromCombo = Common.generateCombo(settings);
      $('#from_filter').html(fromCombo);
   }
   else if(this.sub_module === 'events'){
      var settings = {name: 'from', id: 'fromId', data: allLevels, initValue: 'Select One', required: 'true'};
      var fromCombo = Common.generateCombo(settings);
      $('#from_filter').html(fromCombo);
   }

   // if any dropdown is changed, show the animals
   $('#fromId, #toComboId').live('change', function(that){ animals.filterAnimals(that); });

   $("#from_list").jqxListBox({width: 200, source: [], displayMember: 'name', valueMember: 'id', checkboxes: true, height: 350});
   $("#to_list").jqxListBox({width: 200, source: [], displayMember: 'name', valueMember: 'id', checkboxes: true, height: 350, hasThreeStates: true});
};

/**
 * Initiates the to filter of the events sub_module
 * @returns    {void}
 */
Animals.prototype.initiateEventsToFilter = function(){
   var settings = {name: 'toCombo', id: 'toComboId', data: animals.allEvents, initValue: 'Select One', required: 'true'};
   var toCombo = Common.generateCombo(settings);
   $('#to_filter').html(toCombo);
};

/**
 * Filter the grids based on the selected item
 *
 * @param   {object}    sender   The drop down which fired the event leading to this function being called
 * @returns {void}
 */
Animals.prototype.filterAnimals = function(sender){
   // check who initiated me and what he wants
   var selected = $('#'+sender.target.id).val();
   var neededAnimals = {};
   if(selected === 'floating'){
      if(this.sub_module === 'move_animals'){ neededAnimals = animals.byLocations.animals.floating; }
      else if(this.sub_module === 'ownership'){ neededAnimals = animals.byOwners['floating']; }
      else if(this.sub_module === 'events'){ neededAnimals = animals.byLocations.animals['floating']; }
      else if(this.sub_module === 'experiments'){ neededAnimals = animals.byOwners['floating']; }
   }
   else if(selected === 'new' && this.sub_module === 'events'){ animals.newEventName(); }
   else{
      // get the needed animals
      if(this.sub_module === 'move_animals') {neededAnimals = animals.byLocations.animals[selected]; }
      else if(this.sub_module === 'ownership') {neededAnimals = animals.byOwners[selected]; }
      else if(this.sub_module === 'events') {neededAnimals = animals.byLocations.animals[selected]; }
      else if(this.sub_module === 'experiments') {
         if(sender.target.id === 'fromId'){ neededAnimals = animals.byOwners[selected]; }
         else if(sender.target.id === 'toComboId'){ neededAnimals = animals.byExperiments[selected]; }
      }
   }

   // there is no need to the animals attached to this event... because we are not attaching animals to events
   if(this.sub_module === 'events' && sender.target.id === 'toComboId') { neededAnimals = {}; }

   // now attach them to the respective list box
   if(sender.target.id === 'fromId'){
      $("#from_list").jqxListBox({ source: neededAnimals });
   }
   else if(sender.target.id === 'toComboId'){
      // check for unsaved changes
      if(Object.keys(animals.movedAnimals).length !== 0){
         animals.showNotification('There are unsaved changes. Please save them first', 'error');
      }
      else{
         $("#to_list").jqxListBox({ source: neededAnimals });
      }
      // make the moved items unselectable....
      $.each($("#to_list").jqxListBox('getItems'), function(index, that){
         $("#to_list").jqxListBox('disableItem', that.value);
      });
   }
   // check if we need to mask out some animals from the from list
   if(this.sub_module === 'experiments'){

      if($('#fromId').val() === '0' || $('#toComboId').val() === '0'){ return; }
      else{
         // if the animal is already in the to list... no need to enable it for selection
         $.each($("#to_list").jqxListBox('getItems'), function(index, that){
            $.each($("#from_list").jqxListBox('getItems'), function(jndex, thist){
               if(that.value === thist.value){
                  // disable me
                  $("#from_list").jqxListBox('disableItem', thist.value);
               }
            });
         });
      }
   }
};

/**
 * Move the animals from the selected place to the selected other place .... hehehehehe
 *
 * @param   {object}    sender   The drop down which fired the event leading to this function being called
 * @returns {void}
 */
Animals.prototype.moveAnimals = function(sender){
   // check that
   // 3. The selected animals are not in the destination
   var error = false, mssg = '', checkedAnimals;

   if(sender.target.id === 'add' || sender.target.id === 'add_all') {
      // 1. Animals have been selected
      checkedAnimals = $("#from_list").jqxListBox('getCheckedItems');

      // 2. A destination have been selected
      if($('#toComboId').val() === '0'){
         error = true;
         mssg += (mssg === '') ? '': '<br />';
         if(this.sub_module === 'move_animals'){ mssg += 'Please select a destination location'; }
         else if(this.sub_module === 'ownership'){ mssg += 'Please select a new owner'; }
         else if(this.sub_module === 'events'){ mssg += 'Please select an event'; }
      }
   }
   else{
      // 1. Animals have been selected
      checkedAnimals = $("#to_list").jqxListBox('getCheckedItems');

      // 2. A destination have been selected
      if($('#fromComboId').val() === '0'){
         error = true;
         mssg += (mssg === '') ? '': '<br />';
         if(this.sub_module === 'move_animals'){ mssg += 'Please select a destination location'; }
         else if(this.sub_module === 'ownership'){ mssg += 'Please select a new owner'; }
      }
   }

   // we have some animals to move
   if(checkedAnimals.length === 0 && !(sender.target.id === 'add_all' || sender.target.id === 'reset')) {
      error = true;
      if(this.sub_module === 'move_animals'){ mssg = 'Please select an animal to move.'; }
      else if(this.sub_module === 'ownership'){ mssg = 'Please select an animal to assign new ownership.'; }
      else if(this.sub_module === 'events'){ mssg = 'Please select an animal to add an event to.'; }
   }
   // 4. In addition, the source and destination are not the same
   if($('#toComboId').val() === $('#fromId').val()){
      var error1;
      mssg += (mssg === '') ? '': '<br />';
      if(this.sub_module === 'move_animals'){ mssg += 'The source and destination cannot be the same'; }
      else if(this.sub_module === 'ownership'){ mssg += 'The previous and next owner cannot be the same'; }
      else if(this.sub_module === 'events'){ error1 = (error === true) ? true : false; /* This case doesnt really matter for the events sub_module*/ }
      error = (error1 === undefined) ? true : error1;
   }
   if(error){
      // something is a miss... so show the error and return
      animals.showNotification(mssg, 'error');
      return false;
   }

   // so lets move the animals
   if(sender.target.id === 'add' || sender.target.id === 'add_all'){
      var animals2move;
      if(sender.target.id === 'add'){ animals2move = $("#from_list").jqxListBox('getCheckedItems');  }
      else if(sender.target.id === 'add_all'){ animals2move =  $("#from_list").jqxListBox('getItems'); }

      $.each(animals2move, function(i, that){
         $("#to_list").jqxListBox('addItem', {label: that.label, value: that.value });
         $("#from_list").jqxListBox('removeAt', that.index);

         // add the animal to the list of moved animals
         animals.movedAnimals[that.value] = that.label;
      });
   }
   // so lets remove the animals ... but leave the ones which were there....
   if(sender.target.id === 'remove'){
      var animals2move;
      if(sender.target.id === 'remove'){
         animals2move = $("#to_list").jqxListBox('getCheckedItems');
         $.each(animals2move, function(i, that){
            $("#to_list").jqxListBox('removeAt', that.index);
            $("#from_list").jqxListBox('addItem', {label: that.label, value: that.value });

            // delete the animal from the moved list
            delete animals.movedAnimals[that.value];
         });
      }
   }
   else if(sender.target.id === 'reset'){
      // to reset the grid boxes, just call the change function assigned to the checkboxes, but first clear the moved animals list
      animals.movedAnimals = {};
      $('#fromId').change();
      $('#toComboId').change();
   }
};

/**
 * Save the animals which are to be moved
 * @returns    {void}
 */
Animals.prototype.saveChanges = function (){
   var  toId;
   if(this.sub_module === 'events' && $('#eventId').length !== 0){ toId = $('#eventId').val(); }
   else { toId = $('#toComboId').val(); }

   var fromId = $('#fromId').val();
    $.ajax({
      type:"POST", url: 'mod_ajax.php?page=farm_animals&do='+this.sub_module, dataType:'json', data: {'action': 'save', 'from': fromId, 'animals': $.toJSON(animals.movedAnimals), 'to': toId },
      success: function (data) {
         if(data.error === true){
            animals.showNotification(data.mssg, 'error');
            return false;
         }
         else{
            animals.showNotification(data.mssg, 'success');
            if(animals.sub_module === 'ownership'){ animals.reInitializeOwnership(); }
            else if(animals.sub_module === 'events'){ animals.reInitializeEvents(); }
            else if(animals.sub_module === 'experiments'){ animals.reInitializeExperiment(); }
            else if(animals.sub_module === 'move_animals'){
               animals.byLocations = data.data;
               $("#to_list").jqxListBox('clear');
               $('#fromComboId').val(0);
               $("#toComboId").val(0);
            }
         }
     }
   });
};

/**
 * Show a notification on the page
 *
 * @param   message     The message to be shown
 * @param   type        The type of message
 */
Animals.prototype.showNotification = function(message, type){
   if(type === undefined) { type = 'error'; }

   $('#messageNotification div').html(message);
   if($('#messageNotification').jqxNotification('width') === undefined){
      $('#messageNotification').jqxNotification({
         width: 350, position: 'top-right', opacity: 0.9,
         autoOpen: false, animationOpenDelay: 800, autoClose: true, autoCloseDelay: 3000, template: type
       });
   }
   $('#messageNotification').jqxNotification('open');
};

/**
 * We are adding a new level
 *
 * @returns {void}
 */
Animals.prototype.newLevel =  function(){
   if($('#level1Id').val() === '-1'){
      $('#level1_add').html("<input type='text' name='level1' id='level1Id' class='input-medium form-control' /><a href='javascript:;' class='cancel'><img src='images/close.png' /></a>");
      $('#level1_add .cancel').live('click', function(){
         var settings = {name: 'level1', id: 'level1Id', data: animals.level1Locations, initValue: 'Select One', required: 'true'};
         var level1Combo = Common.generateCombo(settings);
         $('#level1_add').html(level1Combo);
      });
   }
};

/**
 * Initiate a grid for the animal events
 *
 * @returns {void}
 */
Animals.prototype.initiateAnimalsEventsGrid = function(){
   // create the source for the grid
   var source = {
       datatype: 'json', datafields: [ {name: 'animal_id'}, {name: 'event_name'}, {name: 'event_date'}, {name: 'recorded_by'}, {name: 'done_by'}, {name: 'time_recorded'} ],
       id: 'id', root: 'data', async: false, type: 'POST', data: {action: 'list', field: 'animal_events'}, url: 'mod_ajax.php?page=farm_animals&do=events'
     };
     var eventsAdapter = new $.jqx.dataAdapter(source);
   // initialize jqxGrid
     if($('#events_grid :regex(class, jqx\-grid)').length === 0){
        $("#events_grid").jqxGrid({
            width: 910,
            height: 350,
            source: source,
            pageable: true,
            autoheight: true,
            sortable: true,
            altrows: true,
            enabletooltips: true,
            columns: [
              { text: 'Animal', datafield: 'animal_id', width: 75 },
              { text: 'Event', datafield: 'event_name', width: 150 },
              { text: 'Event Date', datafield: 'event_date', width: 100 },
              { text: 'Recorded By', datafield: 'recorded_by', width: 150 },
              { text: 'Performed By', datafield: 'performed_by', width: 150 },
              { text: 'Time Recorded', datafield: 'time_recorded', width: 200 }
            ]
         });
     }
     else{
        $("#events_grid").jqxGrid({source: eventsAdapter});
     }
};

/**
 * Creates an interface for adding new events
 * @returns    {void}
 */
Animals.prototype.newEvent = function(){
   // get animals groupings and all the events
   if(animals.byLocations === undefined){
      $.ajax({
          type:"POST", url: "mod_ajax.php?page=farm_animals&do=events", async: false, dataType:'json', data: {action: 'list', fields: $.toJSON(['byOwners', 'byLocations'])},
          success: function (data) {
             if(data.error === true){
                 animals.showNotification(data.mssg, 'error');
                 $('#animal_id').val('').focus();
                 return;
             }
             else{
                 animals.showNotification(data.mssg, 'success');
                animals.byLocations = data.data.byLocations;
                animals.allEvents = data.data.events;
                animals.byOwners = data.data.byOwners;
                // add the add new option for the events
               animals.allEvents[Object.keys(animals.allEvents).length] = {id:'new', name: 'Add new'};
             }
         }
      });
   }

   // change the interface to be able to add new ownership
var mainContent = '\
   <div id="all_animals">\
      <div id="from_filter"></div>\
      <div id="from_list"></div>\
   </div>\n\
   <div id="actions">\n\
      <button style="padding:4px 16px;" id="add">Add ></button>\
      <button style="padding:4px 16px;" id="add_all">Add All >></button>\
      <button style="padding:4px 16px;" id="remove">< Remove</button>\
      <button style="padding:4px 16px;" id="reset">Reset</button>\
   </div>\n\
   <div id="new_locations">\
      <div id="to_filter"></div>\
      <div id="to_list"></div>\
   </div>\n\
   <div id="actions">\n\
      <button style="padding:4px 16px;" id="save">Save</button>\n\
   </div>';

   $('#events').html(mainContent);
   $("#save").on('click', function(){ animals.saveChanges(); });

   // now initiate the grids
   $("#add").jqxButton({ width: '150'});
   $("#add_all").jqxButton({ width: '150'});
   $("#remove").jqxButton({ width: '150'});
   $("#reset").jqxButton({ width: '150'});
   $("#reset, #remove, #add, #add_all").on('click', function(sender){ animals.moveAnimals(sender); });

   // initiate the list boxes
   animals.initiateFiltersnLists();
   animals.movedAnimals = {};
};

Animals.prototype.reInitializeEvents = function(){
   var content = '<div id="events_grid"></div>\
   <div id="actions">\
      <button style="padding:4px 16px;" id="new">Add Events</button>\
   </div>';

   $('#events').html(content);
   $("#new").jqxButton({ width: '150'});
   // bind the click functions of the buttons
   $("#new").live('click', function(){ animals.newEvent(); });

   animals.initiateAnimalsEventsGrid();
};

/**
 * Initialize the experiments grid
 * @returns    {void}
 */
Animals.prototype.initiateExperimentsGrid = function(){
   // create the source for the grid
   var source = {
       datatype: 'json', datafields: [ {name: 'exp_name'}, {name: 'pi_name'}, {name: 'start_date'}, {name: 'end_date'}, {name: 'comments'} ],
       id: 'id', root: 'data', async: false, type: 'POST', data: {action: 'list', field: 'experiments'}, url: 'mod_ajax.php?page=farm_animals&do=experiments'
     };
     var expAdapter = new $.jqx.dataAdapter(source);
   // initialize jqxGrid
     if($('#exp_grid :regex(class, jqx\-grid)').length === 0){
        $("#exp_grid").jqxGrid({
            width: 910,
            height: 350,
            source: source,
            pageable: true,
            autoheight: true,
            sortable: true,
            altrows: true,
            enabletooltips: true,
            columns: [
              { text: 'Experiment Name', datafield: 'exp_name', width: 100 },
              { text: 'PI Name', datafield: 'pi_name', width: 150 },
              { text: 'Start Date', datafield: 'start_date', width: 100 },
              { text: 'End Date', datafield: 'end_date', width: 100 },
              { text: 'Comments', datafield: 'comments', width: 200}
            ]
         });
     }
     else{
        $("#exp_grid").jqxGrid({source: expAdapter});
     }

};

/**
 * Creates a new interface for adding a new experiment
 * @returns {void}
 */
Animals.prototype.newExperiment = function(){
      // get all the people who can be PIs
   $.ajax({
       type:"POST", url: "mod_ajax.php?page=farm_animals&do=experiments", dataType:'json', async: false, data: {'action': 'list', field: 'pis'},
       success: function (data) {
          if(data.error === true){
              animals.showNotification(data.mssg, 'error');
              $('#animal_id').val('').focus();
              return;
          }
          else{ animals.pis = data.data.owners; }
      }
   });

   var content = "\
<form id='new_experiment' class='form-horizontal' >\
   <div class='control-group'>\
      <label class='control-label' for='experiment'>Experiment&nbsp;&nbsp;<img class='mandatory' src='images/mandatory.gif' alt='Required' /></label>\n\
      <div id='exp_pl' class='animal_input controls'><input type='text' name='experiment' id='experimentId' placeholder='Experiment Name' class='input-medium form-control' required=true /></div>\n\
   </div>\n\
\
   <div class='control-group'>\
      <label class='control-label' for='pi'>Exp PI&nbsp;&nbsp;<img class='mandatory' src='images/mandatory.gif' alt='Required' /></label>\n\
      <div id='pis_pl' class='animal_input controls'></div>\n\
   </div>\n\
\
   <div class='control-group'>\
      <label class='control-label' for='start_date'>Start Date&nbsp;&nbsp;<img class='mandatory' src='images/mandatory.gif' alt='Required' /></label>\n\
      <div id='start_date_pl' class='animal_input controls'><input type='text' name='start_date' id='start_date' placeholder='Start Date' class='input-medium form-control' required=true /></div>\n\
   </div>\n\
\
   <div class='control-group'>\
      <label class='control-label' for='end_date'>End Date</label>\n\
      <div id='end_date_pl' class='animal_input controls'><input type='text' name='end_date' id='end_date' placeholder='End Date' class='input-medium form-control' /></div>\n\
   </div>\n\
\
   <div class='control-group'>\
      <label class='control-label' for='comments'>Comments</label>\n\
      <div id='comments_pl' class='animal_input controls'><textarea name='comments' id='comments' class=' form-control'></textarea></div>\n\
   </div>\n\
</form>\
";
   // create a popup that will add a new ownership of the animal
   CustomMssgBox.createMessageBox({ okText: 'Save', cancelText: 'Cancel', callBack: animals.saveNewExperiment, cancelButton: true, customTitle: 'New Experiment', message: content, width: '500px' });

   // create the date pickers
   datePickerController.createDatePicker({ formElements:{'start_date': '%d-%m-%Y'}, fillGrid: true, constraintSelection:false, maxDate: 0 });
   datePickerController.createDatePicker({ formElements:{'end_date': '%d-%m-%Y'}, fillGrid: true, constraintSelection:false, maxDate: 0 });

   // populate the animal and owner fields with the respective drop downs
   var settings = {name: 'pis', id: 'pi_id', data: animals.pis, initValue: 'Select One', required: 'true'};
   var pisCombo = Common.generateCombo(settings);
   $('#pis_pl').html(pisCombo);
};

/**
 * Saves a new experiment
 * @param   {object}    sender   The object of the opened pop up
 * @param   {boolean}   value    The value of the button clicked on the popup
 * @returns {void}
 */
Animals.prototype.saveNewExperiment = function(sender, value){
   if(value === false) {
      sender.close();
      return;
   }
   // get the data that we want before we close the pop up
   var formInfo = $('#new_experiment').formToArray(true), missingInfo = false;
   $.each(formInfo, function(){
      if((this.required && this.value === '' || this.required && this.value === 0 && this.type === 'select1') === true){
         // we have a mandatory field with no data...
         $('[name='+this.name+']').css({'aria-invalid': 'invalid'});
         missingInfo = true;
      }
   });
   if(missingInfo){
      animals.showNotification('Please fill in the missing mandatory information.', 'error');
      return;
   }

   // ok, so we good, lets save the new experiment
   var formSerialized = $('#new_experiment').formSerialize();
   $.ajax({
       type:"POST", url: "mod_ajax.php?page=farm_animals&do=experiments", dataType:'json', async: true, data: formSerialized + '&action=save_exp',
       success: function (data) {
          if(data.error === true){
              animals.showNotification(data.data, 'error');
              $('#experimentId').focus();
              return;
          }
          else{
              sender.close();
              animals.showNotification('The experiment has been saved successfully', 'success');
              animals.reInitializeExperiment();
          }
      }
   });
};

/**
 * Create a new interface for relating animals with experiments
 * @returns {void}
 */
Animals.prototype.newExperimentAnimals = function(){
   // get animals groupings and all the events
   $.ajax({
       type:"POST", url: "mod_ajax.php?page=farm_animals&do=experiments", async: false, dataType:'json', data: {action: 'list', fields: $.toJSON(['byOwners', 'experiments'])},
       success: function (data) {
          if(data.error === true){
              animals.showNotification(data.mssg, 'error');
              $('#animal_id').val('').focus();
              return;
          }
          else{
              animals.showNotification(data.mssg, 'success');
              animals.allExperiments = data.data.experiments;
              animals.byExperiments = data.data.byExperiments;
              animals.owners = data.data.byOwners.owners;
              animals.byOwners = data.data.byOwners.byOwners;
          }
      }
   });

   // change the interface to be able to add new ownership
var mainContent = '\
   <div id="all_animals">\
      <div id="from_filter"></div>\
      <div id="from_list"></div>\
   </div>\n\
   <div id="actions">\n\
      <button style="padding:4px 16px;" id="add">Add ></button>\
      <button style="padding:4px 16px;" id="add_all">Add All >></button>\
      <button style="padding:4px 16px;" id="remove">< Remove</button>\
      <button style="padding:4px 16px;" id="reset">Reset</button>\
   </div>\n\
   <div id="new_locations">\
      <div id="to_filter"></div>\
      <div id="to_list"></div>\
   </div>\n\
   <div id="actions">\n\
      <button style="padding:4px 16px;" id="save">Save</button>\n\
   </div>';

   $('#experiments').html(mainContent);
   $("#save").on('click', function(){ animals.saveChanges(); });

   // now initiate the grids
   $("#add").jqxButton({ width: '150'});
   $("#add_all").jqxButton({ width: '150'});
   $("#remove").jqxButton({ width: '150'});
   $("#reset").jqxButton({ width: '150'});
   $("#reset, #remove, #add, #add_all").on('click', function(sender){ animals.moveAnimals(sender); });
   $('#whoisme .back').html('<a href=\'?page=farm_animals&do=experiments\'>Back</a>');       //back link

   // initiate the list boxes
   animals.initiateFiltersnLists();
   animals.movedAnimals = {};
};

/**
 * Re-initialize the experiments grid to show the newly added experiments
 * @returns {void}
 */
Animals.prototype.reInitializeExperiment = function(){
   var content = '<div id="exp_grid"></div>\
   <div id="actions">\
      <button style="padding:4px 16px;" id="new_exp">Add an Experiment</button>\
      <button style="padding:4px 16px;" id="new_exp_animals">Manage Exp Animals</button>\
   </div>';

   $('#experiments').html(content);
   $("#new_exp").jqxButton({ width: '150'});
   $("#new_exp_animals").jqxButton({ width: '150'});
   // bind the click functions of the buttons
   $("#new_exp").on('click', function(){ animals.newExperiment(); });
   $("#new_exp_animals").on('click', function(){ animals.newExperimentAnimals(); });

   animals.initiateExperimentsGrid();
};
/**
 * Create an inbox for adding a new event name
 * @returns    {void}
 */
Animals.prototype.newEventName = function(){
   $('#to_filter').html("<input type='text' name='event_name' id='eventId' class='input-medium form-control' /><a href='javascript:;' class='cancel'><img src='images/close.png' /></a>");
   $('#to_filter .cancel').live('click', function(){ animals.initiateEventsToFilter(); });
};

// add a trim function
if (typeof(String.prototype.trim) === "undefined") {
   String.prototype.trim = function() {
      return String(this).replace(/^\s+|\s+$/g, '');
   };
}