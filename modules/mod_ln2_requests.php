<?php
/**
 * The main class of the system. All other classes inherit from this main one
 *
 * @category   AVID
 * @package    LN2 Requests
 * @author     Kihara Absolomon <a.kihara@cgiar.org>
 * @author     Jason Rogena <j.rogena@cgiar.org>
 * @since      v0.2
 */

class Ln2Requests extends Repository{

   public $Dbase;

   public function __construct($Dbase) {
      $this->Dbase = $Dbase;
   }

   public function TrafficController() {

      if(OPTIONS_REQUEST_TYPE == 'normal'){
         echo "<script type='text/javascript' src='js/ln2_requests.js'></script>";
         echo "<script type='text/javascript' src='" . OPTIONS_COMMON_FOLDER_PATH . "jquery.form.js' /></script>";
         echo "<script type='text/javascript' src='" . OPTIONS_COMMON_FOLDER_PATH . "jquery.flexigrid/flexigrid.pack.js' /></script>";
         echo "<script type='text/javascript' src='" . OPTIONS_COMMON_FOLDER_PATH . "jquery.ui/js/jquery-ui.min.js' /></script>";
         echo "<link rel='stylesheet' type='text/css' href='" . OPTIONS_COMMON_FOLDER_PATH . "jquery.flexigrid/css/flexigrid.pack.css' />";
         echo "<link rel='stylesheet' type='text/css' href='" . OPTIONS_COMMON_FOLDER_PATH . "jquery.ui/css//smoothness/jquery-ui.css' />";
      }

      if (OPTIONS_REQUESTED_SUB_MODULE == '') $this->HomePage();
      if (OPTIONS_REQUESTED_SUB_MODULE == 'request') $this->submitAcquisitionRequest ();
      elseif (OPTIONS_REQUESTED_SUB_MODULE == 'fetch') $this->fetchRequestHistory ();
      elseif (OPTIONS_REQUESTED_SUB_MODULE == 'setAmountApproved') $this->setAmountApproved ();
      elseif (OPTIONS_REQUESTED_SUB_MODULE == 'getProjects') $this->getProjects ();
   }

   /**
    * Create the home page for generating the labels
    */
   private function HomePage($addinfo = '') {
      $projects = $this->getProjects();
      if($projects == 1){
         $this->RepositoryHomePage("There was an error while fetching data from the database.");
         return;
      }
      $chargeCodes = array();
      $chargeCodesWP = array();
      foreach ($projects as $currentP) {
         $chargeCodes[] = $currentP['charge_code'];
         $chargeCodesWP[$currentP['charge_code']] = $currentP['name'];
      }
      $addinfo = ($addinfo != '') ? "<div id='addinfo'>$addinfo</div>" : '';

      ?>
<div id='home'>
   <h3>Nitrogen Requests</h3>
   <?php echo $addinfo?>
   <form enctype="multipart/form-data" name="upload" method="POST" action="index.php?page=ln2_requests&do=request" onsubmit="return Ln2Requests.submitNewRequest();" >
      <div id="generate">
         <fieldset>
            <legend>Add a Request</legend>
               <table id="mainField">
                  <tr><td class="label">Name</td><td><input type="text" name="user" id="user" disabled="true" value="<?php echo $_SESSION['onames']." ".$_SESSION['surname'];?>"/></td></tr>
                  <tr><td class="label">Date of Request</td><td><input type="text" name="date" id="date" value="<?php echo date("d-m-Y")?>"/></td></tr>
                  <script>
                     $(function() {
                        $( "#date" ).datepicker({maxDate: '0', dateFormat: 'dd-mm-yy'});
                     });
                  </script>
                  <tr><td class="label">Amount of Nitrogen (Litres)</td><td><input type="text" name="amount" id="amount" value="" size="4"/></td></tr>
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
   $('#whoisme .back').html('<a href=\'?page=home\'>Back</a>');
   $("#past_requests").flexigrid({
      url: "mod_ajax.php?page=ln2_requests&do=fetch",
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
         if(isset($_SESSION['username']) && in_array($_SESSION['username'],Config::$ln2_request_managers)) {
            echo "buttons : [{name: 'Set Amount Approved', bclass: 'edit', onpress : Ln2Requests.changeAmountApproved}],";
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
    * Submits Nitrogen Acquisition Request to database
    */
   private function submitAcquisitionRequest() {
      $message = "";
      //$userID = $this->addUserIfNotExists($_POST['user']);
      $projectID = $this->getProjectID($_POST['chargeCode']);
      if($projectID !== 0){
         $cols = array("project_id","date","amount_req","added_by","ldap_name");
         $ldapName = $this->Dbase->getUserFullName($_SESSION['username']);
         $date = DateTime::createFromFormat('d-m-Y',$_POST['date']);
         $colVals = array($projectID,$date->format("Y-m-d"),$_POST['amount'],$_SESSION['username'],$ldapName);
         if($ldapName !== 0){
            $res = $this->Dbase->InsertOnDuplicateUpdate("ln2_acquisitions",$cols,$colVals);
            if($res === 0) {
               $message = "Unable to add the last request. Try again later";
            }
            else{
                $this->sendRequestEmail($ldapName, $_POST['amount']);
            }
         }
         else {
            $message = "Unable to add the last request. Requests can only be made by valid ILRI Users";
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
    * Gets the project ID corresponding to the specified charge code
    * @param   string   $chargeCode   The charge code for which the wanted project corresponds to
    * @return int       Returns the project ID or 0 if and error occures during execution
    */
   private function getProjectID($chargeCode) {
      $query = "SELECT id FROM ln2_chargecodes WHERE charge_code = ?";
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
         if(!in_array($_SESSION['username'],Config::$ln2_request_managers)) {
            $criteria = $criteria." AND a.`added_by` = ?";
            $criteriaArray[] = $_SESSION['username'];
         }
      }
      else {
         $criteria = "";
         if(!in_array($_SESSION['username'],Config::$ln2_request_managers)) {
            $criteria = $criteria."WHERE a.`added_by` = ?";
            $criteriaArray[] = $_SESSION['username'];
         }
      }

      $startRow = ($_POST['page'] - 1) * $_POST['rp'];
      $query = "SELECT a.*, b.name AS project, b.`charge_code`".
              " FROM ln2_acquisitions AS a".
              " INNER JOIN ln2_chargecodes AS b ON a.`project_id` = b.id".
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
         $rows[] = array("id" => $row['id'], "cell" => array("date" => $row['date'],"username" => $row['ldap_name'],"amount_req" => $row["amount_req"], "amount_appr" => $row["amount_appr"], "charge_code" => $row["charge_code"]));
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
      if(in_array($_SESSION['username'],Config::$ln2_request_managers)) {
        $query = "SELECT `amount_appr` FROM ln2_acquisitions WHERE id = ?";
        $result = $this->Dbase->ExecuteQuery($query,array($_POST['rowID']));
        if(sizeof($result) === 1 && is_null($result[0]['amount_appr'])) {
           $query = "UPDATE ln2_acquisitions SET `amount_appr` = ?, `apprvd_by` = ? WHERE id = ?";
           $this->Dbase->ExecuteQuery($query,array($_POST['amountApproved'],$_SESSION['username'],$_POST['rowID']));
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
      $query = "SELECT `a`.*, c.`charge_code` FROM `ln2_acquisitions` AS a INNER JOIN `ln2_chargecodes` AS c ON a.`project_id` = c.id WHERE a.id = ?";
      $res = $this->Dbase->ExecuteQuery($query, array($rowID));
      if($res !==1) {
         $date = $res[0]['date'];
         $today = date('d M Y');
         $time = date('h:i A');
         $amount = $res[0]['amount_appr'];
         $unitPrice = $this->getNitrogenPrice();
         $name = $res[0]['ldap_name'];
         $requestedBY = strtoupper($name);
         $hash = md5($date.$amount.$unitPrice.$requestedBY);
         $pageName = $hash.".php";
         $ldapUser = $res[0]['added_by'];
         $chargeCode = $res[0]['charge_code'];
         $email = $this->Dbase->getEmailAddress($ldapUser);
         $userTitle = $this->Dbase->getUserTitle($ldapUser);
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

      <div style='position: absolute; top: 180px; left: 80px;'>
         <p>To ".$name.",<br />".$email.",<br />".$userTitle.".</p>
      </div>

      <div style='position: absolute; top: 310px; left: 100px; width: 800px;'>
         <table cellpadding='1' style='border: 1px solid #333333; border-collapse: collapse;' class='invoiceTable'>
            <tr style='background-color: #b0b6f1;'>
               <th width='550' height='40' style='text-align: left; padding-left: 20px;'>Description</th><th width='220'>Charge code</th><th width='220'>Quantity</th><th width='220'>Unit Price</th><th width='220'>Net Price</th>
            </tr>
            <tr style='background-color: #e0e1ec;'>
               <td style='padding-left: 35px;' height='30'>Liquid Nitrogen</td><td style='text-align: center;'>".$chargeCode."</td><td style='text-align: center;'>".$amount." (Litres)</td><td style='text-align: center;'>".$unitPrice."</td><td style='text-align: center;'>".($unitPrice*$amount)." USD </td>
            </tr>
            <tr style='font-weight:bold;'><td height='40' colspan='4' style='text-align: right; padding-right: 20px;'>Total Amount</td><td style='text-align: center;'>".($unitPrice*$amount)." USD </td></tr>
         </table>
      </div>
      <div style='position: absolute; top: 1370px; left: 700px; text-align: right; width: 300px;'>
         <p>Page 1 of 1</p>
      </div>
   </body>
</html>";
            if(!file_exists('./generated_pages')){
               mkdir('./generated_pages', 0777, true);
            }
            file_put_contents("./generated_pages/" . $pageName, $pageText);
            $pdfName = "Azizi Invoice ".$rowID;
            shell_exec(Config::$xvfb ." ". Config::$wkhtmltopdf . " http://" . $_SERVER['HTTP_HOST'] . Config::$baseURI . "generated_pages/" . $pageName . " '/tmp/" . $pdfName . ".pdf'");
            unlink("./generated_pages/" . $pageName);
            //copy("/tmp/".$hash.".pdf", "./generated_pages/".$hash.".pdf");
            //unlink("/tmp/".$hash.".pdf");
            $this->sendApprovalEmail("'/tmp/" . $pdfName . ".pdf'", $email, $date, $name);
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
   private function sendApprovalEmail($pdfURL, $reciever, $date, $name) {
      //$reciever = $this->getEmailAddress($ldapUser);
      $cc = Config::$managerEmail;
      $subject = "Invoice for Nitrogen requested on ".$date;
      $message = "Hi ".$name.",\n\nYour request for Nitrogen has been approved. An invoice of the acquisition is attached to this email. \n\nThis email has been auto-generated, please do not reply to it. For any additional information/clarification, please get in touch with Sammy Kemei, s.kemei@cgiar.org";
      shell_exec('echo "'.$message.'"|'.Config::$muttBinary.' -F '.Config::$muttConfig.' -s "'.$subject.'" -c '.$cc.' -a '.$pdfURL.' -- '.$reciever);
      //shell_exec('echo "'.$message.'"|mailx -s "'.$subject.'" -c '.$cc.' -a '.$pdfURL.' -r '.Config::$repositoryMailAdd.' '.$reciever);
      unlink($pdfURL);
   }
   
   private function sendRequestEmail($requester, $amount){
       $reciever = Config::$managerEmail;
       $subject = "Nitrogen request from ".$requester;
       $message = $requester." sent a request out for ".$amount." Kg(s) of Nitrogen on ".date('d/m/Y h:i:s a', time())." Please approve it";
       shell_exec('echo "'.$message.'"|'.Config::$muttBinary.' -F '.Config::$muttConfig.' -s "'.$subject.'" -- '.$reciever);
   }

   /**
    * Gets the price of nitrogen from the database that is valid for today
    *
    * @return  float    Returns -1 if an error occures or the price of nitrogen
    */
   private function getNitrogenPrice() {
      $query = "SELECT price FROM `ln2_prices` WHERE `start_date` <= CURDATE()";
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
      $query = "SELECT * FROM ln2_chargecodes";
      $result = $this->Dbase->ExecuteQuery($query);
      return $result;
   }
}
?>
