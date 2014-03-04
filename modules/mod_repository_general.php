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
      if(Config::$downloadFile){
         $res = $this->Dbase->ConfirmUser($_GET['u'], $_GET['t']);
         if($res != 0) die('Permission Denied. You do not have permission to access this module');
      }
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
      if(OPTIONS_REQUESTED_MODULE == 'logout'){
         $this->Dbase->LogOut();
         $this->Dbase->session['restart'] = true;
      }
      if(!Config::$downloadFile && ($this->Dbase->session['error'] || $this->Dbase->session['timeout'])){
         if(OPTIONS_REQUEST_TYPE == 'normal'){
            $this->LoginPage($this->Dbase->session['message'], $_SESSION['username']);
            return;
         }
         elseif(OPTIONS_REQUEST_TYPE == 'ajax') die('-1' . $this->Dbase->session['message']);
      }

      if(!isset($_SESSION['user_id']) && !isset($_SESSION['username']) && !in_array(OPTIONS_REQUESTED_MODULE, array('login', 'logout', ''))){
         if(OPTIONS_REQUEST_TYPE == 'ajax') die('-1' .OPTIONS_MSSG_INVALID_SESSION);
         else{
            $this->LoginPage(OPTIONS_MSSG_INVALID_SESSION);
            return;
         }
      }

      $aka_module = Config::$modules[OPTIONS_REQUESTED_MODULE];
      //check if the user has permissions to access the module he is asking for
      if(!in_array(OPTIONS_REQUESTED_MODULE, Config::$freeAreas)) {
         //we are asking for a module which is not open to all
         if(!in_array($_SESSION['user_type'], Config::$userPermissions[OPTIONS_REQUESTED_MODULE]['allowed_groups']) ){
            $this->Dbase->CreateLogEntry("Denied Access: The user '{$_SESSION['username']}'({$_SESSION['user_type']}) was denied access to ". OPTIONS_REQUESTED_MODULE .' module.', 'debug');
            $this->Dbase->CreateLogEntry('Access Denied: The user '. Config::$curUser .' was denied access to '. OPTIONS_REQUESTED_MODULE .' module.', 'audit');
            if(!in_array(OPTIONS_REQUESTED_MODULE, array_keys(Config::$userPermissions))){
               if(OPTIONS_REQUEST_TYPE == 'ajax') die('-1'. OPTIONS_MSSG_UNKNOWN_MODULE);
               else $this->RepositoryHomePage(OPTIONS_MSSG_UNKNOWN_MODULE);
               return;
            }
            if(OPTIONS_REQUEST_TYPE == 'normal'){
               $this->RepositoryHomePage(sprintf(OPTIONS_MSSG_RESTRICTED_MODULE_ACCESS, $aka_module[OPTIONS_REQUESTED_MODULE]));
               return;
            }
            elseif(OPTIONS_REQUEST_TYPE == 'ajax') die('-1' . sprintf(OPTIONS_MSSG_RESTRICTED_MODULE_ACCESS, $aka_module));
         }
      }

      //checkif the user has permissions to access the requested sub module and action
      if(OPTIONS_REQUESTED_SUB_MODULE != '' && !in_array(OPTIONS_REQUESTED_MODULE, Config::$freeAreas)){
         if(!key_exists($_SESSION['user_type'], Config::$userPermissions[OPTIONS_REQUESTED_MODULE][OPTIONS_REQUESTED_SUB_MODULE])) {
            $this->Dbase->CreateLogEntry("Denied Access: The user '{$_SESSION['username']}'({$_SESSION['user_type']}) was denied access to ". OPTIONS_REQUESTED_MODULE .' module,'. OPTIONS_REQUESTED_SUB_MODULE .' sub module.', 'debug');
            $this->Dbase->CreateLogEntry('Access Denied: The user '. Config::$curUser .' was denied access to '. OPTIONS_REQUESTED_MODULE .' module,'. OPTIONS_REQUESTED_SUB_MODULE .' sub module.', 'audit');
            if(!in_array(OPTIONS_REQUESTED_SUB_MODULE, array_keys(Config::$userPermissions[OPTIONS_REQUESTED_MODULE]))){
               if(OPTIONS_REQUEST_TYPE == 'ajax') die('-1'. OPTIONS_MSSG_UNKNOWN_SUB_MOUDLE);
               else $this->RepositoryHomePage(OPTIONS_MSSG_UNKNOWN_SUB_MODULE);
               return;
            }
            if(OPTIONS_REQUEST_TYPE == 'normal') {
               $this->RepositoryHomePage(sprintf(OPTIONS_MSSG_RESTRICTED_FUNCTION_ACCESS, $aka_module[OPTIONS_REQUESTED_SUB_MODULE]));
               return;
            }
            elseif(OPTIONS_REQUEST_TYPE == 'ajax') die('-1' . sprintf(OPTIONS_MSSG_RESTRICTED_FUNCTION_ACCESS, $aka_module[OPTIONS_REQUESTED_SUB_MODULE]));
         }
         if(OPTIONS_REQUESTED_ACTION != ''){
//            print_r($aka_module);
            if(!in_array(OPTIONS_REQUESTED_ACTION, Config::$userPermissions[OPTIONS_REQUESTED_MODULE][OPTIONS_REQUESTED_SUB_MODULE][$_SESSION['user_type']]) &&
               !in_array(OPTIONS_REQUESTED_ACTION, Config::$userPermissions[OPTIONS_REQUESTED_MODULE][OPTIONS_REQUESTED_SUB_MODULE][$_SESSION['delegated_role']]) ){
               //check whether the user has access to the action that he/she wants to do
               $this->Dbase->CreateLogEntry('Access Denied: The user '. Config::$curUser .' was denied access to '. OPTIONS_REQUESTED_MODULE .' module,'. OPTIONS_REQUESTED_SUB_MODULE .' sub module.', 'audit');
               if(OPTIONS_REQUEST_TYPE == 'normal') {
                  $this->RepositoryHomePage(sprintf(OPTIONS_MSSG_RESTRICTED_FUNCTION_ACCESS, OPTIONS_REQUESTED_ACTION, $aka_module[OPTIONS_REQUESTED_SUB_MODULE]));
                  return;
               }
               elseif(OPTIONS_REQUEST_TYPE == 'ajax') die('-1' . sprintf(OPTIONS_MSSG_RESTRICTED_FUNCTION_ACCESS, Config::$actions_aka[OPTIONS_REQUESTED_ACTION], $aka_module[OPTIONS_REQUESTED_SUB_MODULE]));
            }
         }
      }

      if(OPTIONS_REQUEST_TYPE == 'normal' && !in_array(OPTIONS_REQUESTED_MODULE, array('logout', 'login')) ) $this->WhoIsMe();
      //Set the default footer links
      $this->footerLinks = "";
      if(OPTIONS_REQUESTED_MODULE == '') $this->LoginPage();
      elseif(OPTIONS_REQUESTED_MODULE == 'logout') $this->LogOutCurrentUser();
      elseif(OPTIONS_REQUESTED_MODULE == 'login') $this->ValidateUser();
      elseif(OPTIONS_REQUESTED_MODULE == 'home') $this->RepositoryHomePage();
      elseif(OPTIONS_REQUESTED_MODULE == 'ln2_requests'){
         require_once 'mod_ln2_requests.php';
         $Ln2 = new Ln2Requests($this->Dbase);
         $Ln2->TrafficController();
      }
      elseif(OPTIONS_REQUESTED_MODULE == 'odk_parser'){
         require_once 'mod_parse_odk.php';
         $ParseODK = new ParseODK();
         $ParseODK->TrafficController();
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
      else if(OPTIONS_REQUESTED_MODULE == 'tray_storage'){
         require_once './mod_tray_storage.php';
         $trayStorage = new TrayStorage($this->Dbase);
         $trayStorage->TrafficController();
      }
      else{
         $this->Dbase->CreateLogEntry(print_r($_POST, true), 'debug');
         $this->Dbase->CreateLogEntry(print_r($_GET, true), 'debug');
         $this->RepositoryHomePage(OPTIONS_MSSG_MODULE_UNKNOWN);
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
      $addinfo = ($addinfo == '') ? '' : "<div id='addinfo'>$addinfo</div>" ;
      echo $addinfo;
?>
<div class="user_options">
   <ul>
      <li><a href='?page=ln2_requests'>Request Liquid Nitrogen</a></li>
      <li><a href='?page=inventory'>Inventory Management</a></li>
<?php
      $this->HomeLinks($_SESSION['user_type']);
?>
   </ul>
</div>
<?php
   }

   /**
    * Create additional links according to the passed user type
    *
    * @param   string   $userType   The user type we want to create links for
    */
   private function HomeLinks($userType){
      //fetch all keys in $userPermissions
      $allModules = array_keys(Config::$userPermissions);
      foreach ($allModules as $currentModuleName) {
         if($currentModuleName!=="ln2_transfers" && isset(Config::$actions_aka[$currentModuleName]) && isset(Config::$userPermissions[$currentModuleName]['allowed_groups']) && (in_array($userType, Config::$userPermissions[$currentModuleName]['allowed_groups']))){
            echo "<li><a href='?page=".$currentModuleName."'>".Config::$actions_aka[$currentModuleName]."</a></li>";
         }
         else if($currentModuleName === "ln2_transfers" && in_array($_SESSION['username'], Config::$ln2_transfer_engineers)){
             $_SESSION['user_type'] = "LN2 Engineers";
             echo "<li><a href='?page=".$currentModuleName."'>".Config::$actions_aka[$currentModuleName]."</a></li>";
         }
      }
   }

   /**
    * Validates the user credentials as received from the client
    */
   private function ValidateUser(){
      $username = $_POST['username'];
      $password = $_POST['md5_pass'];
      $unHashedPW = $_POST['password'];
         $adAuth = $this->Dbase->ConfirmUser($username, $unHashedPW);
         if ($adAuth === 0) {
            $this->WhoIsMe();
            $this->RepositoryHomePage();
            return;
         }
         else if($adAuth === 1) {
            $this->Dbase->CreateLogEntry("There was an error while authenticating the user: '$username'.", 'info');
            $this->LoginPage('There was an error while logging in. Please try again or contact the system administrator');
            return;
         }
         else if($adAuth === 4){
            $this->Dbase->CreateLogEntry("'$username' tried to log in while the account was still disabled", 'info');
            $this->LoginPage('Your account has beed disabled. Please contact the system administrator');
            return;
         }
         else if(is_string($adAuth)){

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
      Config::$curUser = "{$_SESSION['surname']} {$_SESSION['onames']}, {$_SESSION['user_type']}";
      echo "<div id='whoisme'><span class='back'>&nbsp;</span><span class='user'>" . Config::$curUser . " | <a href='javascript:;'>My Account</a> | <a href='?page=logout'>Logout</a></span></div>";
      return 0;
   }

   /**
    * Logs out the current user
    */
   private function LogOutCurrentUser(){
      $this->LogOut();
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
