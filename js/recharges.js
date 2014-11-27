/**
 * Constructor for the recharges class
 * @returns {undefined}
 */
function Recharges(){
   window.rc = this;
   
   //init the data objects
   window.rc.space = {
      projects:new Array(),//this stores the IDs of projects to be included in the recharge
      updateTimeoutID:undefined
   };
   
   window.rc.spaceRechargeTAdapter = undefined;
   
   //initialize the event handlers
   window.rc.windowResized();//call it the first time
   $(window).resize(function(){
      window.rc.windowResized();
   });
   
   $("#recharge_storage_btn").click(function(){
      window.rc.updateSpaceRechargeProjects();
      
      if(window.rc.space.projects.length > 0){
         $("#recharge_storage_dialog").show();
      }
   });
   
   $("#recharge_storage_dialog_close").click(function(){
      $("#space_recharge_table").jqxGrid('updatebounddata');
      $("#recharge_storage_dialog").hide();
      $("#confirm_recharge_storage_btn").removeAttr('disabled');
   });
   
   $("#confirm_recharge_storage_btn").click(function(){
      window.rc.submitSpaceRecharge();
   });
   
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
}

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
   $("#recharge_storage_dialog").css('left', (window.innerWidth / 2) - ($("#recharge_storage_dialog").width() / 2) + "px");
   $("#recharge_storage_dialog").css('top', (window.innerHeight / 2) - ($("#recharge_storage_dialog").height() / 2) + "px");
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
         data: data,
         beforeprocessing: function (data){
            console.log(data);
            if(data.data.length > 0)
               source.totalrecords = data.data[0].total_row_count;
            else
               source.totalrecords = 0;
         }
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
         data: data,
         beforeprocessing: function (data){
            console.log(data);
            if(data.data.length > 0){
               source.totalrecords = data.data[0].total_row_count;
               //console.log(source.totalrecords);
            }

            else
               source.totalrecords = 0;
         }
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
         var url = "mod_ajax.php?page=recharges&do=space&action=submit_recharge";
         url += "&project_ids="+window.rc.space.projects.join(",");
         url += "&period_ending="+$("#period_ending").val();
         url += "&price="+$("#price").val();
         
         $("#confirm_recharge_storage_btn").attr('disabled', 'disabled');
         window.rc.startDownload(url);
      }
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