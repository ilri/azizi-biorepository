<?php
class DMP extends Repository{

   public $Dbase;

   public function __construct($Dbase) {
      $this->Dbase = $Dbase;
   }

   public function trafficController() {
      global $Repository;
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
      if (OPTIONS_REQUESTED_SUB_MODULE == '') $this->homePage();
      else if(OPTIONS_REQUESTED_SUB_MODULE == 'view_schema') {
         $this->viewSchemaPage();
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
<script type="text/javascript" src="js/dmp_home.js"></script>
<div class="center" style="height: 500px; margin-top: 200px;">
   <input type="button" id="new_project_btn" value="Create a new project" />
   <div id="projects_list_box" style="margin:0 auto;"></div>
</div>
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
<script type="text/javascript" src="js/dmp_view_schema.js"></script>
<div id="menu_bar">
   <ul>
      <li>Project
         <ul style="width: 250px;">
            <li>Home</li>
            <li>Contributors</li>
         </ul>
      </li>
      <li>Edit
          <ul style='width: 250px;'>
              <li>Undo
                 <ul id="undo_container" style='width: 220px;'>
                  </ul>
              </li>
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
         <form method="post" action="">
            <div style="margin-left: 5%">
               <label for="project_name">Project name</label>
               <input type="text" id="project_name" />
            </div>
            <div id="file_drop_area" class="drag_drop_area" style="position: relative; width: 90%; height: 200px; margin-left: 5%; margin-right: 5%;">
               <label style="position: absolute; left: 42%; top: 35%; color: #3d3d3d;">Drop file here</label>
               <div id="manual_file_upload" style="position: absolute; bottom: 0;"></div>
               <input type="file" style="display: none;" name="data_file" id="data_file" />
            </div>
         </form>
      </div>
   </div>
   <div id="split_window">
      <div id="sheets"></div>
      <div id="columns"></div>
   </div>
   <div style="margin-top:20px; margin-right: 50px; text-align: right;">
      <button type="button" id="cancel_btn" class="btn btn-danger" disabled>Cancel</button>
      <button type="button" id="update_btn" class="btn btn-primary" disabled>Update</button>
   </div>
   <div id="loading_box" style="display: none;">Loading..</div>
   <div id="enotification_pp"></div>
   <div id="inotification_pp"></div>
</div>
<script type="text/javascript">
   $('#whoisme .back').html('<a href=\'?page=home\'>Back</a>');//back link
   var dmpVSchema = new DMPVSchema("<?php echo $_SERVER['SERVER_ADDR']; ?>", "<?php echo $_SESSION['username']; ?>", "<?php echo $sessionId; ?>", "<?php echo $project;?>");
</script>
<?php
   }
   
   /**
    * This function returns the session ID corresponding to the current user
    * @return string
    */
   private function getAPISessionID() {
      return "FUBKjeJpHrgC4SKAQKAk";
   }
}
?>