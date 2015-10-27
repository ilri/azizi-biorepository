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
};

/**
 * Initiates the home page for match GPS module
 * @returns {undefined}
 */
Toolkit.prototype.initMatchGPSHome = function(){

};