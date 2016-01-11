<?php
class DMP extends Repository{

   public $Dbase;

   public function __construct($Dbase) {
      $this->Dbase = $Dbase;
   }

   public function trafficController() {
      global $Repository;
      if(OPTIONS_REQUEST_TYPE == "normal") {
?>
<script type="text/javascript">
   $(document).ready(function(){
      $(".user").hide();
      $("#avid_header").hide();
      $("#avid_footer").hide();
      $("#repository").css("border", "0px");
      $("#repository").css("width", "95%");
      $("body,html").css("background-color", "#ffffff");
   });
</script>
<?php
      }
      if (OPTIONS_REQUESTED_SUB_MODULE == '') $this->homePage();
      else if(OPTIONS_REQUESTED_SUB_MODULE == 'view_schema') {
         $this->viewSchemaPage();
      }
      else if(OPTIONS_REQUESTED_SUB_MODULE == 'ajax') {
         if(OPTIONS_REQUESTED_ACTION == "upload_data_file") $this->uploadDataFile();
      }
   }

   /**
    * This function renders the home page
    */
   public function homePage() {
      $this->jqGridFiles();//import vital jqx files
      $sessionId = $this->getAPISessionID();
?>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jqwidgets/jqwidgets/jqxlistbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jqwidgets/jqwidgets/jqxnotification.js"></script>
<script type="text/javascript" src="js/dmp_home.js"></script>
<div class="center" style="height: 500px; margin-top: 200px;">
   <input type="button" id="new_project_btn" value="Create a new project" />
   <div id="projects_list_box" style="margin:0 auto;"></div>
</div>
<div id="enotification_pp"></div>
<div id="inotification_pp"></div>
<script type="text/javascript">
   $('#whoisme .back').html('<a href=\'?page=home\'>Back</a>');//back link
   var dmpHome = new DMPHome("<?php echo $_SERVER['SERVER_ADDR']; ?>", "<?php echo $_SESSION['username']; ?>", "<?php echo $sessionId; ?>");
</script>
<?php
   }

   /**
    *
    */
   public function viewSchemaPage() {
      $this->jqGridFiles();//import vital jqx files
      $sessionId = $_GET['session'];
      $project = $_GET['project'];
      $query = "SELECT a.* FROM odk_forms AS a INNER JOIN odk_access AS b ON a.id = b.form_id WHERE b.user = :user AND a.is_active=1";
      $forms = $this->Dbase->ExecuteQuery($query, array("user" => $_SESSION['username']));
      $query = "SELECT email FROM users WHERE login = :login";
      $userData = $this->Dbase->ExecuteQuery($query, array("login" => $_SESSION['username']));

      // Vizualization libraries to be included when needed
      $str = '"'. OPTIONS_COMMON_FOLDER_PATH .'angularjs/angular.js", '.
      '"'. OPTIONS_COMMON_FOLDER_PATH .'jqwidgets/jqwidgets/jqxdraw.js", '.
      '"'. OPTIONS_COMMON_FOLDER_PATH .'jqwidgets/jqwidgets/jqxangular.js", '.
      '"'. OPTIONS_COMMON_FOLDER_PATH .'jqwidgets/jqwidgets/jqxchart.core.js"';

?>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jqwidgets/jqwidgets/jqxcore.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jqwidgets/jqwidgets/jqxlistbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jqwidgets/jqwidgets/jqxwindow.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jqwidgets/jqwidgets/jqxsplitter.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jqwidgets/jqwidgets/jqxlistbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jqwidgets/jqwidgets/jqxgrid.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jqwidgets/jqwidgets/jqxgrid.selection.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jqwidgets/jqwidgets/jqxgrid.edit.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jqwidgets/jqwidgets/jqxfileupload.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jqwidgets/jqwidgets/jqxcheckbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jqwidgets/jqwidgets/jqxdropdownlist.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jqwidgets/jqwidgets/jqxnumberinput.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jqwidgets/jqwidgets/jqxnotification.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jqwidgets/jqwidgets/jqxmenu.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jqwidgets/jqwidgets/jqxtabs.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jqwidgets/jqwidgets/jqxgrid.columnsresize.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jqwidgets/jqwidgets/jqxdatetimeinput.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jqwidgets/jqwidgets/jqxcalendar.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jqwidgets/jqwidgets/jqxgrid.filter.js"></script>



<script type="text/javascript" src="js/dmp_view_schema.js"></script>
<div id="project_title" style="font-size: 18px;margin-top: 10px;margin-bottom: 15px;color: #0088cc;cursor: pointer;">New Project</div>
<div id="blanket_cover" style="position: absolute; background-color: white; opacity: 0.6; display: none; z-index: 5;"></div>
<div id="menu_bar">
   <ul>
      <li>DMP
         <ul style="width: 250px;">
            <li id="home_menu_btn">Home</li>
            <li id="create_project_menu_btn">Create New Project</li>
         </ul>
      </li>
      <li>Schema
          <ul style='width: 250px;'>
              <li>Rollback
                 <ul id="undo_container" style='color: black; min-width: 300px;'>
                  </ul>
              </li>
              <li id="add_note_menu_btn">Notes</li>
              <li id="regen_schema_menu_btn">Regenerate Schema</li>
              <li id="merge_version_menu_btn">Combine with another version</li>
              <li id="merge_schema_menu_btn">Merge with another project</li>
              <li id="delete_project_menu_btn">Delete Schema (and project)</li>
          </ul>
      </li>
      <li>Data
         <ul style='width: 250px;'>
            <li  id="run_query_menu_btn">Run cleaning query</li>
            <li id="dump_data_btn">Dump data into database</li>
            <li id="db_credentials_btn">Get Database Credentials</li>
            <li id="get_data_btn">Get Data</li>
          </ul>
      </li>
      <li id="admin_menu_btn">Admin
         <ul style='width: 250px;'>
            <li  id="grant_access_menu_btn">Grant access</li>
            <li id="revoke_access_menu_btn">Revoke access</li>
            <li id="show_users_menu_btn">Show users</li>
          </ul>
      </li>
  </ul>
</div>
<div style="margin-top: 10px;">
   <div id="new_project_wndw" style="display: none; z-index: 6;">
      <div>Create a new project</div>
      <div>
         <div style="margin-left: 5%">
            <label for="project_name">Project name</label>
            <input type="text" id="project_name" style="height: 25px; width: 300px;" />
         </div>
         <div style="margin-left: 5%">
            <label for="data_source">Data source</label>
            <select id="data_source">
               <option value="odk">ODK</option>
               <option value="local">Local file</option>
            </select>
         </div>
         <div id="odk_forms_div" style="margin-left: 5%">
            <label for="odk_forms">Available forms</label>
            <select id="odk_forms">
               <option value=""></option>
               <?php
               foreach ($forms as $currForm)
                  echo "<option value='".$currForm['id']."'>".$currForm['form_name']."</option>"
               ?>
            </select>
         </div>
         <div id="file_drop_area" style="position: relative; width: 90%; height: 60px; margin-left: 5%; margin-right: 5%;display: none;">
            <label for="manual_file_upload">Data file</label>
            <div id="manual_file_upload" style="position: absolute; bottom: 0;"></div>
         </div>
         <button type="button" id="create_project_btn" class="btn btn-primary" style="margin-left: 5%; margin-top: 10px;">Create</button>
      </div>
   </div>
   <div id="get_data_wndw" style="display: none; z-index: 6;">
      <div>Get Data</div>
      <div>
         <div style="margin-left: 5%">
            <label for="data_filter_type">Filter</label>
            <select type="text" id="data_filter_type" style="height: 25px; width: 300px;">
               <option value="all">All</option>
               <option value="prefix">Groups</option>
               <option value="query">Query</option>
               <option value="time">Time</option>
            </select>
         </div>
         <div style="margin-left: 5%; display: none;" id="filter_query_div">
            <textarea id="filter_query" style="width: 500px;" cols="1" placeholder="Valid PostgreSQL select query"></textarea>
         </div>
         <div style="margin-left: 5%; display: none;" id="filter_prefix_div">
            <div id="data_project_groups_div" style="margin-left: 3%; height: 80%;overflow-y: scroll;">
            </div>
         </div>
         <div style="margin-left: 5%; display: none;" id="filter_time_div">
            <label for="time_column">Column</label>
            <select id="time_column" style="margin-left: 3%;max-height: 110px;"></select>
            <label for="start_time">Start date</label>
            <div id="start_time" style="margin-left: 3%;max-height: 110px;"></div>
            <label for="end_time" style="margin-top: 3%;">End date</label>
            <div id="end_time" style="margin-left: 3%;max-height: 110px;"></div>
          </div>
         <button type="button" id="get_data_btn2" class="btn btn-primary" style="margin-left: 5%; margin-top: 10px;">Get Data</button>
      </div>
   </div>
   <div id="new_foreign_key_wndw" style="display: none; z-index: 6;">
      <div>Create a foreign key</div>
      <div>
         <div style="margin-left: 5%">
            <label for="foreign_key_column">Column</label>
            <input type="text" id="foreign_key_column" style="height: 25px; width: 300px;" disabled/>
         </div>
         <div style="margin-left: 5%">
            <label for="foreign_key_ref_column">Reference Column</label>
            <select type="text" id="foreign_key_ref_column" style="height: 25px; width: 300px;"></select>
         </div>
         <button type="button" id="add_foreign_key_btn" class="btn btn-primary" style="margin-left: 5%; margin-top: 10px;">Add</button>
      </div>
   </div>
   <div id="db_credentials_wndw" style="display: none; z-index: 6;">
      <div>Database Credentials</div>
      <div>
         <div style="margin-left: 5%">
            <div id="db_cred_database"></div>
         </div>
         <div style="margin-left: 5%">
            <div id="db_cred_username"></div>
         </div>
         <div style="margin-left: 5%">
            <div id="db_cred_password"></div>
         </div>
         <div style="margin-left: 5%">
            <div id="db_cred_host"></div>
         </div>
      </div>
   </div>
   <div id="rename_sheet_wndw" style="display: none; z-index: 6;">
      <div>Rename sheet</div>
      <div>
         <div style="margin-left: 5%">
            <label for="sheet_name">New name</label>
            <input type="text" id="sheet_name" style="height: 25px; width: 300px;" />
            <input type="hidden" id="sheet_old_name" />
         </div>
         <button type="button" id="rename_sheet_btn2" class="btn btn-primary" style="margin-left: 5%; margin-top: 10px;">Rename</button>
      </div>
   </div>
   <div id="rename_project_wndw" style="display: none; z-index: 6;">
      <div>Rename project</div>
      <div>
         <div style="margin-left: 5%">
            <label for="new_project_name">New name</label>
            <input type="text" id="new_project_name" style="height: 25px; width: 300px;" />
         </div>
         <button type="button" id="rename_project_btn" class="btn btn-primary" style="margin-left: 5%; margin-top: 10px;">Rename</button>
      </div>
   </div>
   <div id="notes_wndw" style="display: none; z-index: 6;">
      <div>Project notes</div>
      <div>
         <div id="notes_grid" style="width: 90%; margin-left: 1%; margin-top: 10px;"></div>
         <div style="position: relative; width: 90%; margin-top: 10px; margin-left: 1%;">
            <label for="new_note">Add a note</label>
            <textarea type="text" id="new_note" rows="2" style="width: 85%;"></textarea>
            <button type="button" id="add_new_note" class="btn btn-primary" style="margin-left: 5%; margin-top: 10px;">Add</button>
         </div>
      </div>
   </div>
   <div id="grant_access_wndw" style="display: none; z-index: 6;">
      <div>Grant access</div>
      <div>
         <div style="position: relative; width: 90%; margin-top: 10px; margin-left: 1%;">
            <label for="grant_access_user">User</label>
            <input type="text" id="grant_access_user" style="height: 25px; width: 300px;" />
         </div>
         <div style="position: relative; width: 90%; margin-top: 10px; margin-left: 1%;">
            <label for="grant_access_level">Access level</label>
            <select type="text" id="grant_access_level"style="width: 85%;">
               <option value="normal">Normal</option>
               <option value="admin">Admin</option>
            </select>
         </div>
         <div style="position: relative; width: 90%; margin-top: 10px; margin-left: 1%;">
            <button type="button" id="grant_access_btn" class="btn btn-primary" style="margin-left: 90%; margin-top: 10px;">Grant</button>
         </div>
      </div>
   </div>
   <div id="revoke_access_wndw" style="display: none; z-index: 6;">
      <div>Revoke access</div>
      <div>
         <div style="position: relative; width: 90%; margin-top: 10px; margin-left: 1%;">
            <label for="revoke_access_user">User</label>
            <select type="text" id="revoke_access_user" style="height: 25px; width: 300px;"></select>
         </div>
         <div style="position: relative; width: 90%; margin-top: 10px; margin-left: 1%;">
            <button type="button" id="revoke_access_btn" class="btn btn-primary" style="margin-left: 90%; margin-top: 10px;">Revoke</button>
         </div>
      </div>
   </div>
   <div id="users_wndw" style="display: none; z-index: 6;">
      <div>Project users</div>
      <div>
         <div style="position: relative; width: 90%; margin-top: 10px; margin-left: 1%;">
            <div id="users_grid"></div>
         </div>
      </div>
   </div>
   <div id="query_wndw" style="display: none; z-index: 6;">
      <div>Cleaning query</div>
      <div>
         <div style="position: relative; width: 90%; margin-top: 10px;margin-right: 5%;margin-left: 5%;">
            <textarea type="text" id="query_box" rows="2" style="width: 100%;"></textarea>
            <button type="button" id="run_query_btn" class="btn btn-primary" style="margin-left: 90%; margin-top: 10px;">Run</button>
         </div>
      </div>
   </div>
   <div id="delete_project_wndw" style="display: none; z-index: 6;">
      <div>Delete Project</div>
      <div>
         <div style="position: relative; width: 90%; margin-left: 5%; margin-right: 5%;">Are you sure you want to delete this project?</div>
         <div style="position: relative; width: 90%; margin-left: 5%; margin-right: 5%; text-align: right;">
            <button type="button" id="delete_project_btn" class="btn btn-danger" style="margin-right: 5%; margin-top: 10px;">Delete</button>
         </div>
      </div>
   </div>
   <div id="other_projects_wndw" style="display: none; z-index: 6;">
      <div>Select the project</div>
      <div>
         <div style="position: relative; width: 90%; margin-left: 5%; margin-right: 5%;"><select id="other_project_list"></select></div>
         <div style="position: relative; width: 90%; margin-left: 5%; margin-right: 5%; text-align: right;">
            <button type="button" id="merge_version_btn" class="btn btn-primary" style="margin-right: 5%; margin-top: 10px;">Okay</button>
            <button type="button" id="merge_schema_btn" class="btn btn-primary" style="margin-right: 5%; margin-top: 10px;">Okay</button>
         </div>
      </div>
   </div>
   <div id="merge_sheet_wndw" style="display: none; z-index: 6;">
      <div>Select the common columns</div>
      <div>
         <div style="margin-left: 20px;">
            <div style="display: inline-block; width: 45%;">
               <div id="curr_project_name" style="margin-bottom: 10px;"></div>
               <div style="position: relative; width: 90%; margin-left: 5%; margin-right: 5%;"><select id="curr_sheet_list"></select></div>
               <div style="position: relative; width: 90%; margin-left: 5%; margin-right: 5%;"><select id="curr_column_list"></select></div>
            </div>
            <div style="display: inline-block; width: 45%;">
               <div id="other_project_name" style="margin-bottom: 10px;"></div>
               <div style="position: relative; width: 90%; margin-left: 5%; margin-right: 5%;"><select id="other_sheet_list"></select></div>
               <div style="position: relative; width: 90%; margin-left: 5%; margin-right: 5%;"><select id="other_column_list"></select></div>
            </div>
         </div>
         <div style="position: relative; width: 90%; margin-left: 5%; margin-right: 5%; text-align: right;">
            <button type="button" id="merge_sheet_btn" class="btn btn-primary" style="margin-right: 5%; margin-top: 10px;">Okay</button>
         </div>
      </div>
   </div>
   <div id="version_diff_wndw" style="display: none; z-index: 6;">
      <div>Resolve Version Differences</div>
      <div>
         <div style="position: relative; width: 90%; margin-left: 5%; margin-right: 5%;">
            <label for="merged_version_name">Merged Project Name</label>
            <input type="text" id="merged_version_name" style="height: 25px; width: 300px;" />
         </div>
         <div id="version_diff_grid" style="width: 97%"></div>
         <div style="position: relative; width: 90%; margin-left: 5%; margin-right: 5%; text-align: right;">
            <button type="button" id="apply_version_changes" class="btn btn-primary" style="margin-right: 5%; margin-top: 10px;">Apply all changes</button>
         </div>
      </div>
   </div>
   <div id="merge_diff_wndw" style="display: none; z-index: 6;">
      <div>Resolve Schema Differences</div>
      <div>
         <div style="position: relative; width: 90%; margin-left: 5%; margin-right: 5%;">
            <label for="merged_schema_name">Merged Schema Name</label>
            <input type="text" id="merged_schema_name" style="height: 25px; width: 300px;" />
         </div>
         <div id="merge_diff_grid" style="width: 90%"></div>
         <div style="position: relative; width: 90%; margin-left: 5%; margin-right: 5%; text-align: right;">
            <button type="button" id="apply_merge_changes" class="btn btn-primary" style="margin-right: 5%; margin-top: 10px;">Apply all changes</button>
         </div>
      </div>
   </div>
   <div id="right_click_menu">
      <ul>
         <li><a href="#" id="rename_sheet_btn">Rename</a></li>
         <li><a href="#" id="delete_sheet_btn">Delete</a></li>
      </ul>
   </div>
   <div id="dynamic_viz" style="display: none; z-index: 6;">
      <div>Visualization of the selected columns data</div>
      <div id="charts">
         <div id="left_panel"></div>
         <div id="viz_pane"></div>
      </div>
   </div>
   <div id="split_window">
      <div id="sheets"></div>
      <div id="tabs">
         <ul>
            <li>Schema</li>
            <li>Data</li>
         </ul>
         <div id="columns"></div>
         <div id="sheet_data"></div>
      </div>
   </div>
   <div style="margin-top:20px; margin-right: 50px; text-align: right;">
      <button type="button" id="viz_btn" class="btn btn-success" disabled>Visualize</button>
      <button type="button" id="cancel_btn" class="btn btn-danger" disabled>Cancel</button>
      <button type="button" id="update_btn" class="btn btn-primary" disabled>Update</button>
   </div>
   <div id="loading_box" style="display: none; background-color: rgb(212, 125, 120); z-index: 2000;">Loading..</div>
   <div id="enotification_pp" style="z-index: 2000;"></div>
   <div id="inotification_pp" style="z-index: 2000;"></div>
</div>
<script type="text/javascript">
   var dmpVSchema = new DMPVSchema("<?php echo $_SERVER['SERVER_ADDR']; ?>", "<?php echo $_SESSION['username']; ?>", "<?php echo $sessionId; ?>", "<?php echo $project;?>", "<?php echo $_SESSION['onames']." ".$_SESSION['surname'];?>", "<?php echo $userData[0]['email'];?>");
   // Vizualization libraries
   window.dvs.jsVisualizationScripts = [<?php echo "$str"; ?>];
</script>
<?php
   }

   /**
    * This function returns the session ID corresponding to the current user
    * @return string
    */
   private function getAPISessionID() {
      include_once OPTIONS_COMMON_FOLDER_PATH."azizi-shared-libs/authmodules/mod_security_v0.1.php";
      $security = new Security($this->Dbase);
      $this->Dbase->CreateLogEntry("Repository username = ".$_SESSION['username'], "debug");
      $tokenString = json_encode(array(
          "server" => $_SERVER['SERVER_ADDR'],
          "user" => $_SESSION['username'],
          "auth_mode" => $_SESSION['auth_type'],
          "secret" => base64_encode($_SESSION['password'])
      ));
      $authURL = Config::$azizi_url .'/repository/mod_ajax.php?page=odk_workflow&do=auth';
      $authCh = curl_init($authURL);
      curl_setopt($authCh, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($authCh, CURLOPT_FOLLOWLOCATION, TRUE);
      curl_setopt($authCh, CURLOPT_CONNECTTIMEOUT, TRUE);
      curl_setopt($authCh, CURLOPT_POST, 1);//one post field
      curl_setopt($authCh, CURLOPT_POSTFIELDS, "token=".$tokenString."");
      $result = curl_exec($authCh);
      $http_status = curl_getinfo($authCh, CURLINFO_HTTP_CODE);
      $this->Dbase->CreateLogEntry("HTTP status from ODK Workflow API auth endpoint = $http_status", "debug");
      $this->Dbase->CreateLogEntry("Response from ODK Workflow API auth endpoint = '$result'", "debug");
      curl_close($authCh);
      if($result != null){//user not authenticated
         $authJson = json_decode($result, true);
         if(isset($authJson['session'])) {//user not authenticated
            return $authJson['session'];
         }
      }
      return null;
   }

   /**
    * This function uploads files from javascript into the server
    */
   private function uploadDataFile() {
      $length = 20;
      $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
      $randomString = "";
      for ($i = 0; $i < $length; $i++) {
          $randomString .= $characters[rand(0, strlen($characters) - 1)];
      }
      $dir = "tmp/";
      if(!file_exists($dir)) {
         mkdir($dir, 0777);//not sure if this are the best permissions
      }
      $targetFilename = $dir.$randomString;
      $this->Dbase->CreateLogEntry("Uploading file from post ".print_r($_FILES, true), "debug");
      $this->Dbase->CreateLogEntry("Current working dir =  ".getcwd(), "debug");
      $uploaded = move_uploaded_file($_FILES["data_file"]["tmp_name"], $targetFilename);
      if($uploaded == true) {
         echo json_encode(array("name" => $targetFilename, "healthy" => true));
      }
      else {
         echo json_encode(array("name" => null, "healthy" => false));
      }
   }
}
?>