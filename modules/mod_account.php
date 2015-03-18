<?php

/* 
 * This module is responsible for managing users and user groups
 */
class Account {
   private $Dbase;
   private $security;
   
   public function __construct($Dbase) {
      $this->Dbase = $Dbase;
      
      $this->security = new Security($this->Dbase);
   }
   
   public function trafficController(){
      if(OPTIONS_REQUESTED_SUB_MODULE == '' || OPTIONS_REQUESTED_SUB_MODULE == "manage_account"){
         $this->manageAccountPage();
      }
   }
   
   /**
    * This function renders the Manage Account page in this module
    * 
    * @param type $addinfo Alert you want displayed to the user
    */
   private function manageAccountPage($addinfo = ''){
      if(OPTIONS_REQUESTED_ACTION == 'edit_user'){
         $result = $this->editOwnAccount();
         if($result == 0){
            $addinfo = "Successfully modified account";
         }
         else {
            $addinfo = "A problem occurred while modifying the account";
         }
      }
      
      $query = "SELECT email, ldap_authentication FROM users WHERE login = :login";
      $userData = $this->Dbase->ExecuteQuery($query, array("login" => $_SESSION['username']));
      if($userData == 1){
         $addinfo .= "Something went wrong while trying to retrieve user data from the database."; 
      }
      else if(count($userData) == 0){//might mean user has logged in using LDAP and is not in the DB
         $userData[0] = array("email" => "", "ldap_authentication" => 1);
      }
      
      if(is_array($userData)){
         $userData = $userData[0];
      }
      
      $addinfo = ($addinfo != '') ? "<div id='addinfo'>$addinfo</div>" : '';
?>
<script type="text/javascript" src="js/account.js">
</script>
<h3 class="mod_user_heading">Manage Your Account</h3>
<?php echo $addinfo;?>
<form action="?page=own_account&do=manage_account&action=edit_user" method="post" class="form-horizontal user_form" onsubmit="return account.validateInput();">
   <div class="form-group">
      <label for="username" class="control-label">Username: </label>
      <input id="username" name="username" type="text" readonly="readonly" value="<?php echo $_SESSION['username'];?>" />
   </div>
   <div class="form-group"><label for="sname" class="control-label">Surname: </label><input id="sname" name="sname" type="text" class="form-control" value="<?php echo $_SESSION['surname'];?>" /></div>
   <div class="form-group"><label for="onames" class="control-label">Other Names: </label><input id="onames" name="onames" type="text" class="form-control" value="<?php echo $_SESSION['onames'];?>" /></div>
   <div class="form-group"><label for="email" class="control-label">Email: </label><input id="email" name="email" type="email" class="form-control" value="<?php if(is_array($userData)) echo $userData['email'];?>" /></div>
   <?php
      if(is_array($userData) && $userData['ldap_authentication'] == 0){
   ?>
   <div class="form-group"><label for="old_pass" class="control-label">Current Password: </label><input id="old_pass" name="old_pass" type="password" class="form-control" /></div>
   <div class="form-group"><label for="pass_1" class="control-label">New Password: </label><input id="pass_1" name="pass_1" type="password" class="form-control" placeholder="Leave blank if not updating" /></div>
   <div class="form-group"><label for="pass_2" class="control-label">Confirm new Password: </label><input id="pass_2" name="pass_2" type="password" class="form-control" placeholder="Leave blank if not updating" /></div>
   <?php
      }
   ?>
   <input id="ldap" name="ldap" type="hidden" value="<?php if(is_array($userData)) echo $userData['ldap_authentication'];?>" />
   <div class="center"><button type="submit" id="update_user_btn" class="btn-primary">Update</button></div>
</form>
<script>
   var account = new Account('<?php echo json_encode(Config::$psswdSettings);?>', "<?php echo Config::$rsaPubKey;?>", <?php echo $userData['ldap_authentication']?>);
   $('#whoisme .back').html('<a href=\'?page=home\'>Back</a>');//back link
</script>
<?php
   }
   
   private function editOwnAccount(){
      $username = $_POST['username'];
      $surname = $_POST['sname'];
      $onames = $_POST['onames'];
      $email = $_POST['email'];
      $oldPass = $_POST['old_pass'];
      $newPass = $_POST['pass_1'];
      $ldap = $_POST['ldap'];
      
      if(strlen($ldap) > 0){
         if(strlen($username) == 0){
            $this->Dbase->CreateLogEntry("Username not set while trying to modify account.".$username, "fatal");
            return 1;
         }
         if(strlen($surname) == 0){
            $this->Dbase->CreateLogEntry("Surname not set while trying to modify account.", "fatal");
            return 1;
         }
         if(strlen($onames) == 0){
            $this->Dbase->CreateLogEntry("Other names not set in while trying to modify account.", "fatal");
            return 1;
         }
         if(strlen($email) == 0){
            $this->Dbase->CreateLogEntry("Username not set in while trying to modify account.", "fatal");
            return 1;
         }
         if($ldap == 0){//user auths using ldap
            if(strlen($oldPass) == 0){
               $this->Dbase->CreateLogEntry("User ".$username." did not provide a password while trying to modify his/her account", "fatal");
               return 1;
            }
            //if(strlen($newPass) == 0)return 1;
         }
         
         //if we've reached this far, everything is fine
         return $this->security->updateOwnAccount($surname, $onames, $username, $email, $oldPass, $newPass, $ldap);
      }
      else {
         $this->Dbase->CreateLogEntry("Unable to determine if user logs in using LDAP", "fatal");
         return 1;
      }
   }
}
?>
