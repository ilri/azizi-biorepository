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
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxlistbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxnotification.js"></script>
<script type="text/javascript" src="js/dmp_home.js"></script>
<div class="center" style="height: 500px; margin-top: 200px;">
   <input type="button" id="new_project_btn" value="Create a new project" />
   <div id="projects_list_box" style="margin:0 auto;"></div>
</div>
<div id="enotification_pp"></div>
<div id="inotification_pp"></div>
<script type="text/javascript">
   $('#whoisme .back').html('<a href=\'?page=home\'>Back</a>');//back link
   //var dmpHome = new DMPHome("<?php echo $_SERVER['SERVER_ADDR']; ?>", "<?php echo $_SESSION['username']; ?>", "<?php echo $sessionId; ?>");
   var dmpHome = new DMPHome("<?php echo $_SERVER['SERVER_ADDR']; ?>", "jrogena2", "<?php echo $sessionId; ?>");
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
?>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxlistbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxwindow.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxsplitter.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxlistbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.selection.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.edit.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxfileupload.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxcheckbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxdropdownlist.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxnumberinput.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxnotification.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxmenu.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxtabs.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.columnsresize.js"></script>
<script type="text/javascript" src="js/dmp_view_schema.js"></script>
<div id="project_title" style="font-size: 18px;margin-top: 10px;margin-bottom: 15px;color: #0088cc;">New Project</div>
<div id="menu_bar">
   <ul>
      <li>DMP
         <ul style="width: 250px;">
            <li><a href="?page=dmp">Home</a></li>
         </ul>
      </li>
      <li>Edit
          <ul style='width: 250px;'>
              <li>Rollback
                 <ul id="undo_container" style='color: black; min-width: 300px;'>
                  </ul>
              </li>
              <li><a id="regen_schema_menu_btn" style="text-decoration: none; color: black;">Regenerate Schema</a></li>
              <li><a id="delete_project_menu_btn" style="text-decoration: none; color: black;">Delete Project</a></li>
          </ul>
      </li>
      <li>Help
         
      </li>
  </ul>
</div>
<div style="margin-top: 10px;">
   <div id="new_project_wndw" style="display: none;">
      <div>Create a new project</div>
      <div>
         <div style="margin-left: 5%">
            <label for="project_name">Project name</label>
            <input type="text" id="project_name" style="height: 25px; width: 300px;" />
         </div>
         <div id="file_drop_area" style="position: relative; width: 90%; height: 60px; margin-left: 5%; margin-right: 5%;">
            <label for="manual_file_upload">Data file</label>
            <div id="manual_file_upload" style="position: absolute; bottom: 0;"></div>
         </div>
         <button type="button" id="create_project_btn" class="btn btn-primary" style="margin-left: 5%; margin-top: 10px;">Create</button>
      </div>
   </div>
   <div id="new_foreign_key_wndw" style="display: none;">
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
   <div id="rename_sheet_wndw" style="display: none;">
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
   <div id="delete_project_wndw" style="display: none;">
      <div>Delete Project</div>
      <div>
         <div style="position: relative; width: 90%; margin-left: 5%; margin-right: 5%;">Are you sure you want to delete this project?</div>
         <div style="position: relative; width: 90%; margin-left: 5%; margin-right: 5%; text-align: right;">
            <button type="button" id="delete_project_btn" class="btn btn-danger" style="margin-right: 5%; margin-top: 10px;">Delete</button>
         </div>
      </div>
   </div>
   <div id="right_click_menu">
      <ul>
         <li><a href="#" id="rename_sheet_btn">Rename</a></li>
         <li><a href="#" id="delete_sheet_btn">Delete</a></li>
      </ul>
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
      <button type="button" id="cancel_btn" class="btn btn-danger" disabled>Cancel</button>
      <button type="button" id="update_btn" class="btn btn-primary" disabled>Update</button>
   </div>
   <div id="loading_box" style="display: none;background-color: rgb(212, 125, 120)">Loading..</div>
   <div id="enotification_pp"></div>
   <div id="inotification_pp"></div>
</div>
<script type="text/javascript">
   //var dmpVSchema = new DMPVSchema("<?php echo $_SERVER['SERVER_ADDR']; ?>", "<?php echo $_SESSION['username']; ?>", "<?php echo $sessionId; ?>", "<?php echo $project;?>");
   var dmpVSchema = new DMPVSchema("<?php echo $_SERVER['SERVER_ADDR']; ?>", "jrogena2", "<?php echo $sessionId; ?>", "<?php echo $project;?>");
</script>
<?php
   }
   
   /**
    * This function returns the session ID corresponding to the current user
    * @return string
    */
   private function getAPISessionID() {
      include_once OPTIONS_COMMON_FOLDER_PATH."authmodules/mod_security_v0.1.php";
      $security = new Security($this->Dbase);
      /*$cypherSecret = $_SESSION['password'];
      $username = $_SESSION['username'];
      $authType = $_SESSION['auth_type'];*/
      $cypherSecret = "Ncb/vu6jAFsPHBqEoP+IhJEQF1Co63LYQdH9MTD4AGERAzlwYc004Dm1Dcg4iTX0GrWfLMv16s1fNeKCLiJTFQ7YUE6UF5b2xHDZYPllBtlMcmSN0EIM+TDlUXRyfh2DRqEb8OlASmW+8DSs7j+Ex+dLnZiej52LCdh55/OPiWw=";
      $username = "jrogena2";
      $authType = "local";
      $this->Dbase->CreateLogEntry("session = ".print_r($_SESSION, true), "debug");
      $this->Dbase->CreateLogEntry("server = ".print_r($_SERVER, true), "debug");
      $tokenString = json_encode(array(
          "server" => $_SERVER['SERVER_ADDR'],
          "user" => $username,
          "auth_mode" => $authType,
          "secret" => base64_encode($cypherSecret)
      ));
      $authURL = "http://".$_SERVER['SERVER_ADDR']."/repository/mod_ajax.php?page=odk_workflow&do=auth";
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