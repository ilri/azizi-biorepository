<?php

/**
 * The main class in the LIMS uploader system.
 *
 * @category   LimsUploader
 * @package    Main
 * @author     Kihara Absolomon <a.kihara@cgiar.org>
 * @since      v0.1
 */
class LimsUploader{

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
    * The constructor of this class. Initializes the Dbase connection and the logging systems
    */
   public function __construct($Dbase){
      $this->Dbase = $DBase;
   }

   /**
    * The traffic controller. Determines where the program execution is directed at any given point.
    *
    * @return type
    */
   public function TrafficController(){
      if(OPTIONS_REQUEST_TYPE == 'normal') echo "<script type='text/javascript' src='js/lims_uploader.js'></script>";

      if(OPTIONS_REQUESTED_SUB_MODULE == '') $this->HomePage();
      elseif(OPTIONS_REQUESTED_SUB_MODULE == 'sample_sheet') $this->SampleSheet();
      elseif(OPTIONS_REQUESTED_SUB_MODULE == 'download') $this->DownloadSampleSheet();
      elseif(OPTIONS_REQUESTED_SUB_MODULE == 'process') $this->ProcessData();
      elseif(OPTIONS_REQUESTED_SUB_MODULE == 'post_data') $this->PostData();
   }

   /**
    * Fetch the details of the person who is logged in
    *
    * @return  mixed    Returns 1 in case an error ocurred, else it returns an array with the logged in user credentials
    */
   public function GetCurrentUserDetails(){
      $this->Dbase->query = "select a.id as user_id, a.sname, a.onames, a.login, b.name as user_type , c.contact_id
         from ".Config::$config['session_dbase'].".users as a
         inner join ".Config::$config['session_dbase'].".user_levels as b on a.user_level=b.id
         inner join ".Config::$config['session_dbase'].".lcmod_users as c on a.login=c.username
         WHERE a.id={$this->Dbase->currentUserId} AND a.allowed=1";

      $result = $this->Dbase->ExecuteQuery(MYSQLI_ASSOC);
      if($result == 1){
         $this->Dbase->CreateLogEntry("There was an error while fetching data from the database.", 'fatal', true);
         $this->Dbase->lastError = "There was an error while fetching data from the session database.<br />Please try again later.";
         return 1;
      }

      return $result[0];
   }

   /**
    * The home page to the system
    */
   private function HomePage($addinfo = '', $rightPanel = '', $module = ''){
      $addinfo = ($addinfo == '') ? '' : "<div id='addinfo'>$addinfo</div>";
      echo $addinfo;
      $primersChecked = ($module == 'primers') ? 'checked' : '';
      $samplesChecked = ($module == 'samples') ? 'checked' : '';
?>
<div id="main">
   <div id="top" class="center">
      <input type="radio" name="module" value="samples" <?php echo $samplesChecked; ?> />Samples &nbsp;&nbsp;&nbsp;<input type="radio" name="module" value="primers" <?php echo $primersChecked; ?> />Primers
   </div>
   <div id="uhondo">
      <div id='left_panel'>
         <ul>
            <li><a href="javascript:;" id="sample_sheet">Sample Spreadsheet</a><img id="download" src="images/download.png" alt="download" /></li>
            <li><a href="javascript:;" id="uploading_panel">Uploading Panel</a></li>
         </ul>
      </div>
      <div id='right_panel'><?php echo $rightPanel; ?></div>
   </div>
</div>
<script type="text/javascript">
   $('#sample_sheet').click(LimsUploader.sampleSheet);
   $('#download').click(LimsUploader.downloadSampleSheet);
   $('#uploading_panel').click(LimsUploader.uploadingPanel);
</script>
<?php
   }

   /**
    * Gets the requested template, processes it and outputs the results as html code
    */
   private function SampleSheet(){
      require_once OPTIONS_COMMON_FOLDER_PATH . 'excelParser/mod_excel_reader_v0.1.php';

      $file = (OPTIONS_REQUESTED_ACTION == 'samples') ? Config::$samplesTemplate : Config::$primersTemplate;
      $Template = new Spreadsheet_Excel_Reader($file);

      die(json_encode(
         array( 'error' => false, 'data' => $Template->dump(false, false, 0) )
      ));
   }

   /**
    * Downloads the template defined for the specified process
    */
   private function DownloadSampleSheet(){
      $file = (OPTIONS_REQUESTED_SUB_MODULE == 'samples') ? Config::$samplesTemplate : Config::$primersTemplate;

      //now offer the label for download
      header("Content-Disposition: attachment; filename=$file");
      header('Content-Type:  application/vnd.ms-excel');
      header('Content-Length: ' . filesize($file));
      header('Content-Transfer-Encoding: binary');
      header('Cache-Control: must-revalidate');
      header('Pragma: public');
      readfile($file);
      die();
   }

   /**
    * Takes the file through the common stages of processing before upload.
    *
    * @param   string   $module  The module the file belongs to
    * @param   string   $file    A path to the file that is to be processed
    * @return  object   Returns a reference to the object which was created after processing the parsed file
    */
   private function CommonFileProcessing($module, $file){
      require_once OPTIONS_COMMON_FOLDER_PATH . 'excelParser/mod_excel_reader_v0.1.php';
      require_once OPTIONS_COMMON_FOLDER_PATH . 'mod_spreadsheet_v0.1.php';
      if($module == 'primers') require_once 'mod_primers.php';
      elseif($module == 'samples') require_once 'mod_samples.php';

      //use the excel reader to read the file
      $excelData = new Spreadsheet_Excel_Reader($file);
      //look for the sheet named 'Final Samples' or 'Final Primers' which should be containing the list to upload, else get the first sheet
      $mainSheeet = ($module == 'samples') ? 'Final Samples' : 'Final Primers';
      $mainSheetIndex = 0;
      foreach($excelData->boundsheets as $index => $sheet){
         if($sheet['name'] == $mainSheeet) $mainSheetIndex = $index;
      }
      if($module == 'primers') $curFile = new Primers('', $file, $excelData->sheets[$mainSheetIndex]);
      elseif($module == 'samples') $curFile = new Samples('', $file, $excelData->sheets[$mainSheetIndex]);

      $curFile->htmlData = $excelData->dump(false, false, $mainSheetIndex);
      $curFile->sheet_name = $excelData->boundsheets[$mainSheetIndex]['name'];
      $curFile->sheet_index = $mainSheetIndex;
      $curFile->type = $module;

      $curFile->ValidateAndProcessFile();   //the file hasnt been uploaded and there is need to upload it

      if($curFile->fillLCRef) $curFile->FillMissingData();

      return $curFile;
   }

   /**
    * Does the initial processing of the
    * @return type
    */
   private function ProcessData(){
      //save the uploaded files
      $module = $_POST['module'];
      //some crazy shit happening here! An excel file saved by libre office gets an application/pdf attribute..... STRANGE BED FELLOWS
      $allowedFiles = array('application/vnd.ms-excel', 'application/ms-excel', 'application/pdf');
      $dataToUpload = GeneralTasks::CustomSaveUploads('uploads/', 'data', $allowedFiles, 10485760, array(session_id() .'.xls'));
      if(is_string($dataToUpload)){
         $this->HomePage($dataToUpload, '', $module);
         return;
      }
      elseif($dataToUpload == 0){
         $this->HomePage("No file selected for uploading");
         return;
      }

      $file = $dataToUpload[0];
      $curFile = $this->CommonFileProcessing($module, $file);

      $res = $curFile->CheckForDuplicates();   //the file hasnt been uploaded and there is need to upload it
      if(is_string($res)){
         $this->HomePage($res);
         return;
      }

      if(count($curFile->errors) != 0){
         //show the errors that we have encountered
         $errors = implode("<br />\n", $curFile->errors);
$content = <<< CONTENT
   <div id='addinfo' class='error'>You have some errors in your input spreadsheet. Address them and try to upload it again!</div>
   <div class='error'>$errors</div>
   <div id='sheet'>{$curFile->htmlData}</div>
CONTENT;
         $this->HomePage('', $content);
      }
      else{
         //create a confirmation step
$content = <<< CONTENT
   <div id='addinfo'>Please review the uploaded spreadsheet and confirm whether you want to upload it the way it is!</div>
   <div id='sheet'>{$curFile->htmlData}</div>
   <div id='footer_links'><input type='button' name='confirm' value='Confirm' /><input type='button' name='confirm' value='Cancel' /></div>
   <input type='hidden' name='uploadedFile' value='$file' /> <input type='hidden' name='curModule' value='$module' />
   <script type='text/javascript'>
      $('[name=confirm]').click(LimsUploader.confirmUpload);
   </script>
CONTENT;
         $this->HomePage('', $content);
      }
   }

   /**
    * Processes the file(again) and saves the data to the database, if the user so wishes.
    *
    * @return type
    */
   private function PostData(){
      if(OPTIONS_REQUESTED_SUB_MODULE == 'cancel'){
         //delete the file which was uploaded and go back to the home screen
         unlink($_GET['file']);
         $this->HomePage();
         return;
      }

      $file = $_GET['file'];
      $curFile = $this->CommonFileProcessing($_GET['module'], $file);

      $pi = pathinfo($file);

      //so we wanna save the data, cp the file somewhere before we save it
      $path = Config::$uploadedFilesFinalLocation;
      if(!is_dir($path)){
         if(!mkdir($path, 0755, true)) echo "I can't create a folder!";
      }
      $curFile->finalUploadedFile = "$path/{$pi['basename']}";
      $curFile->finalUploadedFileLink = "LimsUploader/{$pi['basename']}";
      rename($file, $curFile->finalUploadedFile);

      //now we upload the data
      $curFile->NormalizeData();

//      $curFile->DumpMetaData();
//      die();

      $res = $curFile->UploadData();
      if($res === 0) $this->HomePage("The data has been uploaded successfully.");
      else $this->HomePage($res);
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

