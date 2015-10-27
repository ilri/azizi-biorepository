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
         <li><a href="?page=toolkit&do=match_gps">Match GPS Coordinates</a></li>
      </ul>
   </div>
</div>
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
}