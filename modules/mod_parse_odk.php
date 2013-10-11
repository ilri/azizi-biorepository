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
   
   public function __construct() {
   }
   
   public function TrafficController() {
      if(OPTIONS_REQUEST_TYPE == 'normal'){
         echo "<script type='text/javascript' src='js/odk_parser.js'></script>";
         echo "<script type='text/javascript' src='" . OPTIONS_COMMON_FOLDER_PATH . "jquery/jquery-1.8.3.min.js' /></script>";
         echo "<script type='text/javascript' src='" . OPTIONS_COMMON_FOLDER_PATH . "bootstrap/js/bootstrap.js' /></script>";
         echo "<link rel='stylesheet' type='text/css' href='". OPTIONS_COMMON_FOLDER_PATH ."bootstrap/css/bootstrap.css'/>";
      }
      
      if (OPTIONS_REQUESTED_SUB_MODULE == '') $this->HomePage();
   }
   
   private function HomePage() {
      ?>
      <h3 id="odk_heading">ODK Parser</h3>
      <form class="form-horizontal">
         <table id="odk_table">
            <tr><td>Full Name</td><td><input type="text" id="name" name="name"/></td></tr>
            <tr><td>Email Address</td><td><input type="text" id="email" name="email"/></td></tr>
            <tr><td>Name the Excel file to be generated</td><td><input type="text" id="file_name" name="file_name"/></td></tr>
            <tr><td>JSON File</td><td><input id="json_file" name="json_file" type="file"/></td></tr>
            <tr><td>XML File</td><td><input id="xml_file" name="xml_file" type="file"/></td></tr>
            <tr><td colspan="2" style="text-align: right;"><button id="generate_b" name="generate_b" onclick="return false;" class="btn-primary">Generate</button></td></tr>
         </table>
      </form>
      <script>
         $(document).ready( function() {
         $("#generate_b").click(function ()
            {
               var parser = new Parse();
            });
         });
		   
      </script>
      <?php
   }
}
?>