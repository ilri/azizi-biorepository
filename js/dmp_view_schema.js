function DMPVSchema(server, user, session, project) {
   window.dvs = this;
   window.dvs.server = server;
   window.dvs.user = user;
   window.dvs.session = session;
   window.dvs.project = project;
   window.dvs.schema = null;
   window.dvs.sheetListAdapter = null;
   window.dvs.columnGridAdapter = null;
   window.dvs.leftSideWidth = 0;
   window.dvs.rightSideWidth = 0;
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
   window.dvs.initSheetList();
   window.dvs.initColumnGrid();
   window.dvs.initFileDropArea();
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

DMPVSchema.prototype.initColumnGrid = function() {
   console.log("initializing column grid");
   var data = {
      columns: []
   };
   var source = {
      datatype: "json",
      datafields: [
         {name: 'present'},
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
               editor.jqxDropDownList({ source: columnTypes, width:200});
         }},
         {text: 'Length', datafield: 'length', width: gridWidth*0.1103},
         {text: 'Nullable', columntype: 'dropdownlist', datafield: 'nullable', width: gridWidth*0.1103,initeditor: function (row, cellvalue, editor) {
               editor.jqxDropDownList({ source: nullableTypes});
         }},
         {text: 'Default', datafield: 'default', width: gridWidth*0.1471},
         {text: 'Key', columntype: 'dropdownlist', datafield: 'key', width: gridWidth*0.1471,initeditor: function (row, cellvalue, editor) {
               editor.jqxDropDownList({ source: keyTypes});
         }}
      ]
   });
};

DMPVSchema.prototype.updateColumnGrid = function(data) {
   console.log("updating column grid");
   var source = {
      datatype: "json",
      datafields: [
         {name: 'present'},
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