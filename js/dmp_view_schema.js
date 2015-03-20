function DMPVSchema(server, user, session, project) {
   window.dvs = this;
   window.dvs.server = server;
   window.dvs.user = user;
   window.dvs.session = session;
   window.dvs.project = project;
   window.dvs.schema = null;
   window.dvs.sheetListAdapter = null;
   window.dvs.columnGridAdapter = null;
   window.dvs.schemaChanges={};
   window.dvs.columnDictionary={};//object storing the original and new column names
   window.dvs.leftSideWidth = 0;
   window.dvs.rightSideWidth = 0;
   window.dvs.lastSavePoint = null;
   //initialize source for project_list_box
   $(document).ready(function(){
      window.dvs.documentReady();
   });
   
   if(window.dvs.project == null || window.dvs.project.length == 0) {//if project is not set
      //show create project popup
      $("#new_project_wndw").show();
   }
}

/**
 * This function initializes resources that need to be initialized after the DOM
 * has fully loaded
 * @returns {undefined}
 */
DMPVSchema.prototype.documentReady = function() {
   var pWidth = window.innerWidth*0.942;//split_window width
   window.dvs.leftSideWidth = pWidth*0.2;//30% of split_window
   window.dvs.rightSideWidth = pWidth - window.dvs.leftSideWidth;
   $("#new_project_wndw").jqxWindow({height: 350, width: 600, theme: ''});
   $("#manual_file_upload").jqxFileUpload({width:500, fileInputName: "file"});
   $("#split_window").jqxSplitter({  width: pWidth, height: 600, panels: [{ size: window.dvs.leftSideWidth, min: '10%' }, {size: window.dvs.rightSideWidth, min: '50%'}] });
   $("#loading_box").css("top", (window.innerHeight/2 - (window.innerHeight*0.1))-($("#loading_box").height()/2)+"px");
   $("#loading_box").css("left", (window.innerWidth/2)-($("#loading_box").width()/2)+"px");
   $("#inotification_pp").jqxNotification({position: "top-right", opacity: 0.9, autoOpen: false, autoClose: true, template:"info"});
   $("#enotification_pp").jqxNotification({position: "top-right", opacity: 0.9, autoOpen: false, autoClose: false, template:"error"});
   window.dvs.initSheetList();
   window.dvs.initColumnGrid();
   window.dvs.initFileDropArea();
   $("#cancel_btn").click(window.dvs.cancelButtonClicked);
   $("#update_btn").click(window.dvs.updateButtonClicked);
   $("#menu_bar").jqxMenu();
   window.dvs.refreshSavePoints();
};

DMPVSchema.prototype.cancelButtonClicked = function() {
   console.log("Cancel button clicked");
   window.dvs.schemaChanges = {};
   window.dvs.columnDictionary = {};
   if(window.dvs.schema != null) {
      var sheetIndex = $("#sheets").jqxListBox('selectedIndex');
      window.dvs.updateColumnGrid(window.dvs.schema.sheets[sheetIndex]);
   }
   $("#cancel_btn").prop('disabled', true);
   $("#update_btn").prop('disabled', true);
};

DMPVSchema.prototype.refreshSavePoints = function() {
   console.log("refreshing save points");
   var sData = JSON.stringify({"workflow_id": window.dvs.project});
   var sToken = JSON.stringify({
      "server":window.dvs.server,
      "user": window.dvs.user,
      "session": window.dvs.session
   });
   $.ajax({
      url: "mod_ajax.php?page=odk_workflow&do=get_save_points",
      type: "POST",
      async: true,
      data: {data: sData, token: sToken},
      statusCode: {
         400: function() {//bad request
            $("#enotification_pp").html("Could not fetch save points");
            $("#enotification_pp").jqxNotification("open");
         },
         403: function() {//forbidden
            $("#enotification_pp").html("User not allowed to fetch save points");
            $("#enotification_pp").jqxNotification("open");
         }
      },
      success: function(jsonResult, textStatus, jqXHR){
         if(jsonResult !== null) {
            if(jsonResult.status.healthy == true) {
               var html = "";
               var savePoints = jsonResult.save_points;
               for(var index = 0; index < savePoints.length; index++) {
                  html = html + "<li id='"+savePoints[index].filename+"'>"+savePoints[index].time_created+"</li>";
               }
               $("#undo_container").html(html);
               $("#undo_container li").css("cursor", "pointer");
               $("#undo_container li").click(function(){
                  window.dvs.restoreSavePoint($(this).attr("id"));
               });
            }
            else if(jsonResult.status.healthy == false) {
               $("#enotification_pp").html("Could not fetch save points");
               $("#enotification_pp").jqxNotification("open");
            }
         }
         else {
            $("#enotification_pp").html("Could not fetch save points");
            $("#enotification_pp").jqxNotification("open");
         }
      },
      complete: function() {
         $("#loading_box").hide();
      }
   });
};

DMPVSchema.prototype.restoreSavePoint = function(savePoint) {
   var sData = JSON.stringify({
      "workflow_id": window.dvs.project,
      "save_point":savePoint
   });
   var sToken = JSON.stringify({
      "server":window.dvs.server,
      "user": window.dvs.user,
      "session": window.dvs.session
   });
   $("#loading_box").show();
   $.ajax({
      url: "mod_ajax.php?page=odk_workflow&do=restore_save_point",
      type: "POST",
      async: true,
      data: {data: sData, token: sToken},
      statusCode: {
         400: function() {//bad request
            $("#enotification_pp").html("Could not undo changes");
            $("#enotification_pp").jqxNotification("open");
         },
         403: function() {//forbidden
            $("#enotification_pp").html("User not allowed to undo changes");
            $("#enotification_pp").jqxNotification("open");
         }
      },
      success: function(jsonResult, textStatus, jqXHR){
         if(jsonResult !== null) {
            if(jsonResult.status.healthy == true) {
               window.dvs.updateSheetList();
               window.dvs.refreshSavePoints();
               $("#inotification_pp").html("Changes undone");
               $("#inotification_pp").jqxNotification("open");
            }
            else if(jsonResult.status.healthy == false) {
               $("#enotification_pp").html("Could not undo changes");
               $("#enotification_pp").jqxNotification("open");
            }
         }
         else {
            $("#enotification_pp").html("Could not fetch save points");
            $("#enotification_pp").jqxNotification("open");
         }
      },
      complete: function() {
         $("#loading_box").hide();
      }
   });
};

DMPVSchema.prototype.updateButtonClicked = function() {
   console.log("Update button clicked");
   //go through each and every changed sheet
   $("#loading_box").html("Loading. Please don't close this tab");
   $("#loading_box").show();
   var canContinue = true;
   var isFirstColumn = true;
   
   $.each(window.dvs.schemaChanges, function(sheetName, columns){
      if(canContinue) {
         $.each(columns, function(columnName, details){
            if(canContinue) {
               if(details.present == true) {
                  details["delete"] = false;
               }
               else {
                  details["delete"] = true;
               }
               
               var sData = JSON.stringify({
                     "workflow_id": window.dvs.project,
                     "sheet":sheetName,
                     "column":details
               });
               var sToken = JSON.stringify({
                     "server": window.dvs.server,
                     "user": window.dvs.user,
                     "session": window.dvs.session
               });

               $.ajax({
                  url: "mod_ajax.php?page=odk_workflow&do=alter_field",
                  type: "POST",
                  async: false,
                  data: {data: sData, token: sToken},
                  statusCode: {
                     400: function() {//bad request
                        $("#enotification_pp").html("Mulformed data provided");
                        $("#enotification_pp").jqxNotification("open");
                        canContinue = false;
                     },
                     403: function() {//forbidden
                        $("#enotification_pp").html("User not allowed to perform action");
                        $("#enotification_pp").jqxNotification("open");
                        canContinue = false;
                     }
                  },
                  success: function(jsonResult, textStatus, jqXHR){
                     if(jsonResult !== null) {
                        if(jsonResult.status.healthy == true && isFirstColumn) {
                           window.dvs.lastSavePoint = jsonResult.save_point;
                           isFirstColumn = false;
                        }
                        else if(jsonResult.status.healthy == false) {
                           $("#enotification_pp").html("Could not update "+details.name);
                           $("#enotification_pp").jqxNotification("open");
                           canContinue = false;
                        }
                     }
                     else {
                        $("#enotification_pp").html("Could not update "+details.name);
                        $("#enotification_pp").jqxNotification("open");
                        canContinue = false;
                     }
                  },
                  complete: function() {
                     $("#loading_box").hide();
                  }
               });
            }
         });
      }
   });
   if(canContinue == true) {
      $("#inotification_pp").html("Columns updated successfully");
      $("#inotification_pp").jqxNotification("open");
   }
   $("#loading_box").hide();
   $("#loading_box").html("Loading..");
   window.dvs.updateSheetList();
   window.dvs.refreshSavePoints();
};

/**
 * This function initializes the file drop area
 * @returns {undefined}
 */
DMPVSchema.prototype.initFileDropArea = function() {
   //check if browser supports the W3 File API
   if(window.File && window.FileList && window.FileReader) {
      $("#file_drop_area").bind("drop", function(event) {
         $("#file_drop_area").css("border", "2px #aaa dashed");
         $("#file_drop_area").removeClass("mouse-over");
         //prevent the browser from trying to download the file
         event.preventDefault();
         event.stopPropagation();
         
         console.log("target.files", event.target.files);
         console.log("event.datatransfer", event.dataTransfer);
      });
      $("#file_drop_area").bind("dragover", function(e){
         $("#file_drop_area").addClass("mouse-over");
         $("#file_drop_area").css("border", "2px #0088cc solid");
         e.preventDefault();
         e.stopPropagation();
      });
      $("#file_drop_area").bind("dragend", function(e){
         e.target.className = "";
         $("#file_drop_area").css("border", "2px #aaa dashed");
      });
      
   }
   else {
      //TODO: notify user that drag and drop not supported by browser
   }
};

/**
 * This function initializes the source for the project_list_box jqxList
 * @returns {undefined}
 */
DMPVSchema.prototype.initSheetList = function() {
   console.log("Initializing  sheet list");
   var source = null;
   if(window.dvs.project.length > 0){//project defined
      source = {
         datatype: "json",
         datafields: [
            {name: 'name'}
         ],
         root: 'sheet_names',
         async: true,
         url: "mod_ajax.php?page=odk_workflow&do=get_workflow_schema",
         data:{
            token: {server: window.dvs.server, user: window.dvs.user, session: window.dvs.session},
            data: {workflow_id: window.dvs.project}
         },
         type: "POST",
         id: "name",
         beforeprocessing: function(data) {
            window.dvs.schema = data.schema;
            var sheets = new Array();
            //TODO: alert user if data is null
            if(data != null) {
               console.log(data);
               if(data.status.healthy == true){
                  for(var index = 0; index < data.schema.sheets.length; index++) {
                     sheets[index] = {};
                     sheets[index].name = data.schema.sheets[index].name;
                  }
               }
               else {
                  $("#enotification_pp").html("Please rollback to a previous version if this project");
                  $("#enotification_pp").jqxNotification("open");
               }
            }
            console.log(sheets);
            data.sheet_names = sheets;
         }
      };
   }
   else {//user wants to create a new project
      var data = {sheet_names:[]};
      source = {
         datatype: "json",
         datafields: [
            {name: 'name'}
         ],
         root: 'sheet_names',
         localdata: data,
         beforeprocessing: function() {
            window.dvs.schema = null
         }
      };
   }
   
   window.dvs.sheetListAdapter = new $.jqx.dataAdapter(source, {loadComplete: function(){
         $("#loading_box").hide();
         console.log("load complete");
         if(window.dvs.schema != null && window.dvs.schema.sheets.length > 0){
            $("#sheets").jqxListBox('selectIndex', 0);
         }
   },
   beforeSend: function() {
      $("#loading_box").show();
   }});
   $("#sheets").jqxListBox({width: window.dvs.leftSideWidth, height:'100%', source: window.dvs.sheetListAdapter, displayMember: "name", valueMember: "name", theme: ''});
   $("#sheets").bind("select", function(event){
      var sheetName = event.args.item.value;
      if(window.dvs.schema != null) {
         for(var index = 0; index < window.dvs.schema.sheets.length; index++) {
            if(window.dvs.schema.sheets[index].name == sheetName){
               window.dvs.updateColumnGrid(window.dvs.schema.sheets[index]);
               break;
            }
         }
      }
   });
};

/**
 * This function initializes the source for the project_list_box jqxList
 * @returns {undefined}
 */
DMPVSchema.prototype.updateSheetList = function() {
   console.log("updating sheet list");
   var source = null;
   if(window.dvs.project.length > 0){//project defined
      source = {
         datatype: "json",
         datafields: [
            {name: 'name'}
         ],
         root: 'sheet_names',
         async: true,
         url: "mod_ajax.php?page=odk_workflow&do=get_workflow_schema",
         data:{
            token: {server: window.dvs.server, user: window.dvs.user, session: window.dvs.session},
            data: {workflow_id: window.dvs.project}
         },
         type: "POST",
         id: "name",
         beforeprocessing: function(data) {
            window.dvs.schema = data.schema;
            var sheets = new Array();
            //TODO: alert user if data is null
            if(data != null) {
               for(var index = 0; index < data.schema.sheets.length; index++) {
                  sheets[index] = {};
                  sheets[index].name = data.schema.sheets[index].name;
               }
            }
            console.log(sheets);
            data.sheet_names = sheets;
         }
      };
   }
   else {//user wants to create a new project
      var data = {sheet_names:[]};
      source = {
         datatype: "json",
         datafields: [
            {name: 'name'}
         ],
         root: 'sheet_names',
         localdata: data,
         beforeprocessing: function() {
            window.dvs.schema = null
         }
      };
   }
   
   window.dvs.sheetListAdapter = new $.jqx.dataAdapter(source, {loadComplete: function(){
         $("#loading_box").hide();
         console.log("load complete");
         if(window.dvs.schema != null && window.dvs.schema.sheets.length > 0){
            $("#sheets").jqxListBox('selectIndex', 0);
         }
   },
   beforeSend: function() {
      $("#loading_box").show();
   }});
   $("#sheets").jqxListBox({source: window.dvs.sheetListAdapter});
};

DMPVSchema.prototype.initColumnGrid = function() {
   console.log("initializing column grid");
   var data = {
      columns: []
   };
   var source = {
      datatype: "json",
      datafields: [
         {name: 'present', type:'bool'},
         {name: 'name'},
         {name: 'type'},
         {name: 'length'},
         {name: 'nullable'},
         {name: 'default'},
         {name: 'key'}
      ],
      root: 'columns',
      localdata: data
   };
   
   window.dvs.columnGridAdapter = new $.jqx.dataAdapter(source);
   
   var columnTypes = [
         'varchar',
         'integer',
         'double',
         'tinyint',
         'time without time zone',
         'date',
         'timestamp without time zone',
         'boolean'
   ];
   var nullableTypes = [
      'true',
      'false'
   ];
   var keyTypes = [
      '',
      'primary',
      'unique'
   ];
   var gridWidth = window.dvs.rightSideWidth * 0.975;
   $("#columns").jqxGrid({
      width: window.dvs.rightSideWidth,
      height: '100%',
      source: window.dvs.columnGridAdapter,
      columnsresize: false,
      theme: '',
      pageable: false,
      editable: true,
      rendergridrows: function() {
         return window.dvs.columnGridAdapter.records;
      },
      columns: [
         {text: '', columntype: 'checkbox', datafield: 'present', width:10},
         {text: 'Name', datafield: 'name', width: gridWidth*0.2647 - 10},
         {text: 'Type', columntype: 'dropdownlist', datafield: 'type', width: gridWidth*0.2206,initeditor: function (row, cellvalue, editor) {
               editor.jqxDropDownList({ source: columnTypes});
         }},
         {text: 'Length', columntype: 'numberinput', datafield: 'length', width: gridWidth*0.1103},
         {text: 'Nullable', columntype: 'dropdownlist', datafield: 'nullable', width: gridWidth*0.1103,initeditor: function (row, cellvalue, editor) {
               editor.jqxDropDownList({ source: nullableTypes});
         }},
         {text: 'Default', datafield: 'default', width: gridWidth*0.1471},
         {text: 'Key', columntype: 'dropdownlist', datafield: 'key', width: gridWidth*0.1471,initeditor: function (row, cellvalue, editor) {
               editor.jqxDropDownList({ source: keyTypes});
         }}
      ]
   });
   
   $("#columns").on('cellendedit', window.dvs.columnGridCellValueChanged);
};

DMPVSchema.prototype.columnGridCellValueChanged = function(event) {
   if(window.dvs.schema != null && event.args.oldvalue !== event.args.value) {
      console.log(event);
      var columnData = event.args.row;
      var sheetData = window.dvs.schema.sheets[$("#sheets").jqxListBox('selectedIndex')];
      if(typeof sheetData !== 'undefined') {
         var sheetName = sheetData.name;
         var columnName = null;
         if(typeof window.dvs.schemaChanges[sheetName] === 'undefined') {//initialize the sheet in the changes object
            window.dvs.schemaChanges[sheetName] = {};
         }
         
         if(event.args.datafield == 'name') {//name of the field is what changed
            console.log("oldvalue "+event.args.oldvalue);
            console.log("value "+event.args.value);
            if(typeof window.dvs.columnDictionary[sheetName] === 'undefined') {
               window.dvs.columnDictionary[sheetName] = [];
            }
            
            var found = false;
         
            for(var i = 0; i < window.dvs.columnDictionary[sheetName].length; i++) {
               if(window.dvs.columnDictionary[sheetName][i].new_name === event.args.oldvalue) {
                  window.dvs.columnDictionary[sheetName][i].new_name = event.args.value;
                  found = true;
                  columnName = window.dvs.columnDictionary[sheetName][i].old_name;
                  break;
               }
            }
            if(found == false) {
               window.dvs.columnDictionary[sheetName][window.dvs.columnDictionary[sheetName].length] = {old_name:event.args.oldvalue, new_name:event.args.value};
               columnName = event.args.oldvalue;
            }
         }
         else {//something else apart from the name changed. Look for the columns original name
            if(event.args.datafield == 'present') {
               var columnIndex = event.args.rowindex;
               var sheetIndex = $("#sheets").jqxListBox('selectedIndex');
               var columnData = window.dvs.schema.sheets[sheetIndex].columns[columnIndex];
               columnData.present = event.args.value;
            }
            if(typeof window.dvs.columnDictionary[sheetName] === 'undefined') {//none the columns in the current sheet have their column names edited yet
               columnName = columnData.name;
            }
            else {//at least one column in the current sheet has a modified name
               var found = false;
               for(var i = 0; i < window.dvs.columnDictionary[sheetName].length; i++) {
                  if(window.dvs.columnDictionary[sheetName][i].new_name === columnData.name) {
                     columnName = window.dvs.columnDictionary[sheetName][i].old_name;
                     found = true;
                     break;
                  }
               }
               
               if(found == false) {//current column's name has not been edited
                  columnName = columnData.name;
               }
            }
         }
         
         console.log("Original column name is "+columnName);
         
         if(typeof window.dvs.schemaChanges[sheetName][columnName] === 'undefined') {
            window.dvs.schemaChanges[sheetName][columnName] = {};
         }
         
         window.dvs.schemaChanges[sheetName][columnName] = columnData;
         window.dvs.schemaChanges[sheetName][columnName].original_name = columnName;
         
         $("#cancel_btn").prop('disabled', false);
         $("#update_btn").prop('disabled', false);
         
         console.log(window.dvs.schemaChanges);
      }
   }
   
};

DMPVSchema.prototype.updateColumnGrid = function(data) {
   console.log("updating column grid");
   var source = {
      datatype: "json",
      datafields: [
         {name: 'present', type:'boolean'},
         {name: 'name'},
         {name: 'type'},
         {name: 'length'},
         {name: 'nullable'},
         {name: 'default'},
         {name: 'key'}
      ],
      root: 'columns',
      localdata: data
   };
   
   window.dvs.columnGridAdapter = new $.jqx.dataAdapter(source);
   $("#columns").jqxGrid({source: window.dvs.columnGridAdapter});
};