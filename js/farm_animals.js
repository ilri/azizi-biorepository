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

   // don't show all the animals. skip the exited ones
   this.showAll = false;

   // call the respective function
   if(this.sub_module === 'experiments') this.initiateExperimentsGrid();
};

/**
 * A function to initiate the animals grid
 */
Animals.prototype.initiateAnimalsGrid = function(){
   // create the source for the grid
   var source = {
      datatype: 'json', datafields: [ {name: 'animal_id'}, {name: 'id'}, {name: 'breed'}, {name: 'species'}, {name: 'sex'}, {name: 'owner'}, {name: 'experiment'}, {name: 'location'}, {name: 'status'}],
         id: 'id', root: 'data', async: false, type: 'POST', data: {action: 'list', showAll: this.showAll}, url: 'mod_ajax.php?page=farm_animals&do=inventory'
     };
     var animalsAdapter = new $.jqx.dataAdapter(source);
   // initialize jqxGrid
     if($('#inventory :regex(class, jqx\-grid)').length === 0){
        $("#inventory").jqxGrid({
            width: 917,
            source: source,
            pageable: true,
            autoheight: true,
            sortable: true,
            showfilterrow: false,
            autoshowfiltericon: true,
            showstatusbar: true,
            renderstatusbar: animals.animalGridStatusBar,
            filterable: true,
            altrows: true,
            touchmode: false,
            pagesize: 20,
            pagesizeoptions: ['20', '50', '100'],
            rowdetails: true,
            initrowdetails: animals.initializeInventoryRowDetails,
            ready: function(){
                 var filtergroup = new $.jqx.filter(), filtervalue = 'Alive', filtercondition = 'equal';
                 var filter = filtergroup.createfilter('stringfilter', filtervalue, filtercondition);
                 filtergroup.addfilter(1, filter);
                 $("#inventory").jqxGrid('addfilter', 'status', filtergroup);
                 $("#inventory").jqxGrid('applyfilters');
            },
            rowdetailstemplate: {rowdetails: "<div id='grid' style='margin: 10px;'></div>", rowdetailsheight: 150, rowdetailshidden: true},
            columns: [
              { datafield: 'id', hidden: true },
              { text: 'Animal ID', datafield: 'animal_id', width: 95, cellsrenderer: function (row, columnfield, value, defaulthtml, columnproperties, rowdata) {
                    return '<a href="javascript:;" id="'+ rowdata.id +'" class="anim_id_href">&nbsp;'+ value +'</a>';
                 }
              },
              { text: 'Species', datafield: 'species', width: 60 },
              { text: 'Sex', datafield: 'sex', width: 50 },
              { text: 'Breed', datafield: 'breed', width: 110 },
              { text: 'Current Owner', datafield: 'owner', width: 110 },
              { text: 'Experiment', datafield: 'experiment', width: 190 },
              { text: 'Location', datafield: 'location', width: 150 },
              { text: 'Status', datafield: 'status', width: 120 }
            ]
        });
     }
     else{
        $("#inventory").jqxGrid({source: animalsAdapter});
     }
};

/**
 * Initiate the rendering of the status bar in the animal grid
 * @returns {undefined}
 */
Animals.prototype.animalGridStatusBar = function(statusbar){
   var container = $("<div style='overflow: hidden; position: relative; margin: 5px;'></div>");
   var excelButton = $("<div class='status_bar_div'><img style='position: relative; margin-top: 2px;' src='images/excel.png'/><span class='status_bar_span'>Export</span></div>");

   container.append(excelButton);
   excelButton.jqxButton({  width: 80, height: 20 });
   statusbar.append(container);

   excelButton.click(function (event) {
       $("#inventory").jqxGrid('exportdata', 'xls', 'jqxGrid', false);
   });

   $('#showAllId').on('change', function(){
      animals.showAll = $('#showAllId')[0].checked;
      animals.initiateAnimalsGrid();
   });
};

/**
 * Initiate the rendering of the status bar in the animal grid
 * @returns {undefined}
 */
Animals.prototype.eventsGridStatusBar = function(statusbar){
   var container = $("<div style='overflow: hidden; position: relative; margin: 5px;'></div>");
   var excelButton = $("<div class='status_bar_div'><img style='position: relative; margin-top: 2px;' src='images/excel.png'/><span class='status_bar_span'>Export</span></div>");
   var showAllCheck = $("<div class='status_bar_div'><input type='checkbox' id='showAllId' name='showAll' /><span class='status_bar_span'>Show All</span></div>");
   var sendEmail = $("<div class='status_bar_div'><span class='status_bar_span'>Send Email</span></div>");
   var newEvent = $("<div class='status_bar_div'><span class='status_bar_span'>New Event</span></div>");
   container.append(excelButton);
   container.append(showAllCheck);
   container.append(sendEmail);
   container.append(newEvent);
   excelButton.jqxButton({  width: 80, height: 20 });
   showAllCheck.jqxButton({  width: 80, height: 20 });
   sendEmail.jqxButton({  width: 80, height: 20 });
   newEvent.jqxButton({  width: 80, height: 20 });
   statusbar.append(container);

   newEvent.click(function(event){ animals.newEvent(); });

   sendEmail.click(function (event) {
      $.ajax({
         type:"POST", url: "mod_ajax.php?page=farm_animals&do=events", async: false, dataType:'json', data: {action: 'send_email'},
         success: function (data) {
            if(data.error === true){
               animals.showNotification(data.mssg, 'error');
               return;
            }
        }
     });
   });

   excelButton.click(function (event) {
       $("#events_grid").jqxGrid('exportdata', 'xls', 'jqxGrid', false);
   });

   $('#showAllId').on('change', function(){
      animals.showAll = $('#showAllId')[0].checked;
      animals.initiateAnimalsGrid();
   });
};

/**
 * Initializes the row details for the expanded row
 * @returns {void}
 */
Animals.prototype.initializeInventoryRowDetails = function(index, parentElement, gridElement, dr){
   var grid = $($(parentElement).children()[0]);

   var eventsSource = {
       datatype: "json", datafields: [ {name: 'event_id'}, {name: 'file_name'}, {name: 'path'}, {name: 'event_value'}, {name: 'event_name'}, {name: 'event_date'}, {name: 'record_date'}, {name: 'comments'} ], type: 'POST',
       id: 'id', data: {action: 'list', field: 'events',  animal_id: dr.id}, url: 'mod_ajax.php?page=farm_animals&do=inventory'
    };

    if (grid !== null) {
      grid.jqxGrid({source: eventsSource, theme: '', width: 820, height: 140,
      columns: [
         {datafield: 'file_name', hidden: true},
         {datafield: 'path', hidden: true},
         {text: 'EventId', datafield: 'event_id', hidden: true},
         {text: 'Event Name', datafield: 'event_name', width: 110, cellsrenderer: function(r,c,v,d,cp,rd){
               // update the event name with a link to the file(s)
               if(rd.path === null){ return v; }
               else{ return '<a href="'+ rd.path +'" data-ob="lightbox" class="pdf_href" target="__blank">&nbsp;'+ v +' - Report</a>'; }
         }},
         {text: 'Event Value', datafield: 'event_value', width: 100},
         {text: 'Event Date', datafield: 'event_date', width: 90},
         {text: 'Record Date', datafield: 'record_date', width: 140},
         {text: 'Comments', datafield: 'comments', width: 340, cellsrenderer: function(r,c,v,d,cp,rd){ return v.replace("\n", "<br />"); }}
      ]
      });
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
       type:"POST", url: 'mod_ajax.php?page=farm_animals&do=add', async: false, dataType:'json', data: {animalId: animalId, action: 'confirm'},
       success: function (data) {
          animals.showNotification(data.mssg, data.error);
          if(data.error === true){
             $('#animal_id').val('').focus();
             return;
          }
          else if(data.data.animalDetails !== undefined){
             // we have the animal details, so populate the necessary fields
             var t = data.data.animalDetails;
             if(t.animal_id !== 'null'){ $('#animal_id').val(t.animal_id); } else { $('#animal_id').val(''); }
             if(t.species_id !== 'null'){ $('#speciesId').val(t.species_id); } else { $('#speciesId').val(0); }
             if(t.breed !== 'null'){ $('#breedId').val(t.breed); } else { $('#breedId').val(0); }
             if(t.dob !== 'null'){ $('#dob').val(t.dob); } else { $('#').val(''); }
             if(t.sex !== 'null' || t.sex === ''){ $('#'+t.sex)[0].checked = true; } else { $('#').val(''); }
             if(t.other_id !== 'null'){ $('#other_id').val(t.other_id); } else { $('#other_id').val(''); }
             if(t.sire !== 'null'){ $('#sire').val(t.sire); } else { $('#sire').val(''); }
             if(t.dam !== 'null'){ $('#dam').val(t.dam); } else { $('#dam').val(''); }
             if(t.exp_name !== 'null'){ $('#experiment').val(t.exp_name); } else { $('#experiment').val(''); }
             if(t.comments !== 'null' || t.comments !== ''){ $('#comments').val(t.comments); } else { $('#comments').val(''); }
             animals.editedAnimal = t.id;
             $('#save').val('edit');
             $('#save')[0].innerHTML = 'Update';
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

   // get the breeds
   var breed = [];
   $.each($('#breedId')[0].selectedOptions, function(){
      breed[breed.length] = this.value;
   });

   // date of birth
   var formSerialized = $('#animals').formSerialize();
   var action = ($('#save').val() === 'save') ? 'add' : 'edit';
   var animalEdited = ($('#save').val() === 'save') ? '' : '&editedAnimal='+animals.editedAnimal;
   $.ajax({
       type:"POST", url: 'mod_ajax.php?page=farm_animals&do='+action, async: false, dataType:'json', data: formSerialized +'&action=save'+ animalEdited +'&breed='+ $.toJSON(breed),
       success: function (data) {
          animals.showNotification(data.mssg, data.error);
          if(data.error === true){
              $('#animal_id').val('').focus();
              return;
          }
          else{
             // clear all the fields and get ready for saving a new animal
              $('#animals').clearForm();
              $('#sire').val('');
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
       datatype: 'json', datafields: [ {name: 'animal_id'}, {name: 'animal'}, {name: 'owner'}, {name: 'start_date'}, {name: 'end_date'}, {name: 'comments'} ],
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
            showfilterrow: false,
            autoshowfiltericon: true,
            showstatusbar: true,
            renderstatusbar: animals.animalGridStatusBar,
            filterable: true,
            enabletooltips: false,
            pagesize: 20,
            pagesizeoptions: ['20', '50', '100'],
            rowdetails: true,
            initrowdetails: animals.initializeOwnershipRowDetails,
            rowdetailstemplate: {rowdetails: "<div id='grid' style='margin: 10px;'></div>", rowdetailsheight: 130, rowdetailshidden: true},
            columns: [
              { datefield: 'animal_id', hidden: true, width: 50 },
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
 * Initiate the place for showing a history of animal ownership
 * @returns {undefined}
 */
Animals.prototype.initializeOwnershipRowDetails = function(index, parentElement, gridElement, dr){
   var grid = $($(parentElement).children()[0]);

   var ownersSource = {
       datatype: "json", datafields: [ {name: 'animal'}, {name: 'owner'}, {name: 'start_date'}, {name: 'end_date'}, {name: 'comments'} ], type: 'POST',
       id: 'id', data: {action: 'history',  animal_id: dr.animal_id },
       url: 'mod_ajax.php?page=farm_animals&do=ownership'
    };

    if (grid !== null) {
      grid.jqxGrid({source: ownersSource, theme: '', width: 840, height: 140,
      columns: [
         {text: 'Animal ID', datafield: 'animal', width: 150},
         {text: 'Owner', datafield: 'owner', width: 100},
         {text: 'Start Date', datafield: 'start_date', width: 70},
         {text: 'End Date', datafield: 'end_date', width: 150},
         {text: 'Comments', datafield: 'comments', width: 200}
      ]
      });
   }
};

/**
 * Add a new ownership of an animal
 */
Animals.prototype.addOwnership = function(){
   // get all the animals and all the people who can be owners
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
             animals.allAnimals = data.animals.allAnimals;
             animals.locationOrganiser();
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

   $('#owners_list').html(mainContent);
   $('#links').remove();
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

   // default to all animals
   $('#fromId').val('all').change();
};

/**
 * Re-initializes the ownership module
 * @returns {void}
 */
Animals.prototype.reInitializeOwnership = function(){
   var content = '\n\
   <div id="owners_list">&nbsp;</div>\
   <div id="links" class="center">\
      <button type="button" id="add" class="btn btn-primary">Add Ownership</button>\
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

/**
 * An animal in the destination list box has been selected/deselected
 * @returns {void}
 */
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
   // to filter
   if(this.sub_module === 'ownership'){
      var settings = {name: 'toCombo', id: 'toComboId', data: animals.owners, initValue: 'Select One', required: 'true'};
      var toCombo = Common.generateCombo(settings);
      $('#to_filter').html(toCombo);

   }
   else if(this.sub_module === 'move_animals'){
      var settings = {name: 'toCombo', id: 'toComboId', data: animals.allLevels, initValue: 'Select One', required: 'true'};
      var toCombo = Common.generateCombo(settings);
      $('#to_filter').html(toCombo);
   }
   else if(this.sub_module === 'events'){
      animals.initiateEventsToFilter();
   }
   else if(this.sub_module === 'experiments'){
      var settings = {name: 'toCombo', id: 'toComboId', data: animals.allExperiments, initValue: 'Select One', required: 'true'};
      var toCombo = Common.generateCombo(settings);
      $('#to_filter').html(toCombo);
   }

   // from filter
   if(this.sub_module === 'move_animals'){
      animals.allLevels[Object.keys(animals.allLevels).length] = {id:'all', name: 'Select All'};
      animals.allLevels[Object.keys(animals.allLevels).length] = {id:'floating', name: 'Select unattached'};
      var settings = {name: 'from', id: 'fromId', data: animals.allLevels, initValue: 'Select One', required: 'true'};
      var fromCombo = Common.generateCombo(settings);
      $('#from_filter').html(fromCombo);
   }
   else if(this.sub_module === 'ownership' || this.sub_module === 'experiments'){
      var owners = animals.owners;
      owners[Object.keys(owners).length] = {id:'all', name: 'Select All'};
      owners[Object.keys(owners).length] = {id:'floating', name: 'Select unattached'};
      var settings = {name: 'from', id: 'fromId', data: owners, initValue: 'Select One', required: 'true'};
      var fromCombo = Common.generateCombo(settings);
      $('#from_filter').html(fromCombo);
   }
   else if(this.sub_module === 'events'){
      var settings = {name: 'from', id: 'fromId', data: animals.desiredGroupings, initValue: 'Select One', required: 'true'};
      var fromCombo = Common.generateCombo(settings);
      $('#from_filter').html(fromCombo);
   }

   // if any dropdown is changed, show the animals
   $('#fromId, #toComboId').live('change', function(that){ animals.filterAnimals(that); });

   var fromHeight = (this.sub_module === 'events') ? 320 : 350;
   $("#from_list").jqxListBox({width: 215, source: [], displayMember: 'name', valueMember: 'id', checkboxes: true, height: fromHeight, filterable: true});
   $("#to_list").jqxListBox({width: 215, source: [], displayMember: 'name', valueMember: 'id', checkboxes: true, height: 350, hasThreeStates: true});
};

/**
 * Initiates the to filter of the events sub_module
 * @returns    {void}
 */
Animals.prototype.initiateEventsToFilter = function(){
   var settings = {name: 'toCombo', id: 'toComboId', data: animals.allEvents, initValue: 'Select One', required: 'true'};
   var toCombo = Common.generateCombo(settings);
   $('#to_filter').html(toCombo);
   $('#toComboId').on('change', animals.addEventDetails);
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
   else if(selected === 'all' || selected === 'all_all'){ neededAnimals = animals.allAnimals; }
   else if(selected === 'new' && this.sub_module === 'events'){ animals.newEventName(); }
   else{
      // get the needed animals
      if(this.sub_module === 'move_animals') {neededAnimals = animals.byLocations.animals[selected]; }
      else if(this.sub_module === 'ownership') {neededAnimals = animals.byOwners[selected]; }
      else if(this.sub_module === 'events') {
         // need to decompose the key to find out which animals are needed
         var type = selected.substring(0,3);
         var index = selected.substring(selected.indexOf('_')+1);
         switch (type){
            case 'exp':
               neededAnimals = animals.byExperiments[index];
            break;

            case 'own':
               neededAnimals = animals.byOwners[index];
            break;

            case 'loc':
               neededAnimals = animals.byLocations.animals[index];
            break;
         };
      }
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
   if(this.sub_module === 'experiments' || this.sub_module === 'events' || this.sub_module === 'move_animals' || this.sub_module === 'ownership'){
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
 * Populate the events filter while adding new events
 * @returns {void}
 */
Animals.prototype.populateEventsFilter = function(){
   var selected = $('#from_top_filter').jqxDropDownList('getCheckedItems');
   var desiredGroupings = {};
   var showAllAnimals = false;

   // loop through the selected items and get the items to populate the from dropdown filter
   $.each(selected, function (i, that) {
      switch (that.value) {
         case 'exp':
            $.each(animals.allExperiments, function () {
               desiredGroupings[Object.keys(desiredGroupings).length] = {id: 'exp_' + this.id, name: this.name};
            });
            break;

         case 'location':
            $.each(animals.allLevels, function () {
               desiredGroupings[Object.keys(desiredGroupings).length] = {id: 'loc_' + this.id, name: this.name};
            });
            break;

         case 'pis':
            $.each(animals.allOwners, function () {
               desiredGroupings[Object.keys(desiredGroupings).length] = {id: 'own_' + this.id, name: this.name};
            });
            break;

         case 'all':
            desiredGroupings[Object.keys(desiredGroupings).length] = {id: 'all_all', name: 'All Animals'};
            showAllAnimals = true;
            break;
      }
   });

   animals.desiredGroupings = desiredGroupings;
   var settings = {name: 'from', id: 'fromId', data: animals.desiredGroupings, initValue: 'Select One', required: 'true'};
   var fromCombo = Common.generateCombo(settings);
   $('#from_filter').html(fromCombo);

   // check if we need to show all animals
   if(showAllAnimals){
      $('#fromId').val('all').change();
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
 * Confirm that the additional extras while adding events have been set
 *
 * @returns {Boolean}   Returns false when there are no errors, else it returns true
 */
Animals.prototype.confirmEventsExtras = function(){
   var intendedAction = $('#toComboId option:selected').text();
   var eventValue = $('#eventValueId').val();
   var isError = false, errorMsg = '', err;

   // get the date, person who performed it and the comments
   var performedBy = $('#performedBy_id').val(), eventDate = $("#event_date_pl").jqxDateTimeInput('value'), exitType = $('#sub_events_id').val();
   if(performedBy === '0'){
      isError = true;
      err = 'Please select the person who performed the event';
      errorMsg = (errorMsg === '') ? err : err +'<br />'+ errorMsg;
   }
   if(eventDate === null){
      isError = true;
      err = 'Please specify the date when the event was performed';
      errorMsg = (errorMsg === '') ? err : err +'<br />'+ errorMsg;
   }
   if(intendedAction === animals.exitVariable && exitType === '0'){
      isError = true;
      err = 'Please select the type of exit for the selected animal(s)';
      errorMsg = (errorMsg === '') ? err : err +'<br />'+ errorMsg;
   }
   else if(intendedAction !== animals.exitVariable && exitType === '0'){ exitType = undefined; }
   if(animals.valueEvents.indexOf(intendedAction) !== -1 && eventValue === ''){
      isError = true;
      err = 'Please enter the event value for the selected animal';
      errorMsg = (errorMsg === '') ? err : err +'<br />'+ errorMsg;
   }
   animals.extraData = {eventDate: $("#event_date_pl").jqxDateTimeInput('getText'), performedBy: performedBy, comments: $('#event_comments').val(), exitType: exitType, eventValue: eventValue };


   if(isError === true){ animals.showNotification(errorMsg, 'error'); }
   return isError;
};

/**
 * A generic function used to save changes which have been performed by the user
 * @returns    {void}
 */
Animals.prototype.saveChanges = function (){
   var toId;
   var formData = new FormData();

   // ensure that we have an attachment when we need one
   if($('.addons').length === 1){
      if($('#pmReportPlaceId')[0].style.display === 'block' && animals.pmReport === undefined){
         animals.showNotification('Please add a PM report for this event.', 'error');
         return;
      }

      if(animals.pmReport !== undefined){
         // we have a file to upload... this requires special handling gloves
         // Create a formdata object and add the pm report
         $.each(animals.pmReport, function(key, value){
             formData.append('uploads[]', value);
         });
      }
   }

   if(this.sub_module === 'events'){
      if(Object.keys(animals.movedAnimals).length === 0){
         animals.showNotification('Please add animals involved in this event.', 'error');
         return;
      }
      var error = animals.confirmEventsExtras();
      if(error === true){ return; }
   }
   if(this.sub_module === 'events' && $('#eventId').length !== 0){ toId = $('#eventId').val(); }
   else { toId = $('#toComboId').val(); }

   var fromId = $('#fromId').val();
   formData.append('action', 'save');
   formData.append('from', fromId);
   formData.append('to', toId);
   formData.append('animals', $.toJSON(animals.movedAnimals));
   formData.append('extras', $.toJSON(animals.extraData));
    $.ajax({
      type:"POST", url: 'mod_ajax.php?page=farm_animals&do='+this.sub_module, dataType:'json', cache: false, contentType: false, processData: false,
      data: formData,
      success: function (data) {
         if(data.error === true){
            animals.showNotification(data.mssg, 'error');
            return false;
         }
         else{
            animals.showNotification(data.mssg, 'success');
            animals.movedAnimals = {};
            if(animals.sub_module === 'ownership'){ animals.reInitializeOwnership(); }
            else if(animals.sub_module === 'events'){ animals.reInitializeEvents(); }
            else if(animals.sub_module === 'experiments'){ animals.reInitializeExperiment(); }
            else if(animals.sub_module === 'move_animals'){
               animals.byLocations = data.data;
               animals.locationOrganiser();
               $("#to_list").jqxListBox('clear');
               $('#fromComboId').val(0);
               $("#toComboId").val(0);

               // reset to selecting all animals
               $('#fromId').val('all').change();
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
   else{ $('#messageNotification').jqxNotification({template: type}); }

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
       datatype: 'json', datafields: [ {name: 'event_type_id'}, {name: 'sub_event_type_id'}, {name: 'event_name'}, {name: 'event_date'}, {name: 'recorded_by'}, {name: 'performed_by_id'}, {name: 'performed_by'}, {name: 'time_recorded'}, {name: 'no_animals'}, {name: 'actions'} ],
       id: 'id', root: 'data', async: false, type: 'POST', data: {action: 'list', field: 'animal_events'}, url: 'mod_ajax.php?page=farm_animals&do=events'
     };
     var eventsAdapter = new $.jqx.dataAdapter(source);
   // initialize jqxGrid
     if($('#events_grid :regex(class, jqx\-grid)').length === 0){
        $("#events_grid").jqxGrid({
            width: 910,
            source: source,
            pageable: true,
            autoheight: true,
            sortable: true,
            altrows: true,
            rowdetails: true,
            autoshowfiltericon: true,
            showstatusbar: true,
            renderstatusbar: animals.eventsGridStatusBar,
            filterable: true,
            touchmode: false,
            pagesize: 15,
            pagesizeoptions: ['20', '50', '100'],
            initrowdetails: animals.initializeEventRowDetails,
            rowdetailstemplate: {rowdetails: "<div id='grid' style='margin: 10px;'></div>", rowdetailsheight: 150, rowdetailshidden: true},
            columns: [
              { datafield: 'event_type_id', hidden: true },
              { datafield: 'sub_event_type_id', hidden: true },
              { datafield: 'performed_by_id', hidden: true },
              { text: 'Event', datafield: 'event_name', width: 150 },
              { text: 'Event Date', datafield: 'event_date', width: 90 },
              { text: 'Recorded By', datafield: 'recorded_by', width: 140 },
              { text: 'Performed By', datafield: 'performed_by', width: 130 },
              { text: 'Animals Count', datafield: 'no_animals', width: 100 },
              { text: 'Time Recorded', datafield: 'time_recorded', width: 170 },
              { text: 'Actions', datafield: 'actions', width: 100 }
            ]
         });
     }
     else{
        $("#events_grid").jqxGrid({source: eventsAdapter});
     }
};

/**
 * Initializes the row details for the expanded row
 * @returns {void}
 */
Animals.prototype.initializeEventRowDetails = function(index, parentElement, gridElement, dr){
   var grid = $($(parentElement).children()[0]);

   var eventsSource = {
       datatype: "json", datafields: [ {name: 'event_id'}, {name: 'animal_id'}, {name: 'sex'}, {name: 'performed_by'}, {name: 'comments'}, {name: 'event_value'}, {name: 'actions'} ], type: 'POST',
       id: 'id', data: {action: 'list', field: 'sub_events',  performed_by: dr.performed_by_id, event_type_id: dr.event_type_id, sub_event_type_id: dr.sub_event_type_id, event_date: dr.event_date},
       url: 'mod_ajax.php?page=farm_animals&do=events'
    };

    if (grid !== null) {
      grid.jqxGrid({source: eventsSource, theme: '', width: 840, height: 140,
      columns: [
         {datafield: 'event_id', hidden: true},
         {text: 'Animal ID', datafield: 'animal_id', hidden: false, width: 120},
         {text: 'Sex', datafield: 'sex', width: 100},
         {text: 'Value', datafield: 'event_value', width: 70},
         {text: 'Performed By', datafield: 'performed_by', width: 150},
         {text: 'Comments', datafield: 'comments', width: 280},
         {text: 'Actions', datafield: 'actions', width: 100, cellsrenderer: function (row, columnfield, value, defaulthtml, columnproperties, rowdata) {
               return '<a href="javascript:;" id="'+ rowdata.event_id +'" class="event_id_href '+ rowdata.uniqueid +'">&nbsp;Delete</a>';
            }
          }
      ]
      });
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
          type:"POST", url: "mod_ajax.php?page=farm_animals&do=events", async: false, dataType:'json', data: {action: 'list', fields: $.toJSON(['byOwners', 'byLocations', 'allOwners', 'byExperiments'])},
          success: function (data) {
             if(data.error === true){
               animals.showNotification(data.mssg, 'error');
               $('#animal_id').val('').focus();
               return;
             }
             else{
               animals.showNotification('The data has been successfully fetched.', 'success');
               animals.byLocations = data.data.byLocations;
               animals.allAnimals = data.data.byLocations.allAnimals;
               animals.locationOrganiser();
               animals.allEvents = data.data.events;
               animals.allSubEvents = data.data.sub_events;
               animals.byOwners = data.data.byOwners;
               animals.byExperiments = data.data.byExperiments;
               animals.allExperiments = data.data.allExperiments;
               animals.allOwners = data.data.allOwners;
               animals.eventMinDays = data.data.eventMinDays;
             }
         }
      });
   }

   // change the interface to be able to add new ownership
var mainContent = '\
   <div id="all_animals">\
      <div id="from_top_filter"></div>\
      <div id="from_filter"></div>\
      <div id="from_list"></div>\
   </div>\n\
   <div class="actions">\n\
      <button style="padding:4px 16px;" id="add">Add ></button>\
      <button style="padding:4px 16px;" id="add_all">Add All >></button>\
      <button style="padding:4px 16px;" id="remove">< Remove</button>\
      <button style="padding:4px 16px;" id="reset">Reset</button>\
   </div>\n\
   <div id="new_locations">\
      <div id="to_filter"></div>\
      <div id="to_list"></div>\
   </div>\n\
   <div id="event_actions">\n\
      <div class="save"><button style="padding:4px 16px;" id="save">Save</button></div>\n\
   </div>\n\
   <div class="actions" style="float: none;">&nbsp;</div>\n\
   ';

   $('#events_home').html(mainContent);
   $("#save").on('click', function(){ animals.saveChanges(); });

   // now initiate the grids
   $("#add").jqxButton({ width: '150'});
   $("#add_all").jqxButton({ width: '150'});
   $("#remove").jqxButton({ width: '150'});
   $("#reset").jqxButton({ width: '150'});
   $("#reset, #remove, #add, #add_all").on('click', function(sender){ animals.moveAnimals(sender); });

   // grouping options
   var groups = [{id: 'pis', name: 'By PI'}, {id: 'exp', name: 'By Experiment'}, {id: 'location', name: 'By Location'}, {id: 'all', name: 'Show all animals'}];
//   var groups = ['By PI', 'By Experiment', 'By Location', 'All groupings'];
   $('#from_top_filter').jqxDropDownList({source: groups, dropDownHeight: 100, checkboxes: true, displayMember: 'name', valueMember: 'id'});
   $('#from_top_filter').on('checkChange', function(){ animals.populateEventsFilter(); });

   // initiate the list boxes
   animals.initiateFiltersnLists();
   animals.movedAnimals = {};

   $('#from_top_filter').jqxDropDownList('checkItem', 'all');
   $('#fromId').val('all_all').change();
};

Animals.prototype.reInitializeEvents = function(){
   var content = '<div id="events_grid"></div>';

   $('#events_home').html(content);
   animals.initiateAnimalsEventsGrid();
};

/**
 * Initiate the process of deleting an event
 *
 * @param {type} that
 * @returns {undefined}
 */
Animals.prototype.confirmDeleteEvent = function(that){
   $("#modalWindow").jqxWindow({ height: 150, width: 300, theme: 'summer', isModal: true, autoOpen: false });
   $("#modalWindow").jqxWindow('open');

   $("#button_yes").click(function () { animals.deleteEvent(that.target.id, that.target.classList[1]); });
   $("#button_no").click(function () { $("#modalWindow").jqxWindow('close'); });
};

Animals.prototype.deleteEvent = function(event_id, rowId){
   $("#modalWindow").jqxWindow('close');
   $.ajax({
      type:"POST", url: "mod_ajax.php?page=farm_animals&do=events", dataType:'json', async: false, data: {'action': 'delete', event_id: event_id},
      success: function (data) {
         if(data.error === true){
            animals.showNotification(data.mssg, 'error');
            return;
          }
          else{
             animals.showNotification(data.mssg, 'success');
             animals.initiateAnimalsEventsGrid();
          }
       }
   });
};

/**
 * Initialize the experiments grid
 * @returns    {void}
 */
Animals.prototype.initiateExperimentsGrid = function(){
   // create the source for the grid
   var source = {
       datatype: 'json', datafields: [ {name: 'exp_name'}, {name: 'iacuc'}, {name: 'pi_name'}, {name: 'start_date'}, {name: 'end_date'}, {name: 'comments'} ],
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
            enabletooltips: false,
            pagesize: 15,
            pagesizeoptions: ['20', '50', '100'],
            columns: [
              { text: 'Experiment Name', datafield: 'exp_name', width: 350 },
              { text: 'IACUC No', datafield: 'iacuc', width: 80 },
              { text: 'PI Name', datafield: 'pi_name', width: 100 },
              { text: 'Start Date', datafield: 'start_date', width: 80 },
              { text: 'End Date', datafield: 'end_date', width: 80 },
              { text: 'Comments', datafield: 'comments', width: 220}
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
          else{ animals.pis = data.data; }
      }
   });

   var content = "\
<form id='new_experiment' class='form-horizontal' >\
   <div class='control-group'>\
      <label class='control-label' for='experiment'>Experiment Name&nbsp;&nbsp;<img class='mandatory' src='images/mandatory.gif' alt='Required' /></label>\n\
      <div id='exp_pl' class='animal_input controls'><input type='text' name='experiment' id='experimentId' placeholder='Experiment Name' class='input-medium form-control' required=true /></div>\n\
   </div>\n\
\
   <div class='control-group'>\
      <label class='control-label' for='iacuc'>IACUC No&nbsp;&nbsp;<img class='mandatory' src='images/mandatory.gif' alt='Required' /></label>\n\
      <div id='exp_pl' class='animal_input controls'><input type='text' name='iacuc' id='iacucId' placeholder='IACUC' class='input-medium form-control' required=true /></div>\n\
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

   $('#exp_grid').html(mainContent);
   $('#grid_actions').remove();
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
   <div id="grid_actions">\
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

/**
 * Adds a space for adding details to the selected event
 * @returns {void}
 */
Animals.prototype.addEventDetails = function(sender){
   // get the selected option...
   // 'this' used here is a reference for the target which called the event, the drop down in this case
   var eventName = $('#'+ this.id +' option:selected').text(), content2add;
   if($('.addons').length === 1 && eventName === 'Select One'){
      // we have the add on window open, but we dont want to add an event
      $('.addons').remove();
      return;
   }
   // if the additional details for an event are added
   if($('.addons').length === 1) {
      // if the type of exit field is active and the selected event is not exit, remove it, else just exit
      if($('#eventValuePlaceId')[0].style.display === 'block' && animals.valueEvents.indexOf(eventName) === -1){ $('#eventValuePlaceId').css({'display': 'none'}); }
      else if($('#eventValuePlaceId')[0].style.display === 'none' && animals.valueEvents.indexOf(eventName) !== -1){ $('#eventValuePlaceId').css({'display': 'block'}); }

      if($('#exitTypeId')[0].style.display === 'block' && eventName !== animals.exitVariable){ $('#exitTypeId').css({'display': 'none'}); }
      else if($('#exitTypeId')[0].style.display === 'none' && eventName === animals.exitVariable){ $('#exitTypeId').css({'display': 'block'}); }

      if($('#pmReportPlaceId')[0].style.display === 'block' && eventName !== animals.pmReportVariable){ $('#pmReportPlaceId').css({'display': 'none'}); }
      else if($('#pmReportPlaceId')[0].style.display === 'none' && eventName === animals.pmReportVariable){ $('#pmReportPlaceId').css({'display': 'block'}); }

      return;
   }

   content2add = "<div class='addons'>\n\
      <div id='exitTypeId' class='control-group'>\
         <label class='control-label' for='exitType'>Type of Exit&nbsp;&nbsp;<img class='mandatory' src='images/mandatory.gif' alt='Required' /></label>\n\
         <div id='exit_type_pl' class='animal_input controls'></div>\n\
      </div>\n\
      <div id='eventValuePlaceId' class='control-group'>\
         <label class='control-label' for='eventValue'>Event Value&nbsp;&nbsp;<img class='mandatory' src='images/mandatory.gif' alt='Required' /></label>\n\
         <div id='event_value_pl' class='animal_input controls'>\n\
            <input type='text' value='' id='eventValueId' />\n\
         </div>\n\
      </div>\n\
      <div id='pmReportPlaceId' class='control-group' id='pm_report'>\
         <label class='control-label' for='event_date'>PM Report&nbsp;&nbsp;<img class='mandatory' src='images/mandatory.gif' alt='Required' /></label>\n\
         <div id='pdf_cont' class='animal_input controls'><input type='file' name='pm_report' /></div>\n\
      </div>\n\
      <div class='control-group'>\
         <label class='control-label' for='performed'>Performed By&nbsp;&nbsp;<img class='mandatory' src='images/mandatory.gif' alt='Required' /></label>\n\
         <div id='performedBy_pl' class='animal_input controls'></div>\n\
      </div>\n\
      <div class='control-group'>\
         <label class='control-label' for='event_date'>Event Date&nbsp;&nbsp;<img class='mandatory' src='images/mandatory.gif' alt='Required' /></label>\n\
         <div id='event_date_pl' class='animal_input controls'></div>\n\
      </div>\n\
      <div class='control-group'>\
         <label class='control-label' for='comments'>Event Comments/Values</label>\n\
      <div id='comments_pl' class='animal_input controls'><textarea id='event_comments' rows='3' cols='7'></textarea></div>\n\
   </div>";

   // now add the new functionality to the page
   $('#event_actions .save').before(content2add);
   var todays = new Date(), minDate = new Date();
   minDate.setDate(minDate.getDate() - animals.eventMinDays);
   $("#event_date_pl").jqxDateTimeInput({
      width: '150px', height: '25px', readonly: true,
      min: new Date(minDate.getFullYear(), minDate.getMonth(), minDate.getDate()),
      max: new Date(todays.getFullYear(), todays.getMonth(), todays.getDate())
   });

   // populate the performed by field with the respective drop down
   var settings = {name: 'performed_by', id: 'performedBy_id', data: animals.allOwners, initValue: 'Select One', required: 'true'};
   var ownersCombo = Common.generateCombo(settings);
   $('#performedBy_pl').html(ownersCombo);

   // populate the exit types
   var settings = {name: 'sub_events_by', id: 'sub_events_id', data: animals.allSubEvents, initValue: 'Select One', required: 'true'};
   var subEventsCombo = Common.generateCombo(settings);
   $('#exit_type_pl').html(subEventsCombo);
   $('#sub_events_id').on('change', animals.subEventChanged);

   // hide all the sub types.... this is kind of a hack to ensure the divs are displayed well on selecting an event
   $('#exitTypeId').css({'display': 'none'});
   $('#eventValuePlaceId').css({'display': 'none'});
   $('#pmReportPlaceId').css({'display': 'none'});

   // if we are having an exit type, specify the type of exit
   if(eventName === animals.exitVariable){
      $('#exitTypeId').css({'display': 'block'});
   }
   else if(eventName === animals.pmReportVariable){
      $('#pmReportPlaceId').css({'display': 'block'});
      $('[name=pm_report]').on('change', function(event){ animals.pmReport = event.target.files; });
   }
   else if(animals.valueEvents.indexOf(eventName) !== -1){
      $('#eventValuePlaceId').css({'display': 'block'});
   }
};

/**
 * Create the necessary placeholders for the different types of sub events
 *
 * @returns {undefined}
 */
Animals.prototype.subEventChanged = function(){
   var sub_event = $('#sub_events_id option:selected').text();

//   if(sub_event === animals.pmReportVariable){
//      // create a place for uploading a PM report
//      var pmReport = "<div class='control-group' id='pm_report'>\
//         <label class='control-label' for='event_date'>PM Report&nbsp;&nbsp;<img class='mandatory' src='images/mandatory.gif' alt='Required' /></label>\n\
//         <div id='pdf_cont' class='animal_input controls'><input type='file' name='pm_report' /></div>\n\
//      </div>";
//      $('#exitTypeId').after(pmReport);
//      $('[name=pm_report]').on('change', function(event){ animals.pmReport = event.target.files; });
//   }
//   else{
//      // check if the pm report option is active and deactivate it
//      if($('#pm_report').length === 1){
//         $('#pm_report').remove();
//      }
//   }
};

/**
 * Organises the locations where the animals are kept for easier manipulation
 * @returns {void}
 */
Animals.prototype.locationOrganiser = function(){
   // organise the animal locations by traversing thru level2 object and getting the locations
   var allLevels = {};
   $.each(animals.byLocations.level2, function(level1, that){
      if(animals.includeTopLevels === true){
         allLevels[Object.keys(allLevels).length] = {id: level1, name: level1};
      }
      $.each(that, function(i, sublevel){
         allLevels[Object.keys(allLevels).length] = {id: sublevel.id, name: level1+ ' >> ' +sublevel.name};
      });
   });
   animals.allLevels = allLevels;
};

Animals.prototype.showAnimalDetails = function(that){
   // get the animal details for this animal
   if(animals.info[that.currentTarget.id] === undefined){
      $.ajax({
          type:"POST", url: "mod_ajax.php?page=farm_animals&do=inventory", async: false, dataType:'json', data: {action: 'info', animal_id: that.currentTarget.id},
          success: function (data) {
             if(data.error === true){
               animals.showNotification(data.mssg, 'error');
               return;
             }
             else{
               animals.info[that.currentTarget.id] = data.data;
             }
         }
      });
   }

   var curAnimal = animals.info[that.currentTarget.id];
   var infoContent = '<div data-key="0" role="row" id="row0dataTable">\n\
		<div style="width: 100%; height: 100%;">\n\
			<div style="float: left; width: 50%;">\n\
				<div style="margin: 10px;"><b>Animal ID:</b>'+ curAnimal.animal_id +'</div>\n\
				<div style="margin: 10px;"><b>Breed:</b> '+ curAnimal.breed +'</div>\n\
				<div style="margin: 10px;"><b>Sex:</b> '+ curAnimal.sex +'</div>\n\
				<div style="margin: 10px;"><b>Origin:</b> '+ curAnimal.origin +'</div>\n\
				<div style="margin: 10px;"><b>Dob:</b> '+ curAnimal.dob +'</div>\n\
			</div>\
			<div style="float: left; width: 50%;">\n\
				<div style="margin: 10px;"><b>Sire:</b> '+ curAnimal.sire +'</div>\n\
				<div style="margin: 10px;"><b>Dam:</b> '+ curAnimal.dam +'</div>\n\
				<div style="margin: 10px;"><b>Status:</b> '+ curAnimal.status +'</div>\n\
				<div style="margin: 10px;"><b>Location:</b> '+ curAnimal.location +'</div>\n\
			</div><br /><br />\
			<div style="float:none; margin: 2px auto; width: 99%;">\n\
				<div style="margin: 10px;"><b>Experiment Details:</b></div>\n\
				<div style="margin: 10px;"><b>Experiment Name:</b> '+ curAnimal.exp_name +'</div>\n\
				<div style="margin: 10px;"><b>IACUC No:</b> '+ curAnimal.iacuc +'</div>\n\
				<div style="margin: 10px;"><b>Start Date:</b> '+ curAnimal.exp_startdate +'</div>\n\
				<div style="margin: 10px;"><b>End Date:</b> '+ curAnimal.exp_enddate +'</div>\n\
				<div style="margin: 10px;"><b>Experiment Comments:</b> '+ curAnimal.exp_comments +'</div>\n\
			</div>\
		</div>\
</div>';

   // now lets show the animal details
   if($('#animal_info').jqxWindow('isOpen') === undefined){
      $('#animal_info').jqxWindow({
         showCollapseButton: true, maxHeight: 400, maxWidth: 700, minHeight: 200, minWidth: 200, height: 350, width: 500,
         initContent: function () {
            $('#tab .info').html(infoContent);
            $('#tab .others').html('Not defined yet');
            animals.showAnimalImages(curAnimal.imageList);
            $('#tab').jqxTabs({ height: '100%', width:  '100%' });
            $('#animal_info').jqxWindow('open');
            $('#animal_info').jqxWindow('focus');
         }
      });
   }
   else{
      $('#tab .info').html(infoContent);
      $('#tab .others').html('Not defined yet');
      animals.showAnimalImages(curAnimal.imageList);
      $('#animal_info').jqxWindow('open');
      $('#animal_info').jqxWindow('focus');
   }
   $('#animal_info').jqxWindow('setTitle', curAnimal.animal_id +' information');
};

/**
 * Handle when the tab in the animal details window are clicked
 *
 * @param {type} event
 * @returns {undefined}
 */
Animals.prototype.animalDetailsTabClicked = function(tabIndex, animalId){
   var tabTitle = $('#tab').jqxTabs('getTitleAt', tabIndex);

   if(tabTitle === 'Picture'){
      // get the thumbnails of this animal...

   }
};

Animals.prototype.showAnimalImages = function(imageList){
   // create the links for the thumbnails
   var placeholder = "Images from this animal<br /><ul id='gallery'>";
   $.each(imageList, function (i, that) {
      placeholder += "<li data-src='/farmdb_images/" + that + "'> <img src='/farmdb_images/thumbs/" + that + "' /> </li>";
   });
   placeholder += "</ul>";

   $('#tab .pic').html(placeholder);
   $("#gallery").lightGallery({mode: 'slide'});
};

Animals.prototype.linkrenderer = function (row, column, value) {
   var html = "<a href='javascript:;' id='"+ value +"' class='anim_id_href'>"+ value +"</a>";;
   return html;
};

Animals.prototype.initiateImageUploads = function(){
   // create the placeholder for uploading the images
   $('#upload').jqxFileUpload({
      browseTemplate: 'success', uploadTemplate: 'primary',  cancelTemplate: 'danger', width: 300,
      uploadUrl: 'mod_ajax.php?page=farm_animals&do=images&action=save', fileInputName: 'images_2_upload[]',
      accept: 'image/*'
   });

   // process the response from the server
   $('#upload').on('uploadEnd', function (event) {
      var args = event.args;
      var fileName = args.file;
      var response = JSON.parse(args.response);
      var errorType = (response.error) ? 'error' : 'success';
      animals.showNotification('<b>'+ fileName + '</b>: ' + response.mssg, errorType);
   });
};

Animals.prototype.initiateQuickEventsGrid = function(){
   // create the source for the grid
   var source = {
      datatype: 'json', datafields: [ {name: 'animal_id'}, {name: 'id'}, {name: 'breed'}, {name: 'species'}, {name: 'sex'}, {name: 'owner'},
         {name: 'experiment'}, {name: 'location'}, {name: 'weight', type: 'number'}, {name: 'temp', type: 'number'}],
         id: 'id', root: 'data', async: false, type: 'POST', data: {action: 'quick_events_list', showAll: this.showAll}, url: 'mod_ajax.php?page=farm_animals&do=events'
     };
     var animalsAdapter = new $.jqx.dataAdapter(source);
   // initialize jqxGrid
     if($('#quick_events :regex(class, jqx\-grid)').length === 0){
        $("#quick_events").jqxGrid({
            width: 917,
            source: source,
            pageable: true,
            autoheight: true,
            sortable: true,
            showfilterrow: false,
            autoshowfiltericon: true,
            showstatusbar: true,
            renderstatusbar: animals.animalGridStatusBar,
            filterable: true,
            altrows: true,
            touchmode: false,
            editable: true,
            selectionmode: 'singlecell',
            editmode: 'dblclick',
            pagesize: 20,
            pagesizeoptions: ['20', '50', '100'],
            rowdetails: true,
            columns: [
              { datafield: 'id', hidden: true },
              { text: 'Animal ID', datafield: 'animal_id', width: 95 },
              { text: 'Species', datafield: 'species', width: 60 },
              { text: 'Sex', datafield: 'sex', width: 50 },
              { text: 'Breed', datafield: 'breed', width: 110 },
              { text: 'Current Owner', datafield: 'owner', width: 110 },
              { text: 'Experiment', datafield: 'experiment', width: 130 },
              { text: 'Location', datafield: 'location', width: 150 },
              { text: 'Cur Weight', datafield: 'weight', width: 100, editable: true, cellsrenderer: function(r,c,v,d,cp,rd){ return (v === '') ? '' : v +" Kg"; } },
              { text: 'Cur Temp', datafield: 'temp', width: 100, editable: true }
            ]
        });

        // bind the cell end edit action to a function
        $("#quick_events").on('cellendedit', function (event) {
            var args = event.args;
            animals.saveCellChanges(args.row.id, args.datafield, args.value);
        });

     }
     else{
        $("#quick_events").jqxGrid({source: animalsAdapter});
     }
};

Animals.prototype.saveCellChanges = function(animal_id, dataType, value){
   // send the data to the server
   animals.showNotification('Saving the changes', 'mail');
   $.ajax({
       type:"POST", url: "mod_ajax.php?page=farm_animals&do=events", dataType:'json', data: {action: 'quick_events_save', animal_id: animal_id, type: dataType, value: value},
       success: function (data) {
          if(data.error === true){
            animals.showNotification(data.mssg, 'error');
            return;
          }
          else{
             animals.showNotification(data.mssg, 'success');
          }
      }
   });
};

// add a trim function
if (typeof(String.prototype.trim) === "undefined") {
   String.prototype.trim = function() {
      return String(this).replace(/^\s+|\s+$/g, '');
   };
}