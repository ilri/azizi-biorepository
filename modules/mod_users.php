<?php

/*
 * This module is responsible for managing users and user groups
 */
class Users {
   private $Dbase;
   private $security;

   public function __construct($Dbase) {
      $this->Dbase = $Dbase;

      $this->security = new Security($this->Dbase);
   }

   public function trafficController(){
      if(OPTIONS_REQUESTED_SUB_MODULE == "create_account") $this->createUserPage();
      else if(OPTIONS_REQUESTED_SUB_MODULE == "manage_account") $this->manageUserPage();
      else if(OPTIONS_REQUESTED_SUB_MODULE == "create_group") $this->createGroupPage();
      else if(OPTIONS_REQUESTED_SUB_MODULE == "manage_group") $this->modifyGroupPage();
      else if(OPTIONS_REQUESTED_SUB_MODULE == "manage_modules"){
         if(OPTIONS_REQUESTED_ACTION == 'list') $this->listModules ();
         if(OPTIONS_REQUESTED_ACTION == 'action_list') $this->listModuleActions ();
         else if(OPTIONS_REQUESTED_ACTION == '') $this->manageModulesHome ();
      }
      else if(OPTIONS_REQUESTED_SUB_MODULE == 'ajax'){
         if(OPTIONS_REQUESTED_ACTION == 'get_users'){
            $this->getExistingUsers();
         }
         else if(OPTIONS_REQUESTED_ACTION == 'get_user_data'){
            $this->getUserData();
         }
         else if(OPTIONS_REQUESTED_ACTION == 'get_group_data'){
            $this->getGroupData();
         }
      }
      else if(OPTIONS_REQUESTED_SUB_MODULE == '' || OPTIONS_REQUESTED_SUB_MODULE == 'home') $this->home();
   }

   private function home($addinfo = ''){
      $addinfo = ($addinfo != '') ? "<div id='addinfo'>$addinfo</div>" : '';
?>
<div>
   <ul>
      <li><a href="?page=users&do=create_account">Create an Account</a></li>
      <li><a href="?page=users&do=manage_account">Manage an Account</a></li>
      <li><a href="?page=users&do=create_group">Create a Group</a></li>
      <li><a href="?page=users&do=manage_group">Manage a Group</a></li>
      <li><a href="?page=users&do=manage_modules">Manage Modules and Sub Modules</a></li>
      <li><a href="?page=users&do=odk_access">Grant Access to ODK Form</a></li>
   </ul>
</div>
<script>
   $('#whoisme .back').html('<a href=\'?page=home\'>Back</a>');//back link
</script>
<?php
   }

   /**
    * This function renders the Create User page in this module
    *
    * @param type $addinfo Alert you want displayed to the user
    */
   private function createUserPage($addinfo = ''){
      if(OPTIONS_REQUESTED_ACTION == 'add_user'){
         $result = $this->addUser();
         if($result == 1) die(json_encode(array('error' => true, 'mssg' => '"Something went wrong while trying to add user"')));
         else die(json_encode(array('error' => false, 'mssg' => $result)));
      }
      $query = 'SELECT name, id FROM groups';
      $groups = $this->Dbase->ExecuteQuery($query);

      if($groups == 1){
         if(strlen($addinfo) == 0) $addinfo = "Problem occurred while trying to fetch groups";
         $this->Dbase->CreateLogEntry("Error occurred while trying to fetch groups", "fatal");
         $groups = array();
      }

      $query = 'SELECT val_id AS id, value AS name FROM '. Config::$config['azizi_db'] .'.modules_custom_values';
      $projects = $this->Dbase->ExecuteQuery($query);

      if($projects == 1){
         if(strlen($addinfo) == 0) $addinfo = "Problem occurred while trying to fetch projects";
         $this->Dbase->CreateLogEntry("Error occurred while trying to fetch projects", "fatal");
         $projects = array();
      }
      else{
         $pCount = count($projects);
         $aProjects = array();
         for($i = 0; $i < $pCount; $i++){
            $aProjects[$projects[$i]['id']] = $projects[$i]['name'];;
         }
      }

      $addinfo = ($addinfo != '') ? "<div id='addinfo'>$addinfo</div>" : '';
?>
<link rel="stylesheet" href="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/styles/jqx.base.css" type="text/css" />
<script type="text/javascript" src="js/users.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxcore.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxdata.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxbuttons.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxscrollbar.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxmenu.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxcheckbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxlistbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxdropdownlist.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jquery-form/jquery.form.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxnotification.js"></script>
<h3 class="mod_user_heading">Create An Account</h3>
<?php echo $addinfo;?>
<form action="?page=users&do=create_account" method="post" class="form-horizontal user_form">
   <div id="user_left">
      <div class="form-group"><label for="sname" class="control-label">Surname: </label><input id="sname" name="sname" type="text" class="form-control" /></div>
      <div class="form-group"><label for="onames" class="control-label">Other Names: </label><input id="onames" name="onames" type="text" class="form-control" /></div>
      <div class="form-group"><label for="email" class="control-label">Email: </label><input id="email" name="email" type="email" class="form-control" /></div>
      <div class="form-group"><label for="username" class="control-label">Username: </label><input id="username" name="username" type="text" class="form-control" /></div>
      <div class="form-group">
         <label for="ldap" class="control-label">LDAP Authenticate:</label>
         <select id="ldap" name="ldap" class="form-control">
            <option value="0">No</option>
            <option value="1" selected="">Yes</option>
         </select>
      </div>
      <div class="form-group"><label for="project" class="control-label">Users Project: </label>
         <select id="project" name="project" class="form-control">
<?php
   foreach($projects AS $currProject){
      echo "<option value='".$currProject['id']."'>".$currProject['name']."</option>";
   }
?>
         </select></div>
   </div>

   <div  id="user_right">
      <h4>Groups</h4>
      <div id="all_groups">
         <div id="from_groups"></div>
      </div>
      <div class="actions">
         <input type="button" style="padding:4px 16px;" id="add" value='Add >' />
         <input type="button" style="padding:4px 16px;" id="remove" value='< Remove' />
         <input type="button" style="padding:4px 16px;" id="reset" value='Reset' />
      </div>
      <div id="user_groups">
         <div id="to_groups"></div>
      </div>
   </div>
   <div class="center"><input type='button' id="create_user_btn" class="btn-primary" value='Create' /></div>
   <div id="messageNotification"><div></div></div>
</form>
<script>
   $('#whoisme .back').html('<a href=\'?page=users\'>Back</a>');//back link
   var users = new Users('add_user','<?php echo json_encode(Config::$psswdSettings);?>', "<?php echo Config::$rsaPubKey;?>");

   Main.allGroups = <?php echo json_encode($groups); ?>;
   $("#from_groups").jqxListBox({width: 200, height:300, source: Main.allGroups, displayMember: 'name', valueMember: 'id', checkboxes: true, filterable: true, theme: ''});
   $("#to_groups").jqxListBox({width: 200, height: 300, source: [], displayMember: 'text', valueMember: 'id', checkboxes: true});
   $("#add, #remove, #reset, #create_user_btn").jqxButton({ width: '100'});

   $('#add').on('click', users.addToGroup);
   $('#remove').on('click', users.removeFromGroup);
   $('#reset').on('click', users.resetGroups);
   $('#create_user_btn').on('click', users.saveChanges);
   $('#sname').focus();
</script>
<?php
   }

   /**
    * This function renders the Create User page in this module
    *
    * @param type $addinfo Alert you want displayed to the user
    */
   private function manageUserPage($addinfo = ''){
      if(OPTIONS_REQUESTED_ACTION == 'edit_user'){
         $result = $this->editUser();
         if($result == 0) die(json_encode(array('error' => false, 'mssg' => 'Successfully modified account')));
         else die(json_encode(array('error' => true, 'mssg' => 'A problem occurred while modifying the account')));
      }

      $query = 'SELECT name, id FROM groups';
      $groups = $this->Dbase->ExecuteQuery($query);

      if($groups == 1){
         if(strlen($addinfo) == 0) $addinfo = "Problem occurred while trying to fetch groups";
         $this->Dbase->CreateLogEntry("Error occurred while trying to fetch groups", "fatal");
         $groups = array();
      }

      $query = 'SELECT val_id AS id, value AS name FROM '.Config::$config['azizi_db'].'.modules_custom_values';
      $projects = $this->Dbase->ExecuteQuery($query);

      if($projects == 1){
         if(strlen($addinfo) == 0) $addinfo = "Problem occurred while trying to fetch projects";
         $this->Dbase->CreateLogEntry("Error occurred while trying to fetch projects", "fatal");
         $projects = array();
      }

      $users = $this->getExistingUsers(false);//get date for exisiting users but don't encode as json
      $addinfo = ($addinfo != '') ? "<div id='addinfo'>$addinfo</div>" : '';
?>
<link rel="stylesheet" href="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/styles/jqx.base.css" type="text/css" />
<script type="text/javascript" src="js/users.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxcore.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxdata.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxbuttons.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxscrollbar.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxmenu.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxcheckbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxlistbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxdropdownlist.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jquery-form/jquery.form.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxnotification.js"></script>

<h3 class="mod_user_heading">Manage An Account</h3>
<?php echo $addinfo;?>
<form action="?page=users&do=manage_account" method="post" class="form-horizontal user_form">
   <div id="user_left">
   <div class="form-group"><label for="users" class="control-label">Select a user: </label>
      <select id="existing_users">
         <option id=""></option>
<?php
   foreach($users as $currUser){
      echo "<option id='".$currUser['id']."'>".$currUser['login']."</option>";
   }
?>
      </select>
   </div>
   <div class="form-group">
      <label for="allowed" class="control-label">User allowed: </label>
      <select id="allowed" name="allowed" class="form-control">
         <option value="1">Yes</option>
         <option value="0">No</option>
      </select>
   </div>
   <div class="form-group"><label for="sname" class="control-label">Surname: </label><input id="sname" name="sname" type="text" class="form-control" /></div>
   <div class="form-group"><label for="onames" class="control-label">Other Names: </label><input id="onames" name="onames" type="text" class="form-control" /></div>
   <div class="form-group"><label for="email" class="control-label">Email: </label><input id="email" name="email" type="email" class="form-control" /></div>
   <div class="form-group"><label for="project" class="control-label">Project: </label><select id="project" name="project" class="form-control">
         <option value=""></option>
<?php
   foreach($projects AS $currProject){
      echo "<option value='".$currProject['id']."'>".$currProject['name']."</option>";
   }
?>
   </select></div>
   <div class="form-group"><label for="username" class="control-label">Username: </label><input id="username" name="username" type="text" class="form-control" /></div>
   <div class="form-group">
      <label for="ldap" class="control-label">LDAP Authenticate: </label>
      <select id="ldap" name="ldap" class="form-control">
         <option value="0">No</option>
         <option value="1">Yes</option>
      </select>
   </div>
   <div class="form-group"><label for="pass_1" class="control-label">Password: </label><input id="pass_1" name="pass_1" type="password" class="form-control" placeholder="Leave blank if not updating" /></div>
   <div class="form-group"><label for="pass_2" class="control-label">Confirm Password: </label><input id="pass_2" name="pass_2" type="password" class="form-control" placeholder="Leave blank if not updating" /></div>
   </div>
   <div  id="user_right">
      <h4>Groups</h4>
      <div id="all_groups">
         <div id="from_groups"></div>
      </div>
      <div class="actions">
         <input type="button" style="padding:4px 16px;" id="add" value='Add >' />
         <input type="button" style="padding:4px 16px;" id="remove" value='< Remove' />
         <input type="button" style="padding:4px 16px;" id="reset" value='Reset' />
      </div>
      <div id="user_groups">
         <div id="to_groups"></div>
      </div>
   </div>
   <div class="center"><input type='button' id="create_user_btn" class="btn-primary" value='Update' /></div>
   <div id="messageNotification"><div></div></div>
</form>
<script>
   $('#whoisme .back').html('<a href=\'?page=users\'>Back</a>');//back link
   var users = new Users('edit_user','<?php echo json_encode(Config::$psswdSettings);?>', "<?php echo Config::$rsaPubKey;?>");

   Main.allGroups = <?php echo json_encode($groups); ?>;
   $("#from_groups").jqxListBox({width: 200, height:300, source: Main.allGroups, displayMember: 'name', valueMember: 'id', checkboxes: true, filterable: true, theme: ''});
   $("#to_groups").jqxListBox({width: 200, height: 300, source: [], displayMember: 'text', valueMember: 'id', checkboxes: true});
   $("#add, #remove, #reset, #create_user_btn").jqxButton({ width: '100'});

   $('#add').on('click', users.addToGroup);
   $('#remove').on('click', users.removeFromGroup);
   $('#reset').on('click', users.resetGroups);
   $('#create_user_btn').on('click', users.saveChanges);
</script>
<?php
   }

   /**
    * This function renders the create group page
    */
   private function createGroupPage($addinfo = ""){

      if(OPTIONS_REQUESTED_ACTION == 'add_group'){
         $result = $this->addGroup();

         if($result == 0){
            $addinfo = "Successfully added group";
         }
         else {
            $addinfo = "An error occurred while trying to add group";
         }
      }

      //get all sub module actions
      $query = "SELECT b.id, concat(d.uri, '-', c.uri, '-', b.uri) AS name"
              . " FROM sm_actions AS b"
              . " INNER JOIN sub_modules AS c ON b.sub_module_id = c.id"
              . " INNER JOIN modules AS d on c.module_id = d.id"
              . " ORDER BY d.uri, c.uri, b.uri";
      $actions = $this->Dbase->ExecuteQuery($query);
      if($actions == 1){
         $this->Dbase->CreateLogEntry("Error occurred while trying to fetch sub module actions");
         $actions = array();
      }

      $query = "SELECT id,name"
              . " FROM groups";

      $existingGroups = $this->Dbase->ExecuteQuery($query);

      if($existingGroups == 1){
         $this->Dbase->CreateLogEntry("Error occurred while trying to fetch sub module actions");
         $actions = array();
      }

      $addinfo = ($addinfo != '') ? "<div id='addinfo'>$addinfo</div>" : '';
?>
<script type="text/javascript" src="js/groups.js"></script>
<h3 class="mod_user_heading">Add a Group</h3>
<?php echo $addinfo;?>
<form action="?page=users&do=create_group" method="post" class="form-horizontal user_form">
   <div class="form-group"><label for="name" class="control-label">Name: </label><input type="text" name="group_name" id="group_name" /></div>
   <h4 style="margin-left: 10%;">Sub-Module Groups</h4>
   <div class="form-group"><label for="pass_2" class="control-label">Add access:</label>
      <select id="all_actions" class="form-control">
         <option id=""></option>
<?php
   foreach($actions AS $currAction){
      if(substr($currAction['name'], -1) == "-") $currAction['name'] .= "All Actions";

      echo "<option id='".$currAction['id']."'>".$currAction['name']."</option>";
   }
?>
      </select>
      <button id="add_action_btn" type="button">Add</button></div>
      <div id="action_list" style="overflow: hidden; margin-left: 30%; background: white; width: 40%; max-height: 100px; overflow-y: scroll;"></div>
      <input type="hidden" id="group_actions" name="group_actions" />
      <input type="hidden" name="action" id="action" />
   <div class="center"><button type="submit" id="create_group_btn" class="btn-primary">Add</button></div>
</form>
<script>
   $('#whoisme .back').html('<a href=\'?page=users\'>Back</a>');//back link
   var groups = new Groups('add_group', <?php echo "'".json_encode($existingGroups)."'";?>);
</script>
<?php
   }

   /**
    * This function renders the modify group page
    */
   private function modifyGroupPage($addinfo = ""){
      if(OPTIONS_REQUESTED_ACTION == 'edit_group'){
         $result = $this->editGroup();

         if($result == 0){
            $addinfo = "Successfully updated group";
         }
         else {
            $addinfo = "An error occurred while trying to add group";
         }
      }

      //get all sub module actions
      $query = "SELECT b.id, concat(d.uri, '-', c.uri, '-', b.uri) AS name"
              . " FROM sm_actions AS b"
              . " INNER JOIN sub_modules AS c ON b.sub_module_id = c.id"
              . " INNER JOIN modules AS d on c.module_id = d.id"
              . " ORDER BY d.uri, c.uri, b.uri";
      $actions = $this->Dbase->ExecuteQuery($query);
      if($actions == 1){
         $this->Dbase->CreateLogEntry("Error occurred while trying to fetch sub module actions");
         $actions = array();
      }

      $query = "SELECT id, name FROM groups";
      $existingGroups = $this->Dbase->ExecuteQuery($query);
      if($existingGroups == 1){
         $this->Dbase->CreateLogEntry("Error occurred while trying to fetch sub module actions");
         $actions = array();
      }
      $addinfo = ($addinfo != '') ? "<div id='addinfo'>$addinfo</div>" : '';
?>
<link rel="stylesheet" href="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/styles/jqx.base.css" type="text/css" />
<script type="text/javascript" src="js/groups.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxcore.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxdata.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxbuttons.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxscrollbar.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxmenu.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxcheckbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxlistbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxdropdownlist.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jquery-form/jquery.form.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxnotification.js"></script>
<h3 class="mod_user_heading">Modify a Group</h3>
<?php echo $addinfo;?>
<form action="?page=users&do=manage_group" method="post" class="form-horizontal user_form">
   <div id="user_left">
      <div class="form-group"><label for="name" class="control-label">Group: </label>
         <select name="curr_group_name" id="curr_group_name">
            <option id=""><option>
<?php
   foreach($existingGroups as $currGroup){
      echo "<option id='".$currGroup['id']."'>".$currGroup['name']."</option>";
   }
?>
         </select>
      </div>
      <div class="form-group"><label for="name" class="control-label">Name: </label><input type="text" name="group_name" id="group_name" /></div>
   </div>
   <div id="user_right">
      <h4 style="margin-left: 30%;">Sub-Module Actions</h4>
      <div class="form-group"><label for="pass_2" class="control-label">Add access:</label>
         <select id="all_actions" class="form-control">
            <option id=""></option>
<?php
   foreach($actions AS $currAction){
      if(substr($currAction['name'], -1) == "-") $currAction['name'] .= "All Actions";

      echo "<option id='".$currAction['id']."'>".$currAction['name']."</option>";
   }
?>
         </select>
         <button id="add_action_btn" type="button">Add</button></div>
         <div id="action_list" style="overflow: hidden; margin-left: 30%; background: white; width: 40%; max-height: 100px; overflow-y: scroll;"></div>
         <input type="hidden" id="group_actions" name="group_actions" />
         <input type="hidden" name="action" id="action" />
         <input type="hidden" name="group_id" id="group_id" />
   </div>
   <div class="center"><input type='submit' id="create_group_btn" class="btn-primary" value='Update' /></div>
</form>
<script>
   $('#whoisme .back').html('<a href=\'?page=users\'>Back</a>');//back link
   var groups = new Groups('edit_group', <?php echo "'".json_encode($existingGroups)."'";?>);
</script>
<?php
   }

   /**
    * This function adds a user into the database
    */
   private function addUser(){
      $groupIDs = explode(",",$_POST['user_groups']);

      $email = $_POST['email'];
      $result = $this->security->createUser($_POST['username'], $_POST['sname'], $_POST['onames'], $_POST['project'], $groupIDs, $_POST['ldap'], $email);

      if($result == null || (!is_numeric($result) && strlen($result) > 1)){//probably means the password returned therefore successfully created user
         $this->Dbase->CreateLogEntry("password = $result","debug");
         $this->grantDMPAccess($_POST['username'], $_POST['user_groups'], $_POST['ldap'], $this->security->encryptPlainText($result));
         $this->sendUserPassword($_POST['onames'], $email, $_POST['username'], $result, $_POST['ldap']);
         return "The user '{$_POST['username']}' has been successfully added and email sent to '$email'";
      }
      else {
         return 1;
      }
   }

   private function sendUserPassword($oNames, $email, $username, $password, $ldap){
      $firstName = explode(" ", $oNames);
      $firstName = $firstName[0];
      $emailSubject = "Access to ILRI's Biorepository Portal";
      $emailBody = "Hi ".$firstName.",\nYou have been granted access to ILRI's Biorepository portal.\n";

      if($ldap == 0){//user logs in using local auth
         $emailBody .= "Your credentials are as follows:\n\n";
         $emailBody .= "   Username: ".$username."\n";
         $emailBody .= "   Password: ".$password."\n\n";
         $emailBody .= "You can change this password once you log into the system.\n";
      }
      else {
         $emailBody .= "Use your CGIAR username (".$username.") and password to log into the system.\n";
      }
      $emailBody .= "The system can be accessed by going to http://azizi.ilri.cgiar.org/repository.\n\n"
              . "Regards,\n"
              . "Azizi Biorepository";

      shell_exec('echo "'.$emailBody.'"|'.Config::$config['mutt_bin'].' -F '.Config::$config['mutt_config'].' -s "'.$emailSubject.'" -- '.$email);
   }

   private function editUser(){
      $this->Dbase->CreateLogEntry("Editing a user...", "debug");
      $groupIDs = explode(",",$_POST['user_groups']);
      $result = $this->security->updateUser($_POST['user_id'], $_POST['username'], $_POST['pass_1'], $_POST['sname'], $_POST['onames'], $_POST['project'], $_POST['email'], $groupIDs, $_POST['ldap'], $_POST['allowed']);
      $this->grantDMPAccess($_POST['username'], $_POST['user_groups'], $_POST['ldap'], $_POST['pass_1']);
      return $result;
   }

   private function editOwnAccount(){

   }

   private function getExistingUsers($encode = true){
      $query = "SELECT id, login, sname, onames, project, allowed, ldap_authentication"
              . " FROM users"
              . " ORDER BY login";
      $result = $this->Dbase->ExecuteQuery($query);

      if($result == 1){
         $this->Dbase->CreateLogEntry("An error occurred when obtaining all users","fatal");
         $result = array();
      }

      if($encode == true){
         echo json_encode($result);
      }
      else {
         return $result;
      }
   }

   private function grantDMPAccess($username, $userGroups, $ldap, $encryptedPw) {
      //check if any of the user groups has access to the dmp
      $this->Dbase->CreateLogEntry("DMP access...", "debug");
      $query = "select d.group_id from modules as a
         inner join sub_modules as b on a.id=b.module_id inner join sm_actions as c on b.id=c.sub_module_id
         inner join group_actions as d on c.id=d.sm_action_id
         where a.uri = 'dmp' group by d.group_id";
      $res = $this->Dbase->ExecuteQuery($query, array("dmp_uri"=> Config::$config['dmp_uri'], "group_ids" => $userGroups));
      $res_count = count($res);

      if($res_count == 0){
         $this->Dbase->CreateLogEntry('There is no group defined for dmp', 'fatal');
         return false;
      }
      else if($res == 1){
         $this->Dbase->CreateLogEntry($this->Dbase->lastError, 'fatal');
         return false;
      }
      elseif($res_count == 1 && in_array($res[0]['group_id'], explode (',', $userGroups))) {//at least one of the groups this user is in has access to the dmp
         $this->Dbase->CreateLogEntry("User in a group that has access to the DMP", "debug");
         if($ldap == 0 && $encryptedPw == ''){
            // we are adding a user with local auth and we don't have a password. Get the saved password from the database
            $this->Dbase->CreateLogEntry("We are adding a user with local auth to the DMP and we don't have a password. Get the saved password from the database", "debug");
            $userPsswdQ = 'select psswd from users where login = :username';
            $userPsswd = $this->Dbase->ExecuteQuery($userPsswdQ, array('username' => $username));
            if($userPsswd == 1){
               $this->Dbase->CreateLogEntry($this->Dbase->lastError, 'fatal');
               return false;
            }
            // the password here is a hashed password. We need an encrypted password from the browser..... !!!!! For now lets use it for creating the user, but the user will not be able to log into the DMP
            $encryptedPw = $userPsswd[0]['psswd'];
         }
         //get a session id from ODK Workflow API
         $tokenString = json_encode(array(
             "server" => $_SERVER['SERVER_ADDR'],
             "user" => $_SESSION['username'],
             "auth_mode" => $_SESSION['auth_type'],
             "secret" => base64_encode($_SESSION['password'])
         ));
         $authURL = Config::$azizi_url."/repository/mod_ajax.php?page=odk_workflow&do=auth";
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
         if($result != null){//user authenticated
            $authJson = json_decode($result, true);
            if(isset($authJson['session'])) {
               $sessionID = $authJson['session'];
               $tokenString = json_encode(array(
                  "server" => $_SERVER['SERVER_ADDR'],
                  "user" => $_SESSION['username'],
                  "session" => $sessionID
               ));
               //{"server": "192.168.5.40","user": "jrogena", "secret":"nNEVQa7xhAJ08K0rFfO7u+YLQei5RFIULEmO+tjv/WdWz4mXlI7CQ++d7dmubgvLFA/gDY9rm66zhL7WDkqPsy5G7sFGF1w7XWOkWEON7jAT/W3+QIfiRAYDewGKOB8i4g3UPqzDx7Jv7XhAxUmE+1Z3yn727MsaA86CKflv7FM="}
               $authType = "ldap";
               if($ldap == 0) $authType = "local";
               $this->Dbase->CreateLogEntry("AuthType = ".$authType,"debug");
               $dataString = json_encode(array(
                  "server" => $_SERVER['SERVER_ADDR'],
                  "auth_mode" => $authType,
                  "user" => $username,
                  "secret" => $encryptedPw
               ));
               $query = http_build_query(array("token" => $tokenString, "data" => $dataString), '', '&');
               $authURL = "http://".$_SERVER['SERVER_ADDR']."/repository/mod_ajax.php?page=odk_workflow&do=register";
               $regCh = curl_init($authURL);
               curl_setopt($regCh, CURLOPT_RETURNTRANSFER, TRUE);
               curl_setopt($regCh, CURLOPT_FOLLOWLOCATION, TRUE);
               curl_setopt($regCh, CURLOPT_CONNECTTIMEOUT, TRUE);
               curl_setopt($regCh, CURLOPT_POST, 2);//one post field
               curl_setopt($regCh, CURLOPT_POSTFIELDS, $query);
               $result = curl_exec($regCh);
               $http_status = curl_getinfo($regCh, CURLINFO_HTTP_CODE);
               $this->Dbase->CreateLogEntry("HTTP status from ODK Workflow API auth endpoint = $http_status", "debug");
               $this->Dbase->CreateLogEntry("Response from ODK Workflow API auth endpoint = '$result'", "debug");
               curl_close($regCh);
               if($result != null) {
                  $this->Dbase->CreateLogEntry("Successfully registered ".$username." on ODK Workflow API", "info");
                  return true;
               }
            }
         }
      }
      $this->Dbase->CreateLogEntry("Was unable to register ".$username." on ODK Workflow API", "warnings");
      return false;
   }

   /**
    * This function creates a group passed from the add group page
    *
    * @return int 0 if everyting goes well and 1 otherwise
    */
   private function addGroup(){
      $groupActions = explode(",", $_POST['group_actions']);

      return $this->security->createUserGroup($_POST['group_name'], $groupActions);
   }

   private function editGroup(){
      $groupActions = explode(",", $_POST['group_actions']);

      return $this->security->editUserGroup($_POST['group_id'], $_POST['group_name'], $groupActions);
   }

   /**
    * This function gets data corresponding to a user from the database
    */
   private function getUserData(){
      $query = "SELECT id, login, sname, onames, email, project, allowed, ldap_authentication as ldap FROM users WHERE id = :id";
      $result = $this->Dbase->ExecuteQuery($query, array("id" => $_GET['id']));

      if($result == 1){
         $this->Dbase->CreateLogEntry("An error occurred while trying to get user data", "fatal");
         $result = array();
      }

      if(count($result) == 1){
         $query = "SELECT b.name AS text, b.id FROM user_groups AS a INNER JOIN groups AS b on a.group_id = b.id WHERE a.user_id = :userID";
         $groups = $this->Dbase->ExecuteQuery($query, array("userID" => $result[0]['id']));
         if($groups == 1){
            $this->Dbase->CreateLogEntry("An error occurred while trying to get user groups", "fatal");
            $groups = array();
         }
         $result[0]['groups'] = $groups;
      }
      echo json_encode($result);
   }

   private function getGroupData(){
      $groupID = $_GET['id'];
      $query = "SELECT b.id, concat(d.uri, '-', c.uri, '-', b.uri) AS text"
              . " FROM group_actions AS a"
              . " INNER JOIN sm_actions AS b ON a.sm_action_id = b.id"
              . " INNER JOIN sub_modules AS c ON b.sub_module_id = c.id"
              . " INNER JOIN modules AS d on c.module_id = d.id"
              . " WHERE a.group_id = :groupID"
              . " ORDER BY d.uri, c.uri, b.uri";

      $result = $this->Dbase->ExecuteQuery($query, array("groupID" => $groupID));

      if($result == 1){
         $this->Dbase->CreateLogEntry("An error occurred while trying to fetch submodule actions for group with the id ".$groupID, "fatal");
         $result = array();
      }

      echo json_encode($result);
   }

   /**
    * Creates a home page for managing modules and sub modules
    */
   private function manageModulesHome($addinfo = ''){
?>
<script type="text/javascript" src="js/users.js"></script>
<link rel="stylesheet" href="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/styles/jqx.base.css" type="text/css" />
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxcore.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxdata.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxdata.export.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxbuttons.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxscrollbar.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxmenu.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxcheckbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxlistbox.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxdropdownlist.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxgrid.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxgrid.sort.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxgrid.pager.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxgrid.selection.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxgrid.filter.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxwindow.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxtabs.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH?>jqwidgets/jqwidgets/jqxgrid.export.js"></script>
<div id="manage_modules">
   <h3 class="mod_user_heading">Manage Modules and Sub Modules</h3>
   <?php echo $addinfo;?>
   <div id='modules_grid'></div>
</div>
<script>
   $('#whoisme .back').html('<a href=\'?page=users\'>Back</a>');//back link
   var users = new Users('manage_modules','<?php echo json_encode(Config::$psswdSettings);?>', "<?php echo Config::$rsaPubKey;?>");
   users.initiateModulesGrid();
   $('.module_id_href').live('click', function(that){ users.addNewAction(that); } );
</script>
<?php
   }

   /**
    * Get a list of all the modules as defined in the database
    */
   private function listModules(){
      $modulesQuery = 'select *, id as module_id, name as module_name from modules';
      $modules = $this->Dbase->ExecuteQuery($modulesQuery);
      if($modules == 1)  die(json_encode(array('error' => true, 'message' => $modules)));
      die(json_encode($modules));
   }

   /**
    * Get a list of all the actions for this module
    */
   private function listModuleActions(){
      $actionsQuery = 'select a.id as submodule_id, a.uri as submodule_uri, b.id as action_id, b.uri as action_uri, b.description as action_descr '
          . 'from sub_modules as a inner join sm_actions as b on b.sub_module_id = a.id where a.module_id = :sub_module_id order by a.id, a.uri, b.uri';
      $actions = $this->Dbase->ExecuteQuery($actionsQuery, array('sub_module_id' => $_POST['module_id']));
      if($actions == 1) die(json_encode(array('error' => true, 'message' => $actions)));
      die(json_encode($actions));
   }

   /**
    * Creates a home page for granting a user access to an ODK form
    */
   private function grantODKAccessHome(){

   }
}
?>
