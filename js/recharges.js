/**
 * Constructor for the recharges class
 * @returns {undefined}
 */
var MODE_STORAGE = "storage";
var MODE_LN2 = "ln2";
var MODE_LABELS = "labels";
var MODE_INVENTORY = "inventory";
var MODE_MANAGE_PRICES = "manage_prices";

/**
 * Constructor for the Recharges class. Add code here that you want to run any
 * time an object is initialized
 * 
 * @param {type} mode   Can be value of MODE_STORAGE, MODE_LN2 e.t.c
 * 
 * @returns {Recharges} Returns the instance of Recharges just initialized
 */
function Recharges(mode){
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
   window.rc.priceTables = {
      ln2: undefined,
      storage: undefined,
      labels: undefined
   };
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
         window.rc.updateStorageSpaceTable();
      }
      else if(window.rc.mode == MODE_INVENTORY){
         window.rc.updateInventoryTable();
      }
      else if(window.rc.mode == MODE_LN2){
         window.rc.updateLN2Table();
      }
      else if(window.rc.mode == MODE_LABELS){
         window.rc.updateLabelsTable();
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
   else if(window.rc.mode == MODE_MANAGE_PRICES){
      window.rc.initManagePriceSpecificValues();
   }
}

/**
 * This function runs code that is specific to the MODE_MANAGE_PRICES mode
 * 
 * @returns {undefined}
 */
Recharges.prototype.initManagePriceSpecificValues = function(){
   
   $("#ln2_period_starting").datepicker({dateFormat: 'yy-mm-dd'});
   $("#ln2_period_starting").change(function(){
      $("#ln2_period_ending").val('');
      $("#ln2_period_ending").datepicker('destroy');
      if($("#ln2_period_starting").val().length > 0){   
         $("#ln2_period_ending").datepicker({dateFormat: 'yy-mm-dd', minDate: new Date($("#ln2_period_starting").val())});
      }
      else {
         $("#ln2_period_ending").datepicker({dateFormat: 'yy-mm-dd'});
      }
   });
   $("#ln2_period_ending").datepicker({dateFormat: 'yy-mm-dd'});
   
   $("#labels_period_starting").datepicker({dateFormat: 'yy-mm-dd'});
   $("#labels_period_starting").change(function(){
      $("#labels_period_ending").val('');
      $("#labels_period_ending").datepicker('destroy');
      if($("#labels_period_starting").val().length > 0){   
         $("#labels_period_ending").datepicker({dateFormat: 'yy-mm-dd', minDate: new Date($("#labels_period_starting").val())});
      }
      else {
         $("#labels_period_ending").datepicker({dateFormat: 'yy-mm-dd'});
      }
   });
   $("#labels_period_ending").datepicker({dateFormat: 'yy-mm-dd'});
   
   $("#storage_period_starting").datepicker({dateFormat: 'yy-mm-dd'});
   $("#storage_period_starting").change(function(){
      $("#storage_period_ending").val('');
      $("#storage_period_ending").datepicker('destroy');
      if($("#storage_period_starting").val().length > 0){   
         $("#storage_period_ending").datepicker({dateFormat: 'yy-mm-dd', minDate: new Date($("#storage_period_starting").val())});
      }
      else {
         $("#storage_period_ending").datepicker({dateFormat: 'yy-mm-dd'});
      }
   });
   $("#storage_period_ending").datepicker({dateFormat: 'yy-mm-dd'});
   
   window.rc.initLabelsPricesTable();
   window.rc.initLN2PricesTable();
   window.rc.initStoragePricesTable();
};

/**
 * This function runs code that is specific to the MODE_STORAGE mode
 * 
 * @returns {undefined}
 */
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

/**
 * This function runs code that is specific to the MODE_INVENTORY mode
 * 
 * @returns {undefined}
 */
Recharges.prototype.initInventorySpecificValues = function(){
   console.log("init inventory specific stuff called");
   window.rc.initInventoryTable();
   $("#inventory_period_starting").change(function(){
      window.rc.updateInventoryTable();
   });
   $("#inventory_period_ending").change(function(){
      window.rc.updateInventoryTable();
   });
};

/**
 * This function runs code that is specific to the MODE_LN2 mode
 * 
 * @returns {undefined}
 */
Recharges.prototype.initLN2SpecificValues = function(){
   window.rc.initLN2Table();
   $("#ln2_period_starting").change(function(){
      window.rc.updateLN2Table();
   });
   $("#ln2_period_ending").change(function(){
      window.rc.updateLN2Table();
   });
};

/**
 * This function runs code that is specific to the MODE_LABELS mode
 * 
 * @returns {undefined}
 */
Recharges.prototype.initLabelsSpecificValues = function(){
   window.rc.initLabelsTable();
   $("#labels_period_starting").change(function(){
      window.rc.updateLabelsTable();
   });
   $("#labels_period_ending").change(function(){
      window.rc.updateLabelsTable();
   });
};

/**
 * This function either inits the JQXGrid on the Recharge Storage Space page or
 * rerenders the table if it's already initialized
 * 
 * @returns {undefined}
 */
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
   if(window.rc.mode != MODE_MANAGE_PRICES){
      $("#recharge_dialog").css('left', (window.innerWidth / 2) - ($("#recharge_dialog").width() / 2) + "px");
      $("#recharge_dialog").css('top', (window.innerHeight / 2) - ($("#recharge_dialog").height() / 2) + "px");
   }
};

/**
 * This function the JQXGrid table in the Recharge LN2 Page
 * @returns {undefined}
 */
Recharges.prototype.initLN2PricesTable = function(){
   var theme = '';
   var url = "mod_ajax.php?page=recharges&do=manage_prices";
   
   var data = {
      action: 'get_ln2_prices'
   };

   var source = {
      datatype: 'json',
      datafields: [ 
         {name: 'id'},
         {name: 'start_date'},
         {name: 'end_date'},
         {name: 'price'}
      ],//make sure you update these fields when you update those of the update fetch
      id: 'id',
      root: 'data',
      async: true,
      url: url, 
      type: 'POST',
      data: data
   };

   window.rc.priceTables.ln2 = new $.jqx.dataAdapter(source);

   // create jqxgrid
   if($('#ln2_prices_table :regex(class, jqx\-grid)').length === 0){
      $("#ln2_prices_table").jqxGrid({
         width: 400,
         autoheight: true,
         source: window.rc.priceTables.ln2,
         columnsresize: true,
         theme: theme,
         sortable: false,
         pageable: false,
         virtualmode: true,
         editable: true,
         rendergridrows: function() {
            return window.rc.priceTables.ln2.records;
         },
         columns: [
            {text: 'Period Starting', datafield: 'start_date', width: 150, sortable: false, editable: true},
            {text: 'Period Ending', datafield: 'end_date', width: 150, sortable: false, editable: true},
            {text: 'Price (USD)', datafield: 'price', width: 100, sortable: false, editable: true}
         ]
      });


      $("#ln2_prices_table").on('cellendedit', function (event) {
         // column data field.
         var dataField = event.args.datafield;
         // row's bound index.
         var rowBoundIndex = event.args.rowindex;
         // cell value
         var value = event.args.value;

         //TODO: update database
      });
   }
};

/**
 * This function initialized the JQXGrid table in the Recharge Storage Space page
 * @returns {undefined}
 */
Recharges.prototype.initStoragePricesTable = function(){
   var theme = '';
   var url = "mod_ajax.php?page=recharges&do=manage_prices";
   
   var data = {
      action: 'get_storage_prices'
   };

   var source = {
      datatype: 'json',
      datafields: [ 
         {name: 'id'},
         {name: 'start_date'},
         {name: 'end_date'},
         {name: 'price'}
      ],//make sure you update these fields when you update those of the update fetch
      id: 'id',
      root: 'data',
      async: true,
      url: url, 
      type: 'POST',
      data: data
   };

   window.rc.priceTables.storage = new $.jqx.dataAdapter(source);

   // create jqxgrid
   if($('#storage_prices_table :regex(class, jqx\-grid)').length === 0){
      $("#storage_prices_table").jqxGrid({
         width: 400,
         autoheight: true,
         source: window.rc.priceTables.storage,
         columnsresize: true,
         theme: theme,
         sortable: false,
         pageable: false,
         virtualmode: true,
         editable: true,
         rendergridrows: function() {
            return window.rc.priceTables.storage.records;
         },
         columns: [
            {text: 'Period Starting', datafield: 'start_date', width: 150, sortable: false, editable: true},
            {text: 'Period Ending', datafield: 'end_date', width: 150, sortable: false, editable: true},
            {text: 'Price (USD)', datafield: 'price', width: 100, sortable: false, editable: true}
         ]
      });


      $("#storage_prices_table").on('cellendedit', function (event) {
         // column data field.
         var dataField = event.args.datafield;
         // row's bound index.
         var rowBoundIndex = event.args.rowindex;
         // cell value
         var value = event.args.value;

         //TODO: update database
      });
   }
};

/**
 * This function initialized the JQXGrid table in the Recharge Labels page
 * @returns {undefined}
 */
Recharges.prototype.initLabelsPricesTable = function(){
   var theme = '';
   var url = "mod_ajax.php?page=recharges&do=manage_prices";
   
   var data = {
      action: 'get_labels_prices'
   };

   var source = {
      datatype: 'json',
      datafields: [ 
         {name: 'id'},
         {name: 'label_type'},
         {name: 'start_date'},
         {name: 'end_date'},
         {name: 'price'}
      ],//make sure you update these fields when you update those of the update fetch
      id: 'id',
      root: 'data',
      async: true,
      url: url, 
      type: 'POST',
      data: data
   };

   window.rc.priceTables.labels = new $.jqx.dataAdapter(source);

   // create jqxgrid
   if($('#labels_prices_table :regex(class, jqx\-grid)').length === 0){
      $("#labels_prices_table").jqxGrid({
         width: 600,
         autoheight: true,
         source: window.rc.priceTables.labels,
         columnsresize: true,
         theme: theme,
         sortable: false,
         pageable: false,
         virtualmode: true,
         editable: true,
         rendergridrows: function() {
            return window.rc.priceTables.labels.records;
         },
         columns: [
            {text: 'Label Type', datafield: 'label_type', width: 200, sortable: false, editable: true},
            {text: 'Period Starting', datafield: 'start_date', width: 150, sortable: false, editable: true},
            {text: 'Period Ending', datafield: 'end_date', width: 150, sortable: false, editable: true},
            {text: 'Price (USD)', datafield: 'price', width: 100, sortable: false, editable: true}
         ]
      });


      $("#labels_prices_table").on('cellendedit', function (event) {
         // column data field.
         var dataField = event.args.datafield;
         // row's bound index.
         var rowBoundIndex = event.args.rowindex;
         // cell value
         var value = event.args.value;

         //TODO: update database
      });
   }
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
            sortable: true,
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
               {text: 'Project', datafield: 'project', width: 200, sortable: true, editable: false},
               {text: 'Last Recharge', datafield: 'last_period', width: 150, sortable: true, editable: false},
               {text: 'Duration (days)', datafield: 'duration', width: 100, sortable: false, editable: false},
               {text: 'Cost Per Year (USD)', datafield: 'box_price', width: 100, sortable: false, editable: false},
               {
                  text: 'No. Boxes', datafield: 'no_boxes', width: 75, sortable: false, editable: false,
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
                  text: 'Total Cost (USD)', datafield: 'total_price', width: 175, sortable: false, editable: false,
                  aggregates:[{
                     'Total':function(aggregatedValue, currentValue){
                        var dataInfo = $("#space_recharge_table").jqxGrid('getdatainformation');
                        
                        if(totalPriceIndex >= dataInfo.rowscount){
                           totalPriceIndex = 0;
                        }
                        
                        var rowData = $("#space_recharge_table").jqxGrid('getrowdata', totalPriceIndex);
                        console.log(rowData);
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
         
         $("#space_recharge_table").bind("sort", function(event){
            window.rc.updateStorageSpaceTable();
         });
      }
   }
   
};

/**
 * This function rerenders the JQXGrid table in the Recharge Storage Space page
 * 
 * @returns {undefined}
 */
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
            $("#space_recharge_table").jqxGrid('setcellvalue', rowIndex, "total_price", rowData.total_price);
            $("#space_recharge_table").jqxGrid('setcellvalue', rowIndex, "box_price", rowData.box_price);
         }
      }
      
      //$("#space_recharge_table").jqxGrid('updaterow', rowIndex, rowData);
      if(rowData.recharge == 1){
         window.rc.space.projects.push({project_id:rowData.project_id, box_price:rowData.box_price});
      }
   }
};

/**
 * This function rerenders the JQXGrid table in the Recharge Inventory page
 * 
 * @returns {undefined}
 */
Recharges.prototype.updateInventoryItems = function(rowBoundIndex, dataField, value){
   var dataInfo = $("#inventory_recharge_table").jqxGrid('getdatainformation');
   
   window.rc.inventory.items = new Array();
   for(var rowIndex = 0; rowIndex < dataInfo.rowscount; rowIndex++){
      var rowData = $("#inventory_recharge_table").jqxGrid('getrowdata', rowIndex);
      if(typeof rowBoundIndex != 'undefined' && rowBoundIndex == rowIndex){
         if(dataField == 'quantity'){
            rowData.total = value * rowData.pp_unit;
            $("#inventory_recharge_table").jqxGrid('setcellvalue', rowIndex, "total", rowData.total);
         }
         else if(dataField == 'pp_unit'){
            rowData.total = rowData.quantity * value;
            $("#inventory_recharge_table").jqxGrid('setcellvalue', rowIndex, "total", rowData.total);
         }
      }
      
      //$("#inventory_recharge_table").jqxGrid('updaterow', rowIndex, rowData);
      if(rowData.recharge == 1){
         //window.rc.inventory.items.push(rowData);
         window.rc.inventory.items.push({
            charge_code: rowData.charge_code,
            date_issued: rowData.date_issued,
            id: rowData.id,
            issued_by: rowData.issued_by,
            issued_to: rowData.issued_to,
            item: rowData.item,
            pp_unit: rowData.pp_unit,
            quantity: rowData.quantity,
            recharge: rowData.recharge,
            total: rowData.total,
            uid: rowData.uid
         });
      }
   }
};

/**
 * This function rerenders the JQXGrid table in the Recharge Liquid Nitrogen page
 * 
 * @returns {undefined}
 */
Recharges.prototype.updateLN2Items = function(rowBoundIndex, dataField, value){
   var dataInfo = $("#ln2_recharge_table").jqxGrid('getdatainformation');
   
   window.rc.ln2.items = new Array();
   for(var rowIndex = 0; rowIndex < dataInfo.rowscount; rowIndex++){
      var rowData = $("#ln2_recharge_table").jqxGrid('getrowdata', rowIndex);
      if(typeof rowBoundIndex != 'undefined' && rowBoundIndex == rowIndex){
         if(dataField == 'price'){
            rowData.cost = value * rowData.amount_appr;
            $("#ln2_recharge_table").jqxGrid('setcellvalue', rowIndex, "cost", rowData.cost);
         }
         else if(dataField == 'amount_appr'){
            rowData.cost = rowData.price * value;
            $("#ln2_recharge_table").jqxGrid('setcellvalue', rowIndex, "cost", rowData.cost);
         }
      }
      
      //$("#ln2_recharge_table").jqxGrid('updaterow', rowIndex, rowData);
      if(rowData.recharge == 1){
         window.rc.ln2.items.push({id: rowData.id, amount_appr: rowData.amount_appr, charge_code: rowData.charge_code, price: rowData.price});
      }
   }
};

/**
 * This function updates the window.rc.labels.items object
 * 
 * @returns {undefined}
 */
Recharges.prototype.updateLabelsItems = function(rowBoundIndex, dataField, value){
   var dataInfo = $("#labels_recharge_table").jqxGrid('getdatainformation');
   
   window.rc.labels.items = new Array();
   for(var rowIndex = 0; rowIndex < dataInfo.rowscount; rowIndex++){
      var rowData = $("#labels_recharge_table").jqxGrid('getrowdata', rowIndex);
      if(typeof rowBoundIndex != 'undefined' && rowBoundIndex == rowIndex){
         if(dataField == 'price'){
            rowData.total = value * rowData.labels_printed;
            $("#labels_recharge_table").jqxGrid('setcellvalue', rowIndex, "total", value * rowData.total);
         }
         else if(dataField == 'labels_printed'){
            //rowData.total = rowData.price * value;
         }
      }
      
      /*$("#labels_recharge_table").jqxGrid('updaterow', rowIndex, {
         'recharge': rowData.recharge,
         'id': rowData.id, 
         'project_name': rowData.project_name, 
         'charge_code': rowData.charge_code,
         'date_printed': rowData.date_printed,
         'label_type': rowData.label_type,
         'labels_printed': rowData.labels_printed,
         'price': rowData.price,
         'total': rowData.total
      });*/
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
 * This function updates the already initiated labels recharging table
 * 
 * @returns {undefined}
 */
Recharges.prototype.updateLabelsTable = function(){
   var data = {
      action: 'get_recharges',
      period_starting: $("#labels_period_starting").val(),
      period_ending:$("#labels_period_ending").val()
   };

   var url = "mod_ajax.php?page=recharges&do=labels";
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
      ],//make sure you update these fields when you update those for the initial fetch
      id: 'id',
      root: 'data',
      async: true,
      url: url, 
      type: 'POST',
      data: data
   };

   window.rc.labelsRechargeTAdapter = new $.jqx.dataAdapter(source);
   $("#labels_recharge_table").jqxGrid({source: window.rc.labelsRechargeTAdapter});
};

/**
 * This function updates the already initiated labels recharging table
 * 
 * @returns {undefined}
 */
Recharges.prototype.updateInventoryTable = function(){
   var data = {
      action: 'get_recharges',
      period_starting: $("#inventory_period_starting").val(),
      period_ending:$("#inventory_period_ending").val()
   };

   var url = "mod_ajax.php?page=recharges&do=inventory";
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
      ],//make sure you update these fields when you update those for the initial fetch
      id: 'id',
      root: 'data',
      async: true,
      url: url, 
      type: 'POST',
      data: data
   };

   window.rc.inventoryRechargeTAdapter = new $.jqx.dataAdapter(source);
   $("#inventory_recharge_table").jqxGrid({source: window.rc.inventoryRechargeTAdapter});
};

/**
 * This function updates the already initiated labels recharging table
 * 
 * @returns {undefined}
 */
Recharges.prototype.updateLN2Table = function(){
   var data = {
      action: 'get_recharges',
      period_starting: $("#ln2_period_starting").val(),
      period_ending:$("#ln2_period_ending").val()
   };

   var url = "mod_ajax.php?page=recharges&do=ln2";
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
         {name: 'cost'},
         {name: 'date_requested'}
      ],//make sure you update these fields when you update those for the initial fetch
      id: 'id',
      root: 'data',
      async: true,
      url: url, 
      type: 'POST',
      data: data
   };

   window.rc.ln2RechargeTAdapter = new $.jqx.dataAdapter(source);
   $("#ln2_recharge_table").jqxGrid({source: window.rc.ln2RechargeTAdapter});
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
                  //$("#space_recharge_table").jqxGrid('updatebounddata');
                  window.rc.updateStorageSpaceTable();
                  $("#recharge_dialog").hide();
                  $("#confirm_recharge_btn").removeAttr('disabled');
                  
                  var json = jQuery.parseJSON(data);
                  if(json.error == true){
                     Notification.show({create:true, hide:true, updateText:false, text: json.error_message, error:true});
                  }
                  else {
                     Notification.show({create:true, hide:true, updateText:false, text: "An email has been sent to the Biorepository manager with details on the recharge", error:false});
                  }
               },
               error:function(){
                  window.rc.updateStorageSpaceTable();
                  $("#confirm_recharge_btn").removeAttr('disabled');
                  Notification.show({create:true, hide:true, updateText:false, text: "An error occurred while trying to connect to the server. Please try again", error:true});
               }
            });
         }
      }
   }
};

/**
 * This function starts an AJAX request for submitting an inventory recharge
 * 
 * @returns {undefined}
 */
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
            window.rc.updateInventoryTable();
            $("#recharge_dialog").hide();
            $("#confirm_recharge_btn").removeAttr('disabled');
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
            window.rc.updateInventoryTable();
            $("#confirm_recharge_btn").removeAttr('disabled');
            Notification.show({create:true, hide:true, updateText:false, text: "An error occurred while trying to connect to the server. Please try again", error:true});
         }
      });
   }
};

/**
 * This function starts an AJAX request for submitting a liquid nitrogen recharge
 * 
 * @returns {undefined}
 */
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
            window.rc.updateLN2Table();
            $("#recharge_dialog").hide();
            $("#confirm_recharge_btn").removeAttr('disabled');
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
            window.rc.updateLN2Table();
            $("#confirm_recharge_btn").removeAttr('disabled');
            Notification.show({create:true, hide:true, updateText:false, text: "An error occurred while trying to connect to the server. Please try again", error:true});
         }
      });
   }
   
};

/**
 * This function starts an AJAX request for submitting a labels recharge
 * 
 * @returns {undefined}
 */
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
            window.rc.updateLabelsTable();
            $("#recharge_dialog").hide();
            $("#confirm_recharge_btn").removeAttr('disabled');
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
            window.rc.updateLabelsTable();
            $("#confirm_recharge_btn").removeAttr('disabled');
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
         sortable: true,
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
            {text: 'Item', datafield: 'item', width: 100, sortable: true, editable: false},
            {text: 'Issued By', datafield: 'issued_by', width: 100, sortable: true, editable: false},
            {text: 'Issued To', datafield: 'issued_to', width: 100, sortable: true, editable: false},
            {text: 'Date of Issue', datafield: 'date_issued', width: 100, sortable: true, editable: false},
            {text: 'Charge Code', datafield: 'charge_code', width: 220, sortable: true, editable: false},
            {text: 'Quantity', datafield: 'quantity', width: 70, sortable: true, editable: false},
            {text: 'Unit Price(USD)', datafield: 'pp_unit', width: 70, sortable: false, editable: false},
            {
               text: 'Total Cost (USD)', datafield: 'total', width: 70, sortable: false, editable: false,
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
         //rc_charge_code = :charge_code rc_timestamp = now()
         if(dataField != 'recharge' 
                 /*&& dataField != 'pp_unit' 
                 && dataField != 'quantity'
                 && dataField != 'item'*/
                 && dataField != 'charge_code'){
            Notification.show({create:true, hide:true, updateText:false, text: "Change will not be reflected in the database", error:true});
         }
         window.rc.updateInventoryItems(rowBoundIndex, dataField, value);
         $("#inventory_recharge_table").jqxGrid('refresh');
      });
      
      $("#inventory_recharge_table").bind("sort", function(event){
         window.rc.updateInventoryTable();
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
         {name: 'cost'},
         {name: 'date_requested'}
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
         sortable: true,
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
            {text: 'Requested By', datafield: 'added_by', width: 150, sortable: true, editable: false},
            {text: 'Request Date', datafield: 'date_requested', width: 100, sortable: true, editable: false},
            {text: 'Approved By', datafield: 'apprvd_by', width: 100, sortable: true, editable: false},
            {text: 'Charge Code', datafield: 'charge_code', width: 200, sortable: true, editable: false},
            {text: 'Amount Approved (Litres)', datafield: 'amount_appr', width: 75, sortable: true, editable: false},
            {text: 'Price Per Litre (USD)', datafield: 'price', width: 75, sortable: false, editable: false},
            {
               text: 'Total Cost (USD)', datafield: 'cost', width: 130, sortable: false, editable: false,
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
         //rc_charge_code = :charge_code, rc_price = :price
         if(dataField != 'recharge' 
                 && dataField != 'charge_code'
                 //&& dataField != 'amount_appr'
                 && dataField != 'price'){
            Notification.show({create:true, hide:true, updateText:false, text: "Change will not be reflected in the database", error:true});
         }
         window.rc.updateLN2Items(rowBoundIndex, dataField, value);
         $("#ln2_recharge_table").jqxGrid('refresh');
      });

      $("#ln2_recharge_table").bind("sort", function(event){
         window.rc.updateLN2Table();
      });
   }
};

/**
 * This function initializes the JQXGrid table in the Recharge Labels page
 * 
 * @returns {undefined}
 */
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
         sortable: true,
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
            {text: 'Date Printed', datafield: 'date_printed', width: 150, sortable: true, editable: false},
            {text: 'Project', datafield: 'project_name', width: 150, sortable: true, editable: false},
            {text: 'Charge Code', datafield: 'charge_code', width: 200, sortable: true, editable: false},
            {text: 'Type', datafield: 'label_type', width: 100, sortable: true, editable: false},
            {text: 'Number', datafield: 'labels_printed', width: 60, sortable: true, editable: false},
            {text: 'Price Per Label (USD)', datafield: 'price', width: 60, sortable: false, editable: false},
            {
               text: 'Total Cost (USD)', datafield: 'total', width: 110, sortable: false, editable: false,
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
      
      $("#labels_recharge_table").bind("sort", function(event){
         window.rc.updateLabelsTable();
      });
   }
};

/**
 * This function validates fields related to labels prices in the Manage Recharge
 * Prices page
 * 
 * @returns {Boolean}   TRUE if everything is fine
 */
Recharges.prototype.validateLabelsPrices = function(){
   if($("#labels_type").val().length == 0){
      Notification.show({create:true, hide:true, updateText:false, text: "Set the type of label", error:true});
      $("#labels_type").focus();
      return false;
   }
   if($("#labels_period_starting").val().length == 0){
      Notification.show({create:true, hide:true, updateText:false, text: "Set the period starting date", error:true});
      $("#labels_period_starting").focus();
      return false;
   }
   if($("#labels_period_ending").val().length == 0){
      Notification.show({create:true, hide:true, updateText:false, text: "Set the period ending date", error:true});
      $("#labels_period_ending").focus();
      return false;
   }
   if($("#labels_price").val().length == 0){
      Notification.show({create:true, hide:true, updateText:false, text: "Set the price", error:true});
      $("#labels_price").focus();
      return false;
   }
   else if(isNaN($("#labels_price").val())){
      Notification.show({create:true, hide:true, updateText:false, text: "Enter valid price", error:true});
      $("#labels_price").focus();
      return false;
   }
   return true;
};

/**
 * This function validates fields related to storage space prices in the Manage Recharge
 * Prices page
 * 
 * @returns {Boolean}   TRUE if everything is fine
 */
Recharges.prototype.validateStoragePrices = function(){
   if($("#storage_period_starting").val().length == 0){
      Notification.show({create:true, hide:true, updateText:false, text: "Set the period starting date", error:true});
      $("#storage_period_starting").focus();
      return false;
   }
   if($("#storage_period_ending").val().length == 0){
      Notification.show({create:true, hide:true, updateText:false, text: "Set the period ending date", error:true});
      $("#storage_period_ending").focus();
      return false;
   }
   if($("#storage_price").val().length == 0){
      Notification.show({create:true, hide:true, updateText:false, text: "Set the price", error:true});
      $("#storage_price").focus();
      return false;
   }
   else if(isNaN($("#storage_price").val())){
      Notification.show({create:true, hide:true, updateText:false, text: "Enter a valid price", error:true});
      $("#storage_price").focus();
      return false;
   }
   return true;
};

/**
 * This function validates fields related to liquid nitrogen prices in the Manage Recharge
 * Prices page
 * 
 * @returns {Boolean}   TRUE if everything is fine
 */
Recharges.prototype.validateLN2Prices = function(){
   if($("#ln2_period_starting").val().length == 0){
      Notification.show({create:true, hide:true, updateText:false, text: "Set the period starting date", error:true});
      $("#ln2_period_starting").focus();
      return false;
   }
   if($("#ln2_period_ending").val().length == 0){
      Notification.show({create:true, hide:true, updateText:false, text: "Set the period ending date", error:true});
      $("#ln2_period_ending").focus();
      return false;
   }
   if($("#ln2_price").val().length == 0){
      Notification.show({create:true, hide:true, updateText:false, text: "Set the price", error:true});
      $("#ln2_price").focus();
      return false;
   }
   else if(isNaN($("#ln2_price").val())){
      Notification.show({create:true, hide:true, updateText:false, text: "Enter a valid price", error:true});
      $("#ln2_price").focus();
      return false;
   }
   return true;
};