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
   
   window.rc.spaceRechargeTAdapter = undefined;
   window.rc.inventoryRechargeTAdapter = undefined;
   
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
   });
   
   $("#recharge_dialog_close").click(function(){
      if(window.rc.mode == MODE_STORAGE){
         $("#space_recharge_table").jqxGrid('updatebounddata');
      }
      else if(window.rc.mode == MODE_INVENTORY){
         $("#inventory_recharge_table").jqxGrid('updatebounddata');
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
   });
   
   
   //do mode specific inits
   if(window.rc.mode == MODE_STORAGE){
      window.rc.initStorageSpecificValues();
   }
   else if(window.rc.mode == MODE_INVENTORY){
      window.rc.initInventorySpecificValues();
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
               {text: 'Project', datafield: 'project', width: 250, sortable: false, editable: true},
               {text: 'Last Recharge', datafield: 'last_period', width: 150, sortable: false, editable: true},
               {text: 'Duration (days)', datafield: 'duration', width: 100, sortable: false, editable: true},
               {
                  text: 'No. Boxes', datafield: 'no_boxes', width: 150, sortable: false, editable: true,
                  aggregates:[{
                     'Number':function(aggregatedValue, currentValue){
                        var dataInfo = $("#space_recharge_table").jqxGrid('getdatainformation');
                        
                        if(noBoxesIndex >= dataInfo.rowscount){
                           noBoxesIndex = 0;
                        }
                        
                        var rowData = $("#space_recharge_table").jqxGrid('getrowdata', noBoxesIndex);
                        
                        if(typeof rowData != 'undefined' && rowData.recharge != 0){
                           aggregatedValue += currentValue;
                        }
                        
                        noBoxesIndex++;
                        return aggregatedValue;
                     }
                  }]
               },
               {
                  text: 'Total Cost (USD)', datafield: 'total_price', width: 150, sortable: false, editable: true,
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
            $("#space_recharge_table").jqxGrid('refresh');
            
            window.rc.updateSpaceRechargeProjects();
         });
         
      }
   }
   
};

Recharges.prototype.updateSpaceRechargeProjects = function (){
   var dataInfo = $("#space_recharge_table").jqxGrid('getdatainformation');
            
   window.rc.space.projects = new Array();
   for(var rowIndex = 0; rowIndex < dataInfo.rowscount; rowIndex++){
      var rowData = $("#space_recharge_table").jqxGrid('getrowdata', rowIndex);
      if(rowData.recharge == 1){
         window.rc.space.projects.push(rowData.project_id);
      }
   }
};

Recharges.prototype.updateInventoryItems = function(){
   var dataInfo = $("#inventory_recharge_table").jqxGrid('getdatainformation');
   
   window.rc.inventory.items = new Array();
   for(var rowIndex = 0; rowIndex < dataInfo.rowscount; rowIndex++){
      var rowData = $("#inventory_recharge_table").jqxGrid('getrowdata', rowIndex);
      if(rowData.recharge == 1){
         window.rc.inventory.items.push(rowData);
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
         /*var url = "mod_ajax.php?page=recharges&do=space&action=submit_recharge";
         url += "&project_ids="+window.rc.space.projects.join(",");
         url += "&period_ending="+$("#period_ending").val();
         url += "&price="+$("#price").val();*/
         
         $("#confirm_recharge_btn").attr('disabled', 'disabled');
         //window.rc.startDownload(url);
         
         var data = {
            "action":"submit_recharge",
            "project_ids":window.rc.space.projects.join(","),
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

/**
 * This function populates the jq table for storage space
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


      $("#inventory_recharge_table").on('cellendedit', function (event) {
         $("#space_recharge_table").jqxGrid('refresh');
         window.rc.updateInventoryItems();
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