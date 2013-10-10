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
      <form class="form-horizontal">
         <div class="control-group">
            <label class="control-label" for="name">Full Name</label>
            <div class="controls">
               <input type="text" id="name" name="name"/>
            </div>
         </div>
         <div class="control-group">
            <label class="control-label" for="email">Email Address</label>
            <div class="controls">
               <input type="text" id="email" name="email"/>
            </div>
         </div>
         <div class="control-group">
            <label class="control-label" for="file_name">Name the Excel file to be generated</label>
            <div class="controls">
               <input type="text" id="file_name" name="file_name"/>
            </div>
         </div>
         <div class="controls">
            <label class="control-label" for="json_file">JSON File</label>
            <div class="controls">
               <input id="json_file" name="json_file" type="file"/>
            </div>
         </div>
         <div class="controls">
            <label class="control-label" for="xml_file">XML File</label>
            <div class="controls">
               <input id="xml_file" name="xml_file" type="file"/>
            </div>
         </div>
         <div class="controls">
            <button id="generate_b" name="generate_b" onclick="return false;" class="btn-primary">Generate</button>
         </div>
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