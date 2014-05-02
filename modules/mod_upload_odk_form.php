<?php

/**
 * The main class of the system. All other classes inherit from this main one
 *
 * @category   AZIZI Biorepository
 * @package    ODK Uploader
 * @author     Kihara Absolomon <a.kihara@cgiar.org>
 * @author     Jason Rogena <j.rogena@cgiar.org>
 * @since      v0.2
 */
class UploadODK extends Repository{

   public function __construct() {
   }

   public function TrafficController() {
      if(OPTIONS_REQUEST_TYPE == 'normal'){
         echo "<script type='text/javascript' src='js/odk_upload.js'></script>";
      }

      if (OPTIONS_REQUESTED_SUB_MODULE == ''){
         echo "home";
         $this->homePage();
      }
   }

   /**
    * Creates the home page to the parser function
    */
   private function homePage() {
?>
<h3 id="odk_heading">ODK Uploader</h3>
<form class="form-horizontal odk_parser">
   <div class="form-group">
      <label for="json_file" class="control-label">Excel Form</label>
      <div class="">
         <input type="file" class="form-control" id="excel_form" placeholder="Excel Form">
      </div>
   </div>
   <div class="center"><button id="generate_b" name="generate_b" onclick="return false;" class="btn-primary">Generate</button></div>
</form>
<div>
   <a href="http://opendatakit.org/help/form-design/xlsform/">Help on defining ODK forms as Excel sheets</a>
</div>

<script>
   $(document).ready( function() {
      $("#generate_b").click(function (){ var parser = new Parse(); });
      $("#xml_file").change(function (){
         $("#file_name").val( $('#xml_file').val().split('\\').pop().split('.').shift() );
      });
   });
   $('#whoisme .back').html('<a href=\'?page=home\'>Back</a>');
</script>
<?php
   }
}
?>
