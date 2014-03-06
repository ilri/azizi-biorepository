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
   submitNewRequest : function(){
      if(this.validateInput() === true){
         return true;
      }
      return false;
   },
   
   validateInput: function(){
      if (typeof(String.prototype.trim) === "undefined") {
         String.prototype.trim = function()
         {
            return String(this).replace(/^\s+|\s+$/g, '');
         };
      }
      $("#tray_label").val($("#tray_label").val().trim());
      
      if($("#tray_label").val() === ""){
         Notification.show({create:true, hide:true, updateText:false, text:'Please enter the label no the tray', error:true});
         $("#tray_label").focus();
         return false;
      }
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
      if($("#status").val() === ""){
         Notification.show({create:true, hide:true, updateText:false, text:'Please select the tray\'s status', error:true});
         $("#status").focus();
         return false;
      }
      
      return true;
   },
   
   loadTankData: function(){
      var jsonText = $.ajax({
         type: "GET",
         url: "mod_ajax.php?page=tray_storage&do=ajax&action=get_tank_details",
         async: false
      }).responseText;
      console.log(jsonText);
      
      var json = jQuery.parseJSON(jsonText);
      var tanks = json.data;
      for(var tankIndex = 0; tankIndex < tanks.length; tankIndex++){
         $("#tank").append($("<option></option>")
                 .attr("value", tanks[tankIndex].TankID)
                 .text("Tank "+tanks[tankIndex].TankID));
      }
      
      //populate sector select
      $("#tank").change(function(){//available sectors in tank to sector select
         console.log(tanks);
         if($("#tank").val() !== ""){
            $('#sector').find('option').remove()
                    .end()
                    .append('<option value=""></option>')//append a null option
                        .val('');
            var tankID = parseInt($("#tank").val());
            var sectors = tanks[tankID].sectors;
            for(var sectorIndex = 0; sectorIndex < sectors.length; sectorIndex++){
               $("#sector").append($("<option></option>")
                       .attr("value", sectors[sectorIndex].id)
                       .text("Sector "+sectors[sectorIndex].label));
            }
         }
      });
      
      //populate rack select
      $("#sector").change(function(){
         if($("#tank").val() !== "" && $("#sector").val() !== ""){
            $('#rack').find('option').remove()
                    .end()
                    .append('<option value=""></option>')//append a null option
                        .val('');
            
            var tankID = parseInt($("#tank").val());
            var sectorID = parseInt($("#sector").val());
            var racks = tanks[tankID][sectorID].racks;
            for(var rackIndex = 0; rackIndex < racks.length; rackIndex++){
               $("#rack").append($("<option></option>")
                       .attr("value", racks[rackIndex].id)
                       .text("Rack/Tower "+racks[rackIndex].label));
            }
         }
      });
      
      //populate position select
      $("#rack").change(function(){
         if($("#tank").val() !== "" && $("#sector").val() !== "" && $("#rack").val() !== ""){
            $('#rack').find('option').remove()
                    .end()
                    .append('<option value=""></option>')//append a null option
                        .val('');
            
            //find all the empty positions in selected rack
            //Do this by determining which slots are empty depending on rack size
            var tankID = parseInt($("#tank").val());
            var sectorID = parseInt($("#sector").val());
            var rackID = parseInt($("#rack").val());
            var rack = tanks[tankID][sectorID].racks[rackID];
            var availablePos = new Array();
            
            //populate available positions array with a 1 index list depending on the size of the rack
            for(var currPos = 1; currPos <= rack.size; currPos++){
               availablePos.push(currPos);
            }
            
            //iterate through all boxes/trays in rack and delete their positions from the available positon array
            var trays = rack.boxes;
            for(var trayIndex = 0; trayIndex < trays.length; trayIndex++){
               var index = availablePos.indexOf(trays[trayIndex].rack_position);
               if(index !== -1){//check if position was already in the available positions array (It should have been)
                  availablePos.splice(index, 1);
               }
            }
            
            //add available positions to select
            for(var availPIndex = 0; availablePos < availablePos.length; availPIndex++){
               $("#positon").append($("<option></option>")
                       .attr("value", availablePos[availPIndex])
                       .text("Positon " + availablePos[availPIndex]));
            }
         }
      });
   }
};