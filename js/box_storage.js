/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
var BoxStorage = {
   
   /**
    * 
    * @returns {Boolean}
    */
   submitInsertRequest : function(){
      if(this.validateInsertInput() === true){
         return true;
      }
      return false;
   },
   
   submitRemoveRequest: function(){
      if(this.validateRemoveInput()){
         return true;
      }
      return false;
   },
   
   submitReturnRequest: function(){
      if(this.validateReturnInput()){
         var formData = {return_comment: $("#return_comment").val(), remove_id: $("#remove_id").val()};
         
         var responseText = $.ajax({
            url: "mod_ajax.php?page=box_storage&do=ajax&action=submit_return_request",
            type: "POST",
            data: formData,
            async: false
         }).responseText;
         var responseJson = jQuery.parseJSON(responseText);
         console.log(responseJson);
         if(responseJson.error_message.length > 0){
            Notification.show({create:true, hide:true, updateText:false, text: responseJson.error_message, error:true});
         }
         
         BoxStorage.setRemovedBoxSuggestions();
      }
      
   },
   
   validateInsertInput: function(){
      if (typeof(String.prototype.trim) === "undefined") {
         String.prototype.trim = function()
         {
            return String(this).replace(/^\s+|\s+$/g, '');
         };
      }
      
      //trim spaces from inputs 
      $("#box_label").val($("#box_label").val().trim());
      $("#features").val($("#features").val().trim());
      $("#sample_types").val($("#sample_types").val().trim());
      //$("#sampling_loc").val($("#sampling_loc").val().trim());
      
      if($("#box_label").val() === ""){
         Notification.show({create:true, hide:true, updateText:false, text:'Please enter the label no the box', error:true});
         $("#box_label").focus();
         return false;
      }
      if($("#box_size").val() === ""){
         Notification.show({create:true, hide:true, updateText:false, text:'Please enter the box size', error:true});
         $("#box_size").focus();
         return false;
      }
      if($("#sample_types").val().length > 20){
         Notification.show({create:true, hide:true, updateText:false, text:'Sample type should not be more than 20 characters', error:true});
         $("#sample_types").focus();
         return false;
      }
      if($("#status").val() === ""){
         Notification.show({create:true, hide:true, updateText:false, text:'Please select the box\'s status', error:true});
         $("#status").focus();
         return false;
      }
      
      return BoxStorage.validateTankInformation();
   },
   
   validateRemoveInput: function(){
      if (typeof(String.prototype.trim) === "undefined") {
         String.prototype.trim = function()
         {
            return String(this).replace(/^\s+|\s+$/g, '');
         };
      }
      
      var result = BoxStorage.validateTankInformation();//Put this first because tank information appears before the rest of the form
      
      if(result === true){
         $("#for_who").val($("#for_who").val().trim());
         $("#analysis_type").val($("#analysis_type").val().trim());
         if($('#for_who').val() === ""){
            Notification.show({create:true, hide:true, updateText:false, text:'Please enter the name of the person the box is being removed for', error:true});
            $("#for_who").focus();
            return false;
         }
         else if($("#for_who").val().split(/\s/).length < 2){//at least two names 
            Notification.show({create:true, hide:true, updateText:false, text:'Please enter at least two names', error:true});
            $("#for_who").focus();
            return false;
         }

         if($("#purpose").val() === ""){
            Notification.show({create:true, hide:true, updateText:false, text:'Please enter the purpose of the box', error:true});
            $("#purpose").focus();
            return false;
         }
         if($("#analysis_type_div").is(":visible") && $("#analysis_type").val() === ""){
            Notification.show({create:true, hide:true, updateText:false, text:'Please select the box\'s status', error:true});
            $("#analysis_type").focus();
            return false;
         }
      }
      else{
         return false;
      }
      
      
      return true;
   },
   
   validateReturnInput: function(){
      if (typeof(String.prototype.trim) === "undefined") {
         String.prototype.trim = function()
         {
            return String(this).replace(/^\s+|\s+$/g, '');
         };
      }
      
      $("#return_comment").val($("#return_comment").val().trim());
      $("#box_label").val($("#box_label").val().trim());
      if($("#box_label").val() === ""){
         Notification.show({create:true, hide:true, updateText:false, text:'Please specify the box label', error:true});
         $("#box_label").focus();
         return false;
      }
      if($("#remove_id").val() === ""){
         Notification.show({create:true, hide:true, updateText:false, text:'The box you specified does not exist or has not been removed from the tanks. Use provided suggestions', error:true});
         $("#box_label").focus();
         return false;
      }
      return true;
   },
   
   validateTankInformation: function(){
      if($("#tank").val() === ""){
         Notification.show({create:true, hide:true, updateText:false, text:'Please select a tank', error:true});
         $("#tank").focus();
         return false;
      }
      if($("#sector").val() === ""){
         Notification.show({create:true, hide:true, updateText:false, text:'Please select a sector', error:true});
         $("#sector").focus();
         return false;
      }
      if($("#rack").val() === ""){
         Notification.show({create:true, hide:true, updateText:false, text:'Please select a rack', error:true});
         $("#rack").focus();
         return false;
      }
      if($("#position").val() === ""){
         Notification.show({create:true, hide:true, updateText:false, text:'Please select a rack position', error:true});
         $("#tank").focus();
         return false;
      }
      return true;
   },
   
   /**
    * This function fetches data from the database and inserts this data into cascading selects that cascade as:
    *    
    *    tank -> sector -> rack -> position
    * 
    * Cascading logic added. When user selects certain tank, only sectors in that tank will be shown as options in the sector select
    * 
    * @param {Boolean} forInsertion Set to true if you want to display available box positions
    * @param {Int} boxesToShow Defaults to 0. Set to 0 if you want to show all boxes, 1 to show boxes still in the tanks and 2 for boxes that have been removed
    * 
    * @returns {undefined}
    */
   loadTankData: function(forInsertion, boxesToShow){
      if(typeof(boxesToShow)==='undefined') boxesToShow = 0;//default boxesToShow to 0 (all boxes)
      
      var json = BoxStorage.getTankData(false);//get tank data but dont cache the data in window.tankData
      var tanks = json.data;
      for(var tankIndex = 0; tankIndex < tanks.length; tankIndex++){
         $("#tank").append($("<option></option>")
                 .attr("value", tanks[tankIndex].id)
                 .text("Tank "+tanks[tankIndex].name));
      }
      
      //populate sector select
      $("#tank").change(function(){//available sectors in tank to sector select
         if($("#tank").val() !== ""){
            $('#sector').prop('disabled', false);//enable sector select
            $('#sector').find('option').remove()
                    .end()
                    .append('<option value=""></option>')//append a null option
                        .val('');
            var tankID = parseInt($("#tank").val());
            var sectors = tanks[BoxStorage.getTankIndex(tanks, tankID)].sectors;
            for(var sectorIndex = 0; sectorIndex < sectors.length; sectorIndex++){
               $("#sector").append($("<option></option>")
                       .attr("value", sectors[sectorIndex].id)
                       .text("Sector "+sectors[sectorIndex].facility));
            }
         }
         else {//disable child selects
            $('#sector').find('option').remove()
                    .end()
                    .append('<option value=""></option>')//append a null option
                        .val('');
            $('#sector').prop('disabled', 'disabled');
            $('#rack').find('option').remove()
                    .end()
                    .append('<option value=""></option>')//append a null option
                        .val('');
            $('#rack').prop('disabled', 'disabled');
            $('#position').find('option').remove()
                    .end()
                    .append('<option value=""></option>')//append a null option
                        .val('');
            $('#position').prop('disabled', 'disabled');
         }
      });
      
      //populate rack select
      $("#sector").change(function(){
         if($("#tank").val() !== "" && $("#sector").val() !== ""){
            $('#rack').prop('disabled', false);
            $('#rack').find('option').remove()
                    .end()
                    .append('<option value=""></option>')//append a null option
                        .val('');
            
            var tankID = parseInt($("#tank").val());
            var sectors = tanks[BoxStorage.getTankIndex(tanks, tankID)].sectors;
            var sectorID = parseInt($("#sector").val());
            var racks = sectors[BoxStorage.getSectorIndex(sectors, sectorID)].racks;
            for(var rackIndex = 0; rackIndex < racks.length; rackIndex++){
               $("#rack").append($("<option></option>")
                       .attr("value", racks[rackIndex].name)
                       .text("Rack "+racks[rackIndex].name));
            }
         }
         else {//disable child selects
            $('#rack').find('option').remove()
                    .end()
                    .append('<option value=""></option>')//append a null option
                        .val('');
            $('#rack').prop('disabled', 'disabled');
            $('#position').find('option').remove()
                    .end()
                    .append('<option value=""></option>')//append a null option
                        .val('');
            $('#position').prop('disabled', 'disabled');
         }
      });
      
      //populate position select
      $("#rack").change(function(){
         if($("#tank").val() !== "" && $("#sector").val() !== "" && $("#rack").val() !== ""){
            $('#position').prop('disabled', false);
            $('#position').find('option').remove()
                    .end()
                    .append('<option value=""></option>')//append a null option
                        .val('');
            
            //find all the empty positions in selected rack
            //Do this by determining which slots are empty depending on rack size
            var tankID = parseInt($("#tank").val());
            var sectors = tanks[BoxStorage.getTankIndex(tanks, tankID)].sectors;
            var sectorID = parseInt($("#sector").val());
            var racks = sectors[BoxStorage.getSectorIndex(sectors, sectorID)].racks;
            var rackID = parseInt($("#rack").val());
            var rack = racks[BoxStorage.getRackIndex(racks, rackID)];
            
            var availablePos = new Array();
            
            var boxes = rack.boxes;
            //if purpose is for inserting new box into db then only display rack positions that are empty
            if(forInsertion){
               
               //populate available positions array with a 1 index list depending on the size of the rack
               for(var currPos = 1; currPos <= rack.size; currPos++){
                  availablePos.push(currPos);
               }
               
               //iterate through all boxes/boxes in rack and delete their positions from the available positon array
               
               for(var boxIndex = 0; boxIndex < boxes.length; boxIndex++){
                  var index = availablePos.indexOf(parseInt(boxes[boxIndex].rack_position));
                  if(index !== -1){//check if position was already in the available positions array (It should have been)
                     availablePos.splice(index, 1);
                  }
               }
            }
            
            //if purpose is for deleting or removing a box then show positions that have things in them
            else{
               //iterate through all boxes/boxes in rack and add their positions to the available positon array
               for(var boxIndex = 0; boxIndex < boxes.length; boxIndex++){
                  if(boxesToShow === 0)//add all boxes
                     availablePos.push(parseInt(boxes[boxIndex].rack_position));
                  
                  else if(boxesToShow === 1){//just show boxes that are still in the tanks
                     
                     //search for an instance of remove that has not been returned
                     var safeToShow = true;
                     var retrieves = boxes[boxIndex].retrieves;
                     for(var removeIndex = 0; removeIndex < retrieves.length; removeIndex++){
                        if(retrieves[removeIndex].date_returned === null){
                           safeToShow = false;
                        }
                     }
                     
                     if(safeToShow)
                        availablePos.push(parseInt(boxes[boxIndex].rack_position));
                  }
                  else if(boxesToShow === 1){//just show boxes that have been removed from tanks
                     
                     //search for an instance of remove that has not been returned
                     var safeToShow = false;
                     var retrieves = boxes[boxIndex].retrieves;
                     for(var removeIndex = 0; removeIndex < retrieves.length; removeIndex++){
                        if(retrieves[removeIndex].date_returned === null){
                           safeToShow = true;
                        }
                     }
                     
                     if(safeToShow)
                        availablePos.push(parseInt(boxes[boxIndex].rack_position));
                  }
               }
            }
            
            //add available positions to select
            for(var availPIndex = 0; availPIndex < availablePos.length; availPIndex++){
               $("#position").append($("<option></option>")
                       .attr("value", availablePos[availPIndex])
                       .text("Positon " + availablePos[availPIndex]));
            }
         }
         else {//disable child selects
            $('#position').find('option').remove()
                    .end()
                    .append('<option value=""></option>')//append a null option
                        .val('');
            $('#position').prop('disabled', 'disabled');
         }
         
         //show box label if purpose if for deleting box information or removing box
         if(forInsertion === false){
            $("#position").change(function(){
               if($("#position").val() !== ""){
                  $("#box_label").val("");
                  var tankID = parseInt($("#tank").val());
                  var sectors = tanks[BoxStorage.getTankIndex(tanks, tankID)].sectors;
                  var sectorID = parseInt($("#sector").val());
                  var racks = sectors[BoxStorage.getSectorIndex(sectors, sectorID)].racks;
                  var rackID = parseInt($("#rack").val());
                  var rack = racks[BoxStorage.getRackIndex(racks, rackID)];
                  
                  //get the box/box in rack that is stored in position
                  var boxes = rack.boxes;
                  var position = parseInt($("#position").val());
                  for(var boxIndex = 0; boxIndex < boxes.length; boxIndex++){
                     if(parseInt(boxes[boxIndex].rack_position) === position){
                        $("#box_label").val(boxes[boxIndex].name);
                     }
                  }
               }
            });
         }
      });
   },
   
   /**
    * This function gets the tank data from the server and caches this data in window.tankData if required to
    * 
    * @param {Boolean} saveInWindow If set to TRUE, this function will cache the tank data as a json object in window.tankData
    * 
    * @returns {JSONObject} Returns a JSONObject with the tank data
    */
   getTankData : function(saveInWindow){
      var jsonText = $.ajax({
         type: "GET",
         url: "mod_ajax.php?page=box_storage&do=ajax&action=get_tank_details",
         async: false
      }).responseText;
      
      console.log(jsonText);
      
      var json = jQuery.parseJSON(jsonText);
      if(saveInWindow){
         window.tankData = json;
      }
      return json;
   },
   
   /**
    * This function is used to determine the index of a tank in the tank array using its id
    * 
    * @param {Array} tanks The tank array
    * @param {Number} tankID The id of the tank (As from the database)
    * @returns {Number} Returns the index of a tank in the tank array or -1 if no tank in the array has the provided tankID
    */
   getTankIndex : function(tanks, tankID){
      for(var tankIndex = 0; tankIndex < tanks.length; tankIndex++){
         if(parseInt(tanks[tankIndex].id) === tankID){
            return tankIndex;
         }
      }
      return -1;
   },
   
   /**
    * This function is used to determine the index of a tank sector in a sector array using its id
    * @param {Array} sectors An array of sectors obtained from the tank array
    * @param {Number} sectorID The id of the sector as from the database
    * @returns {Number} Returs the index of the sector in the sector array provided or -1 if no sector in the sector array has the provided sectorID
    */
   getSectorIndex : function(sectors, sectorID){
      for(var sectorIndex = 0; sectorIndex < sectors.length; sectorIndex++){
         if(parseInt(sectors[sectorIndex].id) === sectorID){
            return sectorIndex;
         }
      }
      return -1;
   },
   
   /**
    * This function is used to determine the index of a rack in a rack array using its id
    * @param {Array} racks An array of racks obtained from a sector array
    * @param {Number} rackID The id of the rack as from the database
    * @returns {Number} Returs the index of the rack in the rack array provided or -1 if no rack in the rack array has the provided rackID
    */
   getRackIndex : function(racks, rackID){
      for(var rackIndex = 0; rackIndex < racks.length; rackIndex++){
         if(parseInt(racks[rackIndex].id) === rackID){
            return rackIndex;
         }
      }
      return -1;
   },
   
   setRemovedBoxSuggestions : function(){
      BoxStorage.resetReturnInput(true);
      var tankData = BoxStorage.getTankData(true);//cache fetched tank data into document.tankData so that you wont need to fetch it again
      
      //get all boxes that have been removed
      var suggestions = new Array();
      var tanks = tankData.data;
      for(var tankIndex = 0; tankIndex < tanks.length; tankIndex++){//iterate through all the tanks
         var sectors = tanks[tankIndex].sectors;
         for(var sectorIndex = 0; sectorIndex < sectors.length; sectorIndex++){//iterate through all the sectors
            var racks = sectors[sectorIndex].racks;
            for(var rackIndex = 0; rackIndex < racks.length; rackIndex++){//iterate through all the racks
               var boxes = racks[rackIndex].boxes;
               for(var boxIndex = 0; boxIndex < boxes.length; boxIndex++){//iterate through all the boxes
                  var removes = boxes[boxIndex].removes;
                  //get all the removes that dont have returns, should be a maximux of one
                  for(var removeIndex = 0; removeIndex < removes.length; removeIndex++){
                     if(typeof(removes[removeIndex].date_returned) === 'undefined' || removes[removeIndex].date_returned === null){
                        var keyValue = {value: boxes[boxIndex].name, key: tankIndex+'-'+sectorIndex+'-'+rackIndex+'-'+boxIndex+'-'+removeIndex};
                        suggestions.push(keyValue);
                        break;//you should only have one remove without a return date associated with a box/box
                     }
                  }
               }
            }
         }
      }
      
      $("#box_label").autocomplete({
         source: suggestions,
         minLength: 1,
         select: function(event, ui) {
            var key = ui.item.key;
            
            //split key to get respective indexes for tank, sector, rack, box and remove
            //key looks something like: tankIndex-sectorIndex-rackIndex-boxIndex-removeIndex
            var parentIndexes = key.split("-");
            
            //convert all the parent indexes to integers
            for(var i = 0; i<parentIndexes.length; i++){
               parentIndexes[i] = parseInt(parentIndexes[i]);
            }
            if(parentIndexes.length === 5){
               //set values of tank position inputs
               $("#tank").val(tanks[parentIndexes[0]].TankID);
               
               var sector = tanks[parentIndexes[0]].sectors[parentIndexes[1]];
               $("#sector").val(sector.label);
               
               var rack = sector.racks[parentIndexes[2]];
               $("#rack").val(rack.label);
               
               var box = rack.boxes[parentIndexes[3]];
               $("#position").val(box.rack_position);
               
               var remove = box.removes[parentIndexes[4]];
               $("#remove_id").val(remove.id);
            }
         }
      });
   },
   
   resetReturnInput: function(complete){
      $("#tank").val('');
      $("#sector").val('');
      $("#rack").val('');
      $("#position").val('');
      $('#remove_id').val('');
      if(complete){
         $('#box_label').val('');
         $('#return_comment').val('');
      }
   }
};