/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
var TrayStorage = {
   
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
   
   validateInsertInput: function(){
      if (typeof(String.prototype.trim) === "undefined") {
         String.prototype.trim = function()
         {
            return String(this).replace(/^\s+|\s+$/g, '');
         };
      }
      
      //trim spaces from inputs 
      $("#tray_label").val($("#tray_label").val().trim());
      $("#features").val($("#features").val().trim());
      $("#sample_types").val($("#sample_types").val().trim());
      //$("#sampling_loc").val($("#sampling_loc").val().trim());
      
      if($("#tray_label").val() === ""){
         Notification.show({create:true, hide:true, updateText:false, text:'Please enter the label no the tray', error:true});
         $("#tray_label").focus();
         return false;
      }
      if($("#tray_size").val() === ""){
         Notification.show({create:true, hide:true, updateText:false, text:'Please enter the tray size', error:true});
         $("#tray_size").focus();
         return false;
      }
      if($("#sample_types").val().length > 20){
         Notification.show({create:true, hide:true, updateText:false, text:'Sample type should not be more than 20 characters', error:true});
         $("#sample_types").focus();
         return false;
      }
      if($("#status").val() === ""){
         Notification.show({create:true, hide:true, updateText:false, text:'Please select the tray\'s status', error:true});
         $("#status").focus();
         return false;
      }
      
      return TrayStorage.validateTankInformation();
   },
   
   validateRemoveInput: function(){
      var result = TankStorage.validateTankInformation();//Put this first because tank information appears before the rest of the form
      
      if (typeof(String.prototype.trim) === "undefined") {
         String.prototype.trim = function()
         {
            return String(this).replace(/^\s+|\s+$/g, '');
         };
      }
      
      $("#for_who").val($("for_who").val().trim());
      $("#purpose").val($("purpose").val().trim());
      $("#analysis_type").val($("analysis_type").val().trim());
      if($('#for_who').val() === ""){
         Notification.show({create:true, hide:true, updateText:false, text:'Please enter the name of the person the tray is being removed for', error:true});
         $("#for_who").focus();
         return false;
      }
      else if($("#for_who").val().split(/\s/)){//at least two names 
         Notification.show({create:true, hide:true, updateText:false, text:'Please enter at least two names', error:true});
         $("#for_who").focus();
         return false;
      }
      
      if($("#purpose").val() === ""){
         Notification.show({create:true, hide:true, updateText:false, text:'Please enter the purpose of the tray', error:true});
         $("#purpose").focus();
         return false;
      }
      if($("#analysis_type_div").is(":visible") && $("#analysis_type").val() === ""){
         Notification.show({create:true, hide:true, updateText:false, text:'Please select the tray\'s status', error:true});
         $("#analysis_type").focus();
         return false;
      }
      
      return result;
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
   },
   
   /**
    * This function fetches data from the database and inserts this data into cascading selects that cascade as:
    *    
    *    tank -> sector -> rack -> position
    * 
    * Cascading logic added. When user selects certain tank, only sectors in that tank will be shown as options in the sector select
    * 
    * @param {Boolean} forInsertion Set to true if you want to display available tray positions
    * @returns {undefined}
    */
   loadTankData: function(forInsertion){
      var jsonText = $.ajax({
         type: "GET",
         url: "mod_ajax.php?page=tray_storage&do=ajax&action=get_tank_details",
         async: false
      }).responseText;
      
      var json = jQuery.parseJSON(jsonText);
      var tanks = json.data;
      for(var tankIndex = 0; tankIndex < tanks.length; tankIndex++){
         $("#tank").append($("<option></option>")
                 .attr("value", tanks[tankIndex].TankID)
                 .text("Tank "+tanks[tankIndex].TankID));
      }
      
      //populate sector select
      $("#tank").change(function(){//available sectors in tank to sector select
         if($("#tank").val() !== ""){
            $('#sector').prop('disabled', false);
            $('#sector').find('option').remove()
                    .end()
                    .append('<option value=""></option>')//append a null option
                        .val('');
            var tankID = parseInt($("#tank").val());
            var sectors = tanks[TrayStorage.getTankIndex(tanks, tankID)].sectors;
            for(var sectorIndex = 0; sectorIndex < sectors.length; sectorIndex++){
               $("#sector").append($("<option></option>")
                       .attr("value", sectors[sectorIndex].id)
                       .text("Sector "+sectors[sectorIndex].label));
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
            var sectors = tanks[TrayStorage.getTankIndex(tanks, tankID)].sectors;
            var sectorID = parseInt($("#sector").val());
            var racks = sectors[TrayStorage.getSectorIndex(sectors, sectorID)].racks;
            for(var rackIndex = 0; rackIndex < racks.length; rackIndex++){
               $("#rack").append($("<option></option>")
                       .attr("value", racks[rackIndex].id)
                       .text("Rack "+racks[rackIndex].label));
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
            var sectors = tanks[TrayStorage.getTankIndex(tanks, tankID)].sectors;
            var sectorID = parseInt($("#sector").val());
            var racks = sectors[TrayStorage.getSectorIndex(sectors, sectorID)].racks;
            var rackID = parseInt($("#rack").val());
            var rack = racks[TrayStorage.getRackIndex(racks, rackID)];
            console.log(rack);
            var availablePos = new Array();
            
            var trays = rack.boxes;
            //if purpose is for inserting new tray into db then only display rack positions that are empty
            if(forInsertion){
               
               //populate available positions array with a 1 index list depending on the size of the rack
               for(var currPos = 1; currPos <= rack.size; currPos++){
                  availablePos.push(currPos);
               }
               
               //iterate through all boxes/trays in rack and delete their positions from the available positon array
               
               for(var trayIndex = 0; trayIndex < trays.length; trayIndex++){
                  var index = availablePos.indexOf(parseInt(trays[trayIndex].rack_position));
                  if(index !== -1){//check if position was already in the available positions array (It should have been)
                     availablePos.splice(index, 1);
                  }
               }
            }
            
            //if purpose is for deleting or removing a tray then show positions that have things in them
            else{
               //iterate through all boxes/trays in rack and add their positions to the available positon array
               for(var trayIndex = 0; trayIndex < trays.length; trayIndex++){
                  availablePos.push(parseInt(trays[trayIndex].rack_position));
               }
            }
            
            //add available positions to select
            for(var availPIndex = 0; availPIndex < availablePos.length; availPIndex++){
               $("#position").append($("<option></option>")
                       .attr("value", availablePos[availPIndex])
                       .text("Positon " + availablePos[availPIndex]));
               console.log("appended "+availablePos[availPIndex]);
            }
         }
         else {//disable child selects
            $('#position').find('option').remove()
                    .end()
                    .append('<option value=""></option>')//append a null option
                        .val('');
            $('#position').prop('disabled', 'disabled');
         }
      });
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
         if(parseInt(tanks[tankIndex].TankID) === tankID){
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
   }
};