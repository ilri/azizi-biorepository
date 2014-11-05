<?php

/**
 * The main class of the system. All other classes inherit from this main one
 *
 * @category   AVID
 * @package    LabelPrinter
 * @author     Kihara Absolomon <a.kihara@cgiar.org>
 * @since      v0.2
 */

class LabelPrinter extends Repository{

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

   public function  __construct($DBase) {
      $this->Dbase = $DBase;
   }

   public function TrafficController(){
      if(OPTIONS_REQUEST_TYPE == 'normal'){
         echo "<script type='text/javascript' src='js/label_printer.js'></script>";
         echo "<script type='text/javascript' src='" . OPTIONS_COMMON_FOLDER_PATH . "jquery.form.js' /></script>";
         echo "<script type='text/javascript' src='" . OPTIONS_COMMON_FOLDER_PATH . "jquery.flexigrid/flexigrid.pack.js' /></script>";
         echo "<link rel='stylesheet' type='text/css' href='" . OPTIONS_COMMON_FOLDER_PATH . "jquery.flexigrid/css/flexigrid.pack.css' />";
      }

      $this->Dbase->CreateLogEntry("Starting labels printing module ".OPTIONS_REQUESTED_SUB_MODULE, "info");
      if(OPTIONS_REQUESTED_SUB_MODULE == '') $this->HomePage();
      elseif(OPTIONS_REQUESTED_SUB_MODULE == 'generate') $this->GenerateLabels();
      elseif(OPTIONS_REQUESTED_SUB_MODULE == 'fetch') $this->FetchPrintedLabels();
      elseif(OPTIONS_REQUESTED_SUB_MODULE == 'ajax' && OPTIONS_REQUESTED_ACTION == 'download_recharge_file')$this->downloadRechargeSheet();
   }

   /**
    * Create the home page for generating the labels
    */
   private function HomePage($addinfo = ''){
      Repository::jqGridFiles();//Really important if you want jqx to load
      
      $res = $this->Dbase->GetColumnValues('labels_settings', array('id', 'label_type'));
      if(is_array($res)){
         $ids=array(); $vals=array();
         foreach($res as $t){
            $ids[]=$t['id']; $vals[]=$t['label_type'];
         }
         $settings = array('items' => $vals, 'values' => $ids, 'firstValue' => 'Select One', 'name' => 'labelTypes', 'id' => 'labelTypesId');
         $labelTypes = GeneralTasks::PopulateCombo($settings);
      }
      else $addinfo = "There was an error while fetching data from the database.";

      //get the projects
      $projects = array( array('id' => 999, 'name' => 'Add New') );
      $res = $this->Dbase->GetColumnValues('lcmod_projects', array('id', 'project_name', 'charge_code'));
      if(is_array($res)){
         $ids=array(); $vals=array();
         foreach($res as $t) $projects[] = array('id' => $t['id'], 'name' => $t['project_name'], 'charge_code' => $t['charge_code']);
      }
      else $addinfo = "There was an error while fetching data from the database.";

      //get the projects' people
      $users = array( array('id' => 999, 'name' => 'Add New') );
      $query = "select a.id as userId, b.id as projectId, d.name from lcmod_projectUsers as a inner join lcmod_projects as b on a.project_id = b.id
         inner join lcmod_users as c on a.user_id = c.id inner join ". Config::$config['azizi_db'] .".contacts as d on c.contact_id=d.count group by d.name";
      $res = $this->Dbase->ExecuteQuery($query);
      if($res == 1) $addinfo = "There was an error while fetching data from the database.";
      else{
         $ids=array(); $vals=array();
         foreach($res as $t) $users[] = array('id' => $t['userId'], 'project_id' => $t['projectId'], 'name' => $t['name']);
      }

      //get the projects' people
      $query = "select id, prefix from `labels_coding` group by prefix order by prefix";
      $res = $this->Dbase->ExecuteQuery($query);
      if($res == 1) $addinfo = "There was an error while fetching data from the database.";
      else{
         $ids = array(999); $vals = array('Add New');
         $prefix = array( array('id' => 999, 'name' => 'Add New') );
         foreach($res as $t){
            $ids[]=$t['id']; $vals[]=$t['prefix'];
            $prefix[] = array('id' => $t['id'], 'name' => $t['prefix']);
         }
      }
      $settings = array('items' => $vals, 'values' => $ids, 'firstValue' => 'Select One', 'name' => 'prefix', 'id' => 'prefixId');
      $prefixes = GeneralTasks::PopulateCombo($settings);

      $labelTypes = "$labelTypes <a href='javascript:;' onClick='LabelPrinter.labelSetup();'>Setup</a>";
      
      $query = "select project, sum(total) as total, type from labels_printed where rc_timestamp is null group by project, type";
      $projectLabels = $this->Dbase->ExecuteQuery($query);
      
      $query = "select id, label_type from labels_settings";
      $realLabelTypes = $this->Dbase->ExecuteQuery($query);

      $addinfo = ($addinfo != '') ? "<div id='addinfo'>$addinfo</div>" : '';
?>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.pager.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxdropdownlist.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.columnsresize.js"></script>
<script type="text/javascript" src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jqwidgets/jqxgrid.sort.js"></script>
<script type='text/javascript' src="<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery/jquery.ui/js/jquery-ui.min.js" /></script> <!-- used by autocomplete for the boxes label text field -->
<link rel='stylesheet' type='text/css' href='<?php echo OPTIONS_COMMON_FOLDER_PATH ?>jquery.ui/css/smoothness/jquery-ui.css' />
<div id='home'>
  <h2 class="center">Generate Labels</h2>
  <?php
  echo $addinfo;
  ?>
  <form enctype="multipart/form-data" name="upload" action="index.php?page=labels&do=generate" method="POST" onSubmit="LabelPrinter.generateLabels()">
     <div id='generate'>
         <div id='general'>
            <legend>General</legend>
            <div>
               <div><label>Label Type:</label><?php echo $labelTypes; ?></div>
               <div><label>Purpose:</label><input type='radio' name='purpose' value='testing' /> Testing <input type='radio' name='purpose' value='final' /> Final</div>
               <div><label>Duplicates:</label><input type='radio' name='duplicates' value='allow' /> Allow <input type='radio' name='duplicates' value='not_allowed' /> Not Allowed</div>
               <div class='links'>
                  <input type='submit' value='Generate' name='generate' /><input type='reset' value='Cancel' name='cancel' />
              </div>
            </div>
         </div>
         <div id='sequence'>
            <legend>Labels Sequence</legend>
            <div>
               <div><label>Prefix</label><span id='prefix_place'><?php echo $prefixes; ?></span></div>
               <div><label>Count</label><input type='text' name='count' value='' size='5' /></div>
            </div>
         </div>
         <div id='info'>
            <legend>Purpose of the labels</legend>
         </div>
     </div>
  </form>
  <?php
  if(isset($_SESSION['user_type']) && (in_array("Biorepository Manager", $_SESSION['user_type']) || in_array("Super Administrator", $_SESSION['user_type']))) {
     echo "<div class='center' style='margin-top:10px;margin-left:700px;margin-bottom:10px;'><button id='recharge_btn' type='button' class='btn btn-primary'>Recharge Printed Labels</button></div>";
  }
  ?>
  <!--div><a href='javascript:;' onClick='LabelPrinter.toggleMe("printed_labels");'>Printed Labels</a></div-->
  <div id='lower_panel' class='hidden'>
      <div id='printed_labels'>&nbsp;</div>
  </div>
  <div id="recharge_projects" style="display: none; position: absolute; z-index: 2000; width: 500px; height: auto; background: #ffffff; padding: 20px;">
     <img id="recharge_cancel_btn" src="images/ic_action_cancel.png" style="position: relative; left: 480px; width: 15px; height: 15px; cursor: pointer;" />
     <div class="form-group">
        <label for="selected_recharge_project">Project</label>
        <select id="selected_recharge_project" style="height: 30px;">
           <option value="">Select a Project</option>
            <?php
            foreach($projects as $currProject){
               if($currProject['id'] != 999) echo "<option href='#' value='".$currProject['id']."'>".$currProject['name']."</option>";
            }
            ?>
         </select>
     </div>
     <div class="form-group">
        <label for="label_type">Label type:</label>
        <select id="label_type" style="height: 30px;">
           <option value="">Select a Label Type</option>
           <?php
           foreach($realLabelTypes as $currLabel){
              echo "<option value='".$currLabel['id']."'>".$currLabel['label_type']."</option>";
           }
           ?>
        </select>
     </div>
     <div>
        <label for="number_printed">Number printed:</label>
        <input type="number" id="number_printed" disabled="disabled" class="input-medium" style="height: 30px;" />
     </div>
     <div class="form-group">
        <label for="price">Price (USD)</label>
        <input id="price" class="input-medium" type="number" style="height: 30px;" />
     </div>
     <div class="form-group">
        <label for="charge_code">Charge code:</label>
        <input id="charge_code" class="input-large" type="text" style="height: 30px;" />
     </div>
     <div class="center" style="margin-left: 300px; margin-top: 10px;">
        <button type="button" id="download_recharge_btn" class='btn btn-primary'>Download Recharge Sheet</button>
     </div>
     
  </div>

<script type='text/javascript'>
      $('[name=purpose]').bind('click', LabelPrinter.labelsPurpose);
      
      Main.projectLabels = <?php echo json_encode($projectLabels);?>;
      Main.chargeCodes = <?php echo json_encode($projects); ?>;
      
      //changing the prefix
      $('#prefixId').live('change', function(){
         if($('#prefixId').val() == 999){
            $('#prefix_place').html("<input type='text' name='prefix' id='prefixId' size='5' /><a href='javascript:;' class='cancel'><img src='images/close.png' /></a>");
            $('#prefix_place .cancel').live('click', function(){
               var settings = {name: 'prefix', id: 'prefixId', data: Main.prefix, initValue: 'Select One'};
               var prefix = Common.generateCombo(settings);
               $('#prefix_place').html(prefix);
            })
         }
      });
      //$('[name=generate]').bind('click', LabelPrinter.generateLabels);
      Main.projects = <?php echo json_encode($projects); ?>;
      Main.users = <?php echo json_encode($users); ?>;
      Main.prefix = <?php echo json_encode($prefix); ?>;
      $('#whoisme .back').html('<a href=\'?page=home\'>Back</a>');

      $("#printed_labels").flexigrid({
         url: 'mod_ajax.php?page=labels&do=fetch',
         dataType: 'json',
         colModel : [
            {display: 'Date', name : 'date', width : 120, sortable : true, align: 'left'},
            {display: 'Prefix', name : 'prefix', width : 40, sortable : true, align: 'center'},
            {display: 'First Label', name : 'first_label', width : 70, sortable : true, align: 'left'},
            {display: 'Last Label', name : 'last_label', width : 70, sortable : true, align: 'left'},
            {display: 'Total', name : 'total', width : 50, sortable : true, align: 'left', hide: false},
            {display: 'Project', name : 'project', width : 50, sortable : false, align: 'left'},
            {display: 'Requested By', name : 'requester', width : 160, sortable : true, align: 'left', hide: false}
            ],
         searchitems : [
            {display: 'Prefix', name : 'prefix'}
         ],
         sortname: "prefix",
         sortorder: "asc",
         usepager: true,
         title: 'Printed Labels',
         useRp: true,
         rp: 10,
         showTableToggleBtn: false,
         rpOptions: [10, 20, 50], //allowed per-page values
         width: 900,
         height: 260,
         singleSelect: true
      });
      
      $("#recharge_cancel_btn").click(function(){
         $("#recharge_projects").hide();
      });
      $("#period_ending").datepicker({dateFormat: 'yy-mm-dd'});
      $("#recharge_btn").click(function(){
         LabelPrinter.showRechargeProjects();
      });
      $("#download_recharge_btn").click(function(){
         LabelPrinter.downloadRechargeSheet();
      });
      $("#selected_recharge_project").change(function(){
         console.log("changed");
         var projectId = $("#selected_recharge_project").val();
         console.log(projectId);
         console.log(Main.lastRecharges);
         
         for(var i = 0; i < Main.chargeCodes.length; i++){
            if(Main.chargeCodes[i].id = projectId){
               $("#charge_code").val(Main.chargeCodes[i].charge_code);
            }
         }
      });
      $("#label_type").change(function(){
         $("#number_printed").val("0");
         if($("#selected_recharge_project").val().length > 0 && $("#label_type").val().length > 0){
            for(var i = 0; i < Main.projectLabels.length; i++){
               if(Main.projectLabels[i].project == $("#selected_recharge_project").val() && $("#label_type").val() == Main.projectLabels[i].type){
                  $("#number_printed").val(Main.projectLabels[i].total);
               }
            }
         }
      });
      $("#selected_recharge_project").change(function(){
         $("#number_printed").val("0");
         if($("#selected_recharge_project").val().length > 0 && $("#label_type").val().length > 0){
            for(var i = 0; i < Main.projectLabels.length; i++){
               if(Main.projectLabels[i].project == $("#selected_recharge_project").val() && $("#label_type").val() == Main.projectLabels[i].type){
                  $("#number_printed").val(Main.projectLabels[i].total);
               }
            }
         }
      });
   </script>
<?php
   }

   /**
    * Generates the labels as requested by the users
    *
    * @return type
    */
   private function GenerateLabels(){
      $this->Dbase->CreateLogEntry("Generate Labels called", "info");
      if(isset($this->labelPrintingError)){
         $this->HomePage($this->labelPrintingError['message']);
         return;
      }

      //All our labels will be sequential! No Random. But since the code was already written for Random printing, it might be handy, so lets just bypass it
      $_POST['sequence'] = 'sequential';


      //validate the data passed from the client
      $data = array();
      $data['labelTypes'] = $_POST['labelTypes'];

      if($_POST['purpose'] == 'final'){
         if(!preg_match('/^[0-9]([0-9])?|[a-z]+$/i', $_POST['project'])){
            $this->labelPrintingError = array('error' => true, 'message' => "<span class='error'>Please select or enter the project in which these labels will be used!</span>");
            return;
         }
         if(!preg_match('/^[0-9]([0-9])?|[a-z\s]+$/i', $_POST['requester'])){
            $this->labelPrintingError = array('error' => true, 'message' => "<span class='error'>Please select or enter the person who requested these labels!</span>");
            return;
         }
         if(!preg_match('/^[0-9]([0-9])?$/', $_POST['project'])){
            if(!isset($_POST['new_charge_code'])){
               $this->labelPrintingError = array('error' => true, 'message' => "<span class='error'>Please enter the charge code for the new project!</span>");
               return;
            }
            elseif(!preg_match('/^[a-z]{2}[0-9]{2}\-(nbo|add|hyd|del|ibd|ind)\-[0-9a-z]{6,9}$/i', $_POST['new_charge_code'])){
               $this->labelPrintingError = array('error' => true, 'message' => "<span class='error'>Please enter the correct charge code for the new project!</span>");
               return;
            }
            $data['charge_code'] = $_POST['new_charge_code'];
         }
         else{
            $data['charge_code'] = $this->Dbase->GetSingleRowValue('lcmod_projects', 'charge_code', 'id', $_POST['project']);
            if($data['charge_code'] == -2){
               $this->labelPrintingError = array('error' => true, 'message' => $this->Dbase->lastError);
               return;
            }
         }
         //$data['comments'] = $this->Dbase->dbcon->real_escape_string($_POST['comments']);
         $data['project'] = $_POST['project'];
         $data['requester'] = $_POST['requester'];
      }

      $labelFile = $_COOKIE['labels_printer']. '_' .date('YmdHis') .'.xlsx';
      if($_POST['sequence'] == 'random'){
         //we are expecting a file with the labels to generate
         $uploadedLabels = GeneralTasks::CustomSaveUploads('uploads/', 'labels', array('text/plain'), 10485760, array('uploaded_labels.txt'));
//      $this->Dbase->CreateLogEntry("Curated Data: \n".print_r($uploadedLabels, true), 'debug');
         if(is_string($uploadedLabels)){
            $this->labelPrintingError = array('error' => true, 'message' => $uploadedLabels);
            return;
         }
         elseif($uploadedLabels == 0){
            $this->labelPrintingError = array('error' => true, 'message' => "No file selected for uploading");
            return;
         }

         $labels = $this->ExtractLabelsToPrint($uploadedLabels[0]);
         if(is_string($labels)){
            $this->labelPrintingError = array('error' => true, 'message' => $labels);
            return;
         }
//         $this->Dbase->CreateLogEntry('Extracted Labels - '. print_r($labels, true), 'debug');

         //check whether the labels need to be unique and confirm that they are unique should there be a need for them to be unique
         if($_POST['duplicates'] == 'not_allowed'){
            $res = $this->ConfirmUniqueRandomLabels($labels['labels']);
            if(is_string($res)){
               $this->labelPrintingError = array('error' => true, 'message' => $res);
               return;
            }
            elseif($res == 1){
               $this->labelPrintingError = array('error' => true, 'message' => "Fatal! The specified random labels are not unique as requested!");
               return;
            }
         }
//         $this->Dbase->CreateLogEntry('Extracted Labels - '. print_r($labels, true), 'debug');
         $data['labels'] = $labels['labels'];
         $data['count'] = count($labels['labels']);
         if(count($labels['prefixes']) == 1) $data['prefix'] = $labels['prefixes'][0];
//      $this->Dbase->CreateLogEntry("Curated Data: \n".print_r($data, true), 'debug');

         //we cool now, just send the labels to the perl script to be generated
         $command = "./printLabelsLinux.pl {$_POST['sequence']} {$_POST['purpose']} {$_POST['labelTypes']} $labelFile {$uploadedLabels[0]}";
         $cool_filename = 'Random_Labels_'. date('Ymd_His') .'xlsx';
      }
      elseif($_POST['sequence'] == 'sequential'){
         //we expecting the prefix and the count
         if(!preg_match('/^[1-9]([0-9])?|[a-z]{3,4}$/i', $_POST['prefix'])){
            if($_POST['prefix'] == 0 || $_POST['prefix'] == 999){
               $this->labelPrintingError = array('error' => true, 'message' => "Please select the prefix to use for the labels.");
               return;
            }
            else{
               $this->labelPrintingError = array('error' => true, 'message' => "Please enter the prefix to use for the labels.");
               return;
            }
         }

         if(preg_match('/^[1-9]([0-9])?$/i', $_POST['prefix'])){
            //get the last printed label, and send the data to the perl script to generate the labels
            $labelSettings = $this->Dbase->GetColumnValues('labels_coding', array('last_count', 'prefix', 'length'), "where id = {$_POST['prefix']}");
            if($labelSettings == 1){
               $this->labelPrintingError = array('error' => true, 'message' => $this->Dbase->lastError);
               return;
            }
            $labelSettings = $labelSettings[0];
         }
         else{
            //check if we already have labels with this prefix
            $labelSettings = $this->Dbase->GetColumnValues('labels_coding', array('last_count', 'prefix', 'length'), "where lower(prefix)=lower('{$_POST['prefix']}')");
            if($labelSettings == 1){
               $this->labelPrintingError = array('error' => true, 'message' => $this->Dbase->lastError);
               return;
            }
            elseif(count($labelSettings) != 0) $labelSettings = $labelSettings[0];
            else $labelSettings = array('last_count' => 0, 'prefix' => strtoupper($_POST['prefix']), 'length' => 9);//default length for barcodes is 9 characters
         }
         $data['prefix'] = $labelSettings['prefix'];
         $data['count'] = $_POST['count'];

         $data['first_label'] = ($labelSettings['last_count'] == 0) ? 1 : $labelSettings['last_count'];
         $padding = str_pad($data['first_label'], 9 - strlen($data['prefix']), "0", STR_PAD_LEFT);
         $data['first_label'] = $data['prefix'] . $padding;


         $data['last_count'] = $labelSettings['last_count'] + $data['count'];
         $padding = str_pad($data['last_count'], 9 - strlen($data['prefix']), '0', STR_PAD_LEFT);
         $data['last_label'] = $data['prefix'] . $padding;
         
         //we cool now, just send the labels to the perl script to be generated
         $command = "./printLabelsLinux.pl {$_POST['sequence']} {$_POST['purpose']} {$_POST['labelTypes']} $labelFile {$labelSettings['prefix']} {$labelSettings['last_count']} {$_POST['count']} {$labelSettings['length']}";//TODO: change this
         $cool_filename = "{$data['first_label']}-{$data['last_label']}.xlsx";
      }


      exec($command, $output, $return_var);
      $this->Dbase->CreateLogEntry('command:' . $command, 'info');
      $this->Dbase->CreateLogEntry(print_r($output, true), 'info');
      if(count($output) != 0) {
         $this->Dbase->CreateLogEntry("Print label error: Command: $command\n" . print_r($output, true), 'fatal');
         $this->labelPrintingError = array('error' => true, 'message' => "<span class='error'>" . $output[0] . '</span>');
         return;
      }

      //check if there is need to save these labels
      if($_POST['purpose'] == 'final'){
         $res = $this->SavePrintedLabels($data);
         if(is_string($res)){
            $this->labelPrintingError = array('error' => true, 'message' => "<span class='error'>$res</span>");
            return;
         }
      }
      elseif($_POST['purpose'] == 'testing'){/* We do nothing so far*/}
      else{
         $this->labelPrintingError = array('error' => true, 'message' => 'Stop tampering with the post data!');
         return;
      }

      //now offer the label for download
      header("Content-Disposition: attachment; filename=$cool_filename");
      header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
      header('Content-Length: ' . filesize($labelFile));
      header('Content-Transfer-Encoding: binary');
      header('Cache-Control: must-revalidate');
      header('Pragma: public');
      ob_clean();
      flush();
      readfile($labelFile);
      unlink($labelFile);
      die();
   }

   /**
    * Extracts the list of labels from a file
    *
    * @param   string   $filename   The path to the file where the labels are saved
    * @return  mixed    Returns an array with the extracted labels on success, else it returns a string with an error message
    * @since   v0.2
    */
   private function ExtractLabelsToPrint($filename){
      $fd = fopen($filename, 'rt');
      if(!$fd) return "There was an error while opening the input file for printing.";
      $extractedLabels = array();
      $prefixes = array();

      while($line = fgets($fd)){
         $labels = preg_split('/\t/', $line);
         $labels = array_map('trim', $labels);
         $extractedLabels = array_merge($extractedLabels, $labels);

         //get the prefix
         foreach($labels as $t){
            $ts = preg_split('/[0-9]+/i', $t);
            $prefixes[] = $ts[0];
         }
      }
      fclose($fd);

      $prefixes = array_unique($prefixes);
      return array('labels' => $extractedLabels, 'prefixes' => $prefixes);
   }

   /**
    * Given an array with specified random labels, it checks that these random labels are unique and have not been printed before.
    *
    * @param   array    $labels  An array with the labels to check whether they are unique
    * @return  mixed    Returns 0 incase the labels are unique, or a string with an error message if there was an error or 1 when the labels are not unique
    */
   private function ConfirmUniqueRandomLabels($labels){
      $res = file_put_contents('labels.txt', implode("\n", $labels));
      if(!$res) return "There was an error while creating a temporary file.";

      $command = "grep -ir -f labels.txt ". Config::$printedRandomLabels;
      $this->Dbase->CreateLogEntry('command:' . $command, 'debug');
      exec($command, $output, $exitCode);
      if(count($output) != 0) {
         $this->Dbase->CreateLogEntry("Grep Command: $command\n" . print_r($output, true), 'fatal');
         return "Well this is unexpected! The search command returned with an error.";
      }
      else return 0;
   }

   private function SavePrintedLabels($data){
      if(!preg_match('/[0-9]+/', $data['project'])){
         //lets add the project and the charge code
         $cols = array('project_name', 'charge_code');
         $colvals = array($data['project'], $data['charge_code']);
         $res = $this->Dbase->InsertOnDuplicateUpdate('lcmod_projects', $cols, $colvals);
         if($res == 0) return $this->Dbase->lastError;
         $data['project'] = $res;
      }

      if(preg_match('/^[0-9]{1,2}$/', $data['requester'])){
         $this->Dbase->query = "select a.id as userId, b.id as projectId, d.name from lcmod_projectUsers as a inner join lcmod_projects as b on a.project_id = b.id
         inner join lcmod_users as c on a.user_id = c.id inner join ". Config::$config['azizi_db'] .".contacts as d on c.contact_id=d.count where a.id={$data['requester']} group by a.id";
         $res = $this->Dbase->ExecuteQuery();
         if($res == 1) return "There was an error while fetching data from the database.";
         else $data['requester'] = $res[0]['name'];
         $this->Dbase->CreateLogEntry(print_r($res, true), 'debug');
      }

      //incase we are having random labels dont save them in the coding
      if($_POST['sequence'] == 'sequential'){
         //insert the prefix and the last label
         $cols = array('prefix', 'last_count');
         $colvals = array($data['prefix'], $data['last_count']);
         $res = $this->Dbase->InsertOnDuplicateUpdate('labels_coding', $cols, $colvals);
         if($res == 0) return $this->Dbase->lastError;
      }

      //insert the printed labels
      $cols = array('date', 'user', 'requester', 'prefix', 'first_label', 'last_label', 'copies', 'total', 'project', 'remarks', 'type');
      $colvals = array(
         date('Y-m-d H:i:s'), $_SESSION['user_id'], $data['requester'], $data['prefix'], $data['first_label'],
         $data['last_label'], 1, $data['count'], $data['project'], $data['comments'], $data['labelTypes']
      );
      $res = $this->Dbase->InsertOnDuplicateUpdate('labels_printed', $cols, $colvals);
      if($res == 0) return $this->Dbase->lastError;

      //update our random printed labels database/file
      return 0;
   }

   /**
    * Fetches the details of collection advices from the databases and creates a JSON object to be sent to the users
    */
   private function FetchPrintedLabels(){
      $this->Dbase->CreateLogEntry(print_r($_POST, true), "fatal");
      $this->Dbase->CreateLogEntry(print_r($_GET, true), "fatal");
      if($_POST['query'] != '') $criteria = "where {$_POST['qtype']} like '%{$_POST['query']}%'";
      else $criteria = '';

      //get the start and the number to selectc
      $start = ($_POST['page']-1)*$_POST['rp'];
      $sort = "";
      if(strlen($_POST['sortname']) > 0) $sort = "order by {$_POST['sortname']} {$_POST['sortorder']}";
      $query = "select * from labels_printed as a inner join lcmod_projects as b on a.project = b.id $criteria $sort";
      $query2 = "$query limit $start,{$_POST['rp']}";
      $data = $this->Dbase->ExecuteQuery($query2);
      if($data == 1) die(json_encode(array('error' => true)));
      //for the total count
      $dataCount = $this->Dbase->ExecuteQuery($query);
      if($dataCount == 1) die(json_encode(array('error' => true)));

      $rows = array();
      foreach($data as $t){
         $rows[] = array('id' => $t['id'], 'cell' => array($t['date'], $t['prefix'], $t['first_label'], $t['last_label'], $t['total'], $t['project_name'],
            $t['requester']));
      }
      $content = array(
         'total' => count($dataCount),
         'page' => $_POST['page'],
         'rows' => $rows
      );

      die(json_encode($content));
   }
   
   /**
    * This function generates a csv file containing the recharges to a project
    */
   private function downloadRechargeSheet(){
      
      //var url = "mod_ajax.php?page=labels&do=ajax&action=download_recharge_file&project="+projectID+"&type="+type+"&charge_code="+chargeCode+"&price="+price;
      $project = $_REQUEST['project'];
      $type = $_REQUEST['type'];
      $chargeCode = $_REQUEST['charge_code'];
      $price = $_REQUEST['price'];
      
      if(isset($_SESSION['user_type']) && (in_array("Biorepository Manager", $_SESSION['user_type']) || in_array("Super Administrator", $_SESSION['user_type']))) {//check if user is authed to charge barcodes
         if(strlen($project) > 0 && strlen($type) > 0 && strlen($chargeCode) > 0 && strlen($price) > 0){
            //get all barcodes
            $query = "select a.id as printing_id, b.project_name, c.label_type, a.date as date_printed, a.total as labels_printed"
                    . " from labels_printed as a"
                    . " inner join lcmod_projects as b on a.project = b.id"
                    . " inner join labels_settings as c on a.type = c.id"
                    . " where a.project = :project and a.type = :type and rc_timestamp is null";
            $result = $this->Dbase->ExecuteQuery($query, array("project" => $project, "type" => $type));
            
            if(is_array($result)){
               for($i = 0; $i < count($result); $i++){
                  $result[$i]['cost_per_label'] = $price;
                  $result[$i]['total_price'] = $price * $result[$i]['labels_printed'];
                  $result[$i]['charge_code'] = $chargeCode;
                  
                  //TODO: run query for updating recharging data
                  $query = "update labels_printed"
                          . " set rc_timestamp = now(), rc_price = :price, rc_charge_code = :charge_code"
                          . " where id = :id";
                  $this->Dbase->ExecuteQuery($query, array("price" => $price, "charge_code" => $chargeCode, "id" => $result[$i]['printing_id']));
                  
               }
               
               if(count($result) > 0){
                  $csv = $this->generateCSV($result);
               }
               else {
                  $csv = "No labels for recharging found";
               }
               
               $fileName = "labels_recharge_".$project."_".$type.".csv";
               $this->Dbase->CreateLogEntry("File name for labels recharging = ".$fileName, "info");
               $this->Dbase->CreateLogEntry("Size of file = ".filesize("/tmp/".$fileName), "info");
               
               file_put_contents("/tmp/".$fileName, $csv);
               header('Content-type: document');
               header('Content-Disposition: attachment; filename='. $fileName);
               header("Expires: 0"); 
               header("Cache-Control: must-revalidate, post-check=0, pre-check=0"); 
               header("Content-length: " . filesize("/tmp/".$fileName));
               header('Content-Transfer-Encoding: binary');
               header('Pragma: public');
               //header('Content-Transfer-Encoding: binary');
               ob_clean();
               flush();
               readfile("/tmp/" . $fileName);
               
               if(count($result) > 0){//if we actually have at least one item to recharge
                  $emailSubject = "Recharging ".$result[0]['label_type']." Labels to ".$result[0]['project_name'];
                  $emailMessage = "Find attached a spreadsheet containing recharges made to ".$result[0]['project_name']." on ".date("F jS, Y")." for ".$result[0]['label_type']." labels.";
                  $this->sendRechargeEmail(Config::$managerEmail, $emailSubject, $emailMessage, "/tmp/".$fileName);
               }
               else{
                  $lTypeName = $this->Dbase->ExecuteQuery("select label_type from labels_settings where id = :id", array("id" => $type));
                  $projectName = $this->Dbase->ExecuteQuery("select project_name from lcmod_projects where id = :id", array("id" => $project));
                  
                  $emailSubject = "Recharging ".$lTypeName[0]['label_type']." Labels to ".$projectName[0]['project_name'];
                  $emailMessage = "No ".$lTypeName[0]['label_type']." labels to be recharged to ".$projectName[0]['project_name']." found.";
                  $this->sendRechargeEmail(Config::$managerEmail, $emailSubject, $emailMessage);
               }
               
               unlink("/tmp/" . $fileName);
            }
            else {
               $this->Dbase->CreateLogEntry("An error occured while trying to fetch labels for recharging","fatal");
            }
         }
         else {
            $this->Dbase->CreateLogEntry("One of the variables provided by the user is not correct ".print_r($_REQUEST,true),"fatal");
         }
      }
      else {
         $this->Dbase->CreateLogEntry("User is not permitted to recharge labels","fatal");
      }
   }
   
   /**
    * This function generates a csv string from a two dimensional associative array
    * 
    * @param type $array            The two dimensional array to be used to generate the csv string
    * @param type $headingsFromKeys Set to true if you want to get headings from the keys in the associative array
    * @return string                Comma seperated string corresponding to the array. Will be empty if array is empty or something goes wrong
    */
   private function generateCSV($array, $headingsFromKeys = true){
      $csv = "";
      if(count($array) > 0){
         $colNum = count($array[0]);
         
         if($headingsFromKeys === true){
            $keys = array_keys($array[0]);
            $csv .= "\"".implode("\",\"", $keys)."\"\n";
         }
         
         foreach($array as $currRow){
            $csv .= "\"".implode("\",\"", $currRow)."\"\n";
         }
      }
      
      return $csv;
   }
   
   /**
    * This function sends emails using the biorepository's email address. Duh
    * 
    * @param type $address Email address of the recipient
    * @param type $subject Email's subject
    * @param type $message Email's body/message
    * @param type $file    Attachements for the email. Set to null if none
    */
   private function sendRechargeEmail($address, $subject, $message, $file = null){
      if($file != null){
         shell_exec('echo "'.$message.'"|'.Config::$muttBinary.' -F '.Config::$muttConfig.' -s "'.$subject.'" -a '.$file.' -- '.$address);
      }
      else {
         shell_exec('echo "'.$message.'"|'.Config::$muttBinary.' -F '.Config::$muttConfig.' -s "'.$subject.'" -- '.$address);
      }
   }
}
?>