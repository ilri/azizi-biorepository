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
         $this->Dbase->CreateLogEntry("Unable to get the session id. Rolling back to using time to distinguish session folder", "fatal");
         $this->sessionID = microtime(true);
      }

      if(!file_exists($this->ROOT.'tmp')){
         mkdir($this->ROOT.'tmp',0777,true);
      }

      $this->tmpDir = $this->ROOT.'tmp/'.$this->sessionID;
      $this->Dbase->CreateLogEntry("tmp dir =".$this->tmpDir, "fatal");

      //make the temporary directory for this session
      if(!file_exists($this->tmpDir)){
         mkdir($this->tmpDir,0777,true);
         mkdir($this->tmpDir."/media", 07770,true);//makes the temporary directory for teh media files linked to the currect form
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
         <p class='center'>Upload your ODK forms to the Azizi Biorepository's ODK Aggregate Server.</p>
         <p>Refer to this <a href="http://opendatakit.org/help/form-design/xlsform/" target="_blank"><u>page</u></a> for guideline on creating ODK XLS forms. If you need help, feel free to contact us. Once you have created the form in Excel, upload it 
            here. The system will check for errors in form, and in case any are found, they will be displayed at the top of this page. If your form has no errors, it will be uploaded to the Aggregate Server. You should also receive an email with further instructions.<br />
         <p>Please note that this system is still under development. If you however experience any problem, send any of System Developers an email.</p>
      </p>
   </div>
   <hr />
   <div class="form-group">
      <div class="odk_uploader_field_divs"><label for="excel_file" class="control-label" style="width: 200px;">* Excel Form</label></div>
      <div class="odk_uploader_field_divs"><input type="file" class="form-control" id="excel_file" name="excel_file" placeholder="Excel Form" accept=".xls"></div><!-- Only accept .xls files. xlsx files are not parsed well with the ODK parsing python script -->
   </div>
   <div class="form-group">
      <div class="odk_uploader_field_divs"><label for="media_files" class="control-label" style="width: 200px;">Media Files</label></div>
      <div class="odk_uploader_field_divs"><input type="file" class="form-control" id="media_files" name="media_files[]" placeholder="Media Files" multiple></div><!-- Allow for uploading of multiple files -->
   </div>
   <div class="form-group">
      <div class="odk_uploader_field_divs"><label for="email" class="control-label" style="width: 200px;">* Email Address</label></div>
      <div class="odk_uploader_field_divs"><input type="text" class="form-control" id="email" name="email" value="<?php if($emailAddress !== 0) echo $emailAddress;?>" /></div>
   </div>
   <div class="form-group">
      <div class="odk_uploader_field_divs"><label for="collaborators" class="control-label" style="width: 200px;">Allowed Users</label></div>
      <div class="odk_uploader_field_divs"><input type="text" class="form-control" id="collaborators" name="collaborators" placeholder="Seperate using semicolons"></div><!-- Allow for uploading of multiple files -->
   </div>
   <div class="form-group">
      <div class="odk_uploader_field_divs"><label for="upload_type" class="control-label" style="width: 200px;">* Type of Upload</label></div>
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
            //download the xls form
            $this->excelFileLoc = $this->tmpDir."/".$_FILES['excel_file']['name'];
            $realName = explode(".", $_FILES['excel_file']['name']);
            $this->xmlFileLoc = $this->tmpDir."/".$realName[0].".xml";
            move_uploaded_file($_FILES['excel_file']['tmp_name'], $this->excelFileLoc);
            $this->Dbase->CreateLogEntry("moved file from client to ".$this->excelFileLoc, "debug");
            
            //download the media files
            $this->emptyMediaDir();//make sure the media dir is empty first
            /*
             structure of $_FILES['media_files'] that contains 2 files is 
             (
                  [name] => Array
                      (
                          [0] => ILRI logo.png
                          [1] => CGIAR-green-logo-912x1081.jpg
                      )

                  [type] => Array
                      (
                          [0] => image/png
                          [1] => image/jpeg
                      )

                  [tmp_name] => Array
                     (
                        [0] => /tmp/phpF0IjKJ
                        [1] => /tmp/phpebDgFu
                     )

                  [error] => Array
                    (
                        [0] => 0
                        [1] => 0
                    )
                  [size] => Array
                     (
                         [0] => 25313
                         [1] => 128767
                     )
             )
             */
            
            /*when checking if user is uploading media files, check if the name of the first media file has at least one character.
             * Seems like PHP inserts a 'phantom' media file if none is uploaded by the user. I'm assuming this phantom file has blank fields tied to it
             */
            if(count($_FILES['media_files']['name']) > 0 && strlen($_FILES['media_files']['name'][0]) > 0){//user wants to upload more than one media file
               $this->Dbase->CreateLogEntry("The current form has " . count($_FILES['media_files']['name']) . " media files", "debug");
               //for each of the files, try to download them to the tmp media dir
               for($mCount = 0; $mCount < count($_FILES['media_files']['name']); $mCount++){
                  if(empty($_FILES['media_files']['tmp_name'][$mCount]) || $_FILES['media_files']['error'][$mCount] > 0){
                     $this->Dbase->CreateLogEntry("An error occurred while trying to read one of the media files from POST","fatal");
                     return "One of the media files failed to upload. Please try to resubmit the form";
                  }
                  else {
                     $moveRes = move_uploaded_file($_FILES['media_files']['tmp_name'][$mCount], $this->tmpDir."/media/".$_FILES['media_files']['name'][$mCount]);//move POST file to session's tmp directory and rename it to its original name
                     if($moveRes === FALSE){
                        $this->Dbase->CreateLogEntry("An error occurred while trying to move a media file from POST to ".$this->tmpDir."/media/".$_FILES['media_files']['name'][$mCount], "fatal");
                        return "An error occurred while trying to upload one of the media files. Please try again";
                     }
                  }
               }
            }
            
            //extract external_itemsets from the excel file
            $itemsetsRes = $this->extractExternalItemsets($this->excelFileLoc, $this->tmpDir."/media");
            if($itemsetsRes == 1){
               return "An error occurred while trying to determine if the provided form has an external itemset tied to it";
            }
            else if ($itemsetsRes == 2){
               return "Your form's external_choices sheet and the itemsets.csv file contain similar data. You can use either but not both.";
            }
            
            $result = exec("/usr/bin/python ".OPTIONS_COMMON_FOLDER_PATH."pyxform/pyxform/xls2xform.py ".$this->excelFileLoc." ".$this->xmlFileLoc." 2>&1", $execOutput);//assuming the working directory is where current php file is
            $errorOutput = join("\n", $execOutput);
$this->Dbase->CreateLogEntry("python " . OPTIONS_COMMON_FOLDER_PATH . "pyxform/pyxform/xls2xform.py " . $this->excelFileLoc . " " . $this->xmlFileLoc , "fatal");
            if(file_exists($this->xmlFileLoc) && strlen(file_get_contents($this->xmlFileLoc)) > 1){//no error
               $xmlString = file_get_contents($this->xmlFileLoc);

               if($_POST['upload_type'] === "testing"){
                  //change the instance id for the form
                  ///
                  preg_match_all("/<instance>[\s\n]*<(.+)\s+id=[\"'](.*)[\"']>/i", $xmlString, $instanceIDs);

                  if(isset($instanceIDs[1]) && count($instanceIDs[1]) === 1 && isset($instanceIDs[2]) && count($instanceIDs[2]) === 1){
                     $preID = $instanceIDs[1][0];
                     $instanceID = $instanceIDs[2][0];
                     $random = microtime(true);
                     $newInstanceID = $instanceID.$random;
                     $xmlString = preg_replace("/<instance>[\s\n]*<".$preID."\s+id=[\"']".$instanceID."[\"']>/", "<instance>\n<".$preID." id=\"".$newInstanceID."\">", $xmlString);
                     
                     /*preg_match_all("/<h:title>(.*)<\/h:title>/i", $xmlString, $possibleTitle);
                     
                     if(isset($possibleTitle[1]) && count($possibleTitle[1]) == 1){
                        $title = $possibleTitle[1][0];
                        $newTitle = $title ." (". $random.")";
                        $xmlString = preg_replace("/<h:title>".$title."<\/h:title>/", "<h:title>".$newTitle."<\/h:title>", $xmlString);
                     }
                     else{
                        $this->Dbase->CreateLogEntry("The XML file returned multiple results for the title", "fatal");
                        return "Something went wrong while trying to upload the form. This does not mean your form has a problem. Please contact the Systems Administrators";
                     }*/
                     
                     file_put_contents($this->xmlFileLoc, $xmlString);
                  }
                  else{
                     $this->Dbase->CreateLogEntry("The XML file returned multiple results for the instance id", "fatal");
                     return "Something went wrong while trying to upload the form. This does not mean your form has a problem. Please contact the Systems Administrators";
                  }
               }
               
               preg_match_all("/<instance>[\s\n]*<(.+)\s+id=[\"'](.*)[\"']>/i", $xmlString, $possibleInstanceIDs);
               if(isset($possibleInstanceIDs[1]) && count($possibleInstanceIDs[1]) == 1 && isset($possibleInstanceIDs[2]) && count($possibleInstanceIDs[2]) == 1){
                     $topElement = $possibleInstanceIDs[1][0];
                     $instanceID = $possibleInstanceIDs[2][0];
               }
               
               preg_match_all("/<h:title>(.*)<\/h:title>/i", $xmlString, $possibleTitle);
               $formTitle = "";
               
               if(isset($possibleTitle[1]) && count($possibleTitle[1]) == 1)
                  $formTitle = $possibleTitle[1][0];
               $error = "";
               //check if form with same name in database with same title
               $query = "SELECT id FROM odk_forms WHERE form_name = :form_name";
               $result = $this->Dbase->ExecuteQuery($query, array("form_name" => $formTitle));

               if(is_array($result) && count($result)>0 && $_POST['upload_type'] != "testing"){//do not upload
                  $error .= "There already exists an ODK form with the same form_title. If this is the same form, consider incrementing it by a version<br />";
               }
               
               $query = "SELECT id FROM odk_forms WHERE instance_id = :instance_id";
               $result = $this->Dbase->ExecuteQuery($query, array("instance_id" => $instanceID));
               if(is_array($result) && count($result)>0){//do not upload
                  $error .= "There is another ODK form in the server with the same instance id. Please change ".$instanceID. " to something else<br />";
               }
               
               if(strlen($error) > 0){
                  return $error;
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

               /**
                * Package the data and push to briefcase API's formUpload.
                * Refer to https://code.google.com/p/opendatakit/wiki/BriefcaseAggregateAPI 
                */
               $fullXMLFilePath = realpath($this->xmlFileLoc);
               $fullMediaFilePath = realpath($this->tmpDir."/media");
               $mediaFileNames = scandir($this->tmpDir."/media");
               /*
                * scandir() generates an array that looks like this:
                  Array
                  (
                     [0] => .
                     [1] => ..
                     [2] => filename1
                     ..
                     [n+2] => filenameN
                 )
                 make sure you ignore the first two elements in the generated array
                */
               
               if($mediaFileNames !== FALSE && count($mediaFileNames) > 2) { //media directory has at least one file in it
                  $postFields = array('form_def_file'=>'@'.$fullXMLFilePath);
                  for($mediaCount = 0; $mediaCount < count($mediaFileNames) - 2; $mediaCount++){
                     //, 'datafile'=>'@'.$fullMediaFilePath
                     $postFields["datafile[".$mediaCount."]"] = "@".$fullMediaFilePath."/".$mediaFileNames[$mediaCount + 2];
                  }
                  
               }
               else {//user uploading form without media files
                  $postFields = array('form_def_file'=>'@'.$fullXMLFilePath);
               }
               $this->Dbase->CreateLogEntry("POST to formUpload API looks like this ".print_r($postFields, true), "debug");

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
                           //check if there exists .sql files in the media dir
                           $allMediaFiles = scandir($this->tmpDir."/media/");
                           if(is_array($allMediaFiles)){
                              foreach($allMediaFiles as $currMediaFile) {
                                 if(strpos($currMediaFile, ".sql") !== false) {//check if file contains the .sql suffix
                                    $this->Dbase->CreateLogEntry("$currMediaFile is an ODK puller file","debug");
                                    $preloadName = str_replace(".sql", "", $currMediaFile);
                                    $csvString = file_get_contents($this->tmpDir."/media/".$currMediaFile);
                                    $this->savePullerData($formID, $preloadName, $csvString);
                                    unlink($this->tmpDir."/media/".$currMediaFile);
                                 }
                                 else $this->Dbase->CreateLogEntry("$currMediaFile is NOT an ODK puller file","debug");
                              }
                           }
                        }
                     }
                     
                     //give user access to form
                     $query = "SELECT id FROM odk_access WHERE user = :user AND form_id = :form_id";
                     $result = $this->Dbase->ExecuteQuery($query, array("form_id"=> $formID, "user" => $username));
                     if(is_array($result) && count($result) == 0){//user does not have access to form
                        $query = "INSERT INTO odk_access(form_id, `user`) VALUES(:form_id, :user)";
                        $this->Dbase->ExecuteQuery($query, array("form_id" => $formID, "user" => $username));
                     }
                     
                     //give other users access
                     if(strlen($_POST['collaborators']) > 0){
                        $this->Dbase->CreateLogEntry("User has specified collaborators for this form","fatal");
                        $collaborators = explode(";", $_POST['collaborators']);
                        for($cIndex = 0; $cIndex < count($collaborators); $cIndex++){
                           $currCollab = trim($collaborators[$cIndex]);
                           if(strlen($currCollab) > 0){
                              $query = "SELECT id FROM odk_access WHERE user = :user AND form_id = :form_id";
                              $result = $this->Dbase->ExecuteQuery($query, array("form_id"=> $formID, "user" => $currCollab));
                              if(is_array($result) && count($result) == 0){//user does not have access to form
                                 $query = "INSERT INTO odk_access(form_id, `user`) VALUES(:form_id, :user)";
                                 $this->Dbase->ExecuteQuery($query, array("form_id" => $formID, "user" => $currCollab));
                              }
                           }
                        }
                     }
                     
                     //schedule testing form for a delete
                     if($_POST['upload_type'] == 'testing'){
                        $tenMinLater = date('Y-m-d H:i:s', time()+600);
                        $query = "INSERT INTO odk_deleted_forms(form, status, time_to_delete) VALUES(:form_id, 'not_deleted', :ten_after)";
                        $this->Dbase->ExecuteQuery($query, array("form_id" => $formID, "ten_after" => $tenMinLater));
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
               //$errorOutput = join("\n", $execOutput);
               preg_match_all("/errors\.PyXFormError:(.*)/", $errorOutput, $relevantErrorArray);
               $errorMessg = $errorOutput;
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
   
   /**
    * This function checks whether the provided excel file has the external_choices
    * sheet and generates a itemset.csv file from this that will be uploaded alongside
    * the xml file to Aggregate
    * 
    * @param string $excelFileLoc   The location of the excel file
    * @param string $mediaDirLoc    Directory to save the itemsets.csv file in
    * 
    * @return Integer Returns -1 if excel file does not have an external choices, 0 if able to extract the data, 1 if an error occurred and 2 if itemsets.csv already exists in the media directory
    */
   private function extractExternalItemsets($excelFileLoc, $mediaDirLoc){
      include_once OPTIONS_COMMON_FOLDER_PATH.'PHPExcel/Classes/PHPExcel.php';
      include_once OPTIONS_COMMON_FOLDER_PATH.'PHPExcel/Classes/PHPExcel/IOFactory.php';
      
      try {
         //load the excel file
         $excelFileType = PHPExcel_IOFactory::identify($excelFileLoc);
         $objReader = PHPExcel_IOFactory::createReader($excelFileType);
         $excelFileObj = $objReader->load($excelFileLoc);
         
         //check if excel file has external_choices sheet
         $sheetNames = $excelFileObj->getSheetnames();//returns an array of sheet names with the indexes usable in $excelObject->getSheet($index)
         $ecSheetIndex = array_search("external_choices", $sheetNames);
         if($ecSheetIndex === FALSE){//please make sure you also check data type of $ecSheetIndex
            //means external_choices sheet wasn't found
            $this->Dbase->CreateLogEntry("Form's excel file doens not have the external_choices sheet","info");
            return -1;
         }
         else {//external_choices sheet in excel file
            //check if media directory has itemsets.csv file
            if(!file_exists($mediaDirLoc."/itemsets.csv")){//itemsets.csv does not exist in media directory
               //save the external_choices sheet as itemsets.csv. Refer to https://phpexcel.codeplex.com/discussions/257118
               $objWriter = PHPExcel_IOFactory::createWriter($excelFileObj, "CSV");
               $objWriter->setSheetIndex($ecSheetIndex);
               $objWriter->save($mediaDirLoc."/itemsets.csv");
               return 0;//fils saved successfully, everything is fine.
            }
            else {
               $this->Dbase->CreateLogEntry("User provided both itemsets.csv and an excel file with external_choices sheet. Sending back error message", "fatal");
               return 2;
            }
         }
         
      } 
      catch (Exception $ex) {
         $this->Dbase->CreateLogEntry("An error occurred while loading ".$excelFileLoc." into a PHPExcel file. \n".$ex->getMessage(), "fatal");
      }
      
      return 1;//fuction returns error by default. Return 0 when you are sure you are done and everything is fine
   }
   
   private function emptyMediaDir(){
      $mediaFiles = glob($this->tmpDir."/media/*");
      foreach($mediaFiles as $file){
         if(is_file($file)){
            unlink($file);
         }
      }
   }

   /**
   * This function saves the provided ODK Puller data into the database
   * @param String   $formId        The id for the Form
   * @param String   $preloadName   The name of the preload file as seen by ODK Collect
   * @param String   $preloadText   String with queries for getting the preload data.
   *                                Queries seperated by end of line character
   */
   private function savePullerData($formId, $preloadName, $preloadText) {
      $query = "insert into odk_preloads(form_id, name) values(:formId, :name)";
      $this->Dbase->ExecuteQuery($query, array("formId" => $formId, "name" => $preloadName));
      $query = "select id from odk_preloads where name = :name and form_id = :formId";
      $result = $this->Dbase->ExecuteQuery($query, array("formId" => $formId, "name" => $preloadName));
      if(is_array($result) and count($result) == 1) {
         $preloadId = $result[0]['id'];
         $queries = explode("\n", $preloadText);
         foreach($queries as $currQuery){
            $query = "insert into preload_queries(query, preload_id) values(:query, :preloadId)";
            $this->Dbase->ExecuteQuery($query, array("query" => $currQuery, "preloadId" => $preloadId));
         } 
      }
      else {
         $this->Dbase->CreateLogEntry("Could not record preload with name $preloadName with odk form with id = $formId because we couldn't get the preload id from the database","fatal");
      }
   }

   private function sendInstructionEmail($formName, $instanceID,  $address) {
      $emailSubject = "Upload of ".$formName." Form on Azizi's ODK Server";
      $timeLimit = "";
      if($_POST['upload_type'] === "testing") $timeLimit = " However you have ".  $this->maxTestingTime . " minutes to download it from ODK Collect after which it will be deleted automatically.";

      $message = "Hi {$_SESSION['onames']},\n\n";
      $message .= "$formName (with an insance_id '$instanceID') has been successfully uploaded to the Azizi ODK Server.\n\n";
      $message .= "You can now download the form in ODK Collect on your Android device. $timeLimit.";
      $message .= "If you do not have ODK Collect installed on your device, download it from http://goo.gl/cGVSxc. Once installed, edit the following platform settings in ODK Collect:\n\n";
      $message .= "             URL : http://azizi.ilri.cgiar.org/aggregate\n";
      $message .= "             Username  : collector\n";
      $message .= "             Password  : collector_2013\n\n";
      $message .= "Should you need any assistance, feel free to contact anybody from the Biorepository's technical team.\n\n";
      $message .= "Regards\n";
      $message .= "The Biorepository team\n";

      //$headers = "From: noreply@cgiar.org";
      //mail($_POST['email'], $emailSubject, $message, $headers);

      shell_exec('echo "'.$message.'"|'.Config::$muttBinary.' -F '.Config::$muttConfig.' -s "'.$emailSubject.'" -- '.$address);
   }
}
?>
