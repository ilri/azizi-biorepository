<?php
/**
   Copyright 2015 ILRI

   This file is part of the azizi repository platform.

   The azizi platform is a free software: you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation, either version 3 of the License, or
   (at your option) any later version.

   The azizi platform is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with the azizi platform .  If not, see <http://www.gnu.org/licenses/>.
  */

/**
 * A module with a collection of some common toolset for use in data manipulation
 *
 * @author Absolomon Kihara   a.kihara@cgiar.org
 */
class Toolkit{

   private $Dbase;

   /**
    *
    * @param type $Dbase   Create a new class for farm animals
    */
   public function __construct($Dbase){
      $this->Dbase = $Dbase;
   }

   /**
    * This function determines what user wants to do
    */
   public function trafficController(){
      if(OPTIONS_REQUESTED_SUB_MODULE == '' || OPTIONS_REQUESTED_SUB_MODULE == 'home'){
         if(OPTIONS_REQUESTED_ACTION == '') $this->homePage();
      }
      else if(OPTIONS_REQUESTED_SUB_MODULE == 'match_gps') $this->matchGPSCoordinatesHome ();
      else if(OPTIONS_REQUESTED_SUB_MODULE == 'odk_form_stats'){
         if(OPTIONS_REQUESTED_ACTION == '') $this->odkFormStatHome ();
         elseif(OPTIONS_REQUESTED_ACTION == 'fetch_all') $this->odkFetchAllForms ();
         elseif(OPTIONS_REQUESTED_ACTION == 'delete_form') $this->deleteODKForm();
      }
   }

   /**
    * Creates the home page for the tool kit module
    * @param type $addInfo
    */
   private function homePage($addInfo = ''){
      $addInfo = ($addInfo != '') ? "<div id='addinfo'>$addInfo</div>" : '';
      ?>
<div id='home'>
   <?php echo $addInfo; ?>
   <h3 class="center">Azizi collection of tools</h3>
   <div class="toolkit">
      <ul>
         <!-- li><a href="?page=toolkit&do=match_gps">Match GPS Coordinates</a></li -->
         <li><a href="?page=toolkit&do=odk_form_stats">Uploaded ODK forms statistics</a></li>
      </ul>
   </div>
</div>
<script type="text/javascript">
   $('#whoisme .back').html('<a href=\'?page=home\'>Back</a>');//back link
</script>
<?php
   }

   /**
    * Creates a home page for matching 2 sets of GPS coordinates
    */
   private function matchGPSCoordinatesHome(){
?>
<script type="text/javascript" src="js/toolkit.js"></script>
<link rel="stylesheet" href="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/styles/jqx.base.css" type="text/css" />
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxcore.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxdata.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxinput.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxcheckbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxvalidator.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxpasswordinput.js"></script>
<div id="match_gps" ng-app="matchGPS">
   <form jqx-validator jqx-settings="matchGPSSettings" id="db_settings">
      <table><tr><td>
         <table>
            <tr><td><h4>Table1 Details</h4></td></tr>
            <tr><td valign="top">Server:</td><td valign="top"><jqx-input jqx-width="200" jqx-height="25" id="server1"></jqx-input></td></tr>
            <tr><td valign="top">Username:</td><td valign="top"><jqx-input jqx-width="200" jqx-height="25" id="username1"></jqx-input></td></tr>
            <tr><td valign="top">Password:</td><td valign="top"><jqx-input jqx-width="200" jqx-height="25" type="password" id="password1"></jqx-input></td></tr>
            <tr><td valign="top">Database Name:</td><td valign="top"><jqx-input jqx-width="200" jqx-height="25" id="db_name1"></jqx-input></td></tr>
            <tr><td valign="top">Table Name:</td><td valign="top"><jqx-input jqx-width="200" jqx-height="25" id="table1"></jqx-input></td></tr>
         </table>
      </td><td>
         <table>
            <tr><td><h4>Table2 Details</h4></td></tr>
            <tr><td valign="top">Server:</td><td valign="top"><jqx-input jqx-width="200" jqx-height="25" id="server2"></jqx-input></td></tr>
            <tr><td valign="top">Username:</td><td valign="top"><jqx-input jqx-width="200" jqx-height="25" id="username2"></jqx-input></td></tr>
            <tr><td valign="top">Password:</td><td valign="top"><jqx-input jqx-width="200" jqx-height="25" type="password" id="password2"></jqx-input></td></tr>
            <tr><td valign="top">Database Name:</td><td valign="top"><jqx-input jqx-width="200" jqx-height="25" id="db_name2"></jqx-input></td></tr>
            <tr><td valign="top">Table Name:</td><td valign="top"><jqx-input jqx-width="200" jqx-height="25" id="table2"></jqx-input></td></tr>
         </table>
      </td></tr></table>
   </form>
    <div ng-controller="gps_results">
        <jqx-grid jqx-columns="columns" jqx-sortable="true" jqx-source="gps_matches" jqx-width="800"  jqx-height="200" jqx-alt-rows="true"></jqx-grid>
    </div>
</div>
<?php
   }

   /**
    *
    */
   private function odkFormStatHome(){
?>
<script type="text/javascript" src="js/toolkit.js"></script>
<link rel="stylesheet" href="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/styles/jqx.base.css" type="text/css" />
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxcore.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxdata.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxbuttons.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxscrollbar.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxmenu.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxgrid.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxdropdownlist.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxlistbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxgrid.sort.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxgrid.pager.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxgrid.selection.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxgrid.filter.js"></script>

<div id="main">
   <div id="odk_forms"></div>
</div>
<script type="text/javascript">
   $('#whoisme .back').html('<a href=\'?page=toolkit\'>Back</a>');       //back link
   var Toolkit = new Toolkit();

   // call the function to initiate the form grid
   window.toolkit.initODKForms();
</script>
<?php
   }

   /**
    * Fetch all defined ODK forms in ODK aggregate
    */
   private function odkFetchAllForms(){
      // before creating the db connection, lets import the db settings
      $this->settings = parse_ini_file(Config::$configFile, true);

      $ODKConn = new DBase('mysql');
      $ODKConn->InitializeConnection($this->settings['odk_ro']);
      if(is_null($ODKConn->dbcon)) {
         ob_start();
         $this->LoginPage(OPTIONS_MSSG_DB_CON_ERROR);
         $this->errorPage = ob_get_contents();
         ob_end_clean();
         return;
      }
      $ODKConn->InitializeLogs();

      // get all defined ODK forms in the ODK tables and return their stats
      $formsQuery = 'select a.FORM_NAME as form_name, c.SUBMISSION_FORM_ID as form_id, d.PERSIST_AS_TABLE_NAME as table_name, d.URI_SUBMISSION_DATA_MODEL as data_model '
            . 'from _form_info_fileset as a '
            . 'inner join _form_info as b on a._PARENT_AURI=b._URI '
            . 'inner join _form_info_submission_association as c on b._URI=c.URI_MD5_FORM_ID '
            . 'inner join _form_data_model as d on c.URI_SUBMISSION_DATA_MODEL=d.URI_SUBMISSION_DATA_MODEL '
            . 'where d.PERSIST_AS_TABLE_NAME like "%core%" '
            . 'group by PERSIST_AS_TABLE_NAME '
            . 'order by a.FORM_NAME';

      $forms = $ODKConn->ExecuteQuery($formsQuery);
      $ODKConn->CreateLogEntry($formsQuery, 'fatal');
      if($forms == 1){
         $ODKConn->CreateLogEntry($ODKConn->lastError, 'fatal');
         die(json_encode(array('error' => true, 'message' => 'There was an error while fetching data from the database. Contact the system administrator')));
      }
      $formsCount = count($forms);
      $allForms = array();
      for($i = 0; $i < $formsCount; $i++){
         $f = $forms[$i];
         if(!isset($allForms[$f['form_id']])){
            $allForms[$f['form_id']] = array('form_name' => utf8_encode($f['form_name']), 'form_id' => $f['form_id'], 'no_cores' => 0);
         }
         $coreQuery = "select count(*) as count from {$f['table_name']}";
         $coreCount = $ODKConn->ExecuteQuery($coreQuery);
         if($coreCount == 1){
            $ODKConn->CreateLogEntry($ODKConn->lastError, 'fatal');
            die(json_encode(array('error' => true, 'message' => 'There was an error while fetching data from the database. Contact the system administrator')));
         }
         $matches = array();
         preg_match('/.+core([0-9]{1,2})?$/i', $f['table_name'], $matches);
         $tableIndex = (count($matches[1]) == 0) ? '' : $matches[1];
         $allForms[$f['form_id']]["core{$tableIndex}"] = $coreCount[0]['count'];
         $allForms[$f['form_id']]['no_cores'] += 1;
      }

      // now loop through all the forms and format the data well
      $formsCount = count($allForms);
      $forms2send = array();
      foreach($allForms as $f){
         if(!isset($f['core2'])) $f['core2'] = 'N/A';
         if(!isset($f['core3'])) $f['core3'] = 'N/A';
         $forms2send[] = $f;
      }
      die(json_encode($forms2send));
   }

   /**
    * Delete an ODK form
    * @return type
    */
   private function deleteODKForm(){
      $this->authCookies = "aggregate_auth";
      $getXMLURL = Config::$config['odkDeleteURL'];//URL that handles the GWT Request for deleting Aggregate forms
      $this->authUser();
      $instanceId = $_POST['instanceId'];

      // get the form id from the odk forms table
      $formQuery = 'select id from odk_forms where instance_id = :instance_id';
      $formId = $this->Dbase->ExecuteQuery($formQuery, array('instance_id' => $instanceId));
      if($formId == 1) {
         return $this->Dbase->lastError;
      }
      $formId = $formId[0]['id'];

      $this->Dbase->CreateLogEntry("About to delete $instanceId with form id $formId from the Aggregate server", 'info');
      $ch = curl_init($getXMLURL);
      curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (X11; Linux x86_64; rv:26.0) Gecko/20100101 Firefox/26.0");
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, TRUE);
      curl_setopt($ch, CURLOPT_COOKIEFILE, $this->authCookies);
      curl_setopt($ch, CURLOPT_POST, TRUE);
      //insert the GWT RPC payload into the request. GWT Whaaat? Refer to -> http://blog.gdssecurity.com/labs/2009/10/8/gwt-rpc-in-a-nutshell.html
      curl_setopt($ch, CURLOPT_POSTFIELDS, "7|0|6|".Config::$config['odkUIURL']."|1BAF2E8ED0CEB731FEA73A25EDD25330|org.opendatakit.aggregate.client.form.FormAdminService|deleteForm|java.lang.String/2004016611|".$instanceId."|1|2|3|4|1|5|6|");
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
          "Connection: Keep-Alive",
          "Keep-Alive: 300",
          "Content-Type: text/x-gwt-rpc; charset=UTF-8",
          "X-GWT-Module-Base: ".Config::$config['odkUIURL'],
          "X-GWT-Permutation: 131B412388B99E0A845272E4C5B3CDC8",
          "X-opendatakit-gwt: yes",
          "Accept: */*",
          "Origin: ".Config::$config['repositoryURL'],
          "Accept-Encoding: gzip, deflate",
          "Accept-Language: en-US,en;q=0.8,en-GB;q=0.6,sw;q=0.4"
      ));
      curl_setopt($ch, CURLINFO_HEADER_OUT, TRUE);
      curl_setopt($ch, CURLOPT_REFERER, Config::$config['odkBaseURL']);

      $curlResult = curl_exec($ch);
      $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $this->Dbase->CreateLogEntry(print_r(curl_getinfo($ch), true), 'debug');
      curl_close($ch);

      //the server should return a status code 200 if it was able to process request.
      if($http_status == 200) {//form was successfully deleted
         $this->Dbase->CreateLogEntry("Updating database record for $instanceId", 'info');
         $query = "update azizi_miscdb.odk_deleted_forms set status = 'deleted' where form = $formId";
         $this->Dbase->ExecuteQuery($query);
         $query = "update azizi_miscdb.odk_forms set is_active = 0 where id = $formId";
         $this->Dbase->ExecuteQuery($query);
         die(json_encode(array('isError' => FALSE, 'message' => 'The form was deleted successfully.')));
      }
      else{
         $this->Dbase->CreateLogEntry("Aggregate server did not delete the form. HTTP status = '$http_status'", 'fatal');
         die(json_encode(array('isError' => TRUE, 'message' => 'There was an error while deleting the form.')));
      }
   }

   private function authUser(){
      if(file_exists($this->authCookies) === FALSE){
         echo "about to auth \n";
         $authURL = Config::$config['odkAuthURL'];
         touch($this->authCookies);
         chmod($this->authCookies, 0777);
         $authCh = curl_init($authURL);

         curl_setopt($authCh, CURLOPT_USERAGENT, "Mozilla/5.0 (X11; Linux x86_64; rv:26.0) Gecko/20100101 Firefox/26.0");
         curl_setopt($authCh, CURLOPT_RETURNTRANSFER, TRUE);
         curl_setopt($authCh, CURLOPT_FOLLOWLOCATION, TRUE);
         curl_setopt($authCh, CURLOPT_CONNECTTIMEOUT, TRUE);
         curl_setopt($authCh, CURLOPT_COOKIEJAR, $this->authCookies);
         curl_setopt($authCh, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
         curl_setopt($authCh, CURLOPT_USERPWD, Config::$config['odk_pruner_user'].":".Config::$config['odk_pruner_pass']);

         $result = curl_exec($authCh);
         $http_status = curl_getinfo($authCh, CURLINFO_HTTP_CODE);
         curl_close($authCh);
         if($http_status == 401){//user not authenticated
            echo "The ODK user is not authorised to upload ODK forms \n";
         }
         else {
            echo " \n **".$http_status."** \n";
         }
      }
   }
}