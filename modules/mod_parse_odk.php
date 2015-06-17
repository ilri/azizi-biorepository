<?php

/**
 * The main class of the system. All other classes inherit from this main one
 *
 * @category   AVID
 * @package    LN2 Requests
 * @author     Kihara Absolomon <a.kihara@cgiar.org>
 * @author     Jason Rogena <j.rogena@cgiar.org>
 * @since      v0.2
 */
class ParseODK extends Repository{

   public $Dbase;
   public function __construct($Dbase) {
      $this->Dbase = $Dbase;
   }

   public function TrafficController() {
      if(OPTIONS_REQUEST_TYPE == 'normal'){
         echo "<script type='text/javascript' src='js/odk_parser.js'></script>";
         echo "<script type='text/javascript' src='" . OPTIONS_COMMON_FOLDER_PATH . "jquery/jquery.min.js' /></script>";
         echo "<script type='text/javascript' src='" . OPTIONS_COMMON_FOLDER_PATH . "bootstrap/dist/js/bootstrap.min.js' /></script>";
         echo "<link rel='stylesheet' type='text/css' href='". OPTIONS_COMMON_FOLDER_PATH ."bootstrap/dist/css/bootstrap.min.css'/>";
      }

      if (OPTIONS_REQUESTED_SUB_MODULE == '') $this->HomePage();
      if (OPTIONS_REQUESTED_SUB_MODULE == 'proc_odk_form') $this->procODKForm();
   }

   /**
    * Creates the home page to the parser function
    */
   private function HomePage() {
      //get all odk forms uploaded by this user
      $username = $_SESSION['username'];
      if(strlen($username) === 0)
         $username = $_SESSION['onames']." ". $_SESSION['sname'];
      
      $query = "SELECT a.* FROM odk_forms AS a INNER JOIN odk_access AS b ON a.id = b.form_id WHERE b.user = :user AND a.is_active=1";
      $forms = $this->Dbase->ExecuteQuery($query, array("user" => $username));
      
?>
<h3 id="odk_heading">ODK Parser</h3>
<form class="form-horizontal odk_parser">
   <div class="form-group">
      <label for="form_on_server" class="control-label">Form on ODK Aggregate</label>
      <div class="">
         <select class="form-control" id="form_on_server">
            <option value=""></option>
            <?php
            foreach ($forms as $currForm)
               echo "<option value='".$currForm['id']."'>".$currForm['form_name']."</option>"
            ?>
         </select>
      </div>
   </div>
   <div class="form-group" id="data_file_div">
      <label for="json_file" class="control-label">JSON or CSV File</label>
      <div class=""><input type="file" class="form-control" id="data_file" placeholder="JSON or CSV File"></div>
   </div>
   <div class="form-group" id="xml_file_div">
      <label for="xml_file" class="control-label">XML File</label>
      <div class=""><input type="file" class="form-control" id="xml_file" name="xml_file" placeholder="XML file"></div>
   </div>
   <div class="form-group">
      <label for="name" class="control-label">Full Name</label>
      <div class="col-sm-10"><input type="text" class="form-control" id="name" value="<?php echo "{$_SESSION['surname']} {$_SESSION['onames']}"; ?>" placeholder="Full Names"></div>
   </div>
   <div class="form-group">
      <label for="email" class="control-label">Email Address</label>
      <div class="col-sm-10"><input type="text" class="form-control" id="email" placeholder="Email"></div>
   </div>
   <div class="form-group">
      <label for="file_name" class="control-label">Excel spreadsheet to be generated</label>
      <div class=""><input type="text" class="form-control" name="file_name" id="file_name" placeholder="Excel Spreadsheet Name"></div>
   </div>
   <div class="form-group">
      <label for="parseType" class="control-label">Type of Output</label>
      <div class="">
         <select name="parseType" id="parseType">
            <option value="viewing">Easy viewing</option>
            <option value="analysis">Analysis</option>
         </select>
      </div>
   </div>
   <div class="form-group">
      <label for="dwnldImages" class="control-label">Download Images?</label>
      <div class="">
         <select name="dwnldImages" id="dwnldImages">
            <option value="yes">Yes</option>
            <option value="no">No</option>
         </select>
      </div>
   </div>
   <div class="center"><button id="generate_b" name="generate_b" onclick="return false;" class="btn-primary">Generate</button></div>
</form>

<script>
   $(document).ready( function() {
      $("#form_on_server").change(function(){
         if($("#form_on_server").val() === ""){//
            $("#data_file_div").show(250);
            $("#xml_file_div").show(250);
            $("#file_name").val("");
         }
         else{
            $("#data_file_div").hide(250);
            $("#xml_file_div").hide(250);
            $("#file_name").val($("#form_on_server option:selected").html());
         }
      });
      
      $("#generate_b").click(function (){ var parser = new Parse(); });
      $("#xml_file").change(function (){
         $("#file_name").val( $('#xml_file').val().split('\\').pop().split('.').shift() );
      });
   });
   $('#whoisme .back').html('<a href=\'?page=home\'>Back</a>');
</script>
   <?php
   }
   
   private function procODKForm(){
      include_once 'mod_proc_onserv_odk_form.php';
      $procODKFormOnServer = new ProcODKForm($this->Dbase);
   }
}
?>
