/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
var Main = {tanks: undefined};

var BoxStorage = {

   /**
    * This function submits Data from the Add a Box page. Fields are first checked before data is submitted to the server.
    * Page refreshes after this function successfully executes
    * 
    * @returns {Boolean}
    */
   submitInsertRequest : function(){
      if(this.validateInsertInput() === true){
         BoxStorage.deleteDataCache("tankData");
         return true;
      }
      return false;
   },

   /**
    * This function renders the JQXGrid in the Add a Box page
    * 
    * @returns {undefined}
    */
   initiateAddBoxesGrid: function(){
      var theme = '';
      var url = "mod_ajax.php?page=box_storage&do=ajax&action=fetch_boxes";
      var source = {
         datatype: 'json',
         datafields: [ 
            {name: 'box_name'},
            {name: 'position'}, 
            {name: 'status'},
            {name: 'date_added'},
            {name: 'added_by'},
            {name: 'total_row_count'}
         ],
         id: 'id',
         root: 'data',
         async: true,
         url: url,
         type: 'POST',
         data: {action: 'fetch_boxes'},
         beforeprocessing: function(data){
            if(data.data.length > 0)
               source.totalrecords = data.data[0].total_row_count;
            else
               source.totalrecords;
         }
      };

      var boxesAdapter = new $.jqx.dataAdapter(source);

      // create jqxgrid
      if($('#tank_boxes :regex(class, jqx\-grid)').length === 0){
         $("#tank_boxes").jqxGrid({
            width: 905,
            height: 400,
            source: boxesAdapter,
            columnsresize: true,
            theme: theme,
            pageable: true,
            virtualmode: true,
            rendergridrows: function() {
               return boxesAdapter.records;
            },
            columns: [
               {text: 'Box Label', datafield: 'box_name', width: 245},
               {text: 'Tank Position', datafield: 'position', width: 250},
               {text: 'Status', datafield: 'status', width: 90},
               {text: 'Date Added', datafield: 'date_added', width: 120},
               {text: 'Added By', datafield: 'added_by', width: 200}
            ]
         });
      }
      else{ $("#tank_boxes").jqxGrid({source: boxesAdapter}); }
   },
   
   /**
    * This function renders the JQXGrid in the Retrieve a Box page
    * 
    * @returns {undefined}
    */
   initiateRetrievedBoxesGrid: function(){
      var theme = '';
      var url = "mod_ajax.php?page=box_storage&do=ajax&action=fetch_removed_boxes";
      var source = {
         datatype: 'json',
         datafields: [ {name: 'box_name'}, {name: 'position'}, {name: 'removed_by'}, {name: 'removed_for'}, {name: 'date_removed'}, {name: 'date_returned'} ],
         id: 'id', 
         root: 'data', 
         async: true,
         url: url,
         type: 'POST',
         data: {action: 'fetch_removed_boxes'},
         beforeprocessing: function (data){
            if(data.data.length > 0)
               source.totalrecords = data.data[0].total_row_count;
            else
               source.totalrecords;
         }
      };

      var boxesAdapter = new $.jqx.dataAdapter(source);

      // create jqxgrid
      if($('#retrieved_boxes :regex(class, jqx\-grid)').length === 0){//element does not exist in DOM
         $("#retrieved_boxes").jqxGrid({
            width: 905,
            height: 400,
            source: boxesAdapter,
            columnsresize: true,
            theme: theme,
            pageable: true,
            virtualmode: true,
            rendergridrows: function() {
               return boxesAdapter.records;
            },
            columns: [
               {text: 'Box Label', datafield: 'box_name', width: 100},
               {text: 'Tank Position', datafield: 'position', width: 205},
               {text: 'Retrieved By', datafield: 'removed_by', width: 165},
               {text: 'For Who', datafield: 'removed_for', width: 165},
               {text: 'Date Retrieved', datafield: 'date_removed', width: 90},
               {text: 'Date Returned', datafield: 'date_returned', width: 180}
            ]
         });
      }
      else{ $("#removed_boxes").jqxGrid({source: boxesAdapter}); }
   },
   
   /**
    * This function renders the JQXGrid in the Return a Box page
    * 
    * @returns {undefined}
    */
   initiateReturnedBoxesGrid: function(){
      var theme = '';
      var url = "mod_ajax.php?page=box_storage&do=ajax&action=fetch_removed_boxes";
      var source = {
         datatype: 'json',
         datafields: [ {name: 'box_name'}, {name: 'position'}, {name: 'returned_by'}, {name: 'removed_by'}, {name: 'date_removed'}, {name: 'date_returned'} ],
         id: 'id',
         root: 'data',
         async: true,
         url: url,
         type: 'POST',
         data: {action: 'fetch_removed_boxes'},
         beforeprocessing: function (data){
            if(data.data.length > 0)
               source.totalrecords = data.data[0].total_row_count;
            else
               source.totalrecords;
         }
      };

      var boxesAdapter = new $.jqx.dataAdapter(source);

      // create jqxgrid
      if($('#returned_boxes :regex(class, jqx\-grid)').length === 0){//element does not exist in DOM
         $("#returned_boxes").jqxGrid({
            width: 905,
            height: 400,
            source: boxesAdapter,
            columnsresize: true,
            theme: theme,
            pageable: true,
            virtualmode: true,
            rendergridrows: function() {
               return boxesAdapter.records;
            },
            columns: [
               {text: 'Box Label', datafield: 'box_name', width: 100},
               {text: 'Tank Position', datafield: 'position', width: 205},
               {text: 'Retrieved By', datafield: 'removed_by', width: 165},
               {text: 'Returned By', datafield: 'returned_by', width: 165},
               {text: 'Date Retrieved', datafield: 'date_removed', width: 90},
               {text: 'Date Returned', datafield: 'date_returned', width: 180}
            ]
         });
      }
      else{ $("#returned_boxes").jqxGrid({source: boxesAdapter}); }
   },
   
   /**
    * This function renders the JQXGrid in the Search for a Box page
    * 
    * @returns {undefined}
    */
   initiateSearchBoxesGrid: function(){
      //console.log("initiateSearchBoxGrid called");
      var theme = '';
      var url = "mod_ajax.php?page=box_storage&do=ajax&action=search_boxes";
      var source = {
         datatype: 'json',
         datafields: [ 
            {name: 'box_name'}, 
            {name: 'position'}, 
            {name: 'status'}, 
            {name: 'date_added'},
            {name: 'box_features'}, 
            {name: 'keeper'},
            {name: 'size'},
            {name: 'box_id'},
            {name: 'no_samples'},
            {name: 'tank_id'},
            {name: 'sector_id'},
            {name: 'rack'},
            {name: 'rack_position'},
            {name: 'total_row_count'},
            {name: 'project'}
         ],//make sure you update these fields when you update those of the update fetch
         id: 'box_id',
         root: 'data',
         async: true,
         url: url, 
         type: 'POST',
         data: {action: 'search_boxes'},
         beforeprocessing: function (data){
            if(data.data.length > 0)
               source.totalrecords = data.data[0].total_row_count;
            else
               source.totalrecords = 0;
         }
      };

      var boxesAdapter = new $.jqx.dataAdapter(source);

      // create jqxgrid
      if($('#searched_boxes :regex(class, jqx\-grid)').length === 0){
         $("#searched_boxes").jqxGrid({
            width: 905,
            height: 400,
            source: boxesAdapter,
            columnsresize: true,
            theme: theme,
            pageable: true,
            virtualmode: true,
            rendergridrows: function() {
               return boxesAdapter.records;
            },
            columns: [
               {text: 'Box Label', datafield: 'box_name', width: 245},
               {text: 'Tank Position', datafield: 'position', width: 320},
               {text: 'Status', datafield: 'status', width: 90},
               {text: 'Number of samples', datafield: 'no_samples', width: 50},
               {text: 'Date Added', datafield: 'date_added', width: 200}
            ]
         });
      }
      else{ $("#searched_boxes").jqxGrid({source: boxesAdapter}); }
      //$("#searched_boxes").jqxGrid('autoresizecolumns');
      
      $("#searched_boxes").bind("pagechanged", function(event){
      });
      /*$("#searched_boxes").bind("pagesizechanged", function(event){
         BoxStorage.searchForBox();
      });*/
      BoxStorage.initSearchSelectedListener();
   },
   
   /**
    * This function updates the JQXGrid on the search page
    * 
    * @param {Array} data Optional post data to be used by the server as a filter for the data
    * 
    * @returns {undefined}
    */
   updateSearchBoxesGrid: function(data){
      //console.log("updateSearchBoxesGrid called");
      data = typeof data !== 'undefined' ? data : {action:"search_boxes"};
      
      var url = "mod_ajax.php?page=box_storage&do=ajax&action=search_boxes";
      var source = {
         datatype: 'json',
         datafields: [ 
            {name: 'box_name'}, 
            {name: 'position'}, 
            {name: 'status'}, 
            {name: 'date_added'},
            {name: 'box_features'}, 
            {name: 'keeper'},
            {name: 'size'},
            {name: 'box_id'},
            {name: 'no_samples'},
            {name: 'tank_id'},
            {name: 'sector_id'},
            {name: 'rack'},
            {name: 'rack_position'},
            {name: 'total_row_count'},
            {name: 'project'}
         ],//make sure you update these fields when you update those for the initial fetch
         id: 'box_id',
         root: 'data',
         async: true,
         url: url, 
         type: 'POST',
         data: data,
         beforeprocessing: function (data){
            if(data.data.length > 0)
               source.totalrecords = data.data[0].total_row_count;
            else
               source.totalrecords = 0;
         }
      };
      var boxesAdapter = new $.jqx.dataAdapter(source);
      $("#searched_boxes").jqxGrid({
         source: boxesAdapter,
         rendergridrows: function() {
            return boxesAdapter.records;
         },
         virtualmode: true
      });
   },
   
   /**
    * This function renders the JQXGrid in the Delete a Box page
    * 
    * @returns {undefined}
    */
   initiateDeletedBoxesGrid: function(){
      var theme = '';
      var url = "mod_ajax.php?page=box_storage&do=ajax&action=fetch_deleted_boxes";
      var source = {
         datatype: 'json',
         datafields: [ {name: 'box_name'}, {name: 'deleted_by'}, {name: 'date_deleted'}, {name: 'delete_comment'} ],
         id: 'id',
         root: 'data',
         async: true,
         url: url,
         type: 'POST',
         data: {action: 'fetch_deleted_boxes'},
         beforeprocessing: function (data){
            if(data.data.length > 0)
               source.totalrecords = data.data[0].total_row_count;
            else
               source.totalrecords;
         }
      };

      var boxesAdapter = new $.jqx.dataAdapter(source);

      // create jqxgrid
      if($('#deleted_boxes :regex(class, jqx\-grid)').length === 0){//element does not exist in DOM
         $("#deleted_boxes").jqxGrid({
            width: 905,
            height: 400,
            source: boxesAdapter,
            columnsresize: true,
            theme: theme,
            pageable: true,
            virtualmode: true,
            rendergridrows: function() {
               return boxesAdapter.records;
            },
            columns: [
               {text: 'Box Label', datafield: 'box_name', width: 150},
               {text: 'Deleted By', datafield: 'deleted_by', width: 230},
               {text: 'Date Deleted', datafield: 'date_deleted', width: 150},
               {text: 'Comment', datafield: 'delete_comment', width: 375}
            ]
         });
      }
      else{ $("#returned_boxes").jqxGrid({source: boxesAdapter}); }
   },

   /**
    * This function submits data from the Retrieve a Box page to the server.
    * Fields are first checked before submit is made.
    * Page should reload after this function executes successfully (Returns TRUE)
    * 
    * @returns {Boolean}   TRUE if everything is fine with the data in the form
    */
   submitRemoveRequest: function(){
      if(this.validateRemoveInput()){
         BoxStorage.deleteDataCache("tankData");
         return true;
      }
      return false;
   },

   /**
    * This function submits data from the Return a Box page to the server.
    * Fields are first checked before submit is made.
    * Page WILL NOT reload after this function executes successfully (Returns TRUE)
    */
   submitReturnRequest: function(){
      if(this.validateReturnInput()){
         BoxStorage.deleteDataCache("tankData");
         var formData = {return_comment: $("#return_comment").val(), remove_id: $("#remove_id").val()};

         var responseText = $.ajax({
            url: "mod_ajax.php?page=box_storage&do=ajax&action=submit_return_request",
            type: "POST",
            data: formData,
            async: false
         }).responseText;
         var responseJson = jQuery.parseJSON(responseText);

         if(responseJson.error_message.length > 0){
            Notification.show({create:true, hide:true, updateText:false, text: responseJson.error_message, error:true});
         }
         else{
            Notification.show({create:true, hide:true, updateText:false, text: "Return successfully recorded", error:false});
            $("#returned_boxes").trigger('reloadGrid');//reload grid
         }

         BoxStorage.setRemovedBoxSuggestions();
      }

   },

   /**
    * This function submits data from the Delete a Box page to the server.
    * Fields are first checked before submit is made.
    * Page WILL NOT RELOAD after this function is executed successfully
    */
   submitDeleteRequest: function(){
      //console.log("submited delete called");
      if(this.validateDeleteInput()){
         BoxStorage.deleteDataCache("tankData");
         //console.log("trying to delete");
         var formData = {delete_comment: $("#delete_comment").val(), box_id: $("#box_id").val()};

         var responseText = $.ajax({
            url: "mod_ajax.php?page=box_storage&do=ajax&action=submit_delete_request",
            type: "POST",
            data: formData,
            async: false
         }).responseText;
         var responseJson = $.parseJSON(responseText);

         if(responseJson.error_message.length > 0){
            Notification.show({create:true, hide:true, updateText:false, text: responseJson.error_message, error:true});
         }
         else{
            Notification.show({create:true, hide:true, updateText:false, text: "Box successfully deleted", error:false});
         }

         BoxStorage.setDeleteBoxSuggestions();
      }
   },

   /**
    * This function validates form data in the Delete a Box page
    * 
    * @returns {Boolean}   TRUE if everything is fine with the data
    */
   validateDeleteInput: function(){
      if (typeof(String.prototype.trim) === "undefined") {
         String.prototype.trim = function()
         {
            return String(this).replace(/^\s+|\s+$/g, '');
         };
      }

      //trim spaces from inputs
      $("#delete_comment").val($("#delete_comment").val().trim());
      $("#box_label").val($("#box_label").val().trim());

      if($("#box_label").val() === ""){
         Notification.show({create:true, hide:true, updateText:false, text:'Please specify the box label', error:true});
         $("#box_label").focus();
         return false;
      }
      if($("#box_id").val() === ""){
         Notification.show({create:true, hide:true, updateText:false, text:'The box you specified does not exist. Use provided suggestions', error:true});
         $("#box_label").focus();
         return false;
      }
      return true;
   },

   /**
    * This function validates form data in the Add a Box page
    * 
    * @returns {Boolean}   TRUE if everything is fine with the data
    */
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
      if($('#owner').is(':disabled')=== false && $("#owner").val() === ""){
         Notification.show({create:true, hide:true, updateText:false, text:'Please specify the owner of the box', error:true});
         $("#owner").focus();
         return false;
      }
      if($("#status").val() === ""){
         Notification.show({create:true, hide:true, updateText:false, text:'Please select the box\'s status', error:true});
         $("#status").focus();
         return false;
      }
      if($("#status").val() === "temporary" && $("#project").val() === ""){
         Notification.show({create:true, hide:true, updateText:false, text:'Please enter the project', error:true});
         $("#project").focus();
         return false;
      }

      return BoxStorage.validateTankInformation();
   },

   /**
    * This function validates form data in the Retrieve a Box page
    * 
    * @returns {Boolean}   TRUE if everything is fine with the data
    */
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

   /**
    * This function validates form data in the Return a Box page
    * 
    * @returns {Boolean}   TRUE if everything is fine with the data
    */
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

   /**
    * This function validates generic tank location information in the Add a box, Retrieve a box, Return a box and Delete a box pages
    * 
    * @returns {Boolean}   TRUE if everything is fine with the tank location data
    */
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
      if($("#rack_spec").length && $("#rack_spec").is(":visible")){//check if rack specify input is in dom and if it's visible
         if($("#rack_spec").val() === ""){
            Notification.show({create:true, hide:true, updateText:false, text:'Please enter the rack name', error:true});
            $("#rack_spec").focus();
            return false;
         }
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
    * @param   {Boolean}   forInsertion Set to true if you want to display available box positions
    * @param   {Int}       boxesToShow Defaults to 0. Set to 0 if you want to show all boxes, 1 to show boxes still in the tanks and 2 for boxes that have been removed
    *
    * @returns {undefined}
    */
   loadTankData: function(forInsertion, boxesToShow){
      $("#tank").prop("disabled", "disabled");
      
      if(typeof(boxesToShow)==='undefined') boxesToShow = 0;//default boxesToShow to 0 (all boxes)
      Main.forInsertion = forInsertion;
      Main.boxesToShow = boxesToShow;
      
      var json = BoxStorage.getTankData(false);//get tank data but dont cache the data in window.tankData
      Main.tanks = json.data;
      for(var tankIndex = 0; tankIndex < Main.tanks.length; tankIndex++){
         $("#tank").append($("<option></option>")
                 .attr("value", Main.tanks[tankIndex].id)
                 .text(Main.tanks[tankIndex].name));
      }
      
      //populate sector select
      $("#tank").change(BoxStorage.populateTankSectors);

      //populate rack select
      $("#sector").change(BoxStorage.populateSectorRacks);

      //populate position select
      $("#rack").change(BoxStorage.populateSelectedPosition);
      
      $("#tank").prop("disabled", false);
   },

   /**
    * Populates the selected position .....
    *
    * @returns {undefined}
    * @todo    Jason, finish the description of this function. In addition, break down this function to smaller sub functions which are easier to read/understand
    */
   populateSelectedPosition: function(){
      //clear all children selects
      $('#position').find('option').remove()
              .end()
              .append('<option value=""></option>')//append a null option
              .val('');
      $('#position').prop('disabled', 'disabled');
      if ($("#tank").val() !== "" && $("#sector").val() !== "" && $("#rack").val() !== "") {
         if ($("#rack").val() === "n£WR@ck") {//user wants to specify rack name
            $("#rack_spec_div").show();
            $("#rack_div").hide();
         }

         $('#position').prop('disabled', false);
         $('#position').find('option').remove()
                 .end()
                 .append('<option value=""></option>')//append a null option
                 .val('');

         //find all the empty positions in selected rack
         //Do this by determining which slots are empty depending on rack size 
         var tankID = parseInt($("#tank").val());
         var tankIndex = BoxStorage.getTankIndex(tankID);
         var sectors = Main.tanks[tankIndex].sectors;
         var sectorID = parseInt($("#sector").val());

         var availablePos = new Array();

         if ($("#rack").val() !== "n£WR@ck") {//if user has selected an existing rack from list
            var racks = sectors[BoxStorage.getSectorIndex(sectors, sectorID)].racks;
            var rackName = $("#rack").val();
            var rack = racks[BoxStorage.getRackIndex(racks, rackName)];


            var boxes = rack.boxes;
            //if purpose is for inserting new box into db then only display rack positions that are empty
            if (Main.forInsertion) {

               //populate available positions array with a 1 index list depending on the size of the rack
               for (var currPos = 1; currPos <= rack.size; currPos++) {
                  availablePos.push(currPos);
               }

               //iterate through all boxes/boxes in rack and delete their positions from the available positon array
               for (var boxIndex = 0; boxIndex < boxes.length; boxIndex++) {
                  var index = availablePos.indexOf(parseInt(boxes[boxIndex].rack_position));
                  if (index !== -1) {//check if position was already in the available positions array (It should have been)
                     availablePos.splice(index, 1);
                  }
               }
            }

            //if purpose is for deleting or removing a box then show positions that have things in them
            else {
               //iterate through all boxes/boxes in rack and add their positions to the available positon array
               for (var boxIndex = 0; boxIndex < boxes.length; boxIndex++) {
                  if (Main.boxesToShow === 0)//add all boxes
                     availablePos.push(parseInt(boxes[boxIndex].rack_position));

                  else if (Main.boxesToShow === 1) {//just show boxes that are still in the tanks

                     //search for an instance of remove that has not been returned
                     var safeToShow = true;
                     var retrieves = boxes[boxIndex].retrieves;
                     for (var removeIndex = 0; removeIndex < retrieves.length; removeIndex++) {
                        if (retrieves[removeIndex].date_returned === null) {
                           safeToShow = false;
                        }
                     }

                     if (safeToShow)
                        availablePos.push(parseInt(boxes[boxIndex].rack_position));
                  }
                  else if (Main.boxesToShow === 1) {//just show boxes that have been removed from tanks

                     //search for an instance of remove that has not been returned
                     var safeToShow = false;
                     var retrieves = boxes[boxIndex].retrieves;
                     for (var removeIndex = 0; removeIndex < retrieves.length; removeIndex++) {
                        if (retrieves[removeIndex].date_returned === null) {
                           safeToShow = true;
                        }
                     }

                     if (safeToShow)
                        availablePos.push(parseInt(boxes[boxIndex].rack_position));
                  }
               }
            }
         }
         else {//user wants to specify the rack manually
            //get the allowed number of tray positions in this sector
            var maxPositions = parseInt(sectors[BoxStorage.getSectorIndex(sectors, sectorID)].rack_pos);
            for (var currPos = 1; currPos <= maxPositions; currPos++) {
               availablePos.push(currPos);
            }
         }

         //add available positions to select
         for (var availPIndex = 0; availPIndex < availablePos.length; availPIndex++) {
            $("#position").append($("<option></option>")
               .attr("value", availablePos[availPIndex])
               .text("Positon " + availablePos[availPIndex]));
         }
      }

      //show box label if purpose if for deleting box information or removing box
      if (Main.forInsertion === false) {
         $("#position").change(function() {
            $("#box_label").val("");
            $("#box_id").val("");
            if ($("#position").val() !== "") {
               var tankID = parseInt($("#tank").val());
               var tankIndex = BoxStorage.getTankIndex(tankID);
               var sectors = Main.tanks[tankIndex].sectors;
               var sectorID = parseInt($("#sector").val());
               var racks = sectors[BoxStorage.getSectorIndex(sectors, sectorID)].racks;
               var rackName = $("#rack").val();
               var rack = racks[BoxStorage.getRackIndex(racks, rackName)];

               //get the box/box in rack that is stored in position
               var boxes = rack.boxes;
               var position = parseInt($("#position").val());
               for (var boxIndex = 0; boxIndex < boxes.length; boxIndex++) {
                  if (parseInt(boxes[boxIndex].rack_position) === position) {
                     $("#box_label").val(boxes[boxIndex].box_name);
                     $("#box_id").val(boxes[boxIndex].box_id);
                  }
               }
            }
         });
      }
   },

   /**
    * Populates the sector dropdown with the sectors of the selected tank
    *
    * @returns {undefined}
    */
   populateTankSectors: function() {
      //available sectors in tank to sector select
      //clear all children selects
      $('#sector').find('option').remove().end()
              .append('<option value=""></option>')//append a null option
              .val('');
      $('#sector').prop('disabled', 'disabled');
      $('#rack').find('option').remove().end()
              .append('<option value=""></option>')//append a null option
              .val('');
      $('#rack').prop('disabled', 'disabled');
      if ($('#rack_div').length) $('#rack_div').show();
      if ($('#rack_spec_div').length) {
         $('#rack_spec_div').hide();
         $('#rack_spec').val("");
      }
      $('#position').find('option').remove().end()
              .append('<option value=""></option>')//append a null option
              .val('');
      $('#position').prop('disabled', 'disabled');
      if ($("#tank").val() !== "") {
         $('#sector').prop('disabled', false);//enable sector select

         var tankID = parseInt($("#tank").val());
         var tankIndex = BoxStorage.getTankIndex(tankID);
         var sectors = Main.tanks[tankIndex].sectors;
         for (var sectorIndex = 0; sectorIndex < sectors.length; sectorIndex++) {
            $("#sector").append($("<option></option>").attr("value", sectors[sectorIndex].id).text(sectors[sectorIndex].facility));
         }
      }
   },

   /**
    * Populates the racks dropdown with racks from the selected sector
    *
    * @returns {undefined}
    */
   populateSectorRacks: function() {
      //clear all children selects
      $('#rack').find('option').remove().end()
         .append('<option value=""></option>')//append a null option
         .val('');
      $('#rack').prop('disabled', 'disabled');
      if ($('#rack_div').length) $('#rack_div').show();
      if ($('#rack_spec_div').length) {
         $('#rack_spec_div').hide();
         $('#rack_spec').val("");
      }
      $('#position').find('option').remove().end()
         .append('<option value=""></option>')//append a null option
         .val('');
      $('#position').prop('disabled', 'disabled');
      if ($("#tank").val() !== "" && $("#sector").val() !== "") {
         $('#rack').prop('disabled', false);
         $('#rack').find('option').remove().end()
            .append('<option value=""></option>')//append a null option
            .val('');

         if (Main.forInsertion) {//only provide the specify option if purpose is insertion of box
            $("#rack").append($("<option></option>")
               .attr("value", "n£WR@ck")
               .text("Specify new rack"));
         }

         var tankID = parseInt($("#tank").val());
         var tankIndex = BoxStorage.getTankIndex(tankID);
         var sectors = Main.tanks[tankIndex].sectors;
         var sectorID = parseInt($("#sector").val());
         var racks = sectors[BoxStorage.getSectorIndex(sectors, sectorID)].racks;
         for (var rackIndex = 0; rackIndex < racks.length; rackIndex++) {
            $("#rack").append($("<option></option>")
                    .attr("value", racks[rackIndex].name)
                    .text("Rack " + racks[rackIndex].name));
         }
      }
   },

   /**
    * This function gets the tank data from the server and caches this data in window.tankData if required to
    *
    * @param {Boolean} saveInWindow If set to TRUE, this function will cache the tank data as a json object in window.tankData
    *
    * @returns {JSONObject} Returns a JSONObject with the tank data
    */
   getTankData : function(fromServer){
      if(fromServer){
         //console.log("getting tank data from the server");
         var jsonText = $.ajax({
            type: "GET",
            url: "mod_ajax.php?page=box_storage&do=ajax&action=get_tank_details",
            async: false
         }).responseText;

         var json = jQuery.parseJSON(jsonText);
         BoxStorage.setDataCache("tankData", jsonText);//set cookie. make expire after one day
         return json;
      }
      else{
         var jsonText = BoxStorage.getDataCache("tankData");
         if(jsonText === -1){//cookie has not been set or is empty
            //console.log("Could not get cached data. Probably means your browser does not support HTML5 sessionStorage or variable was invalidated some in recent past.");
            return BoxStorage.getTankData(true);
         }
         else{
            //console.log("Cached data gotten from sessionStorage");
            return jQuery.parseJSON(jsonText);
            //return jsonText;
         }
      }
   },

   /**
    * This function is used to determine the index of a tank in the tank array using its id
    *
    * @param {Number} tankID The id of the tank (As from the database)
    * @returns {Number} Returns the index of a tank in the tank array or -1 if no tank in the array has the provided tankID
    */
   getTankIndex : function(tankID){
      for(var tankIndex = 0; tankIndex < Main.tanks.length; tankIndex++){
         if(parseInt(Main.tanks[tankIndex].id) === tankID){
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
    * @param {String} rackName The name of the rack as from the database
    * @returns {Number} Returs the index of the rack in the rack array provided or -1 if no rack in the rack array has the provided rackName
    */
   getRackIndex : function(racks, rackName){
      for(var rackIndex = 0; rackIndex < racks.length; rackIndex++){
         if(racks[rackIndex].name === rackName){
            return rackIndex;
         }
      }
      return -1;
   },

  /**
   * This function sets options for removed boxes in the corresponding select tag
   * 
   * @returns {undefined}
   */
   setRemovedBoxSuggestions : function(){
      BoxStorage.resetReturnInput(true);
      var tankData = BoxStorage.getTankData(false);//cache fetched tank data into document.tankData so that you wont need to fetch it again

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
                  var retrieves = boxes[boxIndex].retrieves;
                  //get all the retrieves that dont have returns, should be a maximux of one
                  for(var removeIndex = 0; removeIndex < retrieves.length; removeIndex++){
                     if(typeof(retrieves[removeIndex].date_returned) === 'undefined' || retrieves[removeIndex].date_returned === null){
                        var keyValue = {value: boxes[boxIndex].box_name, key: tankIndex+'-'+sectorIndex+'-'+rackIndex+'-'+boxIndex+'-'+removeIndex};
                        if(suggestions.length < 11)
                           suggestions.push(keyValue);
                        break;//you should only have one remove without a return date associated with a box/box
                     }
                  }
               }
            }
         }
      }
      
      /*if(suggestions.length > 10){
         suggestions = suggestions.slice(0,10);//maximum of 10 suggestions
      }*/

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
               $("#tank").val(tanks[parentIndexes[0]].name);

               var sector = tanks[parentIndexes[0]].sectors[parentIndexes[1]];
               $("#sector").val(sector.facility);

               var rack = sector.racks[parentIndexes[2]];
               $("#rack").val(rack.name);

               var box = rack.boxes[parentIndexes[3]];
               $("#position").val(box.rack_position);

               var remove = box.retrieves[parentIndexes[4]];
               $("#remove_id").val(remove.id);
            }
         }
      });
   },
   
   setRetrievedBoxSuggestions: function(){
      var tankData = BoxStorage.getTankData(false);//get cached tank data
      var suggestions = new Array();
      var tanks = tankData.data;
      //get all box labels
      for(var tankIndex = 0; tankIndex < tanks.length; tankIndex++){//iterate through all the tanks
         var sectors = tanks[tankIndex].sectors;
         for(var sectorIndex = 0; sectorIndex < sectors.length; sectorIndex++){//iterate through all the sectors
            var racks = sectors[sectorIndex].racks;
            for(var rackIndex = 0; rackIndex < racks.length; rackIndex++){//iterate through all the racks
               var boxes = racks[rackIndex].boxes;
               for(var boxIndex = 0; boxIndex < boxes.length; boxIndex++){//iterate through all the boxes
                  var key = tankIndex + "-" + sectorIndex + "-" + rackIndex + "-" + boxIndex;
                  var keyValue = {value: boxes[boxIndex].box_name, key: key};
                  if(suggestions.length < 11)
                     suggestions.push(keyValue);
               }
            }
         }
      }
      
      $("#box_label").autocomplete({
         source: suggestions,
         minLength: 1,
         select: function(event, ui){
            var key = ui.item.key;
            // tankIndex-sectorIndex-rackIndex-boxIndex
            var parentIndexes = key.split("-");

            //convert all the parent indexes to integers
            for(var i = 0; i<parentIndexes.length; i++){
               parentIndexes[i] = parseInt(parentIndexes[i]);
            }
            if(parentIndexes.length === 4){
               //set values of tank position inputs
               $("#tank").val(tanks[parentIndexes[0]].id);
               BoxStorage.populateTankSectors();

               var sector = tanks[parentIndexes[0]].sectors[parentIndexes[1]];
               $("#sector").val(sector.id);
               BoxStorage.populateSectorRacks();

               var rack = sector.racks[parentIndexes[2]];
               $("#rack").val(rack.name);
               BoxStorage.populateSelectedPosition();

               var box = rack.boxes[parentIndexes[3]];
               $("#position").val(box.rack_position);

               var boxID = box.box_id;
               $("#box_id").val(boxID);
            }
         }
      });
   },
   
   setSearchBoxSuggestions : function() {
      BoxStorage.resetReturnInput(true);
      var tankData = BoxStorage.getTankData(false);//cache fetched tank data into document.tankData so that you wont need to fetch it again

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
                  var keyValue = {value: boxes[boxIndex].box_name, key: boxes[boxIndex].box_name};
                  if(suggestions.length < 11)
                     suggestions.push(keyValue);
               }
            }
         }
      }
      
      /*if(suggestions.length > 10){
         suggestions = suggestions.slice(0,10);//maximum of 10 suggestions
      }*/

      $("#search").autocomplete({
         source: suggestions,
         minLength: 2,
         select: function(){
            BoxStorage.searchForBox();
         }
      });
   },

   /**
   * This function sets options for deleted boxes in the corresponding select tag
   * 
   * @returns {undefined}
   */
   setDeleteBoxSuggestions : function(){
      BoxStorage.resetDeleteInput(true);
      var tankData = BoxStorage.getTankData(false);//cache fetched tank data into document.tankData so that you wont need to fetch it again

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
                  var keyValue = {value: boxes[boxIndex].box_name, key: tankIndex+'-'+sectorIndex+'-'+rackIndex+'-'+boxIndex};
                  if(suggestions.length < 11)
                     suggestions.push(keyValue);
               }
            }
         }
      }
      
      /*if(suggestions.length > 10){
         suggestions = suggestions.slice(0,10);//maximum of 10 suggestions
      }*/

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
            if(parentIndexes.length === 4){
               //set values of tank position inputs
               $("#tank").val(tanks[parentIndexes[0]].name);

               var sector = tanks[parentIndexes[0]].sectors[parentIndexes[1]];
               $("#sector").val(sector.facility);

               var rack = sector.racks[parentIndexes[2]];
               $("#rack").val(rack.name);

               var box = rack.boxes[parentIndexes[3]];
               $("#position").val(box.rack_position);

               var boxID = box.box_id;
               $("#box_id").val(boxID);
            }
         }
      });
   },

   /**
    * This function resets fields in the Return a Box page to their default
    * 
    * @param {Boolean} complete  Set to TRUE if you want to competely reset all the fields (including the box label and comment fields)
    * 
    * @returns {undefined}
    */
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
   },
   /**
    * This function resets fields in the Delete a Box page to their default
    * 
    * @param {type} complete  Set to TRUE if you want to competely reset all the fields (including the box label and comment fields)
    * 
    * @returns {undefined}
    */
   resetDeleteInput: function(complete){
      $("#tank").val('');
      $("#sector").val('');
      $("#rack").val('');
      $("#position").val('');
      $('#box_id').val('');
      if(complete){
         $('#box_label').val('');
         $('#return_comment').val('');
      }
   },
   
   /**
    * This function formats the post data to be used in searching for a box from the server
    * 
    * @returns {undefined}
    */
   searchForBox: function (){
      //first check if request has already gone to server and has not been responded to
      $("#searched_boxes").jqxGrid('gotopage', 0);
      var data = {
         search: $("#search").val(),
         project: $("#search_project").val(),
         status: $("#search_status").val(),
         location: $("#search_location").val(),
         keeper: $("#search_keeper").val(),
         boxes_wo_names: $("#boxes_wo_names").is(":checked"),
         samples: $("#samples").val()

      };
      BoxStorage.updateSearchBoxesGrid(data);
   },
   
   /**
    * This function hides/shows the different areas of the search page depending on wheter users wants an advanced search or not
    * 
    * @returns {undefined}
    */
   toggleAdvancedSearch: function() {
      $("select option").filter(function(){
         return $(this).text() == "";
      }).prop('selected', true);
      $("#advanced_search_div").toggle(500);
   },
   
   /**
    * This function initializes a listener for monitoring when rows are selected in the search page's JQXGrid
    * 
    * @returns {undefined}
    */
   initSearchSelectedListener: function() {
      $("#searched_boxes").jqxGrid({selectionmode: 'singlerow'});
      $("#searched_boxes").bind('rowselect', function (event){
         if($("#search_div").is(":visible") === true && $("#edit_div").is(":visible") === false ){//do this check because the rowselect event handler is called several times when event occures. Process only once
            BoxStorage.toggleSearchModes();
            
            var row = event.args.rowindex;
            var rowData = $("#searched_boxes").jqxGrid('getrowdata', row);
            
//            /console.log(rowData.box_id);
            $("#box_id").val(rowData.box_id);
            
            $("#box_label").val(rowData.box_name);
            $("#features").val(rowData.box_features);
            $("#owner").val(rowData.keeper);
            $("input[name='box_size'][value='"+BoxStorage.convertBoxSize(rowData.size)+"']").prop("checked", true);
            
            //tank details
            
            $("#tank").val(rowData.tank_id);
            BoxStorage.populateTankSectors();
            $("#sector").val(rowData.sector_id);
            BoxStorage.populateSectorRacks();
            $("#rack").val(rowData.rack);
            BoxStorage.populateSelectedPosition();
            //add the boxes position to position select because populateSelectPosition function did not add it because 'it has a box :)'
            $("#position").append($("<option></option>").attr("value", rowData.rack_position).text("Position "+rowData.rack_position));
            $("#position").val(rowData.rack_position);
            
            
            $("#status").val(rowData.status);
            if(rowData.status === "temporary"){
               $("#project").prop("disabled", false);
               $("#owner").prop("disabled", true);
               if(isNaN(rowData.project) === false){//project set
                  $("#project").val(rowData.project);
               }
            }
            else{
               $("#project").prop("disabled", true);
               $("#owner").prop("disabled", false);
            }
         }
      });
   },
   
   /**
    * This function toggles the search page between edit mode and search mode depending on what the user is doing
    * 
    * @returns {undefined}
    */
   toggleSearchModes: function(){
      if($("#search_div").is(":visible")){
         $("#search_div").hide(400);
         $("#searched_boxes").hide(400);
         $("#edit_div").show(400);
      }
      else{
         $("#search_div").show(400);
         $("#searched_boxes").show(400);
         $("#edit_div").hide(400);
      }
   },
   
   /**
    * This function converts box sizes used by the LIMS system readable sizes 
    *       (e.g A:1.J:10 = 100)
    * 
    * @param {String} limsSize Size of the box in the LIMS format e.g A:1.J:10
    * 
    * @returns {Number} The size of the box as a number
    */
   convertBoxSize: function(limsSize) {
      var limsDimensions = limsSize.split(".");
      
      //you only need to process the last part of the size ie J:10
      var lastPos = limsDimensions[1];
      var posParts = lastPos.split(":");
      var asciiPart1  = posParts[0].charCodeAt(0) - 64;
      return asciiPart1 * posParts[1]; 
   },
   
   /**
    * This function submits updated box data to the server as an AJAX request. 
    * The Search page will not refresh after this function executes
    * 
    * @returns {undefined}
    */
   submitBoxUpdate: function(){
      if(BoxStorage.validateInsertInput()){
         BoxStorage.deleteDataCache("tankData");
         //console.log("trying to update");
         //#box_label#box_size#owner#status#features
         //#tank#sector#rack#rack_spec#position
         var formData = {
            box_label: $("#box_label").val(),
            box_size: $("input[name='box_size']:checked").val(),
            owner: $("#owner").val(),
            status: $("#status").val(),
            features: $("#features").val(),
            tank: $("#tank").val(),
            sector: $("#sector").val(),
            rack: $("#rack").val(),
            rack_spec: $("#rack_spec").val(),
            position: $("#position").val(),
            box_id: $("#box_id").val(),
            project: $("#project").val()
         };

         var responseText = $.ajax({
            url: "mod_ajax.php?page=box_storage&do=ajax&action=submit_update_request",
            type: "POST",
            data: formData,
            async: false
         }).responseText;
         var responseJson = $.parseJSON(responseText);

         if(responseJson.error === 1){
            Notification.show({create:true, hide:true, updateText:false, text: responseJson.message, error:true});
         }
         else{
            Notification.show({create:true, hide:true, updateText:false, text: responseJson.message, error:false});
            BoxStorage.toggleSearchModes();
            BoxStorage.getTankData(true);//get tank data from the server
            BoxStorage.loadTankData(true);
            BoxStorage.updateSearchBoxesGrid();
         }
      }
   },
   
   setDataCache: function(cname,cvalue){
      if(typeof(Storage) !== "undefined"){//browers supports HTML5 localstorage
         sessionStorage.setItem(cname, cvalue);
         //console.log("Data successfully cached into sessionStorage");
      }
      else{
         //console.log("browser does not support HTML5 sessionStorage");
      }
      /*var d = new Date();
      d.setTime(d.getTime()+(exdays*24*60*60*1000));
      var expires = "expires="+d.toGMTString();
      document.cookie = cname + "=" + cvalue + "; " + expires + "; path=/";
      console.log("saved "+cname+" as a cookie");*/
   },
   
   getDataCache: function(cname) {
      var cached = -1;
      if(typeof(Storage) !== "undefined"){
         cached = sessionStorage.getItem(cname);
         if(cached === null) cached = -1;
      }
      else{
         //console.log("browser does not support HTML5 sessionStorage");
      }
      return cached;
      /*var name = cname + "=";
      var ca = document.cookie.split(';');
      for(var i=0; i<ca.length; i++) {
         var c = ca[i].trim();
         if (c.indexOf(name)==0) return c.substring(name.length,c.length);
      }
      return -1;*/
   },
   
   deleteDataCache: function(name){
      if(typeof(Storage) !== "undefined"){//browers supports HTML5 localstorage
         sessionStorage.removeItem(name);
         //console.log("Data successfully deleted from sessionStorage");
      }
      else{
         //console.log("browser does not support HTML5 sessionStorage");
      }
   }
};