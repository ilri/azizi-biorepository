

function Animals(sub_module){
   window.farm_animals = this;

   // initialize the main variables
   window.farm_animals.sub_module = sub_module;

   this.serverURL = "./modules/mod_farm_animals.php";
   this.procFormOnServerURL = "mod_ajax.php?page=farm_animals";

   // call the respective function
   if(this.sub_module === 'inventory') this.initiateAnimalsGrid();
};

/**
 * A function to initiate the animals grid
 */
Animals.prototype.initiateAnimalsGrid = function(){
   // create the source for the grid
   var source = {
      datatype: 'json', datafields: [ {name: 'animal_id'}, {name: 'species'}, {name: 'sex'}, {name: 'origin'}, {name: 'dob'},
         {name: 'sire'}, {name: 'dam'}, {name: 'status'}, {name: 'experiment'}],
         id: 'id', root: 'data', async: false, type: 'POST', data: {action: 'list'}, url: 'mod_ajax.php?page=farm_animals&do=inventory'
     };
     var animalsAdapter = new $.jqx.dataAdapter(source);
     if($('#inventory :regex(class, jqx\-grid)').length === 0){
        $("#inventory").jqxGrid({
            width: 910,
            source: source,
            pageable: true,
            autoheight: true,
            sortable: true,
            altrows: true,
            enabletooltips: true,
            columns: [
              { datafield: 'system_id', hidden: true },
              { text: 'Animal ID', datafield: 'animal_id', width: 120 },
              { text: 'Species', datafield: 'species', width: 75 },
              { text: 'Sex', datafield: 'sex', width: 50 },
              { text: 'Origin', datafield: 'origin', width: 100 },
              { text: 'Birth Date', datafield: 'dob', width: 100 },
              { text: 'Sire', datafield: 'sire', width: 100 },
              { text: 'Dam', datafield: 'dam', width: 100 },
              { text: 'Status', datafield: 'status', width: 100 },
              { text: 'Experiment', datafield: 'experiment', width: 150 }
            ]
        });
     }
     else{
        $("#inventory").jqxGrid({source: animalsAdapter});
     }

   // initialize jqxGrid

};