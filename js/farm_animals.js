

function Animals(sub_module){
   window.farm_animals = this;

   // initialize the main variables
   window.farm_animals.sub_module = sub_module;

   this.serverURL = "./modules/mod_farm_animals.php";
   this.procFormOnServerURL = "mod_ajax.php?page=farm_animals";

   // call the respective function
   if(this.sub_module === 'inventory') this.initiateAnimalsGrid();
   else if(this.sub_module === 'ownership') this.initiateAnimalsOwnersGrid();
};

/**
 * A function to initiate the animals grid
 */
Animals.prototype.initiateAnimalsGrid = function(){
   // create the source for the grid
   var source = {
      datatype: 'json', datafields: [ {name: 'animal_id'}, {name: 'species'}, {name: 'sex'}, {name: 'origin'}, {name: 'dob'},
         {name: 'sire'}, {name: 'dam'}, {name: 'status'}, {name: 'experiment'}],
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
              { text: 'Status', datafield: 'status', width: 100 },
              { text: 'Experiment', datafield: 'experiment', width: 150 }
            ]
        });
     }
     else{
        $("#inventory").jqxGrid({source: animalsAdapter});
     }
};

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
     if($('#ownership :regex(class, jqx\-grid)').length === 0){
        $("#ownership").jqxGrid({
            width: 910,
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
        $("#ownership").jqxGrid({source: ownersAdapter});
     }
};

/**
 * Add a new ownership of an animal
 */
Animals.prototype.addOwnership = function(){
   // get all the animals and all the people who can be owners
   var userData;
   $.ajax({
       type:"POST", url: "mod_ajax.php?page=farm_animals&do=ownership", async: false, dataType:'json', data: {'action': 'list', 'fields': ['owners','animals']},
       success: function (data) {
          if(data.error === true){
             alert(data.mssg);
             $('#animal_id').val('').focus();
             return;
          }
          else{ userData = data; }
      }
   });


   var content = "\
<form id='new_ownership' class='form-horizontal' >\
   <div class='control-group'>\
      <label class='control-label' for='animal'>Animal&nbsp;&nbsp;<img class='mandatory' src='images/mandatory.gif' alt='Required' /></label>\n\
      <div id='animals_pl' class='animal_input controls'></div>\n\
   </div>\n\
\
   <div class='control-group'>\
      <label class='control-label' for='owner'>Owner&nbsp;&nbsp;<img class='mandatory' src='images/mandatory.gif' alt='Required' /></label>\n\
      <div id='owners_pl' class='animal_input controls'></div>\n\
   </div>\n\
\
   <div class='control-group'>\
      <label class='control-label' for='start_date'>Start Date&nbsp;&nbsp;<img class='mandatory' src='images/mandatory.gif' alt='Required' /></label>\n\
      <div id='start_date_pl' class='animal_input controls'><input type='text' name='start_date' id='start_date' placeholder='Start Date' class='input-medium form-control' required=true /></div>\n\
   </div>\n\
\
   <div class='control-group'>\
      <label class='control-label' for='end_date'>End Date&nbsp;&nbsp;<img class='mandatory' src='images/mandatory.gif' alt='Required' /></label>\n\
      <div id='end_date_pl' class='animal_input controls'><input type='text' name='end_date' id='end_date' placeholder='End Date' class='input-medium form-control' required=true /></div>\n\
   </div>\n\
\
   <div class='control-group'>\
      <label class='control-label' for='comments'>Comments</label>\n\
      <div id='comments_pl' class='animal_input controls'><textarea name='comments' id='comments' class=' form-control'></textarea></div>\n\
   </div>\n\
</form>\
";
   // create a popup that will add a new ownership of the animal
   CustomMssgBox.createMessageBox({ okText: 'Save', cancelText: 'Cancel', callBack: animals.saveOwnership, cancelButton: true, customTitle: 'New Ownership', message: content, width: '500px' });

   // create the date pickers
   datePickerController.createDatePicker({ formElements:{'start_date': '%d-%m-%Y'}, fillGrid: true, constraintSelection:false, maxDate: 0 });
   datePickerController.createDatePicker({ formElements:{'end_date': '%d-%m-%Y'}, fillGrid: true, constraintSelection:false, maxDate: 0 });

   // populate the animal and owner fields with the respective drop downs
   var settings = {name: 'animals', id: 'animal_id', data: userData.animals, initValue: 'Select One', required: 'true'};
   var animalsCombo = Common.generateCombo(settings);
   $('#animals_pl').html(animalsCombo);
   var settings = {name: 'owners', id: 'owner_id', data: userData.owners, initValue: 'Select One', required: 'true'};
   var ownersCombo = Common.generateCombo(settings);
   $('#owners_pl').html(ownersCombo);
};

/**
 * Save the new ownership
 *
 * @param   object   sender   An object of the popup where we specified the details
 * @param   bool     value    The value of the button clicked
 * @param   array    vars     Optional variables which might have been passed along
 * @returns {undefined}
 */
Animals.prototype.saveOwnership = function(sender, value, vars){
   if(value === false){
      sender.close();
      return;
   }

   // ok so we want to save the new ownership
   var formInfo = $('#new_ownership').formToArray(true), missingInfo = false;
   $.each(formInfo, function(){
      if(this.required && this.value === '' || this.required && this.value === 0 && this.type === 'select1'){
         // we have a mandatory field with no data...
         $('[name='+this.name+']').css({'aria-invalid': 'invalid'});
         missingInfo = true;
      }
   });
   if(missingInfo){
      alert("Please fill in the missing mandatory information.");
      return;
   }

   // ok, so we good, lets save the new ownership
   var formSerialized = $('#new_ownership').formSerialize();
   $.ajax({
       type:"POST", url: "mod_ajax.php?page=farm_animals&do=ownership", async: false, dataType:'json', data: formSerialized + '&action=save',
       success: function (data) {
          if(data.error === true){
              alert(data.mssg);
              $('#animal_id').focus();
              return;
          }
          else{
             sender.close();
             alert('The animal has been saved successfully');
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