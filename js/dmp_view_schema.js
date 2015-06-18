function DMPVSchema(server, user, session, project) {
   window.dvs = this;
   window.dvs.server = server;
   window.dvs.user = user;
   window.dvs.session = session;
   window.dvs.project = project;
   window.dvs.schema = null;
   window.dvs.sheetData = {};
   window.dvs.sheetListAdapter = null;
   window.dvs.columnGridAdapter = null;
   window.dvs.versionDiffGridAdapter = null;
   window.dvs.notesGridAdapter = null;
   window.dvs.mergeDiffGridAdapter = null;
   window.dvs.dataGridAdapter = null;
   window.dvs.schemaChanges={};
   window.dvs.columnDictionary={};//object storing the original and new column names
   window.dvs.foreignKeys = null;
   window.dvs.leftSideWidth = 0;
   window.dvs.rightSideWidth = 0;
   window.dvs.lastSavePoint = null;
   window.dvs.uploadFileLoc = null;
   window.dvs.diffProject = null;//holds project id for the project chosen to be merged with this one
   window.dvs.diffProjectSchema = null;
   window.dvs.mergeKeys = null;
   $("#whoisme").hide();
   //initialize source for project_list_box
   $(document).ready(function() {
      window.dvs.documentReady();
   });
   $(window).resize(function() {
      console.log("window resized");
      window.dvs.documentReady();
   });
   
   if(window.dvs.project == null || window.dvs.project.length == 0) {//if project is not set
      //show create project popup
      $("#new_project_wndw").show();
      $("#project_title").html("New Project");
   }
   else {
      window.dvs.refreshSavePoints();
   }
}

/**
 * This function initializes resources that need to be initialized after the DOM
 * has fully loaded
 * 
 * @returns {undefined}
 */
DMPVSchema.prototype.documentReady = function() {
   var currTime = new Date().getTime();
   if(typeof window.dvs.lastDocumentReload == 'undefined' || (currTime - window.dvs.lastDocumentReload) > 1000) {
      console.log("documentReady called");
      window.dvs.lastDocumentReload = currTime;
      var pWidth = window.innerWidth*0.942;//split_window width
      window.dvs.leftSideWidth = pWidth*0.2;//30% of split_window
      window.dvs.rightSideWidth = pWidth - window.dvs.leftSideWidth;
      $("#blanket_cover").position({y:0, x:0});
      $("#blanket_cover").css("height", window.innerHeight * 0.9);
      $("#blanket_cover").css("width", $("#repository").width());
      window.dvs.initWindow($("#new_project_wndw"), 220, 600);
      window.dvs.initWindow($("#get_data_wndw"), 220, 600);
      window.dvs.initWindow($("#delete_project_wndw"), 100, 300);
      window.dvs.initWindow($("#other_projects_wndw"), 130, 300);
      window.dvs.initWindow($("#version_diff_wndw"), (window.innerHeight * 0.9), (window.innerWidth * 0.8));
      window.dvs.initWindow($("#merge_diff_wndw"), window.innerHeight * 0.9, window.innerWidth * 0.8);
      window.dvs.initWindow($("#merge_sheet_wndw"), 200, (window.innerWidth * 0.8));
      window.dvs.initWindow($("#rename_sheet_wndw"), window.innerHeight * 0.8, window.innerWidth * 0.8);
      window.dvs.initWindow($("#new_foreign_key_wndw"), 230, 400);
      window.dvs.initWindow($("#rename_project_wndw"), 150, 400);
      window.dvs.initWindow($("#db_credentials_wndw"), 110, 250);
      window.dvs.initWindow($("#notes_wndw"), window.innerHeight * 0.7, window.innerWidth * 0.9);
      window.dvs.initWindow($("#query_wndw"), 160, 600);
      $("#manual_file_upload").jqxFileUpload({
         width:500,
         fileInputName: "data_file",
         uploadUrl: "mod_ajax.php?page=dmp&do=ajax&action=upload_data_file",
         autoUpload: true
      });
      $("#tabs").jqxTabs({width:"100%", height: "100%", position: "top"});
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
      $("#update_btn").click(window.dvs.applySchemaChanges);
      $("#create_project_btn").click(window.dvs.createProjectButtonClicked);
      $("#delete_project_btn").click(window.dvs.deleteProjectButtonClicked);
      $("#add_new_note").click(window.dvs.addNoteButtonClicked);
      $("#merge_version_btn").click(window.dvs.mergeVersionButtonClicked);
      $("#merge_schema_btn").click(window.dvs.mergeSchemaButtonClicked);
      $("#merge_sheet_btn").click(window.dvs.mergeSheetButtonClicked);
      $("#menu_bar").jqxMenu({autoOpen: false});
      $("#right_click_menu").jqxMenu({mode: "popup", width: "200px", autoOpenPopup: false});
      $("#rename_sheet_btn2").click(function() {
         window.dvs.renameSheetButton2Clicked($("#sheet_old_name").val(), $("#sheet_name").val());
      });
      $("#add_foreign_key_btn").click(window.dvs.addForeignKeyButtonClicked);
      $("#project_title").dblclick(function() {
         $("#new_project_name").val(window.dvs.schema.title);
         $("#rename_project_wndw").show();
      });
      $("#rename_project_btn").click(window.dvs.renameProjectButtonClicked);
      $("#apply_version_changes").click(window.dvs.applyVersionDiffButtonClicked);
      $("#apply_merge_changes").click(window.dvs.applyMergeDiffButtonClicked);
      $("#run_query_btn").click(window.dvs.runQueryButtonClicked);
      $("#data_filter_type").change(function() {
         var wHeight = 160;
         if($("#data_filter_type").val() == "all") {
            $("#filter_query_div").hide();
            $("#filter_prefix_div").hide();
         }
         else if($("#data_filter_type").val() == "query") {
            wHeight = 230;
            $("#filter_query_div").show();
            $("#filter_prefix_div").hide();
         }
         else if($("#data_filter_type").val() == "prefix") {
            wHeight = 280;
            $("#filter_query_div").hide();
            $("#filter_prefix_div").show();
         }
         $("#get_data_wndw").jqxWindow({height: wHeight});
      });
      $("#get_data_btn2").click(window.dvs.getDataButtonClicked);
      $("#menu_bar").on('itemclick', window.dvs.menuItemClicked);
   }
   else {
      console.log("ignoring documentReady call");
   }
};

DMPVSchema.prototype.menuItemClicked = function(event) {
   var itemId = event.args.id;
   if(itemId == "home_menu_btn") {
      window.location.href = "?page=dmp";
   }
   else if(itemId == "create_project_menu_btn") {
      window.location.href = "?page=dmp&do=view_schema&project=&session="+window.dvs.session;
   }
   else if(itemId == "add_note_menu_btn") {
      window.dvs.refreshNotes();
      $("#notes_wndw").show();
   }
   else if(itemId == "regen_schema_menu_btn") {
      window.dvs.processProjectSchema();
   }
   else if(itemId == "merge_version_menu_btn") {
      window.dvs.mergeVSMenuButtonClicked("version");
   }
   else if(itemId == "merge_schema_menu_btn") {
      window.dvs.mergeVSMenuButtonClicked("schema");
   }
   else if(itemId == "delete_project_menu_btn") {
      $("#delete_project_wndw").show();
   }
   else if(itemId == "run_query_menu_btn") {
      $("#query_wndw").show();
   }
   else if(itemId == "dump_data_btn") {
      window.dvs.dumpDataButtonClicked();
   }
   else if(itemId == "db_credentials_btn") {
      window.dvs.dbCredentailsButtonClicked();
   }
   else if(itemId == "get_data_btn") {
      //get the project groups
      var projectGroups = window.dvs.getProjectGroups();
      var html = "";
      for(var gIndex = 0; gIndex < projectGroups.length; gIndex++) {
         html = html + "<input type='checkbox' name='project_groups' id='"+projectGroups[gIndex]+"' value='"+projectGroups[gIndex]+"' />"+projectGroups[gIndex]+"<br />";
      }
      $("#data_project_groups_div").html(html);
      $("#get_data_wndw").show();
   }
};

DMPVSchema.prototype.runQueryButtonClicked = function() {
   if(window.dvs.project != null && $("#query_box").val().length > 0) {
      $("#loading_box").show();
      var sData = JSON.stringify({
         "workflow_id": window.dvs.project,
         "query":$("#query_box").val()
      });
      var sToken = JSON.stringify({
         "server":window.dvs.server,
         "user": window.dvs.user,
         "session": window.dvs.session
      });
      $.ajax({
         url: "mod_ajax.php?page=odk_workflow&do=run_query",
         type: "POST",
         async: true,
         data: {data: sData, token: sToken},
         statusCode: {
            400: function() {//bad request
               $("#enotification_pp").html("Was unable to run the query");
               $("#enotification_pp").jqxNotification("open");
            },
            403: function() {//forbidden
               $("#enotification_pp").html("User not allowed to run queries");
               $("#enotification_pp").jqxNotification("open");
            },
            500: function() {//forbidden
               $("#enotification_pp").html("An error occurred in the server");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         success: function(jsonResult, textStatus, jqXHR){
            console.log("Response from run_query endpoint = ", jsonResult);
            if(jsonResult !== null) {
               if(jsonResult.status.healthy == false) {
                  var message = "";
                  if(typeof jsonResult.status.errors != 'undefined' && jsonResult.status.errors.length > 0) {
                     if(typeof jsonResult.status.errors[0].message != 'undefined') {
                        message = "<br />"+jsonResult.status.errors[0].message;
                     }
                  }
                  $("#enotification_pp").html("Could not run the query"+message);
                  $("#enotification_pp").jqxNotification("open");
               }
               else {
                  $("#inotification_pp").html("Successfully run query");
                  $("#inotification_pp").jqxNotification("open");
               }
            }
            else {
               $("#enotification_pp").html("Could not run the query");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         complete: function() {
            window.dvs.refreshSavePoints();
         }
      });
   }
};

/**
 * This function records a project note
 * 
 * @returns {undefined}
 */
DMPVSchema.prototype.addNoteButtonClicked = function() {
   if(window.dvs.project != null && $("#new_note").val().length > 0) {
      $("#loading_box").show();
      var sData = JSON.stringify({
         "workflow_id": window.dvs.project,
         "note":$("#new_note").val()
      });
      var sToken = JSON.stringify({
         "server":window.dvs.server,
         "user": window.dvs.user,
         "session": window.dvs.session
      });
      $.ajax({
         url: "mod_ajax.php?page=odk_workflow&do=add_note",
         type: "POST",
         async: true,
         data: {data: sData, token: sToken},
         statusCode: {
            400: function() {//bad request
               $("#enotification_pp").html("Was unable to add the note");
               $("#enotification_pp").jqxNotification("open");
            },
            403: function() {//forbidden
               $("#enotification_pp").html("User not allowed to add notes");
               $("#enotification_pp").jqxNotification("open");
            },
            500: function() {//forbidden
               $("#enotification_pp").html("An error occurred in the server");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         success: function(jsonResult, textStatus, jqXHR){
            console.log("Response from add_note endpoint = ", jsonResult);
            if(jsonResult !== null) {
               if(jsonResult.status.healthy == false) {
                  var message = "";
                  if(typeof jsonResult.status.errors != 'undefined' && jsonResult.status.errors.length > 0) {
                     if(typeof jsonResult.status.errors[0].message != 'undefined') {
                        message = "<br />"+jsonResult.status.errors[0].message;
                     }
                  }
                  $("#enotification_pp").html("Could not add note"+message);
                  $("#enotification_pp").jqxNotification("open");
               }
               else {
                  console.log("Successfully added note");
                  window.dvs.refreshNotes();
               }
            }
            else {
               $("#enotification_pp").html("Could not add the note");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         complete: function() {
            $("#loading_box").hide();
         }
      });
   }
};

DMPVSchema.prototype.initWindow = function(wndw, height, width) {
   (function ($) {
      $.each(['show', 'hide'], function (i, ev) {
        var el = $.fn[ev];
        $.fn[ev] = function () {
          this.trigger(ev);
          return el.apply(this, arguments);
        };
      });
   })(jQuery);
   var windowPos = {
      y:window.innerHeight/2 - height/2,
      x:window.innerWidth/2 - width/2
   };
   wndw.jqxWindow({height: height, width: width, position: windowPos, theme: ''});
   wndw.on("show", function(){$("#blanket_cover").show();});
   wndw.on("hide", function(){$("#blanket_cover").hide();});
};

DMPVSchema.prototype.refreshNotes = function() {
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
         url: "mod_ajax.php?page=odk_workflow&do=get_notes",
         type: "POST",
         async: true,
         data: {data: sData, token: sToken},
         statusCode: {
            400: function() {//bad request
               $("#enotification_pp").html("Was unable to get notes");
               $("#enotification_pp").jqxNotification("open");
            },
            403: function() {//forbidden
               $("#enotification_pp").html("User not allowed to get notes");
               $("#enotification_pp").jqxNotification("open");
            },
            500: function() {//forbidden
               $("#enotification_pp").html("An error occurred in the server");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         success: function(jsonResult, textStatus, jqXHR){
            console.log("Response from get_notes endpoint = ", jsonResult);
            if(jsonResult !== null) {
               if(jsonResult.status.healthy == false) {
                  var message = "";
                  if(typeof jsonResult.status.errors != 'undefined' && jsonResult.status.errors.length > 0) {
                     if(typeof jsonResult.status.errors[0].message != 'undefined') {
                        message = "<br />"+jsonResult.status.errors[0].message;
                     }
                  }
                  $("#enotification_pp").html("Could not get notes"+message);
                  $("#enotification_pp").jqxNotification("open");
               }
               else {
                  console.log("Successfully gotten notes");
                  window.dvs.initNotesGrid(jsonResult.notes);
               }
            }
            else {
               $("#enotification_pp").html("Could not resolve trivial differences between projects");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         complete: function() {
            $("#loading_box").hide();
         }
      });
   }
};

DMPVSchema.prototype.applyVersionDiffButtonClicked = function() {
   if(window.dvs.diffProject != null && window.dvs.project != null && $("#merged_version_name").val().length > 0) {
      $("#apply_version_changes").prop('disabled', true);
      //first resolve the trivial conflicts then apply the non trivial ones one by one
      $("#loading_box").show();
      var sData = JSON.stringify({
         "workflow_id": window.dvs.project,
         "workflow_id_2": window.dvs.diffProject,
         "name": $("#merged_version_name").val()
      });
      var sToken = JSON.stringify({
         "server":window.dvs.server,
         "user": window.dvs.user,
         "session": window.dvs.session
      });
      var wasSuccessful = false;
      $.ajax({
         url: "mod_ajax.php?page=odk_workflow&do=resolve_version_diff",
         type: "POST",
         async: true,
         data: {data: sData, token: sToken},
         statusCode: {
            400: function() {//bad request
               $("#enotification_pp").html("Was unable to apply schema changes");
               $("#enotification_pp").jqxNotification("open");
            },
            403: function() {//forbidden
               $("#enotification_pp").html("User not allowed to make schema changes");
               $("#enotification_pp").jqxNotification("open");
            },
            500: function() {//forbidden
               $("#enotification_pp").html("An error occurred in the server");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         success: function(jsonResult, textStatus, jqXHR){
            console.log("Response from resolve_version_diff endpoint = ", jsonResult);
            if(jsonResult !== null) {
               if(jsonResult.status.healthy == false) {
                  var message = "";
                  if(typeof jsonResult.status.errors != 'undefined' && jsonResult.status.errors.length > 0) {
                     if(typeof jsonResult.status.errors[0].message != 'undefined') {
                        message = "<br />"+jsonResult.status.errors[0].message;
                     }
                  }
                  $("#enotification_pp").html("Could not resolve trivial differences"+message);
                  $("#enotification_pp").jqxNotification("open");
               }
               else {
                  console.log("Successfully resolved trivial changes");
                  wasSuccessful = true;//this will prevent this function from refreshing save points in onComplete
                  window.dvs.applySchemaChanges();//calling this function will trigger refreshSavePoints at some point
                  $("#version_diff_wndw").hide();
               }
            }
            else {
               $("#enotification_pp").html("Could not resolve trivial differences between projects");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         complete: function() {
            $("#apply_version_changes").prop('disabled', false);
            $("#loading_box").hide();
            window.dvs.refreshSavePoints();
         }
      });
   }
   else if($("#merged_version_name").val().length == 0) {//the user did not define the merged sheet name
      $("#enotification_pp").html("Please specify the new name to be given to this project");
      $("#enotification_pp").jqxNotification("open");
   }
};

DMPVSchema.prototype.applyMergeDiffButtonClicked = function() {
   if(window.dvs.diffProject != null && window.dvs.project != null && $("#merged_schema_name").val().length > 0 && window.dvs.mergeKeys != null) {
      $("#apply_merge_changes").prop('disabled', true);
      //first resolve the non-trivial conflicts before merging the two schemas
      var success = window.dvs.applySchemaChanges(false);//apply schema changes but do not refresh the sheet list
      if(success == true) {
         $("#loading_box").show();
         var sData = JSON.stringify({
            "workflow_id": window.dvs.project,
            "workflow_id_2": window.dvs.diffProject,
            "name": $("#merged_schema_name").val(),
            "key_1": window.dvs.mergeKeys.key_1,
            "key_2": window.dvs.mergeKeys.key_2
         });
         var sToken = JSON.stringify({
            "server":window.dvs.server,
            "user": window.dvs.user,
            "session": window.dvs.session
         });
         var wasSuccessful = false;
         $.ajax({
            url: "mod_ajax.php?page=odk_workflow&do=resolve_merge_diff",
            type: "POST",
            async: true,
            data: {data: sData, token: sToken},
            statusCode: {
               400: function() {//bad request
                  $("#enotification_pp").html("Was unable to apply schema changes");
                  $("#enotification_pp").jqxNotification("open");
               },
               403: function() {//forbidden
                  $("#enotification_pp").html("User not allowed to make schema changes");
                  $("#enotification_pp").jqxNotification("open");
               },
               500: function() {//forbidden
                  $("#enotification_pp").html("An error occurred in the server");
                  $("#enotification_pp").jqxNotification("open");
               }
            },
            success: function(jsonResult, textStatus, jqXHR){
               console.log("Response from resolve_merge_diff endpoint = ", jsonResult);
               if(jsonResult !== null) {
                  if(jsonResult.status.healthy == false) {
                     var message = "";
                     if(typeof jsonResult.status.errors != 'undefined' && jsonResult.status.errors.length > 0) {
                        if(typeof jsonResult.status.errors[0].message != 'undefined') {
                           message = "<br />"+jsonResult.status.errors[0].message;
                        }
                     }
                     $("#enotification_pp").html("Could not resolve trivial differences"+message);
                     $("#enotification_pp").jqxNotification("open");
                  }
                  else {
                     console.log("Successfully resolved trivial changes");
                     wasSuccessful = true;//this will prevent this function from refreshing save points in onComplete
                     $("#merge_diff_wndw").hide();
                     window.dvs.reload();
                  }
               }
               else {
                  $("#enotification_pp").html("Could not resolve trivial differences between projects");
                  $("#enotification_pp").jqxNotification("open");
               }
            },
            complete: function() {
               $("#apply_merge_changes").prop('disabled', false);
               $("#loading_box").hide();
               window.dvs.refreshSavePoints();
            }
         });
      }
   }
   else if($("#merged_schema_name").val().length == 0) {//the user did not define the merged sheet name
      $("#enotification_pp").html("Please specify the new name to be given to this project");
      $("#enotification_pp").jqxNotification("open");
   }
};

DMPVSchema.prototype.reload = function() {
   window.location.href = "?page=dmp&do=view_schema&project="+window.dvs.project+"&session="+window.dvs.session;
};

DMPVSchema.prototype.getProjectGroups = function() {
   var groupNames = [];
   for(var sIndex = 0; sIndex < window.dvs.schema.sheets.length; sIndex++) {
      var currSection = window.dvs.schema.sheets[sIndex];
      for(var cIndex = 0; cIndex < currSection.columns.length; cIndex++) {
         var currColumnName = currSection.columns[cIndex].name;
         var nameParts = currColumnName.split("-");
         if(groupNames.indexOf(nameParts[0]) == -1) {
            groupNames[groupNames.length] = nameParts[0];
         }
      }
   }
   console.log(groupNames);
   return groupNames;
};

DMPVSchema.prototype.getDataButtonClicked = function() {
   var filterType = $("#data_filter_type").val();
   var query = $("#filter_query").val();
   var prefixes = [];
   $("input:checkbox[name=project_groups]:checked").each(function() {
      prefixes[prefixes.length] = $(this).val();
   });
   var correct = true;
   if(filterType == "query" && query.length == 0) correct = false;
   if(filterType == "prefix" && prefixes.length == 0) correct = false;
   if(window.dvs.project != null && correct == true) {
      $("#get_data_btn2").attr("disabled", true);
      $("#apply_version_changes").prop('disabled', true);
      //first resolve the trivial conflicts then apply the non trivial ones one by one
      $("#loading_box").show();
      var sDataObj = {};
      sDataObj.workflow_id = window.dvs.project;
      sDataObj.filter = filterType;
      if(filterType == "query") {
         sDataObj.query = query;
      }
      else if(filterType == "prefix") {
         sDataObj.prefix = prefixes;
      }
      var sData = JSON.stringify(sDataObj);
      var sToken = JSON.stringify({
         "server":window.dvs.server,
         "user": window.dvs.user,
         "session": window.dvs.session
      });
      $.ajax({
         url: "mod_ajax.php?page=odk_workflow&do=get_data",
         type: "POST",
         async: true,
         data: {data: sData, token: sToken},
         statusCode: {
            400: function() {//bad request
               $("#enotification_pp").html("Was unable to get the data");
               $("#enotification_pp").jqxNotification("open");
            },
            403: function() {//forbidden
               $("#enotification_pp").html("User not allowed to get some or all of the data");
               $("#enotification_pp").jqxNotification("open");
            },
            500: function() {//forbidden
               $("#enotification_pp").html("An error occurred in the server");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         success: function(jsonResult, textStatus, jqXHR){
            console.log("Response from get_data endpoint = ", jsonResult);
            if(jsonResult !== null) {
               if(jsonResult.status.healthy == false) {
                  var message = "";
                  if(typeof jsonResult.status.errors != 'undefined' && jsonResult.status.errors.length > 0) {
                     if(typeof jsonResult.status.errors[0].message != 'undefined') {
                        message = "<br />"+jsonResult.status.errors[0].message;
                     }
                  }
                  $("#enotification_pp").html("Could not get the data"+message);
                  $("#enotification_pp").jqxNotification("open");
               }
               else {
                  $("#get_data_wndw").hide();
                  window.dvs.startDownload(jsonResult.data_file);
               }
            }
            else {
               $("#enotification_pp").html("Could not resolve trivial differences between projects");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         complete: function() {
            $("#loading_box").hide();
            $("#get_data_btn2").attr("disabled", false);
         }
      });
   }
};

DMPVSchema.prototype.startDownload = function(url) {
   $("#hiddenDownloader").remove();
   $('#repository').append("<iframe id='hiddenDownloader' style='display:none;' />");   
   $("#hiddenDownloader").attr("src", url);
};

DMPVSchema.prototype.dbCredentailsButtonClicked = function() {
   if(window.dvs.project != null && window.dvs.project.length > 0) {
      $("#loading_box").show();
      var sData = JSON.stringify({
         "workflow_id": window.dvs.project,
      });
      var sToken = JSON.stringify({
         "server":window.dvs.server,
         "user": window.dvs.user,
         "session": window.dvs.session
      });
      $.ajax({
         url: "mod_ajax.php?page=odk_workflow&do=get_db_credentials",
         type: "POST",
         async: true,
         data: {data: sData, token: sToken},
         statusCode: {
            400: function() {//bad request
               $("#enotification_pp").html("Was unable to gete database credentails");
               $("#enotification_pp").jqxNotification("open");
            },
            403: function() {//forbidden
               $("#enotification_pp").html("User not allowed exteral access to the database");
               $("#enotification_pp").jqxNotification("open");
            },
            500: function() {//forbidden
               $("#enotification_pp").html("An error occurred in the server");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         success: function(jsonResult, textStatus, jqXHR){
            console.log("Response from dump_data endpoint = ", jsonResult);
            if(jsonResult !== null) {
               if(jsonResult.status.healthy == false) {
                  var message = "";
                  if(typeof jsonResult.status.errors != 'undefined' && jsonResult.status.errors.length > 0) {
                     if(typeof jsonResult.status.errors[0].message != 'undefined') {
                        message = "<br />"+jsonResult.status.errors[0].message;
                     }
                  }
                  $("#enotification_pp").html("Could not get database credentials"+message);
                  $("#enotification_pp").jqxNotification("open");
               }
               else {
                  $("#db_cred_database").html("Database: "+window.dvs.project);
                  $("#db_cred_host").html("Host: "+jsonResult.credentials.host);
                  $("#db_cred_username").html("User: "+jsonResult.credentials.user);
                  $("#db_cred_password").html("Password: "+jsonResult.credentials.password);
                  $("#db_credentials_wndw").show();
               }
            }
            else {
               $("#enotification_pp").html("Could not get database credentails");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         complete: function() {
            $("#loading_box").hide();
            window.dvs.updateSheetList();
         }
      });
   }
};

DMPVSchema.prototype.mergeVSMenuButtonClicked = function(type) {
   if(type == "version") {
      $("#merge_schema_btn").hide();
      $("#merge_version_btn").show();
   }
   else if(type == "schema") {
      $("#merge_schema_btn").show();
      $("#merge_version_btn").hide();
   }
   if(window.dvs.project != null && window.dvs.project.length > 0) {//make sure at least we have an active project
      $("#loading_box").show();
      var sToken = JSON.stringify({
         "server":window.dvs.server,
         "user": window.dvs.user,
         "session": window.dvs.session
      });
      $.ajax({
         url: "mod_ajax.php?page=odk_workflow&do=get_workflows",
         type: "POST",
         async: true,
         data: {token: sToken},
         statusCode: {
            400: function() {//bad request
               $("#enotification_pp").html("Was unable to get the list of other projects");
               $("#enotification_pp").jqxNotification("open");
            },
            403: function() {//forbidden
               $("#enotification_pp").html("User not allowed to get the list of other projects");
               $("#enotification_pp").jqxNotification("open");
            },
            500: function() {//forbidden
               $("#enotification_pp").html("An error occurred in the server");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         success: function(jsonResult, textStatus, jqXHR){
            console.log("Response from get_workflows endpoint = ", jsonResult);
            if(jsonResult !== null) {
               if(jsonResult.status.healthy == false) {
                  var message = "";
                  if(typeof jsonResult.status.errors != 'undefined' && jsonResult.status.errors.length > 0) {
                     if(typeof jsonResult.status.errors[0].message != 'undefined') {
                        message = "<br />"+jsonResult.status.errors[0].message;
                     }
                  }
                  $("#enotification_pp").html("Could not get a list of other projects"+message);
                  $("#enotification_pp").jqxNotification("open");
               }
               else {
                  var html = "";
                  for(var pIndex = 0; pIndex < jsonResult.workflows.length; pIndex++){
                     if(jsonResult.workflows[pIndex].workflow_id != window.dvs.project){
                        html = html + "<option value='"+jsonResult.workflows[pIndex].workflow_id+"'>"+jsonResult.workflows[pIndex].workflow_name+"</option>";
                     }
                  }
                  $("#other_project_list").html(html);
                  $("#other_projects_wndw").show();
               }
            }
            else {
               $("#enotification_pp").html("Could not get a list of other projects");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         complete: function() {
            $("#loading_box").hide();
         }
      });
   }
};

DMPVSchema.prototype.mergeVersionButtonClicked = function(){
   console.log("selected option = ", $("#other_project_list").val());
   if(window.dvs.project != null && window.dvs.project.length > 0) {
      $("#loading_box").show();
      var sData = JSON.stringify({
         "workflow_id": window.dvs.project,
         "workflow_id_2": $("#other_project_list").val(),
         "type": "all"
      });//get only non-trivial diffs
      var sToken = JSON.stringify({
         "server":window.dvs.server,
         "user": window.dvs.user,
         "session": window.dvs.session
      });
      $.ajax({
         url: "mod_ajax.php?page=odk_workflow&do=get_schema_diff",
         type: "POST",
         async: true,
         data: {data: sData, token: sToken},
         statusCode: {
            400: function() {//bad request
               $("#enotification_pp").html("Was unable to get the difference in schemas");
               $("#enotification_pp").jqxNotification("open");
            },
            403: function() {//forbidden
               $("#enotification_pp").html("User not allowed to get schema differences for the two projects");
               $("#enotification_pp").jqxNotification("open");
            },
            500: function() {//forbidden
               $("#enotification_pp").html("An error occurred in the server");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         success: function(jsonResult, textStatus, jqXHR){
            console.log("Response from dump_data endpoint = ", jsonResult);
            if(jsonResult !== null) {
               if(jsonResult.status.healthy == false) {
                  var message = "";
                  if(typeof jsonResult.status.errors != 'undefined' && jsonResult.status.errors.length > 0) {
                     if(typeof jsonResult.status.errors[0].message != 'undefined') {
                        message = "<br />"+jsonResult.status.errors[0].message;
                     }
                  }
                  $("#enotification_pp").html("Could not get the difference in schemas"+message);
                  $("#enotification_pp").jqxNotification("open");
               }
               else {
                  $("#other_projects_wndw").hide();
                  window.dvs.diffProject = jsonResult.workflow_2;
                  window.dvs.initVersionDiffGrid(jsonResult.workflow_2, jsonResult.diff);
                  $("#version_diff_wndw").show();
               }
            }
            else {
               $("#enotification_pp").html("Could not get the difference in schemas");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         complete: function() {
            $("#loading_box").hide();
         }
      });
   }
};

DMPVSchema.prototype.mergeSheetButtonClicked = function(){
   console.log("merge_sheet_btn clicked");
   window.dvs.mergeKeys = null;
   if(window.dvs.project != null 
           && window.dvs.project.length > 0
           && window.dvs.diffProjectSchema != null
           && $("#curr_sheet_list").val().length > 0
           && $("#curr_column_list").val().length > 0
           && $("#other_sheet_list").val().length > 0
           && $("#other_column_list").val().length > 0) {
      $("#loading_box").show();
      var sData = JSON.stringify({
         "workflow_id": window.dvs.project,
         "workflow_id_2": window.dvs.diffProjectSchema.workflow_id,
         "type": "all",
         "key_1": {
            "sheet": $("#curr_sheet_list").val(),
            "column": $("#curr_column_list").val()
         },
         "key_2": {
            "sheet": $("#other_sheet_list").val(),
            "column": $("#other_column_list").val()
         }
      });//get only non-trivial diffs
      var sToken = JSON.stringify({
         "server":window.dvs.server,
         "user": window.dvs.user,
         "session": window.dvs.session
      });
      $.ajax({
         url: "mod_ajax.php?page=odk_workflow&do=get_merge_diff",
         type: "POST",
         async: true,
         data: {data: sData, token: sToken},
         statusCode: {
            400: function() {//bad request
               $("#enotification_pp").html("Was unable to get the difference in schemas");
               $("#enotification_pp").jqxNotification("open");
            },
            403: function() {//forbidden
               $("#enotification_pp").html("User not allowed to get schema differences for the two projects");
               $("#enotification_pp").jqxNotification("open");
            },
            500: function() {//forbidden
               $("#enotification_pp").html("An error occurred in the server");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         success: function(jsonResult, textStatus, jqXHR){
            console.log("Response from dump_data endpoint = ", jsonResult);
            if(jsonResult !== null) {
               if(jsonResult.status.healthy == false) {
                  var message = "";
                  if(typeof jsonResult.status.errors != 'undefined' && jsonResult.status.errors.length > 0) {
                     if(typeof jsonResult.status.errors[0].message != 'undefined') {
                        message = "<br />"+jsonResult.status.errors[0].message;
                     }
                  }
                  $("#enotification_pp").html("Could not get the difference in schemas"+message);
                  $("#enotification_pp").jqxNotification("open");
               }
               else {
                  $("#merge_sheet_wndw").hide();
                  window.dvs.diffProject = jsonResult.workflow_2;
                  window.dvs.initMergeDiffGrid(jsonResult.workflow_2, jsonResult.diff);
                  window.dvs.mergeKeys = {
                     "key_1": {
                        "sheet": $("#curr_sheet_list").val(),
                        "column": $("#curr_column_list").val()
                     },
                     "key_2": {
                        "sheet": $("#other_sheet_list").val(),
                        "column": $("#other_column_list").val()
                     }
                  };
                  $("#merge_diff_wndw").show();
               }
            }
            else {
               $("#enotification_pp").html("Could not get the difference in schemas");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         complete: function() {
            $("#loading_box").hide();
         }
      });
   }
};

DMPVSchema.prototype.mergeSchemaButtonClicked = function(){
   console.log("selected option = ", $("#other_project_list").val());
   if(window.dvs.project != null && window.dvs.project.length > 0) {
      $("#loading_box").show();
      var sData = JSON.stringify({
         "workflow_id": $("#other_project_list").val()
      });//get only non-trivial diffs
      var sToken = JSON.stringify({
         "server":window.dvs.server,
         "user": window.dvs.user,
         "session": window.dvs.session
      });
      $.ajax({
         url: "mod_ajax.php?page=odk_workflow&do=get_workflow_schema",
         type: "POST",
         async: true,
         data: {data: sData, token: sToken},
         statusCode: {
            400: function() {//bad request
               $("#enotification_pp").html("Was unable to get the difference in schemas");
               $("#enotification_pp").jqxNotification("open");
            },
            403: function() {//forbidden
               $("#enotification_pp").html("User not allowed to get schema differences for the two projects");
               $("#enotification_pp").jqxNotification("open");
            },
            500: function() {//forbidden
               $("#enotification_pp").html("An error occurred in the server");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         success: function(jsonResult, textStatus, jqXHR){
            console.log("Response from get_workflow_schema endpoint = ", jsonResult);
            if(jsonResult !== null) {
               if(jsonResult.status.healthy == false) {
                  var message = "";
                  if(typeof jsonResult.status.errors != 'undefined' && jsonResult.status.errors.length > 0) {
                     if(typeof jsonResult.status.errors[0].message != 'undefined') {
                        message = "<br />"+jsonResult.status.errors[0].message;
                     }
                  }
                  $("#enotification_pp").html("Could not get the difference in schemas"+message);
                  $("#enotification_pp").jqxNotification("open");
               }
               else {
                  $("#other_projects_wndw").hide();
                  console.log(jsonResult);
                  window.dvs.diffProjectSchema = jsonResult.schema;
                  window.dvs.diffProject = $("#other_project_list").val();
                  window.dvs.initMergeSheetWindow();
                  $("#merge_sheet_wndw").show();
               }
            }
            else {
               $("#enotification_pp").html("Could not get the difference in schemas");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         complete: function() {
            $("#loading_box").hide();
         }
      });
   }
};

DMPVSchema.prototype.initMergeSheetWindow = function() {
   //init section on other schema
   var otherSchema = window.dvs.diffProjectSchema;
   $("#other_project_name").html(otherSchema.title);
   var html = "";
   for(var index = 0; index < otherSchema.sheets.length; index++) {
      html = html + "<option value='"+otherSchema.sheets[index].name+"'>"+otherSchema.sheets[index].name+"</option>";
   }
   $("#other_sheet_list").html(html);
   html = "";
   if(otherSchema.sheets.length > 0) {
      for(var index = 0; index < otherSchema.sheets[0].columns.length; index++) {
         html = html + "<option value='"+otherSchema.sheets[0].columns[index].name+"'>"+otherSchema.sheets[0].columns[index].name+"</option>";
      }
      $("#other_column_list").html(html);
   }
   $("#other_sheet_list").change(function() {
      var currSheet = $("#other_sheet_list").val();
      var otherSchema = window.dvs.diffProjectSchema;//reinitialize schema
      for(var index = 0; index < otherSchema.sheets.length; index++) {
         if(otherSchema.sheets[index].name == currSheet) {
            var html = "";
            for(var cIndex = 0; cIndex < otherSchema.sheets[index].columns.length; cIndex++) {
               html = html + "<option value='"+otherSchema.sheets[index].columns[cIndex].name+"'>"+otherSchema.sheets[index].columns[cIndex].name+"</option>";
            }
            $("#other_column_list").html(html);
            break;
         }
      }
   });
   
   //init section on current schema
   var currSchema = window.dvs.schema;
   $("#curr_project_name").html(currSchema.title);
   var html = "";
   for(var index = 0; index < currSchema.sheets.length; index++) {
      html = html + "<option value='"+currSchema.sheets[index].name+"'>"+currSchema.sheets[index].name+"</option>";
   }
   $("#curr_sheet_list").html(html);
   html = "";
   if(currSchema.sheets.length > 0) {
      for(var index = 0; index < currSchema.sheets[0].columns.length; index++) {
         html = html + "<option value='"+currSchema.sheets[0].columns[index].name+"'>"+currSchema.sheets[0].columns[index].name+"</option>";
      }
      $("#curr_column_list").html(html);
   }
   $("#curr_sheet_list").change(function() {
      var currSheet = $("#curr_sheet_list").val();
      var currSchema = window.dvs.schema;//reinitialize schema
      for(var index = 0; index < currSchema.sheets.length; index++) {
         if(currSchema.sheets[index].name == currSheet) {
            var html = "";
            for(var cIndex = 0; cIndex < currSchema.sheets[index].columns.length; cIndex++) {
               html = html + "<option value='"+currSchema.sheets[index].columns[cIndex].name+"'>"+currSchema.sheets[index].columns[cIndex].name+"</option>";
            }
            $("#curr_column_list").html(html);
            break;
         }
      }
   });
};

/**
 * This function initializes the jqxGrid displaying schema links
 * 
 * @returns {undefined}
 */
DMPVSchema.prototype.initVersionDiffGrid = function(project2, diffs) {
   console.log("initializing diff grid");
   var data = {
      diffs: []
   };
   if(diffs.length > 0) {
      $("#version_diff_wndw").jqxWindow({height: window.innerHeight * 0.9});
      $("#version_diff_grid").show();
   }
   else {
      $("#version_diff_wndw").jqxWindow({height: '150px'});
      $("#version_diff_grid").hide();
   }
   for(var diffIndex = 0; diffIndex < diffs.length; diffIndex++){
      var currDiffData = {};
      if(diffs[diffIndex].level == "column"){
         currDiffData['sheet'] = diffs[diffIndex].sheet;
         currDiffData['name'] = diffs[diffIndex][window.dvs.project].name;
         currDiffData['type'] = diffs[diffIndex][window.dvs.project].type;
         currDiffData['vlength'] = diffs[diffIndex][window.dvs.project].length;
         currDiffData['nullable'] = diffs[diffIndex][window.dvs.project].nullable;
         currDiffData['default'] = diffs[diffIndex][window.dvs.project].default;
         currDiffData['key'] = diffs[diffIndex][window.dvs.project].key;
         currDiffData['present'] = true;
         currDiffData['type_2'] = diffs[diffIndex][project2].type;
         currDiffData['length_2'] = diffs[diffIndex][project2].length;
         currDiffData['nullable_2'] = diffs[diffIndex][project2].nullable;
         currDiffData['default_2'] = diffs[diffIndex][project2].default;
      }
      data.diffs[diffIndex] = currDiffData;
   }
   
   var source = {
      datatype: "json",
      datafields: [
         {name: 'present'},
         {name: 'sheet'},
         {name: 'name'},
         {name: 'type'},
         {name: 'type_2'},
         {name: 'vlength'},
         {name: 'length_2'},
         {name: 'nullable'},
         {name: 'nullable_2'},
         {name: 'default'},
         {name: 'default_2'},
         {name: 'key'}
      ],
      root: 'diffs',
      localdata: data
   };
   
   window.dvs.versionDiffGridAdapter = new $.jqx.dataAdapter(source);
   
   var columnTypes = [
         'varchar',
         'numeric',
         'double precision',
         'smallint',
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
   var gridWidth = $("#version_diff_wndw").width() * 0.95;
   $("#version_diff_grid").jqxGrid({
      width: window.dvs.rightSideWidth,
      height: '80%',
      source: window.dvs.versionDiffGridAdapter,
      columnsresize: true,
      theme: '',
      selectionmode: "singlerow",
      pageable: false,
      editable: true,
      rendergridrows: function() {
         return window.dvs.versionDiffGridAdapter.records;
      },
      columns: [
         {text: 'Sheet', datafield: 'sheet', editable:false, width: gridWidth * 0.1},
         {text: 'Name', datafield: 'name', editable:false, width: gridWidth * 0.18},
         {text: 'Type', columntype: 'dropdownlist', width: gridWidth * 0.1 , datafield: 'type',initeditor: function (row, cellvalue, editor) {
               editor.jqxDropDownList({ source: columnTypes});
         }, cellsrenderer: function(row, column, value) {
            var rowData = $("#version_diff_grid").jqxGrid('getrowdata', row);
            var color = "";
            if(rowData.type != rowData.type_2){
               color = "color:#FF3D3D; font-weight:bold;";
            }
            return '<div style="overflow: hidden; text-overflow: ellipsis; padding-bottom: 2px; text-align: left; margin-right: 2px; margin-left: 4px; margin-top: 4px; '+color+'">' + value + '</div>';
         }},
         {text: 'Conflict Type', datafield: 'type_2', editable: false, width: gridWidth * 0.1},
         {text: 'Length', columntype: 'numberinput', datafield: 'vlength', width: gridWidth * 0.08},
         {text: 'Conflict Length', datafield: 'length_2', editable: false, width: gridWidth * 0.08},
         {text: 'Nullable', columntype: 'dropdownlist', width: gridWidth * 0.08, datafield: 'nullable',initeditor: function (row, cellvalue, editor) {
               editor.jqxDropDownList({ source: nullableTypes});
         }},
         {text: 'Conflict Nullable', datafield: 'nullable_2', width: gridWidth * 0.08, editable: false},
         {text: 'Default', datafield: 'default', width: gridWidth * 0.1, cellsrenderer: function(row, column, value) {
            var rowData = $("#version_diff_grid").jqxGrid('getrowdata', row);
            var color = "";
            if(rowData.default != rowData.default_2){
               color = "color:#FF3D3D; font-weight:bold;";
            }
            return '<div style="overflow: hidden; text-overflow: ellipsis; padding-bottom: 2px; text-align: left; margin-right: 2px; margin-left: 4px; margin-top: 4px; '+color+'">' + value + '</div>';
         }},
         {text: 'Conflict Default', datafield: 'default_2', editable: false, width: gridWidth * 0.1}
      ]
   });
   window.dvs.click = {
      time: new Date(),
      last_time: new Date(),
      last_row: -1
   };
   $("#version_diff_grid").on('cellendedit', window.dvs.versionDiffGridCellValueChanged);
};

/**
 * This function initializes the jqxGrid displaying notes
 * 
 * @returns {undefined}
 */
DMPVSchema.prototype.initNotesGrid = function(notes) {
   console.log("initializing notes grid");
   
   var source = {
      datatype: "json",
      datafields: [
         {name: 'user'},
         {name: 'time_added'},
         {name: 'message'},
         {name: 'id'}
      ],
      localdata: notes
   };
   
   window.dvs.notesGridAdapter = new $.jqx.dataAdapter(source);
   var gridWidth = $("#notes_wndw").width() * 0.95;
   $("#notes_grid").jqxGrid({
      width: gridWidth,
      autoheight: true,
      autorowheight: true,
      source: window.dvs.notesGridAdapter,
      columnsresize: true,
      theme: '',
      selectionmode: "singlerow",
      pageable: false,
      editable: false,
      rendergridrows: function() {
         return window.dvs.notesGridAdapter.records;
      },
      columns: [
         {text: 'Time', datafield: 'time_added', editable:false, width: gridWidth * 0.2},
         {text: 'User', datafield: 'user', editable:false, width: gridWidth * 0.15},
         {text: 'Note', datafield: 'message', editable:false, width: gridWidth * 0.65, cellsrenderer: function (row, columnfield, value, defaulthtml, columnproperties) {
            return '<div style="white-space: normal;">' + value + '</div>';
         }}
      ]
   });
   $("#notes_grid").on('rowclick', function (event) {
      if (event.args.rightclick) {
         var rowIndex = event.args.rowindex;
         $("#notes_grid").jqxGrid('selectrow', rowIndex);
         $("#right_click_menu").html('<ul><li><a href="#" id="delete_note_btn">Delete note</a></li></ul>');
         var scrollTop = $(window).scrollTop();
         var scrollLeft = $(window).scrollLeft();
         $("#right_click_menu").jqxMenu('open',  parseInt(event.args.originalEvent.clientX) + 5 + scrollLeft, parseInt(event.args.originalEvent.clientY) + 5 + scrollTop);
         $("#delete_note_btn").click(window.dvs.deleteNoteButtonClicked);
         return false;
      }
  });
};

DMPVSchema.prototype.deleteNoteButtonClicked = function() {
   var noteData = $("#notes_grid").jqxGrid('getrowdata', $("#notes_grid").jqxGrid('selectedrowindex'));
   if(typeof noteData != 'undefined') {
      window.dvs.deleteNote(noteData.id);
   }
   $("#right_click_menu").jqxMenu('close');
};

DMPVSchema.prototype.deleteNote = function($noteId) {
   if(window.dvs.project != null && $noteId != null) {
      $("#loading_box").show();
      var sData = JSON.stringify({
         "workflow_id": window.dvs.project,
         "note_id": $noteId
      });
      var sToken = JSON.stringify({
         "server":window.dvs.server,
         "user": window.dvs.user,
         "session": window.dvs.session
      });
      $.ajax({
         url: "mod_ajax.php?page=odk_workflow&do=delete_note",
         type: "POST",
         async: true,
         data: {data: sData, token: sToken},
         statusCode: {
            400: function() {//bad request
               $("#enotification_pp").html("Was unable to delete note");
               $("#enotification_pp").jqxNotification("open");
            },
            403: function() {//forbidden
               $("#enotification_pp").html("User not allowed to delete notes");
               $("#enotification_pp").jqxNotification("open");
            },
            500: function() {//forbidden
               $("#enotification_pp").html("An error occurred in the server");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         success: function(jsonResult, textStatus, jqXHR){
            console.log("Response from delete_note endpoint = ", jsonResult);
            if(jsonResult !== null) {
               if(jsonResult.status.healthy == false) {
                  var message = "";
                  if(typeof jsonResult.status.errors != 'undefined' && jsonResult.status.errors.length > 0) {
                     if(typeof jsonResult.status.errors[0].message != 'undefined') {
                        message = "<br />"+jsonResult.status.errors[0].message;
                     }
                  }
                  $("#enotification_pp").html("Could not get notes"+message);
                  $("#enotification_pp").jqxNotification("open");
               }
               else {
                  window.dvs.refreshNotes();
               }
            }
            else {
               $("#enotification_pp").html("Could not resolve trivial differences between projects");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         complete: function() {
            $("#loading_box").hide();
            window.dvs.refreshSavePoints();
         }
      });
   }
};

/**
 * This function initializes the jqxGrid displaying schema links
 * 
 * @returns {undefined}
 */
DMPVSchema.prototype.initMergeDiffGrid = function(project2, diffs) {
   console.log("initializing diff grid");
   var data = {
      diffs: []
   };
   if(diffs.length > 0) {
      $("#merge_diff_wndw").jqxWindow({height: window.innerHeight * 0.9});
      $("#merge_diff_grid").show();
   }
   else {
      $("#merge_diff_wndw").jqxWindow({height: '150px'});
      $("#merge_diff_grid").hide();
   }
   for(var diffIndex = 0; diffIndex < diffs.length; diffIndex++){
      var currDiffData = {};
      if(diffs[diffIndex].level == "column"){
         //check if diff is missing or conflict
         if(diffs[diffIndex].type == "conflict") {
            currDiffData['sheet'] = diffs[diffIndex].sheet[window.dvs.project];
            currDiffData['name'] = diffs[diffIndex][window.dvs.project].name;
            currDiffData['type'] = diffs[diffIndex][window.dvs.project].type;
            currDiffData['vlength'] = diffs[diffIndex][window.dvs.project]['length'];
            currDiffData['nullable'] = diffs[diffIndex][window.dvs.project].nullable;
            currDiffData['default'] = diffs[diffIndex][window.dvs.project].default;
            currDiffData['key'] = diffs[diffIndex][window.dvs.project].key;
            currDiffData['sheet_2'] = diffs[diffIndex].sheet[project2];
            currDiffData['name_2'] = diffs[diffIndex][project2].name;
            currDiffData['type_2'] = diffs[diffIndex][project2].type;
            currDiffData['length_2'] = diffs[diffIndex][project2]['length'];
            currDiffData['nullable_2'] = diffs[diffIndex][project2].nullable;
            currDiffData['default_2'] = diffs[diffIndex][project2].default;
            data.diffs[data.diffs.length] = currDiffData;
         }
      }
   }
   console.log("diffs", data.diffs);
   var source = {
      datatype: "json",
      datafields: [
         {name: 'sheet'},
         {name: 'sheet_2'},
         {name: 'name'},
         {name: 'name_2'},
         {name: 'type'},
         {name: 'type_2'},
         {name: 'vlength'},
         {name: 'length_2'},
         {name: 'nullable'},
         {name: 'nullable_2'},
         {name: 'default'},
         {name: 'default_2'},
         {name: 'key'}
      ],
      root: 'diffs',
      localdata: data
   };
   
   window.dvs.mergeDiffGridAdapter = new $.jqx.dataAdapter(source);
   
   var columnTypes = [
         'varchar',
         'numeric',
         'double precision',
         'smallint',
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
   var gridWidth = $("#merge_diff_wndw").width() * 0.95;
   $("#merge_diff_grid").jqxGrid({
      width: '100%',
      height: '80%',
      source: window.dvs.mergeDiffGridAdapter,
      columnsresize: true,
      theme: '',
      selectionmode: "singlerow",
      pageable: false,
      editable: true,
      rendergridrows: function() {
         return window.dvs.mergeDiffGridAdapter.records;
      },
      columns: [
         {text: 'Sheet', datafield: 'sheet', editable:false, width: gridWidth * 0.1},
         {text: 'Sheet', datafield: 'sheet_2', editable:false, width: gridWidth * 0.1, cellclassname: 'column_diff_project'},
         {text: 'Name', datafield: 'name', editable:true, width: gridWidth * 0.18},
         {text: 'Name', datafield: 'name_2', editable:false, width: gridWidth * 0.18, cellclassname: 'column_diff_project'},
         {text: 'Type', columntype: 'dropdownlist', width: gridWidth * 0.1 , datafield: 'type',initeditor: function (row, cellvalue, editor) {
               editor.jqxDropDownList({ source: columnTypes});
         }, cellsrenderer: function(row, column, value) {
            var rowData = $("#merge_diff_grid").jqxGrid('getrowdata', row);
            var color = "";
            if(rowData.type != rowData.type_2){
               color = "color:#FF3D3D; font-weight:bold;";
            }
            return '<div style="overflow: hidden; text-overflow: ellipsis; padding-bottom: 2px; text-align: left; margin-right: 2px; margin-left: 4px; margin-top: 4px; '+color+'">' + value + '</div>';
         }},
         {text: 'Type', columntype: 'dropdownlist', editable: false, cellclassname: 'column_diff_project', width: gridWidth * 0.1 , datafield: 'type_2',initeditor: function (row, cellvalue, editor) {
               editor.jqxDropDownList({ source: columnTypes});
         }, cellsrenderer: function(row, column, value) {
            var rowData = $("#merge_diff_grid").jqxGrid('getrowdata', row);
            var color = "";
            if(rowData.type != rowData.type_2){
               color = "color:#FF3D3D; font-weight:bold;";
            }
            return '<div style="overflow: hidden; text-overflow: ellipsis; padding-bottom: 2px; text-align: left; margin-right: 2px; margin-left: 4px; margin-top: 4px; '+color+'">' + value + '</div>';
         }},
         {text: 'Length', columntype: 'numberinput', datafield: 'vlength', width: gridWidth * 0.08},
         {text: 'Length', columntype: 'numberinput', editable: false, datafield: 'length_2', width: gridWidth * 0.08, cellclassname: 'column_diff_project'},
         {text: 'Nullable', columntype: 'dropdownlist', width: gridWidth * 0.08, datafield: 'nullable',initeditor: function (row, cellvalue, editor) {
               editor.jqxDropDownList({ source: nullableTypes});
         }},
         {text: 'Nullable', columntype: 'dropdownlist', cellclassname: 'column_diff_project', editable: false, width: gridWidth * 0.08, datafield: 'nullable_2',initeditor: function (row, cellvalue, editor) {
               editor.jqxDropDownList({ source: nullableTypes});
         }},
         {text: 'Default', datafield: 'default', width: gridWidth * 0.1, cellsrenderer: function(row, column, value) {
            var rowData = $("#merge_diff_grid").jqxGrid('getrowdata', row);
            var color = "";
            if(rowData.default != rowData.default_2){
               color = "color:#FF3D3D; font-weight:bold;";
            }
            return '<div style="overflow: hidden; text-overflow: ellipsis; padding-bottom: 2px; text-align: left; margin-right: 2px; margin-left: 4px; margin-top: 4px; '+color+'">' + value + '</div>';
         }},
         {text: 'Default', datafield: 'default_2', cellclassname: 'column_diff_project', editable: false, width: gridWidth * 0.1, cellsrenderer: function(row, column, value) {
            var rowData = $("#merge_diff_grid").jqxGrid('getrowdata', row);
            var color = "";
            if(rowData.default != rowData.default_2){
               color = "color:#FF3D3D; font-weight:bold;";
            }
            return '<div style="overflow: hidden; text-overflow: ellipsis; padding-bottom: 2px; text-align: left; margin-right: 2px; margin-left: 4px; margin-top: 4px; '+color+'">' + value + '</div>';
         }}
      ]
   });
   window.dvs.click = {
      time: new Date(),
      last_time: new Date(),
      last_row: -1
   };
   $("#merge_diff_grid").on('cellendedit', window.dvs.mergeDiffGridCellValueChanged);
};

DMPVSchema.prototype.versionDiffGridCellValueChanged = function(event) {
   console.log("Cell in diff grid changed");
   if(window.dvs.schema != null && event.args.oldvalue !== event.args.value) {
      console.log(event);
      var columnData = event.args.row;
      if(typeof columnData.sheet != 'undefined' && typeof columnData.name != 'undefined') {
         console.log("sheet data found");
         var sheetName = columnData.sheet;
         var columnName = columnData.name;
         if(typeof window.dvs.schemaChanges[sheetName] === 'undefined') {//initialize the sheet in the changes object
            window.dvs.schemaChanges[sheetName] = {};
         }
         
         if(typeof window.dvs.schemaChanges[sheetName][columnName] === 'undefined') {
            window.dvs.schemaChanges[sheetName][columnName] = {};
         }
         
         window.dvs.schemaChanges[sheetName][columnName] = columnData;
         window.dvs.schemaChanges[sheetName][columnName].original_name = columnName;
         
         console.log(window.dvs.schemaChanges);
      }
   }
   
};

DMPVSchema.prototype.mergeDiffGridCellValueChanged = function(event) {
   //check if change is for this project or the other project
   console.log("Event", event);
   if(event.args.datafield == "name"
           || event.args.datafield == "type"
           || event.args.datafield == "vlength"
           || event.args.datafield == "nullable"
           || event.args.datafield == "default") {//change is for this project
      if(window.dvs.schema != null && event.args.oldvalue !== event.args.value) {
         console.log("Change is to this project");
         var columnData = event.args.row;
         columnData.present = true;
         if(typeof columnData.sheet != 'undefined' && typeof columnData.name != 'undefined') {
            //search for the index of the column in the schema object
            var rowIndex = -1;
            var sheetName = columnData.sheet;
            var columnName = columnData.name;
            for(var sIndex = 0; sIndex < window.dvs.schema.sheets.length; sIndex++) {
               if(window.dvs.schema.sheets[sIndex].name == sheetName) {
                  for(var cIndex = 0; cIndex < window.dvs.schema.sheets[sIndex].columns.length; cIndex++) {
                     if(window.dvs.schema.sheets[sIndex].columns[cIndex].name == columnName) {
                        rowIndex = cIndex;
                        break;
                     }
                  }
                  break;
               }
            }
            if(rowIndex != -1) {
               var columnChanged = window.dvs.changeColumnDetails(sheetName, rowIndex, event.args.datafield, event.args.oldvalue, event.args.value, columnData);
               if(columnChanged == true) {
                  console.log("column changed");
               }
               else {
                  console.log("could not change column")
               }
            }
            else {
               console.log("could not get the row index for the current column");
            }
         }
      }
   }
   /*else if(event.args.datafield == "sheet") {
      var sheetChanged = window.dvs.renameSheetButton2Clicked(event.args.oldvalue, event.args.value);
      
   }
   else {//should be considere change in the diff project
      console.log("Change is to the other project");
   }*/
   
};

DMPVSchema.prototype.dumpDataButtonClicked = function() {
   if(window.dvs.project != null && window.dvs.project.length > 0) {
      $("#loading_box").show();
      var sData = JSON.stringify({
         "workflow_id": window.dvs.project,
      });
      var sToken = JSON.stringify({
         "server":window.dvs.server,
         "user": window.dvs.user,
         "session": window.dvs.session
      });
      $.ajax({
         url: "mod_ajax.php?page=odk_workflow&do=dump_data",
         type: "POST",
         async: true,
         data: {data: sData, token: sToken},
         statusCode: {
            400: function() {//bad request
               $("#enotification_pp").html("Was unable to dump data");
               $("#enotification_pp").jqxNotification("open");
            },
            403: function() {//forbidden
               $("#enotification_pp").html("User not allowed to dump data");
               $("#enotification_pp").jqxNotification("open");
            },
            500: function() {//forbidden
               $("#enotification_pp").html("An error occurred in the server");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         success: function(jsonResult, textStatus, jqXHR){
            console.log("Response from dump_data endpoint = ", jsonResult);
            if(jsonResult !== null) {
               if(jsonResult.status.healthy == false) {
                  var message = "";
                  if(typeof jsonResult.status.errors != 'undefined' && jsonResult.status.errors.length > 0) {
                     if(typeof jsonResult.status.errors[0].message != 'undefined') {
                        message = "<br />"+jsonResult.status.errors[0].message;
                     }
                  }
                  $("#enotification_pp").html("Could dump data"+message);
                  $("#enotification_pp").jqxNotification("open");
               }
               else {
                  $("#inotification_pp").html("Data successfully dumped");
                  $("#inotification_pp").jqxNotification("open");
               }
            }
            else {
               $("#enotification_pp").html("Could not dump data");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         complete: function() {
            $("#loading_box").hide();
            window.dvs.refreshSavePoints();
            window.dvs.updateSheetList();
         }
      });
   }
};

DMPVSchema.prototype.renameProjectButtonClicked = function() {
   if(window.dvs.project != null && window.dvs.project.length > 0 && $("#new_project_name").val().length > 0) {
      $("#loading_box").show();
      var sData = JSON.stringify({
         "workflow_id": window.dvs.project,
         "name": $("#new_project_name").val()
      });
      var sToken = JSON.stringify({
         "server":window.dvs.server,
         "user": window.dvs.user,
         "session": window.dvs.session
      });
      $.ajax({
         url: "mod_ajax.php?page=odk_workflow&do=alter_name",
         type: "POST",
         async: true,
         data: {data: sData, token: sToken},
         statusCode: {
            400: function() {//bad request
               $("#enotification_pp").html("Was unable to rename project");
               $("#enotification_pp").jqxNotification("open");
            },
            403: function() {//forbidden
               $("#enotification_pp").html("User not allowed to rename project");
               $("#enotification_pp").jqxNotification("open");
            },
            500: function() {//forbidden
               $("#enotification_pp").html("An error occurred in the server");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         success: function(jsonResult, textStatus, jqXHR){
            console.log("Response from alter_name endpoint = ", jsonResult);
            if(jsonResult !== null) {
               if(jsonResult.status.healthy == false) {
                  var message = "";
                  if(typeof jsonResult.status.errors != 'undefined' && jsonResult.status.errors.length > 0) {
                     if(typeof jsonResult.status.errors[0].message != 'undefined') {
                        message = "<br />"+jsonResult.status.errors[0].message;
                     }
                  }
                  $("#enotification_pp").html("Could rename project"+message);
                  $("#enotification_pp").jqxNotification("open");
               }
               else {
                  $("#inotification_pp").html("Successfully renamed project");
                  $("#inotification_pp").jqxNotification("open");
                  window.dvs.reload();
               }
            }
            else {
               $("#enotification_pp").html("Could not rename project");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         complete: function() {
            $("#loading_box").hide();
            window.dvs.refreshSavePoints();
         }
      });
   }
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
 * 
 * @returns {undefined}
 */
DMPVSchema.prototype.renameSheetButton2Clicked = function (oldSheetName, newSheetName) {
   if(oldSheetName.length > 0 && newSheetName.length > 0 && window.dvs.project != null) {
      var selectedSheetIndex = $("#sheets").jqxListBox("getSelectedIndex");
      var sheet = window.dvs.schema.sheets[selectedSheetIndex];
      $("#loading_box").show();
      var sData = JSON.stringify({
         "workflow_id": window.dvs.project,
         "sheet":{"original_name":oldSheetName, "name":newSheetName, "delete":false}
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
            },
            500: function() {//forbidden
               $("#enotification_pp").html("An error occurred in the server");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         success: function(jsonResult, textStatus, jqXHR){
            console.log("Response from alter_sheet endpoint = ", jsonResult);
            if(jsonResult !== null) {
               if(jsonResult.status.healthy == false) {
                  var message = "";
                  if(typeof jsonResult.status.errors != 'undefined' && jsonResult.status.errors.length > 0) {
                     if(typeof jsonResult.status.errors[0].message != 'undefined') {
                        message = "<br />"+jsonResult.status.errors[0].message;
                     }
                  }
                  $("#enotification_pp").html("Could not rename sheet"+message);
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
            window.dvs.refreshSavePoints();
            window.dvs.updateSheetList();
         }
      });
      if(canContinue == true) {
         $("#rename_sheet_wndw").hide();
         $("#inotification_pp").html(" successfully renamed "+oldSheetName+" to "+newSheetName);
         $("#inotification_pp").jqxNotification("open");
      }
   }
   return canContinue;
};

/**
 * This function is fired whenever the delete sheet button is clicked on the right
 * click contextual menu
 * 
 * @returns {undefined}
 */
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
            },
            500: function() {//forbidden
               $("#enotification_pp").html("An error occurred in the server");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         success: function(jsonResult, textStatus, jqXHR){
            console.log("Response from alter_sheet endpoint = ", jsonResult);
            if(jsonResult !== null) {
               if(jsonResult.status.healthy == false) {
                  var message = "";
                  if(typeof jsonResult.status.errors != 'undefined' && jsonResult.status.errors.length > 0) {
                     if(typeof jsonResult.status.errors[0].message != 'undefined') {
                        message = "<br />"+jsonResult.status.errors[0].message;
                     }
                  }
                  $("#enotification_pp").html("Could not delete sheet "+sheet.name+message);
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
            window.dvs.refreshSavePoints();
            window.dvs.updateSheetList();
         }
      });
      if(canContinue == true) {
         $("#inotification_pp").html(sheet.name+" successfully deleted");
         $("#inotification_pp").jqxNotification("open");
      }
   }
};

/**
 * This function is fired whenever the delete button in the delete_project_wndw
 * is clicked
 * 
 * @returns {undefined}
 */
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
            },
            500: function() {//forbidden
               $("#enotification_pp").html("An error occurred in the server");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         success: function(jsonResult, textStatus, jqXHR){
            if(jsonResult !== null) {
               if(jsonResult.status.healthy == true) {
                  window.location.href = "?page=dmp";
               }
               else if(jsonResult.status.healthy == false) {
                  var message = "";
                  if(typeof jsonResult.status.errors != 'undefined' && jsonResult.status.errors.length > 0) {
                     if(typeof jsonResult.status.errors[0].message != 'undefined') {
                        message = "<br />"+jsonResult.status.errors[0].message;
                     }
                  }
                  $("#enotification_pp").html("Could not delete project"+message);
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

/**
 * This function is fired whenever a file is uploaded using the jqxFile element
 * 
 * @param {Object} event   Event object returned from the listener
 * @returns {undefined}
 */
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

/**
 * This function does everthing that needs to be done before a file upload starts
 * 
 * @returns {undefined}
 */
DMPVSchema.prototype.fileUploadStart = function() {
   window.dvs.uploadFileLoc = null;
};

/**
 * This function is fired whenever the create button is clicked in the new_project_wndw
 * 
 * @returns {undefined}
 */
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
            },
            500: function() {//forbidden
               $("#enotification_pp").html("An error occurred in the server");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         success: function(jsonResult, textStatus, jqXHR){
            console.log("Response from init_workflow endpoint = ", jsonResult);
            if(jsonResult !== null) {
               if(jsonResult.status.healthy == true) {
                  $("#inotification_pp").html("Done creating project");
                  $("#inotification_pp").jqxNotification("open");
                  $("#new_project_wndw").hide();
                  window.dvs.project = jsonResult.workflow_id;
                  window.dvs.processProjectSchema();
               }
               else if(jsonResult.status.healthy == false) {
                  var message = "";
                  if(typeof jsonResult.status.errors != 'undefined' && jsonResult.status.errors.length > 0) {
                     if(typeof jsonResult.status.errors[0].message != 'undefined') {
                        message = "<br />"+jsonResult.status.errors[0].message;
                     }
                  }
                  $("#enotification_pp").html("Could not create project"+message);
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

/**
 * This function calls the process_mysql_schema endpoint in the ODK Workflow API
 * 
 * @returns {undefined}
 */
DMPVSchema.prototype.processProjectSchema = function() {
   if(window.dvs.project != null) {
      $("#loading_box").show();
      var sData = JSON.stringify({
         "workflow_id": window.dvs.project,
         "link_sheets": false
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
            },
            500: function() {//forbidden
               $("#enotification_pp").html("An error occurred in the server");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         success: function(jsonResult, textStatus, jqXHR){
            console.log("Response from process_mysql_schema endpoint = ", jsonResult);
            if(jsonResult !== null) {
               if(jsonResult.status.healthy == true) {
                  $("#inotification_pp").html("Done processing project schema");
                  $("#inotification_pp").jqxNotification("open");
                  //window.dvs.updateSheetList();
                  //don't try to be a genious. reload the page
                  window.dvs.reload();
               }
               else if(jsonResult.status.healthy == false) {
                  var message = "";
                  if(typeof jsonResult.status.errors != 'undefined' && jsonResult.status.errors.length > 0) {
                     if(typeof jsonResult.status.errors[0].message != 'undefined') {
                        message = "<br />"+jsonResult.status.errors[0].message;
                     }
                  }
                  $("#enotification_pp").html("Could process project schema"+message);
                  $("#enotification_pp").jqxNotification("open");
               }
            }
            else {
               $("#enotification_pp").html("Could not process project schema");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         complete: function() {
         }
      });
   }
};

/**
 * This function is fired wheneve the cancel button (below the jqxGrid) is clicked
 * 
 * @returns {undefined}
 */
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

/**
 * This function refreshes the list of save points corresponding to the current
 * project
 * 
 * @returns {undefined}
 */
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
            },
            500: function() {//forbidden
               $("#enotification_pp").html("An error occurred in the server");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         success: function(jsonResult, textStatus, jqXHR){
            console.log("Response from get_save_points endpoint = ", jsonResult);
            if(jsonResult !== null) {
               if(jsonResult.status.healthy == true) {
                  Number.prototype.toOrdinal = function() {
                     var n = this.valueOf(),
                     s = [ 'th', 'st', 'nd', 'rd' ],
                     v = n % 100;
                     return n + (s[(v-20)%10] || s[v] || s[0]);
                  };
                  Number.prototype.timePad = function() {
                     var n = this.valueOf()+"";//convert to string
                     if(n.length == 1) n = "0"+n;
                     return n;
                  };
                  var months = ["Jan", "Feb", "March", "April", "May", "June", "July", "Aug", "Sept", "Oct", "Nov", "Dec"];
                  var html = "";
                  var savePoints = jsonResult.save_points;
                  for(var index = 0; index < savePoints.length; index++) {
                     var today = new Date();
                     var savePointDate = new Date(savePoints[index].time_created);
                     var year = -1;
                     if(today.getFullYear() != savePointDate.getFullYear()) year = savePointDate.getFullYear();
                     var month = -1;
                     if(today.getMonth() != savePointDate.getMonth() || year != -1) month = savePointDate.getMonth();
                     var day = -1;
                     if(today.getDate() != savePointDate.getDate() || month != -1) day = savePointDate.getDate();
                     if(day != -1) day = " " + day.toOrdinal();
                     else day = "";
                     if(month == -1) month = "";
                     else month = " " + months[month];
                     if(year == -1) year = "";
                     else year = " " + year;
                     var timeString = day + month + year;
                     if(timeString.length > 0)timeString = "on the "+timeString+" at ";
                     else timeString = "at ";
                     timeString = timeString + (savePointDate.getHours()).timePad() + ":" + savePointDate.getMinutes().timePad() + ":" + savePointDate.getSeconds().timePad();
                     html = html + "<li id='"+savePoints[index].filename+"'>"+savePoints[index].comment+" "+timeString+"</li>";
                  }
                  $("#undo_container").html(html);
                  $("#undo_container li").css("cursor", "pointer");
                  $("#undo_container li").click(function(){
                     window.dvs.restoreSavePoint($(this).attr("id"));
                  });
                  $("#undo_container li").mouseover(function(){
                     console.log("focus in");
                     $(this).css("background", "#D1D1D1");
                  });
                  $("#undo_container li").mouseleave(function(){
                     $(this).css("background", "#FFFFFF");
                  });
               }
               else if(jsonResult.status.healthy == false) {
                  var message = "";
                  if(typeof jsonResult.status.errors != 'undefined' && jsonResult.status.errors.length > 0) {
                     if(typeof jsonResult.status.errors[0].message != 'undefined') {
                        message = "<br />"+jsonResult.status.errors[0].message;
                     }
                  }
                  $("#enotification_pp").html("Could not fetch save points"+message);
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

/**
 * This function restores a project to the specifed save point
 * 
 * @param {type} savePoint The name of the save point to restore to
 * @returns {undefined}
 */
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
         },
         500: function() {//forbidden
            $("#enotification_pp").html("An error occurred in the server");
            $("#enotification_pp").jqxNotification("open");
         }
      },
      success: function(jsonResult, textStatus, jqXHR){
         console.log("Response from restore_save_point endpoint = ", jsonResult);
         if(jsonResult !== null) {
            if(jsonResult.status.healthy == true) {
               window.dvs.updateSheetList();
               $("#inotification_pp").html("Changes undone");
               $("#inotification_pp").jqxNotification("open");
            }
            else if(jsonResult.status.healthy == false) {
               var message = "";
               if(typeof jsonResult.status.errors != 'undefined' && jsonResult.status.errors.length > 0) {
                  if(typeof jsonResult.status.errors[0].message != 'undefined') {
                     message = "<br />"+jsonResult.status.errors[0].message;
                  }
               }
               $("#enotification_pp").html("Could not undo changes"+message);
               $("#enotification_pp").jqxNotification("open");
            }
         }
         else {
            $("#enotification_pp").html("Could not undo changes");
            $("#enotification_pp").jqxNotification("open");
         }
      },
      complete: function() {
         $("#loading_box").hide();
      }
   });
};

/**
 * This function is fired whenever the update button (below the jqxGrid) is clicked
 * @param {boolean} updateSheetList Set to TRUE if you want the sheet list to be refresh after the update
 * 
 * @returns {boolean} TRUE if successfully updated all the fields
 */
DMPVSchema.prototype.applySchemaChanges = function(updateSheetList) {
   if(typeof updateSheetList == "undefined") {
      updateSheetList = true;
   }
   console.log("Update button clicked");
   //go through each and every changed sheet
   $("#loading_box").html("Loading. Please don't close this tab");
   $("#loading_box").show();
   var canContinue = true;
   var isFirstColumn = true;
   $.each(window.dvs.schemaChanges, function(sheetName, columns){
      if(canContinue) {
         $.each(columns, function(columnName, details){
            if(details != null) {//check if already updated in previous request
               if(details.present == true) {
                  details["delete"] = false;
               }
               else {
                  details["delete"] = true;
               }
               if(typeof details["vlength"] != 'undefined'){
                  details['length'] = details['vlength'];
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
                     },
                     500: function() {//forbidden
                        $("#enotification_pp").html("An error occurred in the server");
                        $("#enotification_pp").jqxNotification("open");
                     }
                  },
                  success: function(jsonResult, textStatus, jqXHR){
                     console.log("Response from alter_field endpoint = ", jsonResult);
                     if(jsonResult !== null) {
                        if(jsonResult.status.healthy == true && isFirstColumn) {
                           window.dvs.lastSavePoint = jsonResult.save_point;
                           isFirstColumn = false;
                        }
                        else if(jsonResult.status.healthy == false) {
                           var message = "";
                           if(typeof jsonResult.status.errors != 'undefined' && jsonResult.status.errors.length > 0) {
                              if(typeof jsonResult.status.errors[0].message != 'undefined') {
                                 message = "<br />"+jsonResult.status.errors[0].message;
                              }
                           }
                           $("#enotification_pp").html("Could not update "+details.name+message);
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
                  }
               });
            }
         });
      }
   });
   window.dvs.refreshSavePoints();
   if(canContinue == true) {
      $("#inotification_pp").html("Columns updated successfully");
      $("#inotification_pp").jqxNotification("open");
      $("#cancel_btn").prop('disabled', true);
      $("#update_btn").prop('disabled', true);
      window.dvs.schemaChanges = {};
      window.dvs.columnDictionary = {};
   }
   $("#loading_box").hide();
   $("#loading_box").html("Loading..");
   if(updateSheetList == true) {
      window.dvs.updateSheetList();
   }
   return canContinue;
};

/**
 * This function initializes the file drop area
 * 
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
 * 
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
            //TODO: alert user if data is null
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
      //TODO: get foreign keys for workflow
      window.dvs.refreshForeignKeys();
   }});
   $("#sheets").jqxListBox({width: window.dvs.leftSideWidth, height:'100%', source: window.dvs.sheetListAdapter, displayMember: "name", valueMember: "name", theme: ''});
   $("#sheets").bind("select", function(event){
      var sheetName = event.args.item.value;
      if(window.dvs.schema != null) {
         for(var index = 0; index < window.dvs.schema.sheets.length; index++) {
            if(window.dvs.schema.sheets[index].name == sheetName){
               window.dvs.updateColumnGrid(window.dvs.schema.sheets[index]);
               window.dvs.loadSheetData(window.dvs.schema.sheets[index].name);
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
         $("#right_click_menu").html('<ul><li><a href="#" id="rename_sheet_btn">Rename</a></li><li><a href="#" id="delete_sheet_btn">Delete</a></li></ul>');
         var selectedSheetIndex = $("#sheets").jqxListBox("getSelectedIndex");
         var sheet = window.dvs.schema.sheets[selectedSheetIndex];
         var scrollTop = $(window).scrollTop();
         var scrollLeft = $(window).scrollLeft();
         $("#delete_sheet_btn").html("Delete "+sheet.name);
         $("#rename_sheet_btn").html("Rename "+sheet.name);
         $("#right_click_menu").jqxMenu('open', parseInt(event.clientX) + 5 + scrollLeft, parseInt(event.clientY) + 5 + scrollTop);
         $("#delete_sheet_btn").click(window.dvs.deleteSheetButtonClicked);
         $("#rename_sheet_btn").click(window.dvs.renameSheetButtonClicked);
         return false;
      }
   });
};

DMPVSchema.prototype.loadSheetData = function(sheetName) {
   if(typeof window.dvs.sheetData[sheetName] != 'undefined') {
      window.dvs.updateDataGrid(window.dvs.sheetData[sheetName]);
   }
   else {
      $("#loading_box").show();
      var sData = JSON.stringify({
         "workflow_id": window.dvs.project,
         "sheet":sheetName
      });
      var sToken = JSON.stringify({
         "server":window.dvs.server,
         "user": window.dvs.user,
         "session": window.dvs.session
      });
      $.ajax({
         url: "mod_ajax.php?page=odk_workflow&do=get_sheet_data",
         type: "POST",
         async: true,
         data: {data: sData, token: sToken},
         statusCode: {
            400: function() {//bad request
               $("#enotification_pp").html("Could not fetch sheet data");
               $("#enotification_pp").jqxNotification("open");
            },
            403: function() {//forbidden
               $("#enotification_pp").html("User not allowed to fetch sheet data");
               $("#enotification_pp").jqxNotification("open");
            },
            500: function() {//forbidden
               $("#enotification_pp").html("An error occurred in the server");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         success: function(jsonResult, textStatus, jqXHR){
            console.log("Response from get_sheet_data endpoint = ", jsonResult);
            if(jsonResult !== null) {
               if(jsonResult.status.healthy == true) {
                  window.dvs.sheetData[sheetName] = jsonResult.data;
                  window.dvs.updateDataGrid(window.dvs.sheetData[sheetName]);
               }
               else if(jsonResult.status.healthy == false) {
                  var message = "";
                  if(typeof jsonResult.status.errors != 'undefined' && jsonResult.status.errors.length > 0) {
                     if(typeof jsonResult.status.errors[0].message != 'undefined') {
                        message = "<br />"+jsonResult.status.errors[0].message;
                     }
                  }
                  $("#enotification_pp").html("Could not fetch sheet data"+message);
                  $("#enotification_pp").jqxNotification("open");
               }
            }
            else {
               $("#enotification_pp").html("Could not fetch sheet data");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         complete: function() {
            $("#loading_box").hide();
         }
      });
   }
};

/**
 * This function refreshes the list of foreign keys in the project
 * @returns {undefined}
 */
DMPVSchema.prototype.refreshForeignKeys = function() {
   console.log("refresh foreign keys called");
   if(window.dvs.project != null && window.dvs.project.length > 0) {
      window.dvs.foreignKeys = null;
      console.log("refreshing foreign keys");
      var sData = JSON.stringify({"workflow_id": window.dvs.project});
      var sToken = JSON.stringify({
         "server":window.dvs.server,
         "user": window.dvs.user,
         "session": window.dvs.session
      });
      $.ajax({
         url: "mod_ajax.php?page=odk_workflow&do=get_foreign_keys",
         type: "POST",
         async: true,
         data: {data: sData, token: sToken},
         statusCode: {
            400: function() {//bad request
               $("#enotification_pp").html("Could not fetch foreign keys");
               $("#enotification_pp").jqxNotification("open");
            },
            403: function() {//forbidden
               $("#enotification_pp").html("User not allowed to fetch foreign keys");
               $("#enotification_pp").jqxNotification("open");
            },
            500: function() {//forbidden
               $("#enotification_pp").html("An error occurred in the server");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         success: function(jsonResult, textStatus, jqXHR){
            console.log("Response from get_foreign_keys endpoint = ", jsonResult);
            if(jsonResult !== null) {
               if(jsonResult.status.healthy == true) {
                  window.dvs.foreignKeys = jsonResult.foreign_keys;
               }
               else if(jsonResult.status.healthy == false) {
                  var message = "";
                  if(typeof jsonResult.status.errors != 'undefined' && jsonResult.status.errors.length > 0) {
                     if(typeof jsonResult.status.errors[0].message != 'undefined') {
                        message = "<br />"+jsonResult.status.errors[0].message;
                     }
                  }
                  $("#enotification_pp").html("Could not fetch foreign keys"+message);
                  $("#enotification_pp").jqxNotification("open");
               }
            }
            else {
               $("#enotification_pp").html("Could not fetch foreign keys");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         complete: function() {
            $("#loading_box").hide();
         }
      });
   }
};

/**
 * This function initializes the source for the project_list_box jqxList
 * 
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

/**
 * This function initializes the jqxGrid displaying the schema
 * 
 * @returns {undefined}
 */
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
         {name: 'vlength'},
         {name: 'nullable'},
         {name: 'default'},
         {name: 'key'},
         {name: 'link'}
      ],
      root: 'columns',
      localdata: data
   };
   
   window.dvs.columnGridAdapter = new $.jqx.dataAdapter(source);
   
   var columnTypes = [
         'varchar',
         'numeric',
         'double precision',
         'smallint',
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
      columnsresize: true,
      theme: '',
      selectionmode: "singlerow",
      pageable: false,
      editable: true,
      rendergridrows: function() {
         return window.dvs.columnGridAdapter.records;
      },
      columns: [
         {text: 'Name', datafield: 'name', width: gridWidth*0.2647 - 10},
         {text: 'Type', columntype: 'dropdownlist', datafield: 'type', width: gridWidth*0.2206,initeditor: function (row, cellvalue, editor) {
               editor.jqxDropDownList({ source: columnTypes});
         }},
         {text: 'Length', columntype: 'numberinput', datafield: 'vlength', width: gridWidth*0.1103},
         {text: 'Nullable', columntype: 'dropdownlist', datafield: 'nullable', width: gridWidth*0.1103,initeditor: function (row, cellvalue, editor) {
               editor.jqxDropDownList({ source: nullableTypes});
         }},
         {text: 'Default', datafield: 'default', width: gridWidth*0.1471},
         {text: 'Key', columntype: 'dropdownlist', datafield: 'key', width: gridWidth*0.1471*0.5,initeditor: function (row, cellvalue, editor) {
               editor.jqxDropDownList({ source: keyTypes});
         }},
         {text: 'Link', columntype: 'button', datafield: 'link', width: gridWidth*0.047,cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties){
               if(value == false) return "Add";
               else return "Edit";
         },buttonclick:window.dvs.foreignKeyButtonClicked},
         {text: 'Delete', columntype: 'button', datafield: 'present', width: gridWidth*0.047,cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties){
               return "Delete";
         },buttonclick:function(rowIndex, event){
            var sheetData = window.dvs.schema.sheets[$("#sheets").jqxListBox('selectedIndex')];
            if(typeof sheetData != 'undefined') {
               var sheetName = sheetData.name;
               var sheetIndex = $("#sheets").jqxListBox('selectedIndex');
               var columnData = window.dvs.schema.sheets[sheetIndex].columns[rowIndex];
               var currValue = columnData.present;
               var newValue = false;//new value defaults as delete
               if(currValue == false) newValue = true;
               var columnChanged = window.dvs.changeColumnDetails(sheetName, rowIndex, "present", currValue, newValue, columnData);
               if(columnChanged == true) {
                  if(newValue == true) {
                     $(event.target).val("Delete");
                  }
                  else {
                     $(event.target).val("Restore");
                  }
               }
            }
         }}
      ]
   });
   
   $("#columns").on('cellendedit', window.dvs.columnGridCellValueChanged);
};

DMPVSchema.prototype.updateDataGrid = function(sheetData) {
   var columnNames = Object.keys(sheetData);
   var dataFields = [];
   var columns = [];
   for(var index = 0; index < columnNames.length; index++) {
      dataFields.push({
         "name":columnNames[index]
      });
      columns.push({
         "text": columnNames[index],
         "datafield":columnNames[index],
         "minwidth":80
      });
   }
   var formattedData = [];
   var isComplex = false;
   if(columnNames.length > 50) {
      isComplex = true;
   }
   for(var index = 0; index < sheetData[columnNames[0]].length; index++){
      formattedData[index] = {};
      for(var cIndex = 0; cIndex < columnNames.length; cIndex++){
         formattedData[index][columnNames[cIndex]] = sheetData[columnNames[cIndex]][index];
      }
      /*if(isComplex) {
         index = index + Math.floor(sheetData[columnNames[0]].length*0.1);
      }*/
   }
   var source = {
      datatype: "json",
      datafields: dataFields,
      localdata: formattedData
   };
   
   window.dvs.dataGridAdapter = new $.jqx.dataAdapter(source);
   $("#sheet_data").jqxGrid({
      width: window.dvs.rightSideWidth,
      height: '100%',
      source: window.dvs.dataGridAdapter,
      columnsresize: true,
      theme: '',
      selectionmode: "singlerow",
      pageable: false,
      editable: false,
      rendergridrows: function() {
         return window.dvs.dataGridAdapter.records;
      },
      columns: columns
   });
};

/**
 * This function is fired whenever the "Edit" or "Add" link button is clicked 
 * in the jqxGrid displaying the schema
 * 
 * @param {type} rowIndex
 * @returns {undefined}
 */
DMPVSchema.prototype.foreignKeyButtonClicked = function(rowIndex) {
   var sheetIndex = $("#sheets").jqxListBox("getSelectedIndex");
   var data = $("#columns").jqxGrid("getrowdata", rowIndex);
   $("#foreign_key_column").val(data.name);
   //get all primary keys
   var primaryKeys = [];
   for(var sIndex = 0; sIndex < window.dvs.schema.sheets.length; sIndex++) {
      if(sIndex != sheetIndex) {
         var columns = window.dvs.schema.sheets[sIndex].columns;
         for(var cIndex = 0; cIndex < columns.length; cIndex++) {
            if(columns[cIndex].key == "primary") {
               primaryKeys.push({
                  sheet: window.dvs.schema.sheets[sIndex].name,
                  column: columns[cIndex].name
               });
            }
         }
      }
   }
   if(primaryKeys.length == 0){
      $("#inotification_pp").html("Please define primary keys first");
      $("#inotification_pp").jqxNotification("open");
   }
   var html = "";
   for(var index = 0; index < primaryKeys.length; index++) {
      html = html + "<option value='"+JSON.stringify(primaryKeys[index])+"'>"+primaryKeys[index].sheet+"->"+primaryKeys[index].column+"</option>";
   }
   $("#foreign_key_ref_column").html(html);
   $("#new_foreign_key_wndw").show();
};

/**
 * This function is fired whenever the add_foreign_key_btn in new foreign key window
 * is clicked
 * @returns {undefined}
 */
DMPVSchema.prototype.addForeignKeyButtonClicked = function() {
   var sheetIndex = $("#sheets").jqxListBox("getSelectedIndex");
   var sheetName = window.dvs.schema.sheets[sheetIndex].name;
   var column = $("#foreign_key_column").val();
   var refColumn = $("#foreign_key_ref_column").val();
   if(column.length > 0 && refColumn.length > 0) {
      $("#loading_box").show();
      var references = $.parseJSON($("#foreign_key_ref_column").val());
      console.log("adding a foreign key");
      var sData = JSON.stringify({
         "workflow_id": window.dvs.project,
         "sheet": sheetName,
         "columns": new Array(column),
         "references" : {
            "sheet": references.sheet,
            "columns": new Array(references.column)
         }
      });
      var sToken = JSON.stringify({
         "server":window.dvs.server,
         "user": window.dvs.user,
         "session": window.dvs.session
      });
      $.ajax({
         url: "mod_ajax.php?page=odk_workflow&do=add_foreign_key",
         type: "POST",
         async: true,
         data: {data: sData, token: sToken},
         statusCode: {
            400: function() {//bad request
               $("#enotification_pp").html("Could not fetch foreign keys");
               $("#enotification_pp").jqxNotification("open");
            },
            403: function() {//forbidden
               $("#enotification_pp").html("User not allowed to fetch foreign keys");
               $("#enotification_pp").jqxNotification("open");
            },
            500: function() {//forbidden
               $("#enotification_pp").html("An error occurred in the server");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         success: function(jsonResult, textStatus, jqXHR){
            console.log("Response from add_foreign_key endpoint = ", jsonResult);
            if(jsonResult !== null) {
               if(jsonResult.status.healthy == true) {
                  $("#inotification_pp").html("Successfully added foreign key");
                  $("#inotification_pp").jqxNotification("open");
               }
               else if(jsonResult.status.healthy == false) {
                  var message = "";
                  if(typeof jsonResult.status.errors != 'undefined' && jsonResult.status.errors.length > 0) {
                     if(typeof jsonResult.status.errors[0].message != 'undefined') {
                        message = "<br />"+jsonResult.status.errors[0].message;
                     }
                  }
                  $("#enotification_pp").html("Could not fetch foreign keys"+message);
                  $("#enotification_pp").jqxNotification("open");
               }
            }
            else {
               $("#enotification_pp").html("Could not fetch foreign keys");
               $("#enotification_pp").jqxNotification("open");
            }
         },
         complete: function() {
            $("#new_foreign_key_wndw").hide();
            $("#loading_box").hide();
            window.dvs.refreshSavePoints();
            window.dvs.updateSheetList();
         }
      });
   }
};

/**
 * This function is fired whenever a value in the jqxGrid displaying the schema
 * changes
 * 
 * @param {Object} event  The event object returned from the handler
 * @returns {undefined}
 */
DMPVSchema.prototype.columnGridCellValueChanged = function(event) {
   if(window.dvs.schema != null && event.args.oldvalue !== event.args.value) {
      console.log(event);
      var columnData = event.args.row;
      var sheetData = window.dvs.schema.sheets[$("#sheets").jqxListBox('selectedIndex')];
      if(typeof sheetData !== 'undefined') {
         var sheetName = sheetData.name;
         var columnChanged = window.dvs.changeColumnDetails(sheetName, event.args.rowindex, event.args.datafield, event.args.oldvalue, event.args.value, columnData);
         
         /*if(typeof window.dvs.schemaChanges[sheetName] === 'undefined') {//initialize the sheet in the changes object
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
         }*/
         
         /*console.log("Original column name is "+columnName);
         
         if(typeof window.dvs.schemaChanges[sheetName][columnName] === 'undefined') {
            window.dvs.schemaChanges[sheetName][columnName] = {};
         }
         
         window.dvs.schemaChanges[sheetName][columnName] = columnData;
         window.dvs.schemaChanges[sheetName][columnName].original_name = columnName;
         
         $("#cancel_btn").prop('disabled', false);
         $("#update_btn").prop('disabled', false);
         
         console.log(window.dvs.schemaChanges);*/
      }
   }
   
};

/**
 * This function prepares the data structures in this object before a column in
 * the schema being displayed is updated. The original name given to the column
 * being updated is returned
 * 
 * @param {String} sheetName     The name of the sheet that contains the column being edited
 * @param {Integer} rowIndex     The index of the column in the sheet's object
 * @param {String} changedField  The column's property being changed
 * @param {String} oldValue      The property's old value
 * @param {String} newValue      The property's new value
 * @param {Object} columnData    The data corresponding to the column before property update. Only set to null if changedField is 'present'
 * @returns {Boolean}   True if able to change column data
 */
DMPVSchema.prototype.changeColumnDetails = function(sheetName, rowIndex, changedField, oldValue, newValue, columnData) {
   console.log("Changing column details");
   var columnName = null;
   if(changedField == 'name') {//name of the field is what changed
      console.log("oldvalue "+oldValue);
      console.log("value "+newValue);
      if(typeof window.dvs.columnDictionary[sheetName] === 'undefined') {
         window.dvs.columnDictionary[sheetName] = [];
      }

      var found = false;

      for(var i = 0; i < window.dvs.columnDictionary[sheetName].length; i++) {
         if(window.dvs.columnDictionary[sheetName][i].new_name === oldValue) {
            window.dvs.columnDictionary[sheetName][i].new_name = newValue;
            found = true;
            columnName = window.dvs.columnDictionary[sheetName][i].old_name;
            break;
         }
      }
      if(found == false) {
         window.dvs.columnDictionary[sheetName][window.dvs.columnDictionary[sheetName].length] = {old_name:oldValue, new_name:newValue};
         columnName = oldValue;
      }
   }
   else {//something else apart from the name changed. Look for the columns original name
      if(changedField == 'present') {
         var columnIndex = rowIndex;
         var sheetIndex = $("#sheets").jqxListBox('selectedIndex');
         columnData = window.dvs.schema.sheets[sheetIndex].columns[columnIndex];
         columnData.present = newValue;
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
   if(columnName != null) {
      if(typeof window.dvs.schemaChanges[sheetName] === 'undefined') {//initialize the sheet in the changes object
         window.dvs.schemaChanges[sheetName] = {};
      }
      if(typeof window.dvs.schemaChanges[sheetName][columnName] === 'undefined') {
         window.dvs.schemaChanges[sheetName][columnName] = {};
      }
      
      window.dvs.schemaChanges[sheetName][columnName] = columnData;
      window.dvs.schemaChanges[sheetName][columnName].original_name = columnName;
      console.log("column data = ", window.dvs.schemaChanges[sheetName][columnName]);
      $("#cancel_btn").prop('disabled', false);
      $("#update_btn").prop('disabled', false);
      return true;
   }
   return false;
};

/**
 * This function updates the jqxGrid displaying the schema
 * 
 * @param {type} data   The data to be displayed in the grid
 * @returns {undefined}
 */
DMPVSchema.prototype.updateColumnGrid = function(data) {
   console.log("updating column grid");
   //check if each of the columns is in a foreing key
   var allFKeyColumns = [];
   if(window.dvs.foreignKeys != null && typeof window.dvs.foreignKeys[data.name] != 'undefined') {//the current sheet has foreign keys
      var foreignKeys = window.dvs.foreignKeys[data.name];
      for(var index = 0; index < foreignKeys.length; index++) {
         allFKeyColumns = allFKeyColumns.concat(foreignKeys[index].columns);
      }
   }
   for(var index = 0; index < data.columns.length; index++) {
      data.columns[index].vlength = data.columns[index]['length'];
      if(allFKeyColumns.indexOf(data.columns[index].name) != -1) {
         data.columns[index].link = true;
      }
      else {
         data.columns[index].link = false;
      }
   }
   
   var source = {
      datatype: "json",
      datafields: [
         {name: 'present', type:'boolean'},
         {name: 'name'},
         {name: 'type'},
         {name: 'vlength'},
         {name: 'nullable'},
         {name: 'default'},
         {name: 'key'},
         {name: 'link'}
      ],
      root: 'columns',
      localdata: data
   };
   
   window.dvs.columnGridAdapter = new $.jqx.dataAdapter(source);
   $("#columns").jqxGrid({source: window.dvs.columnGridAdapter});
};