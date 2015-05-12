<?php

/* 
 * This class provides an interface for the ODK Workflow API
 */
class ODKWViewer {
   private $Dbase;
   
   /**
    * Default constructor for the class
    */
   public function __construct($Dbase) {
      $this->Dbase = $Dbase;
      
      if(OPTIONS_REQUEST_TYPE == 'normal'){
         echo "<script type='text/javascript' src='js/box_storage.js'></script>";
      }
      
      if (OPTIONS_REQUESTED_SUB_MODULE == '') $this->homePage();
   }
   
   /**
    * This function renders the home page
    */
   public function homePage() {
      //TODO: check if data file already uploaded for this session
?>
<div>
   <form enctype="multipart/form-data" name="upload" role='form' class="form-horizontal" method="POST" onsubmit="return BoxStorage.submitInsertRequest();">
      <label for="odk_data_file">Upload an ODK Data file</label>
      <input name="odk_data_file" id="odk_data_file" type="file" placeholder="Excel File" accept=".xlsx">
   </form>
</div>
<?php
   }
   
   /**
    * This function renders the data types page where the different sheets are shown
    * with the different columns in the sheet
    */
   public function dataTypesPage() {
      
   }
   
   /**
    * This function:
    *    - creates a directory for the current viewer session (A viewer session can extend multiple php sessions)
    *    - writes session data 
    */
   private function initSession() {
      
   }
}
?>