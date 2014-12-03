/**
 * Constructor for the recharges class
 * @returns {undefined}
 */
var MODE_STORAGE = "storage";
var MODE_LN2 = "ln2";
var MODE_LABELS = "labels";
var MODE_INVENTORY = "inventory";

function Recharges(mode){
   console.log("called");
   window.rc = this;
   
   //init the data objects
   window.rc.mode = mode;
   console.log(mode);
   window.rc.space = {
      projects:new Array(),//this stores the IDs of projects to be included in the recharge
      updateTimeoutID:undefined
   };
   
   window.rc.inventory = {
      items: new Array()//stores array of items {id, name, issued_to e.t.c}
   };
   
   window.rc.ln2 = {
      items: new Array()//stores [{id, amount_appr}] for liquid nitrogen requests
   };
   
   window.rc.labels = {
      items: new Array()
   };
   
   window.rc.spaceRechargeTAdapter = undefined;
   window.rc.inventoryRechargeTAdapter = undefined;
   window.rc.ln2RechargeTAdapter = undefined;
   window.rc.labelsRechargeTAdapter = undefined;
   
   //initialize the event handlers
   window.rc.windowResized();//call it the first time
   $(window).resize(function(){
      window.rc.windowResized();
   });
   
   $("#recharge_btn").click(function(){
      if(window.rc.mode == MODE_STORAGE){
         window.rc.updateSpaceRechargeProjects();
         if(window.rc.space.projects.length > 0){
            $("#recharge_dialog").show();
         }
      }
      else if(window.rc.mode == MODE_INVENTORY){
         window.rc.updateInventoryItems();
         if(window.rc.inventory.items.length > 0){
            $("#recharge_dialog").show();
         }
      }
      else if(window.rc.mode == MODE_LN2){
         window.rc.updateLN2Items();
         if(window.rc.ln2.items.length > 0){
            $("#recharge_dialog").show();
         }
      }
      else if(window.rc.mode == MODE_LABELS){
         window.rc.updateLabelsItems();
         if(window.rc.labels.items.length > 0){
            $("#recharge_dialog").show();
         }
      }
   });
   
   $("#recharge_dialog_close").click(function(){
      if(window.rc.mode == MODE_STORAGE){
         $("#space_recharge_table").jqxGrid('updatebounddata');
      }
      else if(window.rc.mode == MODE_INVENTORY){
         $("#inventory_recharge_table").jqxGrid('updatebounddata');
      }
      else if(window.rc.mode == MODE_LN2){
         $("#ln2_recharge_table").jqxGrid('updatebounddata');
      }
      else if(window.rc.mode == MODE_LABELS){
         $("#labels_recharge_table").jqxGrid('updatebounddata');
      }
      
      $("#recharge_dialog").hide();
      $("#confirm_recharge_btn").removeAttr('disabled');
   });
   
   $("#confirm_recharge_btn").click(function(){
      if(window.rc.mode == MODE_STORAGE){
         window.rc.submitSpaceRecharge();
      }
      else if(window.rc.mode == MODE_INVENTORY){
         window.rc.submitInventoryRecharge();
      }
      else if(window.rc.mode == MODE_LN2){
         window.rc.submitLN2Recharge();
      }
      else if(window.rc.mode == MODE_LABELS){
         window.rc.submitLabelsRecharge();
      }
   });
   
   
   //do mode specific inits
   if(window.rc.mode == MODE_STORAGE){
      window.rc.initStorageSpecificValues();
   }
   else if(window.rc.mode == MODE_INVENTORY){
      window.rc.initInventorySpecificValues();
   }
   else if(window.rc.mode == MODE_LN2){
      window.rc.initLN2SpecificValues();
   }
   else if(window.rc.mode == MODE_LABELS){
      window.rc.initLabelsSpecificValues();
   }
}

Recharges.prototype.initStorageSpecificValues = function(){
   $("#period_ending").change(function(){
      window.rc.loadStorageSpaceTable();
   });
   $("#price").change(function(){
      window.rc.loadStorageSpaceTable();
   });
   $("#price").focus().live('keyup', function(){
      if(typeof window.rc.space.updateTimeoutID != 'undefined'){
         window.clearTimeout(window.rc.space.updateTimeoutID);
      }
      window.rc.space.updateTimeoutID = window.setTimeout(window.rc.loadStorageSpaceTable, 1000);
  });
};

Recharges.prototype.initInventorySpecificValues = function(){
   console.log("init inventory specific stuff called");
   window.rc.initInventoryTable();
};

Recharges.prototype.initLN2SpecificValues = function(){
   window.rc.initLN2Table();
};

Recharges.prototype.initLabelsSpecificValues = function(){
   window.rc.initLabelsTable();
};

Recharges.prototype.loadStorageSpaceTable = function(){
   if($("#space_recharge_table").html().length == 0){
      window.rc.initStorageSpaceTable();
   }
   else {
      window.rc.updateStorageSpaceTable();
   }
};

/**
 * This function is called whenever the window is resized.
 * Put code here that you want to run whenever this happens
 * 
 * @returns {undefined}
 */
Recharges.prototype.windowResized = function(){
   $("#recharge_dialog").css('left', (window.innerWidth / 2) - ($("#recharge_dialog").width() / 2) + "px");
   $("#recharge_dialog").css('top', (window.innerHeight / 2) - ($("#recharge_dialog").height() / 2) + "px");
};

/**
 * This function populates the jq table for storage space
 * 
 * @returns {undefined}
 */
Recharges.prototype.initStorageSpaceTable = function(){
   var theme = '';
   var url = "mod_ajax.php?page=recharges&do=space";
   
   if($("#price").val().length !== 0 && $("#period_ending").val().length !== 0){
      var data = {
         action: 'get_recharges',
         period_ending: $("#period_ending").val(),
         price: $("#price").val()
      };

      var source = {
         datatype: 'json',
         datafields: [ 
            {name: 'recharge', type: 'bool'}, 
            {name: 'project'}, 
            {name: 'last_period'}, 
            {name: 'duration'},
            {name: 'no_boxes'},
            {name: 'box_price'},
            {name: 'total_price'},
            {name: 'project_id'}
         ],//make sure you update these fields when you update those of the update fetch
         id: 'project',
         root: 'data',
         async: true,
         url: url, 
         type: 'POST',
         data: data
      };

      window.rc.spaceRechargeTAdapter = new $.jqx.dataAdapter(source);
      
      var noBoxesIndex = 0;
      var totalPriceIndex = 0;
      
      // create jqxgrid
      if($('#space_recharge_table :regex(class, jqx\-grid)').length === 0){
         $("#space_recharge_table").jqxGrid({
            width: 900,
            autoheight: true,
            source: window.rc.spaceRechargeTAdapter,
            columnsresize: true,
            theme: theme,
            showaggregates: true,
            showstatusbar: true,
            statusbarheight: 35,
            sortable: false,
            pageable: false,
            ready: function(){
               console.log("grid ready");
               window.rc.updateSpaceRechargeProjects();
            },
            virtualmode: true,
            editable: true,
            rendergridrows: function() {
               return window.rc.spaceRechargeTAdapter.records;
            },
            columns: [
               {text: 'Recharge', datafield: 'recharge', columntype: 'checkbox', width: 100, sortable: false, editable: true},
               {text: 'Project', datafield: 'project', width: 200, sortable: false, editable: true},
               {text: 'Last Recharge', datafield: 'last_period', width: 150, sortable: false, editable: true},
               {text: 'Duration (days)', datafield: 'duration', width: 100, sortable: false, editable: true},
               {text: 'Cost Per Year (USD)', datafield: 'box_price', width: 100, sortable: false, editable: true},
               {
                  text: 'No. Boxes', datafield: 'no_boxes', width: 75, sortable: false, editable: true,
                  aggregates:[{
                     'Number':function(aggregatedValue, currentValue){
                        var dataInfo = $("#space_recharge_table").jqxGrid('getdatainformation');
                        
                        if(noBoxesIndex >= dataInfo.rowscount){
                           noBoxesIndex = 0;
                        }
                        
                        var rowData = $("#space_recharge_table").jqxGrid('getrowdata', noBoxesIndex);
                        //TODO: calculate current value based on price per box per year and duration
                        if(typeof rowData != 'undefined' && rowData.recharge != 0){
                           aggregatedValue += currentValue;
                        }
                        
                        noBoxesIndex++;
                        return aggregatedValue;
                     }
                  }]
               },
               {
                  text: 'Total Cost (USD)', datafield: 'total_price', width: 175, sortable: false, editable: true,
                  aggregates:[{
                     'Total':function(aggregatedValue, currentValue){
                        var dataInfo = $("#space_recharge_table").jqxGrid('getdatainformation');
                        
                        if(totalPriceIndex >= dataInfo.rowscount){
                           totalPriceIndex = 0;
                        }
                        
                        var rowData = $("#space_recharge_table").jqxGrid('getrowdata', totalPriceIndex);
                        
                        if(rowData.recharge != 0){
                           aggregatedValue += currentValue;
                        }
                        
                        totalPriceIndex++;
                        return Math.round(aggregatedValue * 100)/100;
                     }
                  }]
               }
            ]
         });
         
         
         $("#space_recharge_table").on('cellendedit', function (event) {
            // column data field.
            var dataField = event.args.datafield;
            // row's bound index.
            var rowBoundIndex = event.args.rowindex;
            // cell value
            var value = event.args.value;
            
            if(dataField != 'recharge' && dataField != 'box_price'){
               Notification.show({create:true, hide:true, updateText:false, text: "Change will not be reflected in the database", error:true});
            }
            
            window.rc.updateSpaceRechargeProjects(rowBoundIndex, dataField, value);
            $("#space_recharge_table").jqxGrid('refresh');
         });
         
      }
   }
   
};

Recharges.prototype.updateSpaceRechargeProjects = function (rowBoundIndex, dataField, value){
   var dataInfo = $("#space_recharge_table").jqxGrid('getdatainformation');
            
   window.rc.space.projects = new Array();
   for(var rowIndex = 0; rowIndex < dataInfo.rowscount; rowIndex++){
      var rowData = $("#space_recharge_table").jqxGrid('getrowdata', rowIndex);
      if(typeof rowBoundIndex != 'undefined' && rowBoundIndex == rowIndex){
         if(dataField == 'box_price'){
            //$result[$i]['duration'] * $priceBoxDay * $result[$i]['no_boxes']
            var pricePerDay = value/365;
            rowData.total_price = Math.round(rowData.duration * pricePerDay * rowData.no_boxes * 100)/100;
            rowData.box_price = value;
         }
      }
      
      $("#space_recharge_table").jqxGrid('updaterow', rowIndex, rowData);
      if(rowData.recharge == 1){
         window.rc.space.projects.push({project_id:rowData.project_id, box_price:rowData.box_price});
      }
   }
};

Recharges.prototype.updateInventoryItems = function(rowBoundIndex, dataField, value){
   var dataInfo = $("#inventory_recharge_table").jqxGrid('getdatainformation');
   
   window.rc.inventory.items = new Array();
   for(var rowIndex = 0; rowIndex < dataInfo.rowscount; rowIndex++){
      var rowData = $("#inventory_recharge_table").jqxGrid('getrowdata', rowIndex);
      if(typeof rowBoundIndex != 'undefined' && rowBoundIndex == rowIndex){
         if(dataField == 'quantity'){
            rowData.total = value * rowData.pp_unit;
         }
         else if(dataField == 'pp_unit'){
            rowData.total = rowData.quantity * value;
         }
      }
      
      $("#inventory_recharge_table").jqxGrid('updaterow', rowIndex, rowData);
      if(rowData.recharge == 1){
         window.rc.inventory.items.push(rowData);
      }
   }
};

Recharges.prototype.updateLN2Items = function(rowBoundIndex, dataField, value){
   var dataInfo = $("#ln2_recharge_table").jqxGrid('getdatainformation');
   
   window.rc.ln2.items = new Array();
   for(var rowIndex = 0; rowIndex < dataInfo.rowscount; rowIndex++){
      var rowData = $("#ln2_recharge_table").jqxGrid('getrowdata', rowIndex);
      if(typeof rowBoundIndex != 'undefined' && rowBoundIndex == rowIndex){
         if(dataField == 'price'){
            rowData.cost = value * rowData.amount_appr;
         }
         else if(dataField == 'amount_appr'){
            rowData.cost = rowData.price * value;
         }
      }
      
      $("#ln2_recharge_table").jqxGrid('updaterow', rowIndex, rowData);
      if(rowData.recharge == 1){
         window.rc.ln2.items.push({id: rowData.id, amount_appr: rowData.amount_appr, charge_code: rowData.charge_code, price: rowData.price});
      }
   }
};

Recharges.prototype.updateLabelsItems = function(rowBoundIndex, dataField, value){
   var dataInfo = $("#labels_recharge_table").jqxGrid('getdatainformation');
   
   window.rc.labels.items = new Array();
   for(var rowIndex = 0; rowIndex < dataInfo.rowscount; rowIndex++){
      var rowData = $("#labels_recharge_table").jqxGrid('getrowdata', rowIndex);
      if(typeof rowBoundIndex != 'undefined' && rowBoundIndex == rowIndex){
         if(dataField == 'price'){
            rowData.total = value * rowData.labels_printed;
         }
         else if(dataField == 'labels_printed'){
            //rowData.total = rowData.price * value;
         }
      }
      
      $("#labels_recharge_table").jqxGrid('updaterow', rowIndex, rowData);
      if(rowData.recharge == 1){
         window.rc.labels.items.push({id: rowData.id, charge_code: rowData.charge_code, price: rowData.price});
      }
   }
};

/**
 * This function updates the already initiated storage space recharging table
 * 
 * @returns {undefined}
 */
Recharges.prototype.updateStorageSpaceTable = function(){
   if($("#price").val().length !== 0 && $("#period_ending").val().length !== 0){
      var data = {
         action: 'get_recharges',
         period_ending: $("#period_ending").val(),
         price: $("#price").val()
      };

      var url = "mod_ajax.php?page=recharges&do=space";
      var source = {
         datatype: 'json',
         datafields: [ 
            {name: 'recharge', type: 'bool'}, 
            {name: 'project'}, 
            {name: 'last_period'}, 
            {name: 'duration'},
            {name: 'no_boxes'},
            {name: 'box_price'},
            {name: 'total_price'},
            {name: 'project_id'}
         ],//make sure you update these fields when you update those for the initial fetch
         id: 'project',
         root: 'data',
         async: true,
         url: url, 
         type: 'POST',
         data: data
      };

      window.rc.spaceRechargeTAdapter = new $.jqx.dataAdapter(source);
      $("#space_recharge_table").jqxGrid({source: window.rc.spaceRechargeTAdapter});
   }
};

/**
 * This function inits UI elements that use the period starting and period ending values in 
 * storage space recharge page
 * 
 * @param {int} lastPeriodEnding The timestamp in milliseconds for the last recorded period ending
 * 
 * @returns {undefined}
 */
Recharges.prototype.setStorageRechargePeriods = function(lastPeriodEnding){
   var periodStarting = new Date(lastPeriodEnding);
   var months = new Array("January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December");
   
   //set date for period starting input
   $("#period_starting").val(months[periodStarting.getMonth()] + " " + (periodStarting.getYear() + 1900));
   
   //populate the period ending select
   var peSelect = $("#period_ending");
   peSelect.empty();
   
   //generate 12 months
   var selectMonths = new Array();
   var nextMonth = periodStarting.getMonth() + 1;
   var nextYear = periodStarting.getYear() + 1900;
   
   var thirtieth = new Array(8, 3, 5, 10);//array of indexes of months that have 30 days
   
   for(var i = 0; i < 12; i++){
      if(nextMonth === 12) {
         nextMonth = 0;
         nextYear++;
      }
      
      var nextDay = 31;
      if(nextMonth == 1){
         if((nextYear % 4) === 0){//leap year
            nextDay = 29;
         }
         else {
            nextDay = 28;
         }
      }
      else if(jQuery.inArray(nextMonth, thirtieth) != -1){//month has thirty days
         nextDay = 30;
      }
      
      selectMonths.push({"id": nextYear+ "-" + (nextMonth + 1) + "-" + nextDay, "value":months[nextMonth]+" "+nextYear});
      nextMonth++;
   }
   
   //add the months to the select
   peSelect.append("<option value=''></option>");
   for(var i = 0; i < selectMonths.length; i++){
      peSelect.append("<option value='"+selectMonths[i].id+"'>"+selectMonths[i].value+"</option>");
   }
};

/**
 * This function should start a downlod of the recharged boxes
 * 
 * @returns {undefined}
 */
Recharges.prototype.submitSpaceRecharge = function(){
   if(window.rc.space.projects.length > 0){
      
      if(window.rc.space.projects.length > 0 && $("#period_ending").val().length > 0 && $("#price").val().length > 0){
         
         var fine = true;
         for(var index = 0; index < window.rc.space.projects.length; index++){
            if(window.rc.space.projects[index].box_price.length == 0){
               fine = false;
            }
         }
         
         if(fine == false){
            $("#recharge_dialog").hide();
            Notification.show({create:true, hide:true, updateText:false, text: "Please make sure all the information in the table is filled out", error:true});
         }
         else {
            /*var url = "mod_ajax.php?page=recharges&do=space&action=submit_recharge";
            url += "&project_ids="+window.rc.space.projects.join(",");
            url += "&period_ending="+$("#period_ending").val();
            url += "&price="+$("#price").val();*/

            $("#confirm_recharge_btn").attr('disabled', 'disabled');
            //window.rc.startDownload(url);

            var data = {
               "action":"submit_recharge",
               "projects":window.rc.space.projects,
               "period_ending":$("#period_ending").val(),
               "price":$("#price").val()
            };

            jQuery.ajax({
               url:"mod_ajax.php?page=recharges&do=space",
               data:data,
               type:"POST",
               async:true,
               success:function(data){
                  console.log(data);
                  var json = jQuery.parseJSON(data);
                  if(json.error == true){
                     Notification.show({create:true, hide:true, updateText:false, text: json.error_message, error:true});
                  }
                  else {
                     Notification.show({create:true, hide:true, updateText:false, text: "An email has been sent to the Biorepository manager with details on the recharge", error:false});
                  }
               },
               error:function(){
                  console.log("an error occurred");
                  Notification.show({create:true, hide:true, updateText:false, text: "An error occurred while trying to connect to the server. Please try again", error:true});
               }
            });
         }
      }
   }
};

Recharges.prototype.submitInventoryRecharge = function(){
   var good = true;
   for(var index = 0; index < window.rc.inventory.items.length; index++){
      var currItem = window.rc.inventory.items[index];
      //recharge, a.id, a.item, a.issued_by, a.issued_to, a.date_issued, b.name as charge_code, a.alt_ccode, a.pp_unit, a.quantity
      if(currItem.id.length == 0 
              || currItem.item.length == 0 
              || currItem.issued_to.length == 0 
              || currItem.issued_by.length == 0 
              || currItem.date_issued.length == 0 
              || currItem.charge_code.length == 0
              || currItem.pp_unit.length == 0){
         good = false;
      }
   }
   
   if(good == false){
      $("#recharge_dialog").hide();
      Notification.show({create:true, hide:true, updateText:false, text: "Please make sure all the information in the table is filled out", error:true});
   }
   else {
      $("#confirm_recharge_btn").attr('disabled', 'disabled');
      //window.rc.startDownload(url);
      
      var data = {
         "action":"submit_recharge",
         "items":window.rc.inventory.items
      };

      jQuery.ajax({
         url:"mod_ajax.php?page=recharges&do=inventory",
         data:data,
         type:"POST",
         async:true,
         success:function(data){
            if(data.length > 0){
               var json = jQuery.parseJSON(data);
               if(json.error == true){
                  Notification.show({create:true, hide:true, updateText:false, text: json.error_message, error:true});
               }
               else {
                  Notification.show({create:true, hide:true, updateText:false, text: "An email has been sent to the Biorepository manager with details on the recharge", error:false});
               }
            }
            else {
               Notification.show({create:true, hide:true, updateText:false, text: "No data received from the server", error:true});
            }
         },
         error:function(){
            console.log("an error occurred");
            Notification.show({create:true, hide:true, updateText:false, text: "An error occurred while trying to connect to the server. Please try again", error:true});
         }
      });
   }
};

Recharges.prototype.submitLN2Recharge = function(){
   var good = true;
   for(var index = 0; index < window.rc.ln2.items.length; index++){
      var currItem = window.rc.ln2.items[index];
      //id: rowData.id, amount_appr: rowData.amount_appr, charge_code: rowData.charge_code, price: rowData.price
      if(currItem.id.length == 0 
              || currItem.amount_appr.length == 0
              || currItem.charge_code.length == 0
              || currItem.price.length == 0){
         good = false;
      }
   }
   
   if(good == false){
      $("#recharge_dialog").hide();
      Notification.show({create:true, hide:true, updateText:false, text: "Please make sure all the information in the table is filled out", error:true});
   }
   else {
      $("#confirm_recharge_btn").attr('disabled', 'disabled');
      //window.rc.startDownload(url);
      
      var data = {
         "action":"submit_recharge",
         "items":window.rc.ln2.items
      };

      jQuery.ajax({
         url:"mod_ajax.php?page=recharges&do=ln2",
         data:data,
         type:"POST",
         async:true,
         success:function(data){
            if(data.length > 0){
               var json = jQuery.parseJSON(data);
               if(json.error == true){
                  Notification.show({create:true, hide:true, updateText:false, text: json.error_message, error:true});
               }
               else {
                  Notification.show({create:true, hide:true, updateText:false, text: "An email has been sent to the Biorepository manager with details on the recharge", error:false});
               }
            }
            else {
               Notification.show({create:true, hide:true, updateText:false, text: "No data received from the server", error:true});
            }
         },
         error:function(){
            console.log("an error occurred");
            Notification.show({create:true, hide:true, updateText:false, text: "An error occurred while trying to connect to the server. Please try again", error:true});
         }
      });
   }
   
};

Recharges.prototype.submitLabelsRecharge = function(){
   //TODO: implement
   //id: rowData.id, charge_code: rowData.charge_code, price: rowData.price
   var good = true;
   for(var index = 0; index < window.rc.labels.items.length; index++){
      var currItem = window.rc.labels.items[index];
      //id: rowData.id, amount_appr: rowData.amount_appr, charge_code: rowData.charge_code, price: rowData.price
      if(currItem.id.length == 0 
              || currItem.charge_code.length == 0
              || currItem.price.length == 0){
         good = false;
      }
   }
   
   if(good == false){
      $("#recharge_dialog").hide();
      Notification.show({create:true, hide:true, updateText:false, text: "Please make sure all the information in the table is filled out", error:true});
   }
   else {
      $("#confirm_recharge_btn").attr('disabled', 'disabled');
      //window.rc.startDownload(url);
      
      var data = {
         "action":"submit_recharge",
         "items":window.rc.labels.items
      };

      jQuery.ajax({
         url:"mod_ajax.php?page=recharges&do=labels",
         data:data,
         type:"POST",
         async:true,
         success:function(data){
            if(data.length > 0){
               var json = jQuery.parseJSON(data);
               if(json.error == true){
                  Notification.show({create:true, hide:true, updateText:false, text: json.error_message, error:true});
               }
               else {
                  Notification.show({create:true, hide:true, updateText:false, text: "An email has been sent to the Biorepository manager with details on the recharge", error:false});
               }
            }
            else {
               Notification.show({create:true, hide:true, updateText:false, text: "No data received from the server", error:true});
            }
         },
         error:function(){
            console.log("an error occurred");
            Notification.show({create:true, hide:true, updateText:false, text: "An error occurred while trying to connect to the server. Please try again", error:true});
         }
      });
   }
};

/**
 * This function populates the jq table for inventory
 * 
 * @returns {undefined}
 */
Recharges.prototype.initInventoryTable = function(){
   var theme = '';
   var url = "mod_ajax.php?page=recharges&do=inventory";
   
   var data = {
      action: 'get_recharges'
   };

   //recharge, a.id, a.item, a.issued_by, a.issued_to, a.date_issued, b.name as charge_code, a.alt_ccode, a.pp_unit, a.quantity

   var source = {
      datatype: 'json',
      datafields: [ 
         {name: 'recharge', type: 'bool'}, 
         {name: 'id'}, 
         {name: 'item'}, 
         {name: 'issued_by'},
         {name: 'issued_to'},
         {name: 'date_issued'},
         {name: 'charge_code'},
         {name: 'pp_unit'},
         {name: 'quantity'},
         {name: 'total'}
      ],//make sure you update these fields when you update those of the update fetch
      id: 'id',
      root: 'data',
      async: true,
      url: url, 
      type: 'POST',
      data: data
   };

   window.rc.inventoryRechargeTAdapter = new $.jqx.dataAdapter(source);

   var totalPriceIndex = 0;
   // create jqxgrid
   if($('#inventory_recharge_table :regex(class, jqx\-grid)').length === 0){
      $("#inventory_recharge_table").jqxGrid({
         width: 900,
         autoheight: true,
         source: window.rc.inventoryRechargeTAdapter,
         columnsresize: true,
         theme: theme,
         showaggregates: true,
         showstatusbar: true,
         statusbarheight: 35,
         sortable: false,
         pageable: false,
         ready: function(){
            window.rc.updateInventoryItems();
         },
         virtualmode: true,
         editable: true,
         rendergridrows: function() {
            return window.rc.inventoryRechargeTAdapter.records;
         },
         columns: [
            {text: 'Recharge', datafield: 'recharge', columntype: 'checkbox', width: 70, sortable: false, editable: true},
            {text: 'Item', datafield: 'item', width: 100, sortable: false, editable: true},
            {text: 'Issued By', datafield: 'issued_by', width: 100, sortable: false, editable: true},
            {text: 'Issued To', datafield: 'issued_to', width: 100, sortable: false, editable: true},
            {text: 'Date of Issue', datafield: 'date_issued', width: 100, sortable: false, editable: true},
            {text: 'Charge Code', datafield: 'charge_code', width: 220, sortable: false, editable: true},
            {text: 'Quantity', datafield: 'quantity', width: 70, sortable: false, editable: true},
            {text: 'Unit Price(USD)', datafield: 'pp_unit', width: 70, sortable: false, editable: true},
            {
               text: 'Total Cost (USD)', datafield: 'total', width: 70, sortable: false, editable: true,
               aggregates:[{
                  'Total':function(aggregatedValue, currentValue){
                     var dataInfo = $("#inventory_recharge_table").jqxGrid('getdatainformation');

                     if(totalPriceIndex >= dataInfo.rowscount){
                        totalPriceIndex = 0;
                     }

                     var rowData = $("#inventory_recharge_table").jqxGrid('getrowdata', totalPriceIndex);
                     
                     //currentValue = rowData.pp_unit * rowData.quantity;
                     //rowData.total = currentValue;
                     if(rowData.recharge != 0){
                        aggregatedValue += currentValue;
                     }
                     
                     //$("#inventory_recharge_table").jqxGrid('updaterow', totalPriceIndex, rowData);
                     totalPriceIndex++;
                     return Math.round(aggregatedValue * 100)/100;
                  }
               }]
            }
         ]
      });


      $("#inventory_recharge_table").on('cellendedit', function (event) {
         // column data field.
         var dataField = event.args.datafield;
         // row's bound index.
         var rowBoundIndex = event.args.rowindex;
         // cell value
         var value = event.args.value;
         if(dataField != 'recharge' 
                 && dataField != 'pp_unit' 
                 && dataField != 'quantity'
                 && dataField != 'item'
                 && dataField != 'charge_code'){
            Notification.show({create:true, hide:true, updateText:false, text: "Change will not be reflected in the database", error:true});
         }
         window.rc.updateInventoryItems(rowBoundIndex, dataField, value);
         $("#inventory_recharge_table").jqxGrid('refresh');
      });

   }
   
};

/**
 * This function populates the jq table for LN2
 * 
 * @returns {undefined}
 */
Recharges.prototype.initLN2Table = function(){
   var theme = '';
   var url = "mod_ajax.php?page=recharges&do=ln2";
   
   var data = {
      action: 'get_recharges'
   };

   //recharge, a.id, b.name as charge_code, a.alt_ccode, a.added_by, a.amount_appr

   var source = {
      datatype: 'json',
      datafields: [ 
         {name: 'recharge', type: 'bool'},
         {name: 'id'}, 
         {name: 'charge_code'}, 
         {name: 'added_by'},
         {name: 'apprvd_by'},
         {name: 'amount_appr'},
         {name: 'price'},
         {name: 'cost'}
      ],//make sure you update these fields when you update those of the update fetch
      id: 'id',
      root: 'data',
      async: true,
      url: url, 
      type: 'POST',
      data: data
   };

   window.rc.ln2RechargeTAdapter = new $.jqx.dataAdapter(source);

   var totalCostIndex = 0;
   // create jqxgrid
   if($('#ln2_recharge_table :regex(class, jqx\-grid)').length === 0){
      $("#ln2_recharge_table").jqxGrid({
         width: 900,
         autoheight: true,
         source: window.rc.ln2RechargeTAdapter,
         columnsresize: true,
         theme: theme,
         showaggregates: true,
         showstatusbar: true,
         statusbarheight: 35,
         sortable: false,
         pageable: false,
         ready: function(){
            window.rc.updateLN2Items();
         },
         virtualmode: true,
         editable: true,
         rendergridrows: function() {
            return window.rc.ln2RechargeTAdapter.records;
         },
         columns: [
            {text: 'Recharge', datafield: 'recharge', columntype: 'checkbox', width: 70, sortable: false, editable: true},
            {text: 'Requested By', datafield: 'added_by', width: 150, sortable: false, editable: true},
            {text: 'Approved By', datafield: 'apprvd_by', width: 150, sortable: false, editable: true},
            {text: 'Charge Code', datafield: 'charge_code', width: 200, sortable: false, editable: true},
            {text: 'Amount Approved (Litres)', datafield: 'amount_appr', width: 100, sortable: false, editable: true},
            {text: 'Price Per Litre (USD)', datafield: 'price', width: 100, sortable: false, editable: true},
            {
               text: 'Total Cost (USD)', datafield: 'cost', width: 130, sortable: false, editable: true,
               aggregates:[{
                  'Total':function(aggregatedValue, currentValue){
                     var dataInfo = $("#ln2_recharge_table").jqxGrid('getdatainformation');

                     if(totalCostIndex >= dataInfo.rowscount){
                        totalCostIndex= 0;
                     }

                     var rowData = $("#ln2_recharge_table").jqxGrid('getrowdata', totalCostIndex);

                     //currentValue = rowData.price * rowData.amount_appr;
                     //rowData.cost = currentValue;
                     if(rowData.recharge != 0){
                        aggregatedValue += currentValue;
                     }
                     //$("#ln2_recharge_table").jqxGrid('updaterow', totalCostIndex, rowData);
                     totalCostIndex++;
                     return Math.round(aggregatedValue * 100)/100;//round off to two decimal places
                  }
               }]
            }
         ]
      });


      $("#ln2_recharge_table").on('cellendedit', function (event) {
         // column data field.
         var dataField = event.args.datafield;
         // row's bound index.
         var rowBoundIndex = event.args.rowindex;
         // cell value
         var value = event.args.value;
         if(dataField != 'recharge' 
                 && dataField != 'charge_code'
                 && dataField != 'amount_appr'
                 && dataField != 'price'){
            Notification.show({create:true, hide:true, updateText:false, text: "Change will not be reflected in the database", error:true});
         }
         window.rc.updateLN2Items(rowBoundIndex, dataField, value);
         $("#ln2_recharge_table").jqxGrid('refresh');
      });

   }
   
};

Recharges.prototype.initLabelsTable = function(){
   
   var theme = '';
   var url = "mod_ajax.php?page=recharges&do=labels";
   
   var data = {
      action: 'get_recharges'
   };

   //a.id, a.requester, b.project_name, b.charge_code, a.type, c.label_type, a.date as date_printed, a.total as labels_printed, a.copies, price, total

   var source = {
      datatype: 'json',
      datafields: [ 
         {name: 'recharge', type: 'bool'},
         {name: 'id'}, 
         {name: 'project_name'}, 
         {name: 'charge_code'},
         {name: 'date_printed'},
         {name: 'label_type'},
         {name: 'labels_printed'},
         {name: 'price'},
         {name: 'total'}
      ],//make sure you update these fields when you update those of the update fetch
      id: 'id',
      root: 'data',
      async: true,
      url: url, 
      type: 'POST',
      data: data
   };

   window.rc.labelsRechargeTAdapter = new $.jqx.dataAdapter(source);

   var totalCostIndex = 0;
   // create jqxgrid
   if($('#labels_recharge_table :regex(class, jqx\-grid)').length === 0){
      $("#labels_recharge_table").jqxGrid({
         width: 900,
         autoheight: true,
         source: window.rc.labelsRechargeTAdapter,
         columnsresize: true,
         theme: theme,
         showaggregates: true,
         showstatusbar: true,
         statusbarheight: 35,
         sortable: false,
         pageable: false,
         ready: function(){
            window.rc.updateLabelsItems();
         },
         virtualmode: true,
         editable: true,
         rendergridrows: function() {
            return window.rc.labelsRechargeTAdapter.records;
         },
         columns: [
            {text: 'Recharge', datafield: 'recharge', columntype: 'checkbox', width: 70, sortable: false, editable: true},
            {text: 'Date Printed', datafield: 'date_printed', width: 150, sortable: false, editable: true},
            {text: 'Project', datafield: 'project_name', width: 150, sortable: false, editable: true},
            {text: 'Charge Code', datafield: 'charge_code', width: 200, sortable: false, editable: true},
            {text: 'Type', datafield: 'label_type', width: 100, sortable: false, editable: true},
            {text: 'Number', datafield: 'labels_printed', width: 60, sortable: false, editable: true},
            {text: 'Price Per Label (USD)', datafield: 'price', width: 60, sortable: false, editable: true},
            {
               text: 'Total Cost (USD)', datafield: 'total', width: 110, sortable: false, editable: true,
               aggregates:[{
                  'Total':function(aggregatedValue, currentValue){
                     var dataInfo = $("#labels_recharge_table").jqxGrid('getdatainformation');

                     if(totalCostIndex >= dataInfo.rowscount){
                        totalCostIndex= 0;
                     }

                     var rowData = $("#labels_recharge_table").jqxGrid('getrowdata', totalCostIndex);

                     //currentValue = rowData.labels_printed * price;
                     //rowData.total = currentValue;
                     if(rowData.recharge != 0){
                        aggregatedValue += currentValue;
                     }
                     //$("#labels_recharge_table").jqxGrid('updaterow', totalCostIndex, rowData);
                     totalCostIndex++;
                     return Math.round(aggregatedValue * 100)/100;//round off to two decimal places
                  }
               }]
            }
         ]
      });


      $("#labels_recharge_table").on('cellendedit', function (event) {
         // column data field.
         var dataField = event.args.datafield;
         // row's bound index.
         var rowBoundIndex = event.args.rowindex;
         // cell value
         var value = event.args.value;
         if(dataField != 'recharge' 
                 && dataField != 'price'
                 && dataField != 'charge_code'){
            Notification.show({create:true, hide:true, updateText:false, text: "Change will not be reflected in the database", error:true});
         }
         window.rc.updateLabelsItems(rowBoundIndex, dataField, value);
         $("#labels_recharge_table").jqxGrid('refresh');
      });

   }
};

/**
 * This function starts
 */
Recharges.prototype.startDownload = function(url, onLoadFunction){
   $("#hiddenDownloader").remove();
   $('#repository').append("<iframe id='hiddenDownloader' style='display:none;' />");
   $("#hiddenDownloader").attr("src", url);
};