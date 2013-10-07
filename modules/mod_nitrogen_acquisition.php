<?php

/**
 * The main class of the system. All other classes inherit from this main one
 *
 * @category   AVID
 * @package    NAcquisition
 * @author     Kihara Absolomon <a.kihara@cgiar.org>
 * @author     Jason Rogena <j.rogena@cgiar.org>
 * @since      v0.2
 */
class NAcquisition{

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
      $this->Dbase = new DBase("mysql");
      $connStatus = $this->Dbase->InitializeConnection();
      if ($connStatus === 1) {
         die("something went wrong ".$this->Dbase->lastError);
      }
      /*else {
         print_r($this->Dbase->dbcon);
      }*/
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
            /*if (OPTIONS_REQUEST_TYPE == 'ajax')
               die('-1' . OPTIONS_MSSG_INVALID_SESSION);
            else {
               $this->LoginPage(OPTIONS_MSSG_INVALID_SESSION);
               return;
            }*/
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
      elseif (OPTIONS_REQUESTED_MODULE == 'acquisition' && OPTIONS_REQUESTED_SUB_MODULE == 'getProjects')
         $this->getProjects ();
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
      
      $projects = $this->getProjects();
      $chargeCodes = array();
      $chargeCodesWP = array();
      foreach ($projects as $currentP) {
         $chargeCodes[] = $currentP['charge_code'];
         $chargeCodesWP[$currentP['charge_code']] = $currentP['name'];
      }

      $addinfo = ($addinfo != '') ? "<div id='addinfo'>$addinfo</div>" : '';
      echo "<script type='text/javascript' src='" . OPTIONS_COMMON_FOLDER_PATH . "jquery.form.js' /></script>";
      echo "<script type='text/javascript' src='" . OPTIONS_COMMON_FOLDER_PATH . "jquery.flexigrid/flexigrid.pack.js' /></script>";
      echo "<script type='text/javascript' src='" . OPTIONS_COMMON_FOLDER_PATH . "jquery.ui/js/jquery-ui.min.js' /></script>";
      echo "<link rel='stylesheet' type='text/css' href='" . OPTIONS_COMMON_FOLDER_PATH . "jquery.flexigrid/css/flexigrid.pack.css' />";
      echo "<link rel='stylesheet' type='text/css' href='" . OPTIONS_COMMON_FOLDER_PATH . "jquery.ui/css//smoothness/jquery-ui.css' />";
      
      ?>
<div id='home'>
   <h3>Nitrogen Requests</h3>
   <?php echo $addinfo?>
   <form enctype="multipart/form-data" name="upload" method="POST" action="index.php?page=acquisition&do=request" onsubmit="return NAcquisition.submitNewRequest();" >
      <div id="generate">
         <fieldset>
            <legend>Add a Request</legend>
               <table id="mainField">
                  <tr><td class="label">Name</td><td><input type="text" name="user" id="user" value="<?php echo $_SESSION['onames']." ".$_SESSION['surname'];?>"/></td></tr>
                  <tr><td class="label">Date of Request</td><td><input type="text" name="date" id="date" value="<?php echo date("d-m-Y")?>"/></td></tr>
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
                              <tr><td class="label">Project</td><td><input type="text" name="project" id="project" value="" disabled="true" size="50"/></td>
                                 <td class="label">Charge Code</td><td><input type="text" name="chargeCode" id="chargeCode" value=""/></td></tr>
                              <script>
                                 $(function (){
                                    var chargeCodes = <?php echo json_encode($chargeCodes);?>;
                                    var projects = <?php echo json_encode($chargeCodesWP);?>;
                                    for(var i = 0; i < chargeCodes.length; i++) {
                                       if(chargeCodes[i] === null) {
                                          chargeCodes.splice(i, 1);
                                          i--;
                                       }
                                    }
                                    $("#chargeCode").autocomplete({
                                       source: chargeCodes,
                                       minLength: 2,
                                       select: function (event, ui) {
                                          var value = ui.item.value;
                                          $("#project").val(projects[value]);
                                       }
                                    });
                                 });
                              </script>
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
<div id="dialog-modal" title="Set the amount approved" style="display: none;">
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
      <?php
         if(isset($_SESSION['user_type']) && $_SESSION['user_type'] === "Super Administrator") {
            echo "buttons : [{name: 'Set Amount Approved', bclass: 'edit', onpress : NAcquisition.changeAmountApproved}],";
         }
      ?>
      sortorder : 'desc',
      usepager : true,
      title : 'Past Requests',
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
    * Validates the user credentials as received from the client. Validation is soley done using LDAP
    */
   public function ValidateUser() {
      $username = $_POST['username'];
      $password = $_POST['md5_pass'];
      $unHashedPW = $_POST['password'];
      //check if we have the user have specified the credentials
      /*if ($username == '' || $password == '') {
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
         $decryptedPW = $this->decryptRSACypherText($unHashedPW);
         if($decryptedPW !==0){
            $adAuth = $this->ADAuthenticate($username, $decryptedPW);
            if ($adAuth === 0) {
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
               $_SESSION['unhashedPW'] = $decryptedPW;
               $_SESSION['username'] = $username;
            
               $this->HomePage();
               return;
            } else {
               $this->Dbase->CreateLogEntry("AD did not authenticate user: '$username'.", 'info');
               $this->LoginPage($adAuth);
               return;
            }
         }
         else {
            $this->Dbase->CreateLogEntry("Error occured while decrypting password", 'info');
            $this->LoginPage('An error occured while decrypting your password');
            return;
         }
      }*/
      //get his/her data and add them to the session data
      $decryptedPW = $this->decryptRSACypherText($unHashedPW);
      if ($decryptedPW !== 0) {
         $adAuth = $this->Dbase->ADAuthenticate($username, $decryptedPW);
         if ($adAuth === 0) {
            $res = $this->GetCurrentUserDetails($username);
            if ($res == 1) {
               $this->LoginPage('Sorry, There was an error while fetching data from the database. Please try again later');
               return;
            }
            //initialize the session variables
            //$_SESSION['surname'] = $res['sname'];
            //$_SESSION['onames'] = $res['onames'];
            $_SESSION['user_type'] = $res['user_type'];
            $_SESSION['user_id'] = $res['user_id'];
            $_SESSION['password'] = $password;
            $_SESSION['unhashedPW'] = $decryptedPW;
            //$_SESSION['username'] = $username;

            $this->HomePage();
            return;
         }
         else if($adAuth === 1) {
            $this->Dbase->CreateLogEntry("AD did not authenticate user: '$username'.", 'info');
            $this->LoginPage("AD did not authenticate user: '$username'.");
            return;
         }
         else {
            $this->Dbase->CreateLogEntry("AD did not authenticate user: '$username'.", 'info');
            $this->LoginPage($adAuth);
            return;
         }
      } else {
         $this->Dbase->CreateLogEntry("Error occured while decrypting password", 'info');
         $this->LoginPage('An error occured while decrypting your password');
         return;
      }
   }

   /**
    * Fetch the details of the person who is logged in
    *
    * @return  mixed    Returns 1 in case an error ocurred, else it returns an array with the logged in user credentials
    */
   public function GetCurrentUserDetails($username) {
      $query = "select a.id as user_id, a.sname, a.onames, a.login, b.name as user_type from " . Config::$config['session_dbase'] . ".users as a
               inner join " . Config::$config['session_dbase'] . ".user_levels as b on a.user_level=b.id  WHERE a.login=? AND a.allowed=?";

      $result = $this->Dbase->ExecuteQuery($query, array($username,1));
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
      if (OPTIONS_REQUEST_TYPE == 'ajax')
         return;
      //display the credentials of the person who is logged in
      Config::$curUser = "{$_SESSION['surname']} {$_SESSION['onames']}, {$_SESSION['user_type']}";
      echo "<div id='whoisme'>" . Config::$curUser . " | <a href='javascript:;'>My Account</a> | <a href='?page=logout'>Logout</a></div>";
      return 0;
   }
   
   /**
    * Submits Nitrogen Acquisition Request to database
    */
   private function submitAcquisitionRequest() {
      $message = "";
      $userID = $this->addUserIfNotExists($_POST['user']);
      $projectID = $this->getProjectID($_POST['chargeCode']);
      if($userID !==0 && $projectID !== 0){
         $cols = array("user_id","project_id","date","amount_req","added_by");
         $date = DateTime::createFromFormat('d-m-Y',$_POST['date']);
         $colVals = array($userID, $projectID,$date->format("Y-m-d"),$_POST['amount'],$_SESSION['username']);
         $res = $this->Dbase->InsertOnDuplicateUpdate("acquisitions",$cols,$colVals);
         if($res === 0) {
            $message = "Unable to add the last request. Try again later";
         }
      }
      else if($projectID === 0) {
         $message = "Unable to add the last request. Enter a valid charge code";
      }
      else {
         $message = "Unable to add the last request. Try again later";
      }
      $this->HomePage($message);
   }
   
   /**
    * Adds the specified name to the database if it does not already exist
    * @param   string   $name     
    * @return  int      Returns the id of the row with the name or 0 if an error occured
    */
   private function addUserIfNotExists($name) {
      $query = "SELECT id FROM ".Config::$config['dbase'].".users AS a WHERE a.name = ?";
      $result = $this->Dbase->ExecuteQuery($query,array($name));
      if ($result == 1) {
         $this->Dbase->CreateLogEntry("There was an error while fetching data from the database.", 'fatal', true);
         $this->Dbase->lastError = "There was an error while fetching data from the session database.<br />Please try again later.";
         return 0;
      }
      else if(sizeof($result)>0){
         return $result[0]['id'];
      }
      else {
         //$result = $this->Dbase->InsertData("users",array("name"),array($name));
         $result = $this->Dbase->InsertOnDuplicateUpdate("users",array("name"),array($name));
         return $result;
      }
   }
   
   /**
    * Gets the project ID corresponding to the specified charge code
    * @param   string   $chargeCode   The charge code for which the wanted project corresponds to 
    * @return int       Returns the project ID or 0 if and error occures during execution
    */
   private function getProjectID($chargeCode) {
      $query = "SELECT id FROM projects WHERE projects.`charge_code` = ?";
      $result = $this->Dbase->ExecuteQuery($query,array($chargeCode));
      if ($result == 1){
         $this->Dbase->CreateLogEntry("There was an error while fetching data from the database.", 'fatal', true);
         $this->Dbase->lastError = "There was an error while fetching data from the session database.<br />Please try again later.";
         return 0;
      }
      else if(sizeof($result) > 0) {
         return $result[0]['id'];
      }
      else {
         return 0;
      }
   }
   
   /**
    * Fetches the requisitons in the database and formats these the way flexigrid likes it
    */
   private function fetchRequestHistory() {
      //check if search criterial provided
      $criteriaArray = array();
      if($_POST['query'] != "") {
         $criteria = "WHERE {$_POST['qtype']} LIKE '%?%'";
         $criteriaArray[] = $_POST['query'];
         if(!isset($_SESSION['user_type'])||$_SESSION['user_type'] !== "Super Administrator"){
            $criteria = $criteria." AND a.`added_by` = ?";
            $criteriaArray[] = $_SESSION['username'];
         }
      }
      else {
         $criteria = "";
         if(!isset($_SESSION['user_type'])||$_SESSION['user_type'] !== "Super Administrator"){
            $criteria = $criteria."WHERE a.`added_by` = ?";
            $criteriaArray[] = $_SESSION['username'];
         }
      }
      
      $startRow = ($_POST['page'] - 1) * $_POST['rp'];
      $query = "SELECT a.*, b.name AS project, b.`charge_code`, c.name AS username".
              " FROM acquisitions AS a".
              " INNER JOIN projects AS b ON a.`project_id` = b.id".
              " INNER JOIN users AS c ON a.`user_id` = c.id".
              " $criteria".
              " ORDER BY {$_POST['sortname']} {$_POST['sortorder']}";
      //$this->Dbase->query = $query." LIMIT $startRow, {$_POST['rp']}";
      $data = $this->Dbase->ExecuteQuery($query." LIMIT $startRow, {$_POST['rp']}" , $criteriaArray);
      
      //check if any data was fetched
      if($data === 1)
         die (json_encode (array('error' => true)));
      //$this->Dbase->query = $query;
      $dataCount = $this->Dbase->ExecuteQuery($query,$criteriaArray);
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
   
   /**
    * Sets the amount of nitrogen just approved for a requisition
    */
   private function setAmountApproved() {
      if($_SESSION['user_type']==="Super Administrator"){
        $query = "SELECT `amount_appr` FROM acquisitions WHERE id = ?";
        $result = $this->Dbase->ExecuteQuery($query,array($_POST['rowID']));
        if(sizeof($result) === 1 && is_null($result[0]['amount_appr'])) {
           $query = "UPDATE acquisitions SET `amount_appr` = ? WHERE id = ?";
           $this->Dbase->ExecuteQuery($query,array($_POST['amountApproved'],$_POST['rowID']));
           $this->generateInvoice($_POST['rowID']);
        }
      }
   }
   
   /*private function ADAuthenticate($username, $password) {
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
                  return "Connected successfully to the AD server, but cannot perform the search! ";
               }
               $entry1 = ldap_first_entry($ldapConnection, $ldapSr);
               if (!$entry1) {
                  return "Connected successfully to the AD server, but there was an error while searching the AD!";
               }
               $ldapAttributes = ldap_get_attributes($ldapConnection, $entry1);
               return 0;
            } else {
               return "There was an error while binding user '$username' to the AD server!";
            }
         }
      }
   }*/
   
   /**
    * Generates an invoice correspinding to a requisition
    * @param   int   $rowID   ID of the requisition
    */
   private function generateInvoice($rowID){
      $query = "SELECT `a`.*, b.name AS username FROM `acquisitions` AS a INNER JOIN users AS b ON a.`user_id`=b.id WHERE a.id = ?";
      $res = $this->Dbase->ExecuteQuery($query, array($rowID));
      if($res !==1) {
         $date = $res[0]['date'];
         $today = date('d M Y');
         $time = date('h:i A');
         $amount = $res[0]['amount_appr'];
         $unitPrice = $this->getNitrogenPrice();
         $requestedBY = strtoupper($res[0]['username']);
         $name = $res[0]['username'];
         $hash = md5($date.$amount.$unitPrice.$requestedBY);
         $pageName = $hash.".php";
         $ldapUser = $res[0]['added_by'];
         if($unitPrice !== -1) {
            $pageText = "<!DOCTYPE HTML PUBLIC '-//W3C//DTD HTML 4.01 Transitional//EN'>
<html style='color: #333333;'>
   <head>
      <title>Invoice</title>
      <link href='http://fonts.googleapis.com/css?family=Open+Sans:400,700' rel='stylesheet' type='text/css'>
      <style type='text/css'>
         .invoiceTable td, .invoiceTable th {
            border: 1px solid #333333;
         }
      </style>
   </head>
   <body style='font-family:Open Sans,sans-serif'>
      <div style='position: absolute; top: 10px; left: 20px; width: 50px'>
         <img src='../images/WTPlogo.jpg' style='width: 100px; height: 100px;'/>
      </div>
      <div style='position: absolute; top: 10px; left: 220px; width: 480px'>
         <h1 style='position: absolute; top: 20px; left: 120px;'>Azizi Biorepository</h1>
      </div>
      <div style='position: absolute; top: 95px; left: 340px; width 600px;'>
         <p style='font-size: 11px; font-style: italic;'>Ensuring proper sample storage with high quality metadata</p>
      </div>
      <div style='position: absolute; top: 40px; left: 700px; text-align: right; width: 300px;'>
         <p style='font-size: 14px;'>Invoice: #".$rowID."<br/>
         ".$today.", ".$time."</p>
      </div>
      
      <div style='position: absolute; top: 300px; left: 120px; width: 800px;'>
         <table cellpadding='1' style='border: 1px solid #333333; border-collapse: collapse;' class='invoiceTable'>
            <tr style='background-color: #b0b6f1;'>
               <th width='550' height='40' style='text-align: left; padding-left: 20px;'>Description</th><th width='250'>Quantity</th><th width='250'>Unit Price</th><th width='250'>Net Price</th>
            </tr>
            <tr style='background-color: #e0e1ec;'>
               <td style='padding-left: 35px;' height='30'>Liquid Nitrogen</td><td style='text-align: center;'>".$amount." (KGs)</td><td style='text-align: center;'>".$unitPrice."</td><td style='text-align: center;'>".($unitPrice*$amount)." USD </td>
            </tr>
            <tr style='font-weight:bold;'><td height='40' colspan='3' style='text-align: right; padding-right: 20px;'>Total Amount</td><td style='text-align: center;'>".($unitPrice*$amount)." USD </td></tr>
         </table>
      </div>
      <div style='position: absolute; top: 1370px; left: 700px; text-align: right; width: 300px;'>
         <p>Page 1 of 1</p>
      </div>
   </body>
</html>";
            file_put_contents("./generated_pages/" . $pageName, $pageText);
            $pdfName = "Azizi Invoice ".$rowID;
            shell_exec(Config::$wkhtmltopdf . " http://" . $_SERVER['HTTP_HOST'] . Config::$baseURI . "generated_pages/" . $pageName . " '/tmp/" . $pdfName . ".pdf'");
            unlink("./generated_pages/" . $pageName);
            //copy("/tmp/".$hash.".pdf", "./generated_pages/".$hash.".pdf");
            //unlink("/tmp/".$hash.".pdf");
            $this->sendEmail("'/tmp/" . $pdfName . ".pdf'", $ldapUser, $date, $name);
         }
         
      }
   }
   
   /**
    * Sends email to specified LDAP user
    * 
    * @param   string   $pdfURL     Location where the attachement has been cached
    * @param   string   $ldapUser   LDAP username of the user to be sent to an email
    * @param   string   $date       The date the requisition waas made
    * @param   string   $name       Name of the reciever
    */
   private function sendEmail($pdfURL, $ldapUser, $date, $name) {
      $reciever = $this->getEmailAddress($ldapUser);
      $cc = Config::$managerEmail;
      $subject = "Invoice for Nitrogen requested on ".$date;
      $message = "Hi ".$name.",\n\nYour request for Nitrogen has been approved. An invoice of the acquisition is attached to this email. \n\nThis email has been auto-generated, please do not reply to it. For any additional information/clarification, please get in touch with Sammy Kemei, s.kemei@cgiar.org";
      shell_exec('echo "'.$message.'"|'.Config::$muttBinary.' -F '.Config::$muttConfig.' -s "'.$subject.'" -c '.$cc.' -a '.$pdfURL.' -- '.$reciever);
      unlink($pdfURL);
   }
   
   /**
    * Gets the email address corresponding to the LDAP username specified
    * 
    * @param   string   $username   LDAP username corresponding to the email address to be searched
    * 
    * @return  mixed    Returns 0 if an error occures durind execution or the email address corresponding to the email address
    */
   private function getEmailAddress($username) {
      $ldapConnection = ldap_connect("ilrikead01.ilri.cgiarad.org");
      if (!$ldapConnection) {
         return 0;
      } else {
         $ldapConnection = ldap_connect("ilrikead01.ilri.cgiarad.org");
         if (!$ldapConnection)
            return 0;
         else {
            if (ldap_bind($ldapConnection, $_SESSION['username']."@ilri.cgiarad.org", $_SESSION['unhashedPW'])) {
               ldap_set_option($ldapConnection, LDAP_OPT_REFERRALS, 0);
               ldap_set_option($ldapConnection, LDAP_OPT_PROTOCOL_VERSION, 3);
               $ldapSr = ldap_search($ldapConnection, 'ou=ILRI Kenya,dc=ilri,dc=cgiarad,dc=org', "(sAMAccountName=$username)", array('mail'));
               if (!$ldapSr) {
                  $this->CreateLogEntry('', 'fatal');
                  return 0;
               }
               $entry1 = ldap_first_entry($ldapConnection, $ldapSr);
               if (!$entry1) {
                  return 0;
               }
               $ldapAttributes = ldap_get_attributes($ldapConnection, $entry1);
               return $ldapAttributes['mail'][0];
            } else {
               return 0;
            }
         }
      }
   }
   
   /**
    * Decrypt the specified cyphertext using the systems private key. Decryption is done using RSA
    * 
    * @param   string      $cypherText    The cypher text to be decrypted
    * 
    * @return  string|int  Returns 0 if an error occures or the decrypted text
    */
   private function decryptRSACypherText($cypherText) {
      $privateKey = openssl_pkey_get_private(Config::$rsaPrivKey);
      $result = "";
      if(openssl_private_decrypt(base64_decode($cypherText), $result, $privateKey)) {
         return $result;
      }
      else {
         return 0;
      }
   }
   
   /**
    * Gets the price of nitrogen from the database that is valid for today
    * 
    * @return  float    Returns -1 if an error occures or the price of nitrogen
    */
   private function getNitrogenPrice() {
      $query = "SELECT price FROM `ln2_prices` WHERE `start_date` <= CURDATE() AND `end_date` >= CURDATE()";
      $result = $this->Dbase->ExecuteQuery($query);
      if($result!==1) {
         if(sizeof($result)===1){
            return $result[0]['price'];
         }
         else {
            return -1;
         }
      }
      else {
         return -1;
      }
   }
   
   /**
    * Returns all the projects in the database in a associative array
    * 
    * @return  assoc array   The fetched projects 
    */
   private function  getProjects() {
      $query = "SELECT * FROM projects";
      $result = $this->Dbase->ExecuteQuery($query);
      return $result;
   }
}
?>
