<?php

/**
 * The main class of the system. All other classes inherit from this main one
 *
 * @category   AVID
 * @package    NAcquisition
 * @author     Kihara Absolomon <a.kihara@cgiar.org>
 * @since      v0.2
 */
class NAcquisition extends Dbase {

   /**
    * @var Object An object with the database functions and properties. Implemented here to avoid having a million & 1 database connections
    */
   public $Dbase;
   public $addinfo;
   public $footerLinks = '';
   private $labelPrintingError;

   /**
    * @var  string   Just a string to show who is logged in
    */
   public $whoisme = '';

   public function __construct() {
      $this->Dbase = new DBase();
      $this->Dbase->InitializeConnection();
      if ($this->Dbase->dbcon->connect_error || (isset($this->Dbase->dbcon->errno) && $this->Dbase->dbcon->errno != 0)) {
         die('Something wicked happened when connecting to the dbase.');
      }
      $this->Dbase->InitializeLogs();
   }

   public function TrafficController() {
      //when we are normally browsing, check that we have the right credentials
//      echo '<pre>'. print_r($this->Dbase, true) .'</pre>';
//      $this->Dbase->CreateLogEntry("Cookies: \n".print_r($this->Dbase, true), 'debug');

      if (OPTIONS_REQUESTED_MODULE != 'login' && !Config::$downloadFile) {
         //we hope that we have still have the right credentials
         $this->Dbase->ManageSession();
         $this->whoisme = "{$_SESSION['surname']} {$_SESSION['onames']}, {$_SESSION['user_level']}";
//      echo '<pre>'. print_r($this, true) .'</pre>';
      }

      if (OPTIONS_REQUESTED_MODULE == 'logout') {
         $this->Dbase->LogOut();
         $this->Dbase->session['restart'] = true;
      }

      if (!Config::$downloadFile && ($this->Dbase->session['error'] || $this->Dbase->session['timeout'])) {
         if (OPTIONS_REQUEST_TYPE == 'normal') {
            $this->LoginPage($this->Dbase->session['message'], $_SESSION['username']);
            return;
         } elseif (OPTIONS_REQUEST_TYPE == 'ajax')
            die('-1' . $this->Dbase->session['message']);
      }

      if (!isset($_SESSION['user_id']) && !in_array(OPTIONS_REQUESTED_MODULE, array('login', 'logout'))) {
         if ($this->Dbase->session['no_session']) {
            //we dont have a active session and we are not requesting for the login module
            if (in_array(OPTIONS_REQUESTED_MODULE, array('upgrade'))) {/* we are cool for now, let the execution flow continue */
            } else {
               //we are trying to access the system resources without logging in first! This is bullshit!
               if (OPTIONS_REQUEST_TYPE == 'ajax')
                  die('-1' . OPTIONS_MSSG_NO_SESSION);
               else {
                  $this->LoginPage();
                  return;
               }
            }
         } else {
            if (OPTIONS_REQUEST_TYPE == 'ajax')
               die('-1' . OPTIONS_MSSG_INVALID_SESSION);
            else {
               $this->LoginPage(OPTIONS_MSSG_INVALID_SESSION);
               return;
            }
         }
      }

      if (OPTIONS_REQUESTED_MODULE == '' && OPTIONS_REQUESTED_SUB_MODULE == '')
         $this->HomePage();
      elseif (OPTIONS_REQUESTED_MODULE == 'login')
         $this->ValidateUser();
      elseif (OPTIONS_REQUESTED_MODULE == 'acquisition' && OPTIONS_REQUESTED_SUB_MODULE == 'request') 
         $this->submitAcquisitionRequest ();
      elseif (OPTIONS_REQUESTED_MODULE == 'acquisition' && OPTIONS_REQUESTED_SUB_MODULE == 'fetch') 
         $this->fetchRequestHistory ();
      elseif (OPTIONS_REQUESTED_MODULE == 'acquisition' && OPTIONS_REQUESTED_SUB_MODULE == 'setAmountApproved')
         $this->setAmountApproved ();
      elseif (OPTIONS_REQUESTED_MODULE == 'logout') {
         $this->Dbase->LogOut();
         $this->LoginPage();
      }
   }

   public function ConfirmUserIsAuntheticated() {
      return true;
   }

   public function LoginPage($addinfo = '', $username = '') {
      $this->footerLinks = '';
      $count = (!isset($_POST['count'])) ? 0 : $_POST['count'] + 1;
      $hidden = "<input type='hidden' name='count' value='$count' />";
      if ($addinfo == '')
         $addinfo = 'Please enter your username and password to access the Label Printing System.';
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
         <form action="?page=login" name='login_form' method='POST'>
            <div id='login_page'>
               <div class="top">Login</div>
               <div id='addinfo'><?php echo $addinfo; ?></div>
               <table>
                  <tr><td>Username</td><td><input type="text" name="username" value="<?php echo $username; ?>" size="15"/></td></tr>
                  <tr><td>Password</td><td><input type="password" name="password" size="15" /></td></tr>
                  <input type="hidden" name="md5_pass" />
               </table>
               <div class='buttons'><input type="submit" name="login" value="Log In" />   <input type="reset" value="Cancel" /></div>
            </div>
      <?php echo $hidden; ?>
         </form>
      </div>
      <?php
      if (OPTIONS_REQUEST_TYPE == 'normal') {
         echo "<script type='text/javascript'>
                 $('[name=login]').bind('click', NAcquisition.submitLogin);
                 $('[name=username]').focus();
             </script>";
      }
   }

   /**
    * Create the home page for generating the labels
    */
   private function HomePage($addinfo = '') {
      $this->WhoIsMe();

      $addinfo = ($addinfo != '') ? "<div id='addinfo'>$addinfo</div>" : '';
      echo "<script type='text/javascript' src='" . OPTIONS_COMMON_FOLDER_PATH . "jquery.form.js' /></script>";
      echo "<script type='text/javascript' src='" . OPTIONS_COMMON_FOLDER_PATH . "jquery.flexigrid/flexigrid.pack.js' /></script>";
      echo "<script type='text/javascript' src='" . OPTIONS_COMMON_FOLDER_PATH . "jquery.ui/js/jquery-ui.min.js' /></script>";
      echo "<link rel='stylesheet' type='text/css' href='" . OPTIONS_COMMON_FOLDER_PATH . "jquery.flexigrid/css/flexigrid.pack.css' />";
      echo "<link rel='stylesheet' type='text/css' href='" . OPTIONS_COMMON_FOLDER_PATH . "jquery.ui/css//smoothness/jquery-ui.css' />";
      
      ?>
<div id='home'>
   <h3>Nitrogen Acquisition Requests</h3>
   <?php echo $addinfo?>
   <form enctype="multipart/form-data" name="upload" method="POST" action="index.php?page=acquisition&do=request" onsubmit="return NAcquisition.submitNewRequest();" >
      <div id="generate">
         <fieldset>
            <legend>Add a Request</legend>
               <table id="mainField">
                  <tr><td class="label">Name</td><td><input type="text" name="user" id="user" value=""/></td></tr>
                  <tr><td class="label">Date of Request</td><td><input type="text" name="date" id="date" value=""/></td></tr>
                  <script>
                     $(function() {
                        $( "#date" ).datepicker({maxDate: '0', dateFormat: 'dd-mm-yy'});
                     });
                  </script>
                  <tr><td class="label">Amount of Nitrogen (KGs)</td><td><input type="text" name="amount" id="amount" value="" size="4"/></td></tr>
                  <tr><td colspan="2">
                        <fieldset>
                           <legend>Project</legend>
                           <table>
                              <tr><td class="label">Project</td><td><input type="text" name="project" id="project" value=""/></td>
                                 <td class="label">Charge Code</td><td><input type="text" name="chargeCode" id="chargeCode" value=""/></td></tr>
                           </table>
                        </fieldset>
                  </td></tr>
                  <tr><td colspan="2">
                        <input type="submit" value="Request" name="submitButton" id="submitButton"/>
                        <input type="reset" value="Cancel" name="cancelButton" id="cancelButton"/>
                  </td></tr>
               </table>
         </fieldset>
      </div>
   </form>
   <div id="past_requests">&nbsp;</div>
</div>
<div id="dialog-modal" title="Change the amount approved" style="display: none;">
   <table><tr><td>Amount Approved : </td><td><input type="text" name = "newAmountApproved" id="newAmountApproved" size="4"></td></tr></table>
</div>
<script type="text/javascript">
   $("#past_requests").flexigrid({
      url: "mod_ajax.php?page=acquisition&do=fetch",
      dataType: 'json',
      colModel : [
         {display: 'Date', name: 'date', width: 100, sortable: true, align: 'center'},
         {display: 'Requester', name: 'username', width: 300, sortable: true, align: 'left'},
         {display: 'Amount Requested', name: 'amount_req', width: 150, sortable: false, align: 'center'},
         {display: 'Amount Approved', name: 'amount_appr', width: 150, sortable: false, align: 'center'},
         {display: 'Charge Code', name: 'charge_code', width: 100, sortable: false, align: 'center'}
      ],
      searchitems : [
         {display: 'Requester', name : 'username'},
         {display: 'Project', name : 'project'},
         {display: 'Charge Code', name : 'charge_code'}
      ],
      sortname : 'date',
      buttons : [
         {name: 'Change Amount Approved', bclass: 'edit', onpress : NAcquisition.changeAmountApproved}
      ],
      sortorder : 'desc',
      usepager : true,
      title : 'Past Transfers',
      useRp : true,
      rp : 10,
      showTableToggleBtn: false,
      rpOptions: [10, 20, 50], //allowed per-page values
      width: 900,
      height: 260,
      singleSelect: true
   });
</script>
      <?php
   }

   /**
    * Validates the user credentials as received from the client
    */
   public function ValidateUser() {
      $username = $_POST['username'];
      $password = $_POST['md5_pass'];
      $unHashedPW = $_POST['password'];
      //check if we have the user have specified the credentials
      if ($username == '' || $password == '') {
         if ($username == '')
            $this->LoginPage("Incorrect login credentials. Please specify a username to log in to the system.");
         elseif ($password == '')
            $this->LoginPage('Incorrect login credentials. Please specify a password to log in to the system.', $username);
         return;
      }
      //now check that the specified username and password are actually correct
      //at this case we assume that we md5 our password when it is being sent from the client side
      $res = $this->Dbase->ConfirmUser($username, $password);
      if ($res == 1) {
         $this->LoginPage('Error! There was an error while authenticating the user.');
         return;
      } elseif ($res == 3) {
         $this->Dbase->CreateLogEntry("No account with the username '$username'.", 'info');
         $this->LoginPage("Sorry, there is no account with '$username' as the username.<br />Please log in to access the system.");
         return;
      } elseif ($res == 4) {
         $this->Dbase->CreateLogEntry("Disabled account with the username '$username'.", 'info');
         $this->LoginPage("Sorry, the account with '$username' as the username is disabled.<br />" . Config::$contact);
         return;
      } elseif ($res == 2) {
         $this->Dbase->CreateLogEntry("Login failed for user: '$username'.", 'info');
         $this->LoginPage('Sorry, the password that you have entered is not correct.<br />Please log in to access the system.');
         return;
      } elseif ($res == 0) {   //this is a valid user
         //get his/her data and add them to the session data
         $adAuth = $this->ADAuthenticate($username, $unHashedPW);
         if($adAuth === 0 ) {
            $res = $this->GetCurrentUserDetails();
            if ($res == 1) {
               $this->LoginPage('Sorry, There was an error while fetching data from the database. Please try again later');
               return;
            }
            //initialize the session variables
            $_SESSION['surname'] = $res['sname'];
            $_SESSION['onames'] = $res['onames'];
            $_SESSION['user_type'] = $res['user_type'];
            $_SESSION['user_id'] = $res['user_id'];
            $_SESSION['password'] = $password;
            $_SESSION['username'] = $username;
            $this->HomePage();
            return;
         }
         else {
            $this->Dbase->CreateLogEntry("AD did not authenticate user: '$username'.", 'info');
            $this->LoginPage($adAuth);
            return;
         }
      }
   }

   /**
    * Fetch the details of the person who is logged in
    *
    * @return  mixed    Returns 1 in case an error ocurred, else it returns an array with the logged in user credentials
    */
   public function GetCurrentUserDetails() {
      $this->Dbase->query = "select a.id as user_id, a.sname, a.onames, a.login, b.name as user_type from " . Config::$config['session_dbase'] . ".users as a
               inner join " . Config::$config['session_dbase'] . ".user_levels as b on a.user_level=b.id  WHERE a.id={$this->Dbase->currentUserId} AND a.allowed=1";

      $result = $this->Dbase->ExecuteQuery(MYSQLI_ASSOC);
      if ($result == 1) {
         $this->Dbase->CreateLogEntry("There was an error while fetching data from the database.", 'fatal', true);
         $this->Dbase->lastError = "There was an error while fetching data from the session database.<br />Please try again later.";
         return 1;
      }

      return $result[0];
   }

   /**
    * Confirms the credentials of the person who is logged in and then displays a link on top of the person who is logged in
    *
    * @return integer   Returns 1 incase of an error or the person has wrong credentials, else returns 0
    */
   public function WhoIsMe() {
      if (OPTIONS_REQUEST_TYPE == 'ajax' || in_array(OPTIONS_REQUESTED_MODULE, array('logout')) || $this->Dbase->session['error'] || $this->Dbase->session['timeout'] || !isset($_SESSION['user_id'])) {
         $this->footerLinks = '';      //clear the footer links
//         echo '<pre>'. print_r($_SESSION, true) .'</pre>';
         return 0;
      }

      //before displaying the current user, lets confirm that the user credentials are ok and the session is not expired
      $res = $this->Dbase->ConfirmUser($_SESSION['username'], $_SESSION['password']);
      if ($res == 1) {
         if (OPTIONS_REQUEST_TYPE == 'ajax')
            die('-1Error! There was an error while authenticating the user.');
         else
            $this->LoginPage('Error! There was an error while authenticating the user.');
         return 1;
      }
      elseif ($res == 2) {
         if (OPTIONS_REQUEST_TYPE == 'ajax')
            die('-1Sorry, You do not have enough privileges to access the system.');
         $this->LoginPage('Sorry, You do not have enough privileges to access the system.');
         return 1;
      }
      if (OPTIONS_REQUEST_TYPE == 'ajax')
         return;
      //display the credentials of the person who is logged in
      Config::$curUser = "{$_SESSION['surname']} {$_SESSION['onames']}, {$_SESSION['user_type']}";
      echo "<div id='whoisme'>" . Config::$curUser . " | <a href='javascript:;'>My Account</a> | <a href='?page=logout'>Logout</a></div>";
      return 0;
   }
   
   private function submitAcquisitionRequest() {
      $message = "";
      $userID = $this->addUserIfNotExists($_POST['user']);
      $projectID = $this->addProjectIfNotExists($_POST['project'], $_POST['chargeCode']);
      if($userID !==0 && $projectID !== 0){
         $cols = array("user_id","project_id","date","amount_req","added_by");
         $date = DateTime::createFromFormat('d-m-Y',$_POST['date']);
         $colVals = array($userID, $projectID,$date->format("Y-m-d"),$_POST['amount'],$_SESSION['username']);
         $res = $this->Dbase->InsertData("acquisitions",$cols,$colVals);
         if($res === 0) {
            $message = "Unable to add the last request. Try again later";
         }
      }
      else {
         $message = "Unable to add the last request. Try again later";
      }
      $this->HomePage($message);
   }
   
   private function addUserIfNotExists($name) {
      $this->Dbase->query = "SELECT id FROM ".Config::$config['dbase'].".users AS a WHERE a.name = '$name'";
      $result = $this->Dbase->ExecuteQuery(MYSQLI_ASSOC);
      if ($result == 1) {
         $this->Dbase->CreateLogEntry("There was an error while fetching data from the database.", 'fatal', true);
         $this->Dbase->lastError = "There was an error while fetching data from the session database.<br />Please try again later.";
         return 0;
      }
      else if(sizeof($result)>0){
         return $result[0]['id'];
      }
      else {
         $result = $this->Dbase->InsertData("users",array("name"),array($name));
         return $result;
      }
   }
   
   private function addProjectIfNotExists($name, $chargeCode) {
      $this->Dbase->query = "SELECT id FROM projects WHERE projects.`charge_code` = '$chargeCode'";
      $result = $this->Dbase->ExecuteQuery(MYSQLI_ASSOC);
      if ($result == 1){
         $this->Dbase->CreateLogEntry("There was an error while fetching data from the database.", 'fatal', true);
         $this->Dbase->lastError = "There was an error while fetching data from the session database.<br />Please try again later.";
         return 0;
      }
      else if(sizeof($result) > 0) {
         return $result[0]['id'];
      }
      else {
         $result = $this->Dbase->InsertData("projects",array("name","charge_code"),array($name,$chargeCode));
         return $result;
      }
   }
   
   private function fetchRequestHistory() {
      //check if search criterial provided
      if($_POST['query'] != "") {
         $criteria = "WHERE {$_POST['qtype']} LIKE '%{$_POST['query']}%";
         if($_SESSION['user_type'] !== "Super Administrator"){
            $criteria = $criteria." AND a.`added_by` = '{$_SESSION['username']}'";
         }
      }
      else {
         $criteria = "";
         if($_SESSION['user_type'] !== "Super Administrator"){
            $criteria = $criteria."WHERE a.`added_by` = '{$_SESSION['username']}'";
         }
      }
      
      $startRow = ($_POST['page'] - 1) * $_POST['rp'];
      $query = "SELECT a.*, b.name AS project, b.`charge_code`, c.name AS username".
              " FROM acquisitions AS a".
              " INNER JOIN projects AS b ON a.`project_id` = b.id".
              " INNER JOIN users AS c ON a.`user_id` = c.id".
              " $criteria".
              " ORDER BY {$_POST['sortname']} {$_POST['sortorder']}";
      $this->Dbase->query = $query." LIMIT $startRow, {$_POST['rp']}";
      $data = $this->Dbase->ExecuteQuery(MYSQLI_ASSOC);
      
      //check if any data was fetched
      if($data === 1)
         die (json_encode (array('error' => true)));
      $this->Dbase->query = $query;
      $dataCount = $this->Dbase->ExecuteQuery();
      if($dataCount === 1) 
         die (json_encode (array('error' => true)));
      else 
         $dataCount = sizeof ($dataCount);
      
      //reformat rows fetched from first query
      $rows = array();
      foreach ($data as $row) {
         $rows[] = array("id" => $row['id'], "cell" => array("date" => $row['date'],"username" => $row['username'],"amount_req" => $row["amount_req"], "amount_appr" => $row["amount_appr"], "charge_code" => $row["charge_code"]));
      }
      $response = array(
          'total' => $dataCount,
          'page' => $_POST['page'],
          'rows' => $rows
      );
      
      die(json_encode($response));
   }
   
   private function setAmountApproved() {
      //$this->Dbase->query = "UPDATE `acquisitions` SET `acquisitions`.`acquisitions` = {$_POST['amountApproved']} WHERE `acquisitions`.`id` = {$_POST['rowID']}";
      //$this->Dbase->ExecuteQuery(MYSQLI_ASSOC);
      if($_SESSION['user_type']==="Super Administrator"){
         $this->Dbase->UpdateRecords("acquisitions",array("amount_appr"),array($_POST['amountApproved']),"id",$_POST['rowID']);
      }
   }
   
   private function ADAuthenticate($username, $password) {
      $ldapConnection = ldap_connect("ilrikead01.ilri.cgiarad.org");
      if (!$ldapConnection) {
         return "Could not connect to the AD server!";
      } else {
         $ldapConnection = ldap_connect("ilrikead01.ilri.cgiarad.org");
         if (!$ldapConnection)
            return "Could not connect to the LDAP host";
         else {
            if (ldap_bind($ldapConnection, "$username@ilri.cgiarad.org", $password)) {
               ldap_set_option($ldapConnection, LDAP_OPT_REFERRALS, 0);
               ldap_set_option($ldapConnection, LDAP_OPT_PROTOCOL_VERSION, 3);
               $ldapSr = ldap_search($ldapConnection, 'ou=ILRI Kenya,dc=ilri,dc=cgiarad,dc=org', "(sAMAccountName=$username)", array('sn', 'givenName', 'title'));
               if (!$ldapSr) {
                  $this->CreateLogEntry('', 'fatal');
                  return "Connected successfully to the AD server, but cannot perform the search!";
               }
               $entry1 = ldap_first_entry($ldapConnection, $ldapSr);
               if (!$entry1) {
                  return "Connected successfully to the AD server, but there was an error while searching the AD!";
               }
               $ldapAttributes = ldap_get_attributes($ldapConnection, $entry1);
               print_r($ldapAttributes["title"]);
               return 0;
            } else {
               return "There was an error while binding user '$username' to the AD server!";
            }
         }
      }
   }
}
?>
