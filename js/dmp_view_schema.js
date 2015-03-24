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
   window.dvs.uploadFileLoc = null;
   $("#whoisme").hide();
   //initialize source for project_list_box
   $(document).ready(function(){
      window.dvs.documentReady();
   });
   
   if(window.dvs.project == null || window.dvs.project.length == 0) {//if project is not set
      //show create project popup
      $("#new_project_wndw").show();
      $("#project_title").html("New Project");
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
   var newProjectWindowPos = {
      y:window.innerHeight/2 - 220/2 - window.innerHeight*0.1,
      x:window.innerWidth/2 - 600/2
   };
   $("#new_project_wndw").jqxWindow({height: 220, width: 600, position:newProjectWindowPos, theme: ''});
   var deleteProjectWindowPos = {
      y:window.innerHeight/2 - 100/2 - window.innerHeight*0.1,
      x:window.innerWidth/2 - 300/2
   };
   $("#delete_project_wndw").jqxWindow({height: 100, width: 300, position: deleteProjectWindowPos, theme: ''});
   var renameSheetWindowPos = {
      y:window.innerHeight/2 - 150/2 - window.innerHeight*0.1,
      x:window.innerWidth/2 - 400/2
   };
   $("#rename_sheet_wndw").jqxWindow({height: 150, width: 400, position: renameSheetWindowPos, theme: ''});
   $("#manual_file_upload").jqxFileUpload({
      width:500,
      fileInputName: "data_file",
      uploadUrl: "mod_ajax.php?page=dmp&do=ajax&action=upload_data_file",
      autoUpload: true
   });
   $("#manual_file_upload").on("uploadEnd", window.dvs.fileUploadEnd);
   $("#manual_file_upload").on("uploadStart", window.dvs.fileUploadStart);
   $("#split_window").jqxSplitter({  width: pWidth, height: window.innerHeight*0.8, panels: [{ size: window.dvs.leftSideWidth, min: '10%' }, {size: window.dvs.rightSideWidth, min: '50%'}] });
   $("#loading_box").css("top", (window.innerHeight/2 - (window.innerHeight*0.1))-($("#loading_box").height()/2)+"px");
   $("#loading_box").css("left", (window.innerWidth/2)-($("#loading_box").width()/2)+"px");
   $("#inotification_pp").jqxNotification({position: "top-right", opacity: 0.9, autoOpen: false, autoClose: true, template:"info"});
   $("#enotification_pp").jqxNotification({position: "top-right", opacity: 0.9, autoOpen: false, autoClose: false, template:"error"});
   window.dvs.initSheetList();
   window.dvs.initColumnGrid();
   //window.dvs.initFileDropArea();
   $("#cancel_btn").click(window.dvs.cancelButtonClicked);
   $("#update_btn").click(window.dvs.updateButtonClicked);
   $("#create_project_btn").click(window.dvs.createProjectButtonClicked);
   $("#delete_project_menu_btn").click(function(){$("#delete_project_wndw").show();});
   $("#delete_project_btn").click(window.dvs.deleteProjectButtonClicked);
   $("#regen_schema_menu_btn").click(window.dvs.processProjectSchema);
   $("#menu_bar").jqxMenu();
   $("#right_click_menu").jqxMenu({mode: "popup", width: "200px", autoOpenPopup: false});
   $("#delete_sheet_btn").click(window.dvs.deleteSheetButtonClicked);
   $("#rename_sheet_btn").click(window.dvs.renameSheetButtonClicked);
   $("#rename_sheet_btn2").click(window.dvs.renameSheetButton2Clicked);
};

/**
 * This function is called when the rename button in contextual right click menu is clicked
 * 
 * @returns {undefined}
 */
DMPVSchema.prototype.renameSheetButtonClicked = function (event) {
   var sheetIndex = $("#sheets").jqxListBox("getSelectedIndex");
   var sheet = window.dvs.schema.sheets[sheetIndex];
   $("#sheet_old_name").val(sheet.name);
   $("#rename_sheet_wndw").show();
};

/**
 * This function is called when the rename button in the rename sheet window is clicked
 * @returns {undefined}
 */
DMPVSchema.prototype.renameSheetButton2Clicked = function () {
   if($("#sheet_old_name").val().length > 0 && $("#sheet_name").val().length > 0 && window.dvs.project != null) {
      var selectedSheetIndex = $("#sheets").jqxListBox("getSelectedIndex");
      var sheet = window.dvs.schema.sheets[selectedSheetIndex];
      $("#loading_box").show();
      var sData = JSON.stringify({
         "workflow_id": window.dvs.project,
         "sheet":{"original_name":$("#sheet_old_name").val(), "name":$("#sheet_name").val(), "delete":false}
      });
      var sToken = JSON.stringify({
         "server":window.dvs.server,
         "user": window.dvs.user,
         "session": window.dvs.session
      });
      var canContinue = true;
      $.ajax({
         url: "mod_ajax.php?page=odk_workflow&do=alter_sheet",
         type: "POST",
         async: true,
         data: {data: sData, token: sToken},
         statusCode: {
            400: function() {//bad request
               $("#enotification_pp").html("Could rename "+sheet.name);
               $("#enotification_pp").jqxNotification("open");
               canContinue = false;
            },
            403: function() {//forbidden
               $("#enotification_pp").html("User not allowed to rename "+sheet.name);
               $("#enotification_pp").jqxNotification("open");
               canContinue = false;
            }
         },
         success: function(jsonResult, textStatus, jqXHR){
            console.log("Response from alter_sheet endpoint = ", jsonResult);
            if(jsonResult !== null) {
               if(jsonResult.status.healthy == false) {
                  $("#enotification_pp").html("Could not rename sheet");
                  $("#enotification_pp").jqxNotification("open");
                  canContinue = false;
               }
            }
            else {
               $("#enotification_pp").html("Could not rename sheet");
               $("#enotification_pp").jqxNotification("open");
               canContinue = false;
            }
         },
         complete: function() {
            $("#loading_box").hide();
            window.dvs.updateSheetList();
         }
      });
      if(canContinue == true) {
         $("#rename_sheet_wndw").hide();
         $("#inotification_pp").html(" successfully renamed "+$("#sheet_old_name").val()+" to "+$("#sheet_name").val());
         $("#inotification_pp").jqxNotification("open");
      }
   }
};

DMPVSchema.prototype.deleteSheetButtonClicked = function() {
   if(window.dvs.project != null) {
      var selectedSheetIndex = $("#sheets").jqxListBox("getSelectedIndex");
      var sheet = window.dvs.schema.sheets[selectedSheetIndex];
      $("#loading_box").show();
      var sData = JSON.stringify({
         "workflow_id": window.dvs.project,
         "sheet":{"original_name":sheet.name, "name":sheet.name, "delete":true}
      });
      var sToken = JSON.stringify({
         "server":window.dvs.server,
         "user": window.dvs.user,
         "session": window.dvs.session
      });
      var canContinue = true;
      $.ajax({
         url: "mod_ajax.php?page=odk_workflow&do=alter_sheet",
         type: "POST",
         async: true,
         data: {data: sData, token: sToken},
         statusCode: {
            400: function() {//bad request
               $("#enotification_pp").html("Could delete "+sheet.name);
               $("#enotification_pp").jqxNotification("open");
               canContinue = false;
            },
            403: function() {//forbidden
               $("#enotification_pp").html("User not allowed to delete "+sheet.name);
               $("#enotification_pp").jqxNotification("open");
               canContinue = false;
            }
         },
         success: function(jsonResult, textStatus, jqXHR){
            console.log("Response from alter_sheet endpoint = ", jsonResult);
            if(jsonResult !== null) {
               if(jsonResult.status.healthy == false) {
                  $("#enotification_pp").html("Could not delete sheet "+sheet.name);
                  $("#enotification_pp").jqxNotification("open");
                  canContinue = false;
               }
            }
            else {
               $("#enotification_pp").html("Could not delete sheet "+sheet.name);
               $("#enotification_pp").jqxNotification("open");
               canContinue = false;
            }
         },
         complete: function() {
            $("#loading_box").hide();
            window.dvs.updateSheetList();
         }
      });
      if(canContinue == true) {
         $("#inotification_pp").html(sheet.name+" successfully deleted");
         $("#inotification_pp").jqxNotification("open");
      }
   }
};

DMPVSchema.prototype.deleteProjectButtonClicked = function() {
   if(window.dvs.project != null) {
      $("#loading_box").show();
      var sData = JSON.stringify({
         "workflow_id": window.dvs.project
      });
      var sToken = JSON.stringify({
         "server":window.dvs.server,
         "user": window.dvs.user,
         "session": window.dvs.session
      });
      $.ajax({
         url: "mod_ajax.php?page=odk_workflow&do=delete_workflow",
         type: "POST",
         async: true,
         data: {data: sData, token: sToken},
         statusCode: {
            400: function() {//bad request
               $("#enotification_pp").html("Could delete project");
               $("#enotification_pp").jqxNotification("open");
            },
            403: function() {//forbidden
               $("#enotification_pp").html("User not allowed to delete projects");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         success: function(jsonResult, textStatus, jqXHR){
            if(jsonResult !== null) {
               if(jsonResult.status.healthy == true) {
                  window.location.href = "?page=dmp";
               }
               else if(jsonResult.status.healthy == false) {
                  $("#enotification_pp").html("Could not delete project");
                  $("#enotification_pp").jqxNotification("open");
               }
            }
            else {
               $("#enotification_pp").html("Could not delete project");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         complete: function() {
            $("#loading_box").show();
         }
      });
   }
};

DMPVSchema.prototype.fileUploadEnd = function(event) {
   console.log(event);
   var serverResponse = event.args.response;
   var jsonResponse = $.parseJSON(serverResponse);
   var error = false;
   if(typeof jsonResponse.healthy !== 'undefined' && jsonResponse.healthy == true) {
      window.dvs.uploadFileLoc = jsonResponse.name;
      if(window.dvs.uploadFileLoc == null || window.dvs.uploadFileLoc.length == 0) {
         error = true;
         window.dvs.uploadFileLoc = null;
      }
   }
   else {
      error = true;
   }
   
   if(error==true) {
      $("#enotification_pp").html("Unable to upload file");
      $("#enotification_pp").jqxNotification("open");
   }
   else {
      $("#inotification_pp").html("Successfully uploaded file");
      $("#inotification_pp").jqxNotification("open");
   }
};

DMPVSchema.prototype.fileUploadStart = function() {
   window.dvs.uploadFileLoc = null;
};

DMPVSchema.prototype.createProjectButtonClicked = function() {
   //check if project name and upload file are set
   var projectName = $("#project_name").val();
   var fileLoc = window.dvs.uploadFileLoc;
   if(projectName.length > 0 && fileLoc != null) {
      $("#loading_box").show();
      var sData = JSON.stringify({
         "data_file_url": fileLoc,
         "workflow_name": projectName
      });
      var sToken = JSON.stringify({
         "server":window.dvs.server,
         "user": window.dvs.user,
         "session": window.dvs.session
      });
      $.ajax({
         url: "mod_ajax.php?page=odk_workflow&do=init_workflow",
         type: "POST",
         async: true,
         data: {data: sData, token: sToken},
         statusCode: {
            400: function() {//bad request
               $("#enotification_pp").html("Could create project");
               $("#enotification_pp").jqxNotification("open");
            },
            403: function() {//forbidden
               $("#enotification_pp").html("User not allowed to create projects");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         success: function(jsonResult, textStatus, jqXHR){
            if(jsonResult !== null) {
               if(jsonResult.status.healthy == true) {
                  $("#inotification_pp").html("Done creating project");
                  $("#inotification_pp").jqxNotification("open");
                  $("#new_project_wndw").hide();
                  window.dvs.project = jsonResult.workflow_id;
                  window.dvs.processProjectSchema();
               }
               else if(jsonResult.status.healthy == false) {
                  $("#enotification_pp").html("Could not create project");
                  $("#enotification_pp").jqxNotification("open");
               }
            }
            else {
               $("#enotification_pp").html("Could not fetch save points");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         complete: function() {
         }
      });
   }
};

DMPVSchema.prototype.processProjectSchema = function() {
   if(window.dvs.project != null) {
      $("#loading_box").show();
      var sData = JSON.stringify({
         "workflow_id": window.dvs.project
      });
      var sToken = JSON.stringify({
         "server":window.dvs.server,
         "user": window.dvs.user,
         "session": window.dvs.session
      });
      $.ajax({
         url: "mod_ajax.php?page=odk_workflow&do=process_mysql_schema",
         type: "POST",
         async: true,
         data: {data: sData, token: sToken},
         statusCode: {
            400: function() {//bad request
               $("#enotification_pp").html("Could not process the project's schema");
               $("#enotification_pp").jqxNotification("open");
            },
            403: function() {//forbidden
               $("#enotification_pp").html("User not allowed to process schemas");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         success: function(jsonResult, textStatus, jqXHR){
            if(jsonResult !== null) {
               if(jsonResult.status.healthy == true) {
                  $("#inotification_pp").html("Done processing project schema");
                  $("#inotification_pp").jqxNotification("open");
                  window.dvs.updateSheetList();
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
         }
      });
   }
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

DMPVSchema.prototype.sheetsRightClicked = function() {
   
};

DMPVSchema.prototype.refreshSavePoints = function() {
   console.log("refresh save points called");
   if(window.dvs.project != null && window.dvs.project.length > 0) {
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
   }
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
};

/**
 * This function initializes the file drop area
 * @returns {undefined}
 */
DMPVSchema.prototype.initFileDropArea = function() {
   //check if browser supports the W3 File API
   if(window.File && window.FileList && window.FileReader) {
      /*$("#file_drop_area").bind("drop", function(event) {
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
      });*/
      
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
            $("#project_title").html(window.dvs.schema.title);
            var sheets = new Array();
            //TODO: alert user if data is null
            if(data != null) {
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
         window.dvs.refreshSavePoints();
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
   //disable default right click context menu
   $(document).on('contextmenu', function (e) {
      return false;
   });
   $("#sheets").mousedown(function(e) {
      if(e.button == 2) {//right click
         var selectedSheetIndex = $("#sheets").jqxListBox("getSelectedIndex");
         var sheet = window.dvs.schema.sheets[selectedSheetIndex];
         var scrollTop = $(window).scrollTop();
         var scrollLeft = $(window).scrollLeft();
         $("#delete_sheet_btn").html("Delete "+sheet.name);
         $("#rename_sheet_btn").html("Rename "+sheet.name);
         $("#right_click_menu").jqxMenu('open', parseInt(event.clientX) + 5 + scrollLeft, parseInt(event.clientY) + 5 + scrollTop);
         return false;
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
            $("#project_title").html(window.dvs.schema.title);
            var sheets = new Array();
            //TODO: alert user if data is null
            if(data != null) {
               for(var index = 0; index < data.schema.sheets.length; index++) {
                  sheets[index] = {};
                  sheets[index].name = data.schema.sheets[index].name;
               }
            }
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
         window.dvs.refreshSavePoints();
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