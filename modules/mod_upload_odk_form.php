<?php

/**
 * The main class of the system. All other classes inherit from this main one
 *
 * @category   AZIZI Biorepository
 * @package    ODK Uploader
 * @author     Kihara Absolomon <a.kihara@cgiar.org>
 * @author     Jason Rogena <j.rogena@cgiar.org>
 * @since      v0.2
 */
class UploadODK extends Repository{

   /**
    * @var string       Relative path to the root of this project
    */
   private $ROOT = "./";

   private $sessionID;
   private $tmpDir;
   private $excelFileLoc;
   private $xmlFileLoc;
   private $authCookies;
   private $userAgent;
   private $maxTestingTime;

   public $Dbase;

   public function __construct($Dbase) {
      $this->Dbase = $Dbase;

      $this->sessionID = session_id();
      if($this->sessionID == NULL || $this->sessionID == "") {
         $this->sessionID = round(microtime(true) * 1000);
      }

      if(!file_exists($this->ROOT.'tmp')){
         mkdir($this->ROOT.'tmp',0777,true);
      }

      $this->tmpDir = $this->ROOT.'tmp/'.$this->sessionID;
      $this->Dbase->CreateLogEntry("tmp dir =".$this->tmpDir, "fatal");

      if(!file_exists($this->tmpDir)){
         mkdir($this->tmpDir,0777,true);
      }

      $this->authCookies = $this->tmpDir."/"."AUTH".mt_rand();
      $this->userAgent = "Mozilla/5.0 (X11; Linux x86_64; rv:26.0) Gecko/20100101 Firefox/26.0";
      $this->maxTestingTime = 10;
   }

   public function TrafficController() {
      /*
       *  - odk_uploader
       *    - do_upload
       *       - upload
       */
      if(OPTIONS_REQUEST_TYPE == 'normal'){
         echo "<script type='text/javascript' src='js/odk_upload.js'></script>";
      }

      if (OPTIONS_REQUESTED_SUB_MODULE == '') $this->homePage();
      if (OPTIONS_REQUESTED_SUB_MODULE == 'do_upload') $this->homePage();
   }

   /**
    * Creates the home page to the parser function
    */
   private function homePage($addInfo = '') {
      if(OPTIONS_REQUESTED_ACTION == 'upload'){
         $addInfo = $addInfo . $this->uploadEXCELFile();
      }
      $emailAddress = $this->Dbase->getEmailAddress($_SESSION['username']);
      $addInfo = ($addInfo != '') ? "<div id='addinfo'>$addInfo</div>" : '';
?>
<h3 id="odk_heading">ODK Uploader</h3>
<hr />
   <?php echo $addInfo?>
<form id="odk_upload_form" class="form-horizontal odk_uploader" method="POST" enctype="multipart/form-data" action="index.php?page=odk_uploader&do=do_upload&action=upload" onsubmit="return window.uploader.validateInput();">
   <div id="odk_instructions">
      <p>
         <p class='center'>This is a system for uploading of ODK surveys in Microsoft Excel formats to the ODK Aggregate server maintained by the <a href='/' target='_blank'>Azizi Biorepository</a> team.</p>
         <p>Refer to this <a href="http://opendatakit.org/help/form-design/xlsform/" target="_blank"><u>page</u></a> for guideline in creating an ODK survey using Microsoft Excel. If you need further assistance in creating the ODK forms please contact us.<br />
         Once you have created the form in Excel, upload it here. The system will validate it for errors, and in case of any errors, they will be displayed at the top of this page. Review the errors and then re-upload it again. if the form has no errors, it will be automatically uploaded to the server and you should receive an email notification of the same.<br />
         After this, go to ODK collect on your mobile device and download the form using the credentials in the email and enjoy using the form.</p>
         <p>Please note that this system is still under development and in case of any bugs, please contact us through the email <a target="_top" href="mailto:azizibiorepository@cgiar.org">Azizi Biorepository</a></p>
      </p>
   </div>
   <hr />
   <div class="form-group">
      <div class="odk_uploader_field_divs"><label for="excel_file" class="control-label">Excel Form</label></div>
      <div class="odk_uploader_field_divs"><input type="file" class="form-control" id="excel_file" name="excel_file" placeholder="Excel Form"></div>
   </div>
   <div class="form-group">
      <div class="odk_uploader_field_divs"><label for="email" class="control-label">Email Address</label></div>
      <div class="odk_uploader_field_divs"><input type="text" class="form-control" id="email" name="email" value="<?php if($emailAddress !== 0) echo $emailAddress;?>" /></div>
   </div>
   <div class="form-group">
      <div class="odk_uploader_field_divs"><label for="upload_type" class="control-label">Type of Upload</label></div>
      <div class="odk_uploader_field_divs">
         <select class="form-control" name="upload_type" id="upload_type" >
            <option value=""></option>
            <option value="testing">For testing</option>
            <option value="final">Final upload</option>
         </select>
      </div>
   </div>
   <div class="center"><input id="upload_b" name="upload_b" type="submit" value="Upload" class="btn btn-success" /></div>
</form>

<script>
   $(document).ready( function() {
      var uploader = new Uploader();
   });
   $('#whoisme .back').html('<a href=\'?page=home\'>Back</a>');
</script>
<?php
   }

   private function uploadEXCELFile(){
      //get the excel file
      if(!empty($_FILES['excel_file'])){
         $this->Dbase->CreateLogEntry("trying to get file from client", "debug");
         if($_FILES['excel_file']['error'] > 0){
            $this->Dbase->CreateLogEntry("File error thrown while tying to download file from client. Error is ".$_FILES['excel_file']['error'], "fatal");
         }
         else{
            $this->excelFileLoc = $this->tmpDir."/".$_FILES['excel_file']['name'];
            $realName = explode(".", $_FILES['excel_file']['name']);
            $this->xmlFileLoc = $this->tmpDir."/".$realName[0].".xml";
            move_uploaded_file($_FILES['excel_file']['tmp_name'], $this->excelFileLoc);
            $this->Dbase->CreateLogEntry("moved file from client to ".$this->excelFileLoc, "debug");
            $result = exec("python ".OPTIONS_COMMON_FOLDER_PATH."pyxform/pyxform/xls2xform.py ".$this->excelFileLoc." ".$this->xmlFileLoc." 2>&1", $execOutput);//assuming the working directory is where current php file is
            if(file_exists($this->xmlFileLoc)){//no error
               $xmlString = file_get_contents($this->xmlFileLoc);

               if($_POST['upload_type'] === "testing"){
                  //change the instance id for the form
                  ///
                  preg_match_all("/<instance>[\s\n]*<(.+)\s+id=[\"'](.*)[\"']>/i", $xmlString, $instanceIDs);

                  if(isset($instanceIDs[1]) && count($instanceIDs[1]) === 1 && isset($instanceIDs[2]) && count($instanceIDs[2]) === 1){
                     $preID = $instanceIDs[1][0];
                     $instanceID = $instanceIDs[2][0];
                     $newInstanceID = $instanceID . mt_rand();
                     $xmlString = preg_replace("/<instance>[\s\n]*<".$preID."\s+id=[\"']".$instanceID."[\"']>/", "<instance>\n<".$preID." id=\"".$newInstanceID."\">", $xmlString);
                     file_put_contents($this->xmlFileLoc, $xmlString);
                  }
                  else{
                     $this->Dbase->CreateLogEntry("The XML file returned multiple results for the instance id", "fatal");
                     return "Something went wrong while trying to upload the form. This does not mean your form has a problem. Please contact the Systems Administrators";
                  }
               }

               //authenticate user
               $formUploadURL = "http://azizi.ilri.cgiar.org/aggregate/formUpload";
               if(file_exists($this->authCookies) === FALSE){
                  $authURL = Config::$config['odkAuthURL'];
                  touch($this->authCookies);
                  chmod($this->authCookies, 0777);
                  $authCh = curl_init($authURL);

                  curl_setopt($authCh, CURLOPT_USERAGENT, $this->userAgent);
                  curl_setopt($authCh, CURLOPT_RETURNTRANSFER, TRUE);
                  curl_setopt($authCh, CURLOPT_FOLLOWLOCATION, TRUE);
                  curl_setopt($authCh, CURLOPT_CONNECTTIMEOUT, TRUE);
                  curl_setopt($authCh, CURLOPT_COOKIEJAR, $this->authCookies);
                  curl_setopt($authCh, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
                  curl_setopt($authCh, CURLOPT_USERPWD, Config::$config['odkUploadUser'].":".Config::$config['odkUploadPass']);

                  $result = curl_exec($authCh);
                  $http_status = curl_getinfo($authCh, CURLINFO_HTTP_CODE);
                  curl_close($authCh);
                  if($http_status == 401){//user not authenticated
                     $this->Dbase->CreateLogEntry("auth status = ".$http_status.". ".Config::$config['odkUploadUser']." is not authenticated to upload forms to aggregate. Exiting", "fatal");
                     return "The ODK user is not authorised to upload ODK forms";
                  }
               }

               $fullXMLFilePath = realpath($this->xmlFileLoc);
               $postFields = array('form_def_file'=>'@'.$fullXMLFilePath);

               $ch = curl_init($formUploadURL);
               curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
               curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
               curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
               curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, TRUE);
               curl_setopt($ch, CURLOPT_COOKIEFILE, $this->authCookies);
               curl_setopt($ch, CURLOPT_POST, 1);
               curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);

               $result = curl_exec($ch);
               $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
               curl_close($ch);
               if($http_status == 201){

                  $possibleInstanceIDs = array();

                  //get the name of the form
                  preg_match_all("/<instance>[\s\n]*<(.+)\s+id=[\"'](.*)[\"']>/i", $xmlString, $possibleInstanceIDs);

                  if(isset($possibleInstanceIDs[1]) && count($possibleInstanceIDs[1]) == 1 && isset($possibleInstanceIDs[2]) && count($possibleInstanceIDs[2]) == 1){
                     $topElement = $possibleInstanceIDs[1][0];
                     $instanceID = $possibleInstanceIDs[2][0];

                     preg_match_all("/<h:title>(.*)<\/h:title>/i", $xmlString, $possibleTitle);
                     $formTitle = "";

                     if(isset($possibleTitle[1]) && count($possibleTitle[1]) == 1)
                        $formTitle = $possibleTitle[1][0];

                     //save upload to database
                     //1. check if form has already been uploaded before
                     $query = "SELECT id FROM odk_forms WHERE instance_id = :instance_id";//form names are unique in database
                     $result = $this->Dbase->ExecuteQuery($query, array("instance_id"=>$instanceID));
                     if(is_array($result) && count($result) === 1){//not worried that there will be more than one fetch. Database enforcing
                        $this->Dbase->CreateLogEntry("Not first time form with instance id = ".$instanceID." is being uploaded", "fatal");
                        $formID = $result[0]['id'];
                        $query = "INSERT INTO odk_uploads(odk_form, uploaded_by, email_address, upload_type) VALUES(:form_id, :uploaded_by, :email, :type)";
                        $username = $_SESSION['username'];
                        if(strlen($username) === 0)
                           $username = $_SESSION['onames']." ".$_SESSION['sname'];

                        if(strlen($username) === 0)
                           $username = "unknown";

                        $result = $this->Dbase->ExecuteQuery($query, array("form_id" => $formID, "uploaded_by" => $username, "email" => $_POST['email'], "type" => $_POST['upload_type']));
                     }
                     else{//this is probably the first time the form is being uploaded
                        $this->Dbase->CreateLogEntry("First time form with instance id = ".$instanceID." is being uploaded", "fatal");

                        $query = "INSERT INTO odk_forms(instance_id, created_by, email_address, top_element, form_name) VALUES(:instance_id, :user, :email, :top_element, :form_name)";
                        $username = $_SESSION['username'];
                        if(strlen($username) === 0)
                           $username = $_SESSION['onames']." ".$_SESSION['sname'];

                        if(strlen($username) === 0)
                           $username = "unknown";

                        $result = $this->Dbase->ExecuteQuery($query, array("instance_id" => $instanceID, "user" => $username, "email" => $_POST['email'], "top_element" => $topElement, "form_name" => $formTitle));

                        $query = "SELECT id FROM odk_forms WHERE instance_id = :instance_id";//form names are unique in database
                        $result = $this->Dbase->ExecuteQuery($query, array("instance_id"=>$instanceID));
                        if(is_array($result) && count($result) === 1){
                           $formID = $result[0]['id'];
                           $query = "INSERT INTO odk_uploads(odk_form, uploaded_by, email_address, upload_type) VALUES(:form_id, :uploaded_by, :email, :type)";

                           $result = $this->Dbase->ExecuteQuery($query, array("form_id" => $formID, "uploaded_by" => $username, "email" => $_POST['email'], "type" => $_POST['upload_type']));
                        }
                     }

                     //email uploader
                     $this->sendInstructionEmail($formTitle,$instanceID, $_POST['email']);
                  }
                  else{
                     $this->Dbase->CreateLogEntry("Unabe to find name of form in xml file just uploaded to ODK aggregate. XML = ".$xmlString, "fatal");
                  }

                  return "Form successfully uploaded. Check you email for further instructions";
               }
               else{
                  $this->Dbase->CreateLogEntry("http status from aggregate = ".$http_status.". Form not uploaded", "fatal");
                  return "Something went wrong while trying to upload the form";
               }
            }
            else{
               $this->Dbase->CreateLogEntry("exec error is ".print_r($execOutput, true), "fatal");
               $errorOutput = join("\n", $execOutput);
               preg_match_all("/errors\.PyXFormError:(.*)/", $errorOutput, $relevantErrorArray);
               $errorMessg = "";
               if(isset($relevantErrorArray[1])){
                  $errorMessg = join("\n", $relevantErrorArray[1]);
               }
               return "Unable to process the excel file you provided. Please check for errors in the excel file <pre>".$errorMessg."</pre>";
            }
         }
      }
      else{
         $this->Dbase->CreateLogEntry("The excel file container is empty", "fatal");
      }
   }

   private function sendInstructionEmail($formName, $instanceID,  $address) {
      $emailSubject = "Upload of ".$formName." Form on Azizi's ODK Server";
      $timeLimit = "";
      if($_POST['upload_type'] === "testing") $timeLimit = " However you have ".  $this->maxTestingTime . " minutes to download it from ODK Collect after which it will be deleted automatically.";

$message = "Hi {$_SESSION['onames']},\n\n";
$message .= "        A $formName (with an insance_id '$instanceID') has been successfully uploaded onto the Azizi ODK Server.\n\n";
$message .= "        You can now download the form using ODK Collect on you mobile device. $timeLimit.";
$message .= "        If you do not have ODK Collect download on your mobile device, download it from http://goo.gl/cGVSxc. Once installed, edit the following general settings from the ODK Collect:\n\n";
$message .= "             URL : http://azizi.ilri.cgiar.org/aggregate\n";
$message .= "             Username  : collector\n";
$message .= "             Password  : collector_2013\n\n";
$message .= "        Should you have any problems, please reply to this email and we shall get back to you as soon as possible.\n\n";
$message .= "With Regards\n";
$message .= "The Biorepository team\n";

      //$headers = "From: noreply@cgiar.org";
      //mail($_POST['email'], $emailSubject, $message, $headers);

      shell_exec('echo "'.$message.'"|'.Config::$muttBinary.' -F '.Config::$muttConfig.' -s "'.$emailSubject.'" -- '.$address);
   }
}
?>
