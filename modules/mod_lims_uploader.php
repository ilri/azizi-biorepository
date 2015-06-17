<?php

/**
 * ChangeLog
 *
 * 2014-04-06
 * - Add support for linked sheets, whose data will be displayed as a sub section in the the description field in Labcollector
 */

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
      $this->Dbase = $Dbase;
   }

   /**
    * The traffic controller. Determines where the program execution is directed at any given point.
    *
    * @return type
    */
   public function TrafficController(){
      global $Repository;

      if(OPTIONS_REQUEST_TYPE == 'normal') echo "<script type='text/javascript' src='js/lims_uploader.js'></script>";

      if(OPTIONS_REQUESTED_SUB_MODULE == '') $this->HomePage();
      elseif(OPTIONS_REQUESTED_SUB_MODULE == 'sample_sheet') $this->SampleSheet();
      elseif(OPTIONS_REQUESTED_SUB_MODULE == 'download') $this->DownloadSampleSheet();
      elseif(OPTIONS_REQUESTED_SUB_MODULE == 'process') $this->ProcessData();
      elseif(OPTIONS_REQUESTED_SUB_MODULE == 'post_data'){
         //re-open the db connection using a profile with rw permissions
         Config::$config['user'] = Config::$config['rw_user']; Config::$config['pass'] = Config::$config['rw_pass'];
         $this->Dbase->InitializeConnection();
         if(is_null($this->Dbase->dbcon)) {
            ob_start();
            $Repository->LoginPage(OPTIONS_MSSG_DB_CON_ERROR);
            $Repository->errorPage = ob_get_contents();
            ob_end_clean();
            return;
         }
         $this->PostData();
      }
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
      $elisaChecked = ($module == 'elisa') ? 'checked' : '';
      $strainsChecked = ($module == 'strains') ? 'checked' : '';
      $cell_linesChecked = ($module == 'cell_lines') ? 'checked' : '';
?>
<div id="main">
   <div id="top" class="center">
      <input type="radio" name="module" value="samples" <?php echo $samplesChecked; ?> />Samples&nbsp;&nbsp;&nbsp;
      <input type="radio" name="module" value="primers" <?php echo $primersChecked; ?> />Primers&nbsp;&nbsp;&nbsp;
      <input type="radio" name="module" value="elisa" <?php echo $elisaChecked; ?> />Elisa Results&nbsp;&nbsp;&nbsp;
      <input type="radio" name="module" value="strains" <?php echo $strainsChecked; ?> />Strains&nbsp;&nbsp;&nbsp;
      <input type="radio" name="module" value="cell_lines" <?php echo $cell_linesChecked; ?> />Cell Lines&nbsp;&nbsp;&nbsp;
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
   $('#whoisme .back').html('<a href=\'?page=home\'>Back</a>');
</script>
<?php
   }

   /**
    * Gets the requested template, processes it and outputs the results as html code
    */
   private function SampleSheet(){
      require_once OPTIONS_COMMON_FOLDER_PATH . 'azizi-shared-libs/excelParser/mod_excel_reader_v0.1.php';//TODO: Try to migrate to bower

      if(OPTIONS_REQUESTED_ACTION == 'samples') $file = Config::$samplesTemplate;
      elseif(OPTIONS_REQUESTED_ACTION == 'primers') $file = Config::$primersTemplate;
      elseif(OPTIONS_REQUESTED_ACTION == 'strains') $file = Config::$strainsTemplate;
      elseif(OPTIONS_REQUESTED_ACTION == 'cell_lines') $file = Config::$cellLinesTemplate;

      $Template = new Spreadsheet_Excel_Reader($file);

      die(json_encode(
         array( 'error' => false, 'data' => $Template->dump(false, false, 0) )
      ));
   }

   /**
    * Downloads the template defined for the specified process
    */
   private function DownloadSampleSheet(){
      if(OPTIONS_REQUESTED_ACTION == 'samples') $file = Config::$samplesTemplate;
      elseif(OPTIONS_REQUESTED_ACTION == 'primers') $file = Config::$primersTemplate;
      elseif(OPTIONS_REQUESTED_ACTION == 'cell_lines') $file = Config::$cellLinesTemplate;

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
      set_include_path(get_include_path() . PATH_SEPARATOR . OPTIONS_COMMON_FOLDER_PATH.'PHPExcel/Classes/' . PATH_SEPARATOR . '/www/common/PHPExcel/Classes/');     //add the classes path to the include path

      include 'PHPExcel/IOFactory.php';      //add the PHPExcel_IOFactory
//      require_once OPTIONS_COMMON_FOLDER_PATH . 'excelParser/mod_excel_reader_v0.2.php';
      require_once OPTIONS_COMMON_FOLDER_PATH . 'azizi-shared-libs/mod_spreadsheet/mod_spreadsheet_v0.2.php';
      if($module == 'primers') require_once 'mod_primers.php';
      elseif($module == 'samples') require_once 'mod_samples.php';
      elseif($module == 'elisa') require_once 'mod_elisa.php';
      elseif($module == 'strains') require_once 'mod_strains.php';
      elseif($module == 'cell_lines') require_once 'mod_cell_lines.php';

      //determine the type of file that was uploaded
      $inputFileType = PHPExcel_IOFactory::identify($file);
      $objExcelReader = PHPExcel_IOFactory::createReader($inputFileType);
      //load all the sheets
      $objExcelReader->setLoadAllSheets();
      $excelReader = $objExcelReader->load($file);

      //look for the sheet named 'Final Samples' or 'Final Primers' which should be containing the list to upload, else get the first sheet
      //once the main sheet has been found, we hope to link the other secondary sheets
      if ($module == 'samples') $mainSheeet = 'Final Samples';
      else if ($module == 'primers') $mainSheeet = 'Final Primers';
      else if ($module == 'elisa') $mainSheeet = 'Final Elisa';
      else if ($module == 'strains') $mainSheeet = 'Final Strains';
      else if ($module == 'cell_lines') $mainSheeet = 'Cell Lines';

      $secondarySheetsNames = array();
      //there is need to determine the main sheet. This will be the first sheet or the one named 'Final Samples' or 'Final Primers'
      $loadedSheetNames = $excelReader->getSheetNames();
      $mainSheetName = $loadedSheetNames[0];
      $mainSheetIndex = 0;
      foreach($loadedSheetNames as $sheetIndex => $sheetName) {
         if($sheetName == $mainSheeet){
            $mainSheetName = $sheetName;
            $mainSheetIndex = $sheetIndex;
         }
         $secondarySheetsNames[] = $sheetName;
      }

//      $sheetData = $excelReader->getSheetByName($mainSheetName)->toArray(null,true,true,true);

      //process all the sheets in the file
      $curfile = array();
      foreach($loadedSheetNames as $index => $sheetName){
         //make sure to skip the main sheet
         $sheetData = $excelReader->getSheetByName($sheetName)->toArray(null,true,true,true);

         if($module == 'primers') $curfile[$index] = new Primers('', $file, $sheetData);
         elseif($module == 'samples') $curfile[$index] = new Samples('', $file, $sheetData);
         elseif($module == 'elisa') $curfile[$index] = new Elisa('', $file, $sheetData);
         elseif($module == 'strains') $curfile[$index] = new Strains('', $file, $sheetData);
         elseif($module == 'cell_lines') $curfile[$index] = new CellLines('', $file, $sheetData);

         $writer = PHPExcel_IOFactory::createWriter($excelReader, 'HTML');
         $curfile[$index]->htmlData = $writer->setSheetIndex($index)->generateSheetData();
         $curfile[$index]->sheet_name = $sheetName;
         $curfile[$index]->sheet_index = $index;

         if($sheetName == $mainSheetName) $curfile[$index]->isMain = true;
         else $curfile[$index]->isMain = false;

         //validate and process the sheet
         $curfile[$index]->ValidateAndProcessFile();
         if($module == 'elisa') $curfile[$index]->NormalizeData(array());
      }

      if($curfile->fillLCRef) $curfile[$mainSheetIndex]->FillMissingData();

      return $curfile;
   }

   /**
    * Does the initial processing of the
    * @return type
    */
   private function ProcessData(){
      //save the uploaded files
      $module = $_POST['module'];
      //some crazy shit happening here! An excel file saved by libre office gets an application/pdf attribute..... STRANGE BED FELLOWS
      $allowedFiles = array('application/vnd.ms-excel', 'application/ms-excel', 'application/pdf', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
      $dataToUpload = GeneralTasks::CustomSaveUploads('uploads/', 'data', $allowedFiles, false, 10485760, array(session_id() .'.xls'));
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

      /**
       * Check for duplicates in the uploaded data. If there are duplicates, add the duplicate values in the error array
       * Check that if we have multiple sheets, we have the necessary foreign-pri key pairs. If some are missing add them to the warnings
       */
      $mergedErrors = array();
      $extraData = array();
      foreach($curFile as $index => $sheet){
         //check for the duplicates
         $res = $sheet->CheckForDuplicates();
         if(is_string($res)){
            $this->HomePage($res);
            return;
         }
         if(count($sheet->errors)){
            $mergedErrors = array_merge($mergedErrors, array("<b><u>Errors from {$sheet->sheet_name}</u></b>"), $sheet->errors);
         }
         if($sheet->isMain) $mainSheetIndex = $index;
         else $extraData[$sheet->sheet_name] = $sheet->getData();
      }
      //if we are processing elisa results, skip checking foreign constraints
      if(!in_array($module, array('elisa'))) $curFile[$mainSheetIndex]->checkForeignConstraints($extraData);

//      $curFile->DumpData();

      Repository::jqGridFiles();
      if(count($mergedErrors) != 0){
         //show the errors that we have encountered
$content =<<< CONTENT
   <div id='addinfo' class='error'>You have some errors in your input spreadsheet. Address them and try to upload it again!</div>
   <div id='all_data'>
      <ul>
        <li>Errors</li>
CONTENT;
         //create the warnings tab if we have some warnings
         if(count($curFile[$mainSheetIndex]->warnings)) $content .= "<li>Warnings</li>";
         foreach($curFile as $sheet) $content .= "<li>{$sheet->sheet_name}</li>";
         $content .= '</ul>';
         //output the errors
         $content .= "<div class='error' style='text-align: left;'>". implode("<br />\n", $mergedErrors) .'</div>';
         if(count($curFile[$mainSheetIndex]->warnings)) $content .= "<div class='warnings'>". implode("<br />\n", $curFile[$mainSheetIndex]->warnings) .'</div>';
         foreach($curFile as $sheet) $content .= "<div>{$sheet->htmlData}</div>";
         $content .= "</div>";
      }
      else{
         //create a confirmation step
$content = <<< CONTENT
   <div id='addinfo'>Please review the uploaded spreadsheet and confirm whether you want to upload it the way it is!</div>
   <div id='footer_links'><input type='button' name='confirm' value='Confirm' /><input type='button' name='confirm' value='Cancel' /></div>
   <input type='hidden' name='uploadedFile' value='$file' /> <input type='hidden' name='curModule' value='$module' />
   <div id='all_data'>
      <ul>
CONTENT;
         if(count($curFile[$mainSheetIndex]->warnings)) $content .= "<li>Warnings</li>";
         foreach($curFile as $sheet) $content .= "<li>{$sheet->sheet_name}</li>\n";
         $content .= "</ul>\n";
         if(count($curFile[$mainSheetIndex]->warnings)) $content .= "<div class='warnings'>". implode("<br />\n", $curFile[$mainSheetIndex]->warnings) .'</div>';
         foreach($curFile as $sheet) $content .= "<div>{$sheet->htmlData}</div>\n";
         $content .= "</div>\n";
      }

      $content .= "<script type='text/javascript' src='". OPTIONS_COMMON_FOLDER_PATH ."jqwidgets/jqwidgets/jqxtabs.js'></script>";
$content .="
   <script type='text/javascript'>
      $('[name=confirm]').click(LimsUploader.confirmUpload);
      $('#all_data').jqxTabs({ width: '99%', height: 500, position: 'top', theme: Main.theme });
   </script>";
      $this->HomePage('', $content);
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

      //ensure that we have the necessary data descriptors
      //also include data from other sheets
      $extraData = array();
      foreach($curFile as $index => $sheet){
         if($sheet->isMain) $mainSheetIndex = $index;
         else if($sheet->hasLinkedSheets()) $extraData[$sheet->sheet_name] = $sheet->getData();
         if($_GET['module'] == 'elisa') $sheet->NormalizeData(array());
      }

      //lets rename the file to the
      $curFile[$mainSheetIndex]->finalUploadedFile = "$path/{$pi['basename']}";
      $curFile[$mainSheetIndex]->finalUploadedFileLink = "LimsUploader/{$pi['basename']}";
      rename($file, $curFile[$mainSheetIndex]->finalUploadedFile);
      if($_GET['module'] != 'elisa') $curFile[$mainSheetIndex]->NormalizeData($extraData);

      //now we upload the data
      if($_GET['module'] == 'elisa'){
         foreach($curFile as $index => $sheet){
            $res = $sheet->UploadData();
            if($res !== 0){
               $this->HomePage($res);
               return;
            }
         }
         $this->HomePage("The data has been uploaded successfully.");
         return;
      }
      else{
         $res = $curFile[$mainSheetIndex]->UploadData();
         if($res === 0) $this->HomePage("The data has been uploaded successfully.");
         else $this->HomePage($res);
         return;
      }
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
