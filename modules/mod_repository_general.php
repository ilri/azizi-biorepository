<?php
/**
 * This module will have the general functions that appertains to the system
 *
 * @category   Repository
 * @package    Main
 * @author     Kihara Absolomon <a.kihara@cgiar.org>
 * @since      v0.1
 */
class Repository extends DBase{

   /**
    * @var Object An object with the database functions and properties. Implemented here to avoid having a million & 1 database connections
    */
   public $Dbase;

   /**
    * @var Object An object that is responsible for all security functions eg (authing user, getting modules user has access to)
    */
   private $security;

   public $addinfo;

   public $footerLinks = '';

   /**
    * @var  string   Just a string to show who is logged in
    */
   public $whoisme = '';

   /**
    * @var  string   A place to store any errors that happens before we have a valid connection
    */
   public $errorPage = '';

   /**
    * @var  bool     A flag to indicate whether we have an error or not
    */
   public $error = false;

   public function  __construct() {
      $this->Dbase = new DBase('mysql');
      $this->Dbase->InitializeConnection();
      if(is_null($this->Dbase->dbcon)) {
         ob_start();
         $this->LoginPage(OPTIONS_MSSG_DB_CON_ERROR);
         $this->errorPage = ob_get_contents();
         ob_end_clean();
         return;
      }
      $this->Dbase->InitializeLogs();

      //if we are looking to download a file, log in first
      /*if(Config::$downloadFile){
         $res = $this->Dbase->ConfirmUser($_GET['u'], $_GET['t']);
         if($res != 0) die('Permission Denied. You do not have permission to access this module');
      }*/

      $this->security = new Security($this->Dbase);
   }

   /**
    * Controls the program execution
    */
   public function TrafficController(){
      if(OPTIONS_REQUESTED_MODULE != 'login' && !Config::$downloadFile){  //when we are normally browsing, check that we have the right credentials
         //we hope that we have still have the right credentials
         $this->Dbase->ManageSession();
         $this->whoisme = "{$_SESSION['surname']} {$_SESSION['onames']}, {$_SESSION['user_level']}";
      }

      if(!Config::$downloadFile && ($this->Dbase->session['error'] || $this->Dbase->session['timeout'])){
         if(OPTIONS_REQUEST_TYPE == 'normal'){
            $this->LoginPage($this->Dbase->session['message'], $_SESSION['username']);
            return;
         }
         elseif(OPTIONS_REQUEST_TYPE == 'ajax') die('-1' . $this->Dbase->session['message']);
      }

      //allow access to open access module
      $openAccess = $this->security->isModuleOpenAccess(OPTIONS_REQUESTED_MODULE);
      $this->Dbase->CreateLogEntry("Open access = ".$openAccess, "info");

      if($openAccess == 0){//the requested module is under open access
         if(OPTIONS_REQUESTED_MODULE == 'samples_vis'){
            require_once 'mod_visualize_samples.php';
            $visSamples = new VisualizeSamples($this->Dbase);
            $visSamples->trafficController();
         }
         else if(OPTIONS_REQUESTED_MODULE == 'repository_3d'){
            require_once 'mod_repository_3d.php';
            $repository3D = new Repository3D($this->Dbase);
            $repository3D->TrafficController();
         }
         else if(OPTIONS_REQUESTED_MODULE == "mta"){
            require_once 'mod_mta.php';
            $mta = new MTA($this->Dbase);
            $mta->trafficController();
         }
         return;//do not show the user any more links
      }
      else if($openAccess == 1){//an error occurred
         $this->RepositoryHomePage(OPTIONS_MSSG_FETCH_ERROR);
      }
      else if(!isset($_SESSION['user_id']) && !isset($_SESSION['username']) && !in_array(OPTIONS_REQUESTED_MODULE, array('login', 'logout', ''))){//if not open access, make sure the session variable is fine
         if(OPTIONS_REQUEST_TYPE == 'ajax') die('-1' .OPTIONS_MSSG_INVALID_SESSION);
         else{
            $this->LoginPage(OPTIONS_MSSG_INVALID_SESSION);
            return;
         }
      }

      //Check if user has access to the requested module
      if(OPTIONS_REQUEST_TYPE == 'normal' && !in_array(OPTIONS_REQUESTED_MODULE, array('logout', 'login')) ) $this->WhoIsMe();
      //Set the default footer links
      $this->footerLinks = "";
      if(OPTIONS_REQUESTED_MODULE == '') $this->LoginPage();
      elseif(OPTIONS_REQUESTED_MODULE == 'logout') {
         $this->updateAccessLog();
         $this->LogOutCurrentUser();
      }
      elseif(OPTIONS_REQUESTED_MODULE == 'login') {
         $this->ValidateUser();
      }
      elseif(OPTIONS_REQUESTED_MODULE == 'home') {
         $this->updateAccessLog();
         $this->RepositoryHomePage();
      }
      else{//other modules require permission
         //check if user has access to the context
         $access = $this->security->isUserAllowed(OPTIONS_REQUESTED_MODULE, OPTIONS_REQUESTED_SUB_MODULE, OPTIONS_REQUESTED_ACTION);

         if($access == 0){//user has access
            $this->updateAccessLog();
            if(OPTIONS_REQUESTED_MODULE == 'ln2_requests'){
               require_once 'mod_ln2_requests.php';
               $Ln2 = new Ln2Requests($this->Dbase);
               $Ln2->TrafficController();
            }
            elseif(OPTIONS_REQUESTED_MODULE == 'odk_parser'){
               require_once 'mod_parse_odk.php';
               $ParseODK = new ParseODK($this->Dbase);
               $ParseODK->TrafficController();
            }
            elseif(OPTIONS_REQUESTED_MODULE == 'odk_uploader'){
               require_once 'mod_upload_odk_form.php';
               $UploadODK = new UploadODK($this->Dbase);
               $UploadODK->TrafficController();
            }
            elseif(OPTIONS_REQUESTED_MODULE == 'labels'){
               require_once 'mod_label_printing.php';
               $LabelsPrinter = new LabelPrinter($this->Dbase);
               $LabelsPrinter->TrafficController();
            }
            elseif(OPTIONS_REQUESTED_MODULE == 'ln2_transfers'){
               require_once 'mod_ln2_transfers.php';
               $Ln2Transfers = new LN2Transferer($this->Dbase);
               $Ln2Transfers->TrafficController();
            }
            elseif(OPTIONS_REQUESTED_MODULE == 'lims_uploader'){
               require_once 'mod_lims_uploader.php';
               $LimsUploader = new LimsUploader($this->Dbase);
               $LimsUploader->TrafficController();
            }
            else if(OPTIONS_REQUESTED_MODULE == 'inventory'){
                require_once 'mod_inventory_management.php';
                $InventoryManager = new InventoryManager($this->Dbase);
                $InventoryManager->TrafficController();
            }
            else if(OPTIONS_REQUESTED_MODULE == 'box_storage'){
               require_once 'mod_box_storage.php';
               $boxStorage = new BoxStorage($this->Dbase);
               $boxStorage->TrafficController();
            }
            else if(OPTIONS_REQUESTED_MODULE == 'users'){
               require_once 'mod_users.php';
               $users = new Users($this->Dbase);
               $users->trafficController();
            }
            else if(OPTIONS_REQUESTED_MODULE == 'own_account'){
               require_once 'mod_account.php';
               $account = new Account($this->Dbase);
               $account->trafficController();
            }
            else if(OPTIONS_REQUESTED_MODULE == 'recharges'){
               require_once 'mod_recharges.php';
               $recharges = new Recharges($this->Dbase);
               $recharges->trafficController();
            }
            else if(OPTIONS_REQUESTED_MODULE == 'farm_animals'){
               require_once 'mod_farm_animals.php';
               $farmAnimals = new FarmAnimals($this->Dbase);
               $farmAnimals->trafficController();
            }
            else{
               $this->Dbase->CreateLogEntry(print_r($_POST, true), 'debug');
               $this->Dbase->CreateLogEntry(print_r($_GET, true), 'debug');
               $this->RepositoryHomePage(OPTIONS_MSSG_MODULE_UNKNOWN);
            }
         }
         else if($access == 1){//an error occurred
            $this->RepositoryHomePage(OPTIONS_MSSG_FETCH_ERROR);
         }
         else if($access == 2){//user does not have access
            $this->RepositoryHomePage(OPTIONS_MSSG_RESTRICTED_FUNCTION_ACCESS);//TODO: get the name of the module
         }
      }
   }

   /**
    * Crates the login page for users to log into the system
    *
    * @param   string   $addinfo    (Optional) Any additional information that we might want to display to the users
    * @param   string   $username   (Optional) In case there was an error in the previous login attemp, now we want to try again
    */
   public function LoginPage($addinfo = '', $username = ''){
      $this->footerLinks = '';
      $count = (!isset($_POST['count'])) ? 0 : $_POST['count'] + 1;
      $hidden = "<input type='hidden' name='count' value='$count' />";
      if ($addinfo == '')
         $addinfo = 'Please enter your ILRI username and password to access this System.';
      if (OPTIONS_REQUEST_TYPE == 'normal')
         echo "<script type='text/javascript' src='" . OPTIONS_COMMON_FOLDER_PATH . "jquery.md5.js'></script>";
      if ($count == Config::$psswdSettings['maxNoofTries']) {
         $this->LockAccount();
         $addinfo .= "<br />You have had $count attempts. <b>Your account is disabled.</b>" . Config::$contact;
      } elseif ($count == Config::$psswdSettings['maxNoofTries'] - 1) {
         $addinfo .= "<br />You have had $count attempts. You have 1 more attempt to log in before your account is disabled.";
      }
      ?>
      <div id='login'>
         <form action="?page=login" name='login_form' method='POST' class="form-horizontal" role="form">
            <div id='login_page'>
               <div class="top">Login</div>
               <div id='addinfo'><?php echo $addinfo; ?></div>
               <div class="form-group">
                  <label for="usernameId" class="control-label col-sm-10">Username</label>
                  <div><input id="usernameId" type="text" name="username" placeholder="username" value="<?php echo $username; ?>" size="15"/></div>
               </div>
               <div class="form-group">
                  <label for="passwordId" class="control-label col-sm-10">Password</label>
                  <div><input class="form-control" id="passwordId" type="password" placeholder="Password" name="password" size="15" /></div>
                  <input type="hidden" name="md5_pass" />
               </div>
               <div class='buttons'><input type="submit" name="login" value="Log In" />   <input type="reset" value="Cancel" /></div>
            </div>
      <?php echo $hidden; ?>
         </form>
      </div>
      <?php
      if (OPTIONS_REQUEST_TYPE == 'normal') {
         echo "<script type='text/javascript'>
                 Repository.publicKey = '". Config::$rsaPubKey ."';
                 $('[name=login]').bind('click', Repository.submitLogin);
                 $('[name=username]').focus();
             </script>";
      }
   }

   /**
    * Creates the home page for the users after they login
    */
   public function RepositoryHomePage($addinfo = ''){

      //get modules that user's groups have access to
      $modules = $this->security->getClosedAccessModules(false);
      if($modules == null){
         $addinfo .= " Unable to get the subsystems you have access to";
      }

      $addinfo = ($addinfo == '') ? '' : "<div id='addinfo'>$addinfo</div>" ;
      echo $addinfo;

      if($modules != null){
?>
<div class="user_options">
   <ul>
<?php
         $moduleURIs = array_keys($modules);

         foreach($moduleURIs as $currURI){
            echo "<li><a href='?page=".$currURI."'>".$modules[$currURI]."</a></li>";
         }
?>
      </ul>
</div>
<?php
      }
   }
   
   /**
    * This function logs the session ditails for purposes of analytics
    */
   private function logAccess() {
      $ldap = 1;
      if($_SESSION['auth_type'] == "local") {
         $ldap = 0;
      }
      $this->Dbase->InsertData("user_access",array("user", "using_ldap"), array("user" => $_SESSION['username'], "ldap" => $ldap));
      $query = "select id from user_access where user = :user and end_time is null order by id desc limit 1";
      $result = $this->Dbase->ExecuteQuery($query, array("user" => $_SESSION['username']));
      if(count($result) == 1) {
         $_SESSION['user_access_id'] = $result[0]['id'];
      }
   }
   
   private function updateAccessLog() {
      $this->Dbase->CreateLogEntry("user_access_id = ".$_SESSION['user_access_id'],"debug");
      if(isset($_SESSION['user_access_id']) && $_SESSION['user_access_id'] != 0) {
         $query = "update user_access set end_time = NOW() where id = :id";
         $this->Dbase->ExecuteQuery($query, array("id" => $_SESSION['user_access_id']));
      }
      else {
         $this->Dbase->CreateLogEntry("Could not update the end time in the user_access table because the database's log id is unknown", "warnings");
      }
   }

   /**
    * Validates the user credentials as received from the client
    */
   private function ValidateUser(){
      $this->Dbase->CreateLogEntry("About to auth user", "debug");

      $username = $_POST['username'];
      $encryptedPW = $_POST['password'];

      $authRes = $this->security->authUser($username, $encryptedPW);
      /*
       * authRes can be:
       *    0 - user successfully authenticated
       *    1 - error occured when trying to auth user
       *    2 - wrong password provided
       *    3 - user does not exist
       *    4 - accout disabled
       */
      $this->Dbase->CreateLogEntry("Auth results = ".$authRes, "info");
      if($authRes == 0){
         $this->logAccess();
         $this->WhoIsMe();
         $this->RepositoryHomePage();
         return;
      }
      else if($authRes == 1){
         $this->LoginPage('An error occurred while trying to log you in. Please try again or contact the system administrator');
         return;
      }
      else if($authRes == 2){
         $this->LoginPage('The username or password you provided is incorrect');
         return;
      }
      else if($authRes == 3){
         $this->LoginPage('The username you provided is unknown');
         return;
      }
      else if($authRes == 4){
         $this->LoginPage('Your username is currently deactivated. Please contact the system administrator if you need it reactivated');
         return;
      }
      else {
         $this->LoginPage('Please try logging in again');
         $this->Dbase->CreateLogEntry("Authentication object returned an unknown response(" . $authRes . ") while trying to auth ".$username, "fatal");
         return;
      }
   }

   /**
    * Confirms the credentials of the person who is logged in and then displays a link on top of the person who is logged in
    *
    * @return integer   Returns 1 incase of an error or the person has wrong credentials, else returns 0
    */
   public function WhoIsMe(){
      if (OPTIONS_REQUEST_TYPE == 'ajax') return;
      //display the credentials of the person who is logged in
      $mainUserGroup = "Unspecified Group";

      if(is_array($_SESSION['user_type']) && count($_SESSION['user_type']) > 0){
         $mainUserGroup = $_SESSION['user_type'][0];
      }

      Config::$curUser = "{$_SESSION['surname']} {$_SESSION['onames']}, {$mainUserGroup}";
      echo "<div id='whoisme'><span class='back'>&nbsp;</span><span class='user'>" . Config::$curUser . " | <a href='?page=own_account'>My Account</a> | <a href='?page=logout'>Logout</a>";

      //show the howto link for LN2 engineers
      if(OPTIONS_REQUESTED_MODULE === "ln2_transfers" ){
         echo " | <a href='?page=ln2_transfers&do=howto'>Help</a>";
      }

      echo "</span></div>";
      return 0;
   }

   /**
    * Logs out the current user
    */
   private function LogOutCurrentUser(){
      $this->Dbase->LogOut();
      $this->Dbase->session['restart'] = true;
      //$this->LogOut();
      $this->LoginPage();
   }

   /**
    * Creates the home page for the systems admins
    */
   private function SysAdminsHomePage(){
?>
   <h2 class='center'>Systems Administrator Roles</h2>
      <li><a href='?page=users&do=browse'>Users</a></li>
      <li><a href='?page=users&do=assigned_roles'>Assign Users Additional Roles</a></li>
      <?php echo $this->DocumentationLink(); ?>
<?php
   }

   /**
    * Checks that the system is not tampered with. ie we have all the basic and necessary data/configuration for the system to work well
    *
    * @return  integer  Returns 0 if all is ok, else it returns 1
    */
   public function SystemCheck(){
      //check for the default lis system admin
      $query = "select a.id from users as a inner join user_levels as b on a.user_level = b.id where a.login = :login and a.sname = :sname and a.onames = :onames";
      $variables = array('login' => Config::$superuser['login'], 'sname' => Config::$superuser['sname'], 'onames' => Config::$superuser['onames']);

      $res = $this->Dbase->ExecuteQuery($query, $variables);
      if($res == 1) return OPTIONS_MSSG_FETCH_ERROR;
      elseif(count($res) == 0) return OPTIONS_MSSG_NO_SYS_ADMIN;
      return 0;
   }

   /**
    * Causes the files used to create the date time picker to be displayed
    */
   public function DateTimePickerFiles(){
      echo '
      <link rel="stylesheet" type="text/css" href="'. OPTIONS_COMMON_FOLDER_PATH .'freqdec_datePicker/datepicker.css" />
      <script src="'. OPTIONS_COMMON_FOLDER_PATH .'freqdec_datePicker/datepicker.js" type="text/javascript"></script>';
   }

   /**
    * Disables a user account
    *
    * @return  mixed    Returns a string with the error message incase of an error, else it returns 0
    */
   private function LockAccount(){
      $res = $this->Dbase->UpdateRecords('users', 'allowed', 0, 'login', $_POST['username']);
      if(!$res) return OPTIONS_MSSG_UPDATE_ERROR;
      else return 0;
   }

   /**
    * Echos the code for including the files that will be used for auto-complete
    */
   public function AutoCompleteFiles(){
      echo "<script type='text/javascript' src='". OPTIONS_COMMON_FOLDER_PATH ."jquery/jquery.autocomplete/jquery.autocomplete.js'></script>";
      echo "<link rel='stylesheet' type='text/css' href='". OPTIONS_COMMON_FOLDER_PATH ."jquery/jquery.autocomplete/styles.css' />";
   }

   /**
    * Spits out the javascript that initiates the autocomplete feature once the DOM has finished loading
    *
    * @param   array    $settings An array with the settings as described below
    * <code>
    *    $settings = array(
    *       'inputId' => 'pat_search',                The id of the HTML element that we want to bind the auto-complete to
    *       'reqModule' => 'patients',                The module that we will use to build the query. It is usually bound to the $_GET['page'] variable
    *       'reqSubModule' => 'search',               The sub module that we will use to build the query. It is usually bound to the $_GET['do'] variable
    *       'selectFunction' => 'Patients.select',    The function to call when an option is selected
    *       'formatResult' => 'Patients.foramt',      (Optional) The function to call if we want to format the suggestions differently
    *       'visibleSuggestions' => 'true',           (Optional) A flag whether or not we want to display the suggestions
    *       'beforeNewQuery' => 'Patients.before'     (Optional) A function to call before we make a new query to the db
    *    );
    * </code>
    */
   public function InitiateAutoComplete($settings){
      //$inputId, $reqModule, $reqSubModule, $selectFunction, $formatResult = '', $visibleSuggestions = '', $beforeNewQuery = ''
//      if($formatResult != '') $formatResult = ", onSearchComplete: $formatResult";
      if($settings['formatResult'] == '') $settings['formatResult'] = 'Lis.fnFormatResult';
      if($settings['visibleSuggestions'] == '') $settings['visibleSuggestions'] = true;
      if($settings['beforeNewQuery'] == '') $settings['beforeNewQuery'] = 'undefined';
?>
<script type='text/javascript'>
   //bind the search to autocomplete
   $(function(){
      var settings = {
         serviceUrl:'mod_ajax.php', minChars:1, maxHeight:400, width:380,
         zIndex: 9999, deferRequestBy: 300, //miliseconds
         params: { page: '<?php echo $settings['reqModule']; ?>', 'do': '<?php echo $settings['reqSubModule']; ?>' }, //aditional parameters
         noCache: true, //default is false, set to true to disable caching
         onSelect: <?php echo $settings['selectFunction'] ?>,
         formatResult: <?php echo $settings['formatResult']; ?>,
         beforeNewQuery: <?php echo $settings['beforeNewQuery']; ?>,
         visibleSuggestions: <?php echo $settings['visibleSuggestions']; ?>
      };
//      settings.params['extras'] = <?php echo $settings['extras']; ?>;
      $('#<?php echo $settings['inputId']; ?>').autocomplete(settings);
   });
</script>
<?php
   }

   /**
    * Generates the necessary pdf and offers it for download
    */
   public function GenerateAndDownloadPDF(){
      $url = str_replace('&', '\&', 'http://localhost' . $_SERVER['REQUEST_URI'] .'&gen=false');
      GeneralTasks::CreateDirIfNotExists(Config::$uploads['destinationFolder']);
      $ofile = Config::$uploads['destinationFolder'] ."{$_COOKIE['lis']}.pdf";
      $command = "/usr/bin/xvfb-run /usr/bin/wkhtmltopdf -q $url $ofile";
      exec($command, $output, $return);
      $this->Dbase->CreateLogEntry($command, 'debug');

      Header('Content-Type: application/pdf');
      $date = date('Ymd_hi');
      Header("Content-Disposition: attachment; filename=". OPTIONS_REQUESTED_SUB_MODULE ."_$date.pdf");
      if(headers_sent()) $this->RepositoryHomePage('Some data has already been output to browser, can\'t send PDF file');
      readfile($ofile);
      unlink($ofile);
      die();
   }

   /**
    * Include the necessary files needed for using the Flexigrid framework
    */
   public function FlexigridFiles(){
?>
      <script type='text/javascript' src='<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jquery.flexigrid/flexigrid.pack.js' /></script>
      <link rel='stylesheet' type='text/css' href='<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jquery.flexigrid/css/flexigrid.pack.css' />
<?php
   }

   /**
    * Includes the necessary files needed to create a grid from jqWidgets framework
    */
   public function jqGridFiles(){
?>
   <link rel="stylesheet" href="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/styles/jqx.base.css" type="text/css" />
   <script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxcore.js"></script>
   <script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxdata.js"></script>
   <script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxbuttons.js"></script>
   <script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxscrollbar.js"></script>
   <script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxlistbox.js"></script>
   <script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxpanel.js"></script>
   <script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxmenu.js"></script>
   <script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.js"></script>
   <script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.selection.js"></script>
   <script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.filter.js"></script>
   <script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.sort.js"></script>
<?php
   }

   /**
    * Converts a numeric position to a position that can be used by LabCollector
    *
    * @param   integer  $position   The numeric position that we want to convert
    * @param   integer  $rack_size  The size of the tray in question.
    * @return  string   Returns the converted position that LC is comfortable with
    */
   public function NumericPosition2LCPosition($position, $rack_size){
      $sideLen = sqrt($rack_size);
      if($position % $sideLen == 0) $box_detail = chr(64+floor($position/$sideLen)).$sideLen;
      else $box_detail = chr(65+floor($position/$sideLen)).$position%$sideLen;
      return $box_detail;
   }
}
?>
