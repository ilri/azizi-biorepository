/**
 * The constructor of the Toolkit object
 *
 * @param   {string}    sub_module     The current sub module
 * @returns {Animals}   The Animal object which will be used in the farm animals module
 */
function Toolkit(sub_module){
   window.toolkit = this;

   // initialize the main variables
   window.toolkit.sub_module = Common.getVariable('do', document.location.search.substring(1));
   window.toolkit.module = Common.getVariable('page', document.location.search.substring(1));

   this.serverURL = "./modules/mod_toolkit.php";
   this.procFormOnServerURL = "mod_ajax.php?page=toolkit";

   window.toolkit.emptyForms = 0;
};

/**
 * Initiates the home page for match GPS module
 * @returns {undefined}
 */
Toolkit.prototype.initMatchGPSHome = function(){

};

/**
 * Initiiate the ODK forms sub module
 * @returns {undefined}
 */
Toolkit.prototype.initODKForms = function(){
   // create the source for the grid
   var source = {
      datatype: 'json', datafields: [ {name: 'form_name'}, {name: 'form_id'}, {name: 'no_cores'}, {name: 'core'}, {name: 'core2'}, {name: 'core3'}, {name: 'actions'}],
         id: 'id', root: 'data', async: false, type: 'POST', data: {action: 'fetch_all'}, url: 'mod_ajax.php?page=toolkit&do=odk_form_stats'
     };
     var odkFormsAdapter = new $.jqx.dataAdapter(source);
   // initialize jqxGrid
     if($('#odk_forms :regex(class, jqx\-grid)').length === 0){
        $("#odk_forms").jqxGrid({
            width: 917,
            source: source,
            pageable: true,
            autoheight: true,
            sortable: true,
            showfilterrow: false,
            autoshowfiltericon: true,
            filterable: true,
            altrows: true,
            touchmode: false,
            pagesize: 20,
            pagesizeoptions: ['20', '50', '100'],
            columns: [
              { text: 'Form Name', datafield: 'form_name', width: 300 },
              { text: 'Form ID', datafield: 'form_id', width: 300 },
              { text: 'Cores', datafield: 'no_cores', width: 50 },
              { text: 'Core1', datafield: 'core', width: 50 },
              { text: 'Core2', datafield: 'core2', width: 50 },
              { text: 'Core3', datafield: 'core3', width: 50},
              { text: 'Actions', datafield: 'actions', width: 95, cellsrenderer: function (row, columnfield, value, defaulthtml, columnproperties, rowdata) {
                    var c1 = parseInt(rowdata.core), c2 = parseInt(rowdata.core3), c3 = parseInt(rowdata.core3);
                    var isError;
                    var deleteForm = (c1 === 0 && (c2 === 0 || isNaN(c2) === true) && (c3 === 0 || isNaN(c3) === true)) ? '<a href="javascript:;" id="'+ rowdata.form_id +'" class="delete_form">&nbsp;Delete</a>' : '';
                    if(isNaN(c2) === false && c1 !== c2){ isError = '<a href="javascript:;" id="'+ rowdata.form_id +'" class="has_errors">&nbsp;Show Errors</a>'; }
                    else if(isNaN(c3) === false && (c1 !== c2) && (c2 !== c3) && (c1 !== c3)){ isError = '<a href="javascript:;" id="'+ rowdata.form_id +'" class="has_errors">&nbsp;Show Errors</a>'; }
                    else isError = '';
                    return deleteForm +'&nbsp;&nbsp;'+ isError;
                 }
              }
            ]
        });
     }
     else{
        $("#odk_forms").jqxGrid({source: odkFormsAdapter});
     }
};