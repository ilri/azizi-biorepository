<?php

/**
 * A class for uploading of the cell lines to labcollector
 */
class CellLines extends SpreadSheet {

   /**
    * @var type
    */
   protected $includeUndefinedColumns = true;

   /**
    * @var  boolean     Whether to attempt to link sheets within the worksheet or not
    */
   protected $linkedSheets = false;

   /**
    * @var   string   The column name in labcollector that has our refence value.
    */
   protected $lcReference = '';

   /**
    * @var array  Holds possible metadata fields and columns and their validators and whether they are required or not
    */
   protected $metadata = array(
      'columns' => array(
         array('name' =>'name', 'regex' => '/^cell\s+line\s+name$/i', 'required' => true, 'lc_ref' => 'name'),
         array('name' =>'animal_id', 'regex' => '/^animal\s+id$/i', 'required' => true, 'lc_ref' => 'Animal_ID'),
         array('name' =>'record_type', 'regex' => '/^type/i', 'required' => true, 'lc_ref' => 'Record_Type'),
         array('name' =>'strain', 'regex' => '/^strain$/i', 'required' => true, 'lc_ref' => 'Strain_Name'),
         array('name' =>'organism', 'regex' => '/^source\s+organism$/i', 'required' => true, 'lc_ref' => 'org'),
         array('name' =>'strain_no', 'regex' => '/^strain\s+no$/i', 'required' => true, 'lc_ref' => 'Strain_No'),
         array('name' =>'freezing_date', 'regex' => '/^freezing\s+date$/i', 'data_regex' => '/^(0?[1-9]|[12][0-9]|3[01])[\/\-](0?[1-9]|1[012])[\/\-]20\d{2}$/', 'required' => true, 'lc_ref' => 'Freezing_Date'),
         array('name' =>'storage_box', 'regex' => '/^storage\s+box$/i', 'required' => true, 'lc_ref' => 'box_id'),
         array('name' =>'sample_pos', 'regex' => '/^position\s+in\s+box$/i', 'data_regex' => '/^[1-9]?[0-9]|100|[a-z][0-9]0?$/i', 'required' => true, 'lc_ref' => 'box_details'),
         array('name' =>'comments', 'regex' => '/^comments$/i', 'required' => false, 'lc_ref' => 'origin'),
         /*array('name' =>'latitude', 'regex' => '/^latitude$/i', 'required' => true, 'lc_ref' => 'Latitude'),
         array('name' =>'longitude', 'regex' => '/^longitude$/i', 'required' => true, 'lc_ref' => 'Longitude'),*/
         array('name' =>'owner', 'regex' => '/^owner$/i', 'data_regex' => '/^[a-z\s\']+$/i', 'required' => true, 'lc_ref' => 'keeper')
      )
   );

   /**
    * @var array  An array with the fields that will be updated in labcollector
    */
   private $lcFields = array();

   /**
    * @var array  An array with the field names of the table columns
    */
   private $lcFieldsRef = array();

   /**
    * @var string    The comments field of this module
    */
   private $commentsField = 'origin';

   private $allTrays = array();
   private $allOrganism = array();

   /**
    * @var array  A list of all strains saved in the database
    */
   private $allStrains = array();

   /**
    * @var array  A list of all record types
    */
   private $allRecordTypes = array();
   private $extraCols = array();
   private $allProjects = array();
   private $owners = array();

   /**
    * The spreadsheet constructor
    *
    * @param   string   $path    The path to the main workbook
    * @param   string   $name    The name of the sheet
    * @param   object   $data    The data of the sheet
    */
   public function __construct($path, $name, $data) {
        parent::__construct($path, $name, $data);
    }

    /**
     * Normalizes the data for the samples so that all samples have consistent data.
     *
     * Enforces a relational DB references...
     *
     * @global object $Repository
     */
    public function NormalizeData($additions){
       global $Repository;

       //get the extra columns and their data
       foreach($this->metadata['columns'] as $key => $t){
          if($t['predefined'] != 1 && (!isset($t['lc_ref']) || $t['lc_ref'] == '')) $this->extraCols[] = $t['name'];
          else{
             // this is not an extra column, its one of the core columns, lets build the query to be used for adding data
             $this->lcFields[] = $t['lc_ref'];
             $this->lcFieldsRef[] = ":{$t['lc_ref']}";
          }
       }

       // get the record id from the database
       $recordQuery = 'select field_id from '. Config::$config['azizi_db'] .'.modules_custom where field_name = :field_name';
       $res = $Repository->Dbase->ExecuteQuery($recordQuery, array('field_name' => 'Record_Type'));
       if($res == 1){ $Repository->Dbase->RollBackTrans(); return $Repository->Dbase->lastError; }
       $recordId = $res[0]['field_id'];

       // get the strain id from the database
       $res = $Repository->Dbase->ExecuteQuery($recordQuery, array('field_name' => 'Strain_Name'));
       if($res == 1){ $Repository->Dbase->RollBackTrans(); return $Repository->Dbase->lastError; }
       $strainId = $res[0]['field_id'];

       //save all the boxes, organism and create a nested array from this data for later use
       foreach($this->data as $key => $t){
          //trays
          if(!array_key_exists($t['storage_box'], $this->allTrays)){
             $boxId = $this->IsBoxSaved($t['storage_box']);
             if(!is_numeric($boxId)) $this->errors[] = $boxId;
             else $this->allTrays[$t['storage_box']] = $boxId;
          }
          $this->data[$key]['storage_box'] = $this->allTrays[$t['storage_box']];

          //owner
          if(!array_key_exists($t['owner'], $this->owners)){
             $ownerId = $this->IsOwnerAdded($t['owner']);
             if(!is_numeric($ownerId)) $this->errors[] = $ownerId;
             else $this->owners[$t['owner']] = $ownerId;
          }
          $this->data[$key]['owner'] = $this->owners[$t['owner']];

          // organism
          if(!array_key_exists($t['organism'], $this->allOrganism)){
             $organismId = $this->IsOrganismSaved($t['organism']);
             if(!is_numeric($organismId)) $this->errors[] = $organismId;
             else $this->allOrganism[$t['organism']] = $organismId;
          }
          $this->data[$key]['organism'] = $this->allOrganism[$t['organism']];

          //project
          if(isset($t['project']) && !is_numeric($t['project'])){
            if(!array_key_exists($t['project'], $this->allProjects)){
               $projectId = $Repository->Dbase->AddProject(Config::$config['azizi_db'], $t['project']);
               if(!is_numeric($projectId)) $this->errors[] = $projectId;
               else{
                  $this->allProjects[$t['project']] = $projectId;
                  $this->data[$key]['project'] = $projectId;
               }
            }
            else $this->data[$key]['project'] = $this->allProjects[$t['project']];
          }

          // Record Type
          if(!array_key_exists($t['record_type'], $this->allRecordTypes)){
             $recordTypeId = $this->isCustomValueSaved($t['record_type'], $recordId);
             if(!is_numeric($recordTypeId)) $this->errors[] = $recordTypeId;
             else $this->allRecordTypes[$t['record_type']] = $recordTypeId;
          }
          $this->data[$key]['record_type'] = $this->allRecordTypes[$t['record_type']];


          // Strain Names
          if(!array_key_exists($t['strain'], $this->allStrains)){
             $strainNameId = $this->isCustomValueSaved($t['strain'], $strainId);
             if(!is_numeric($strainNameId)) $this->errors[] = $strainNameId;
             else $this->allStrains[$t['strain']] = $strainNameId;
          }
          $this->data[$key]['strain'] = $this->allStrains[$t['strain']];

          $this->data[$key]['sample_pos'] = $Repository->NumericPosition2LCPosition($t['sample_pos'], 100);

          //create the comments from the columns which go nowhere
          $descr = (isset($this->data[$key]['parent'])) ? "Parent Sample = {$this->data[$key]['parent']}" : '';
          foreach($this->extraCols as $col){
             if(preg_match('/'. preg_quote($t[$col], '/') .'/i', Config::$emptyValues) === 1) continue;
             if(preg_match('/'. preg_quote($col, '/') .'/i', Config::$columns2exclude) === 1) continue;
             $descr .= ($descr == '') ? '' : '<br />';
             $descr .= "$col = {$t[$col]}";
          }

          $descr .= "<br /><br /><b>Other Comments:</b>{$t['comments']}";
          //add a link to the original file we uploaded
          $descr .= "<br />Field File = <a target='_blank' href='http://azizi.ilri.org/viewSpreadSheet.php?file={$this->finalUploadedFileLink}&sheet={$this->sheet_index}&focused={$t['name']}#focused'>Field Data.xls</a>";
          $this->data[$key]['comments'] = "<div style='float:left; width:380px; margin-right:20px;'>$descr</div>";

          // lets create the column data association
          $this->data[$key]['row_data'] = array();
          foreach($this->metadata['columns'] as $k => $kt){
             if(isset($kt['lc_ref'])){
               $this->data[$key]['row_data'][$kt['lc_ref']] = $this->data[$key][$kt['name']];
             }
          }
       }
    }

    /**
     * Check that all the samples have the necessary foreign/primary keys combo
     *
     * @param  array    $additions  An array with all the secondary spreadsheets data
     */
    public function checkForeignConstraints($additions){
       foreach($this->data as $key => $t){
          $linkKey = (isset($t['pri_key'])) ? $t['pri_key'] : $t['foreign_key'];
          foreach($additions as $sheet_name => $sheet){
            if(isset($sheet[$linkKey])){
               $sdata_desc = "<br /><br /><b><u>$sheet_name</u></b><br />";
               foreach($sheet[$linkKey] as $sData){
                  foreach($sData as $col => $dt) if(preg_match('/'. preg_quote ($col) .'/i', Config::$columns2exclude) === 0) $sdata_desc .= "$col = $dt<br />";
               }
            }
            else{
               //if the foreign/primary key isn't set, add it as a warning
               $this->warnings[] = "The key '$linkKey' doesn't have a corresponding key.";
            }
          }
       }
    }

    /**
     * Prints out the data held in the object
     */
    public function DumpData(){ echo '<pre>'. print_r($this->data, true) .'</pre>'; }

    /**
     * Prints out the metadata held in the object
     */
    public function DumpMetaData(){ echo '<pre>'. print_r($this->metadata, true) .'</pre>'; }

    /**
     * Returns the data held in the object
     */
    public function getData(){ return $this->data; }

    /**
     * Uploads the data to the database. This forms the last resting place for the data
     *
     * @global object      $Repository  The main object of the system
     * @return int|string  Returns 0 when the data has been uploaded successfully, else it returns a string with the error message
     */
    public function UploadData(){
       global $Repository;
       //'label, comments, date_created, date_updated, sample_type, origin, org, main_operator, box_id, box_details, Project,
       //SampleID, VisitID, VisitDate, AnimalID, TrayID,  Longitude, Latitude'
       // very strange that we store comments in the origin field
       $strainsQuery = 'insert into '. Config::$config['azizi_db'] .'.strains('. implode(', ', $this->lcFields) .') values('. implode(', ', $this->lcFieldsRef) .')';

       //check for already saved strains
       $isUploadedQuery = "select count from ". Config::$config['azizi_db'] .".strains where name = :name and box_id = :box_id and box_details = :box_details";

       setlocale(LC_TIME, "en_GB");
       $Repository->Dbase->StartTrans();
       foreach($this->data as $t){
          //check whether this sample is already saved
         $label = $t['name'];
         $isUploadedValues = array('name' => $label, 'box_id' => $t['row_data']['box_id'], 'box_details' => $t['row_data']['box_details']);
         $res = $Repository->Dbase->ExecuteQuery($isUploadedQuery, $isUploadedValues);
         if($res == 1){
            $Repository->Dbase->RollBackTrans();
            return $Repository->Dbase->lastError;
         }
         elseif(count($res) != 0){
            $Repository->Dbase->CreateLogEntry("The strain '$label' has already been uploaded before. Skipping it...", 'debug');
            continue;
         }

          $addedStrain = $Repository->Dbase->ExecuteQuery($strainsQuery, $t['row_data']);
          if($addedStrain == 1){
             if($Repository->Dbase->dbcon->errno == 1062) $mssg = "The strain '$label' is a duplicate and has already been uploaded before.";
             else $mssg = $Repository->Dbase->lastError;
             $Repository->Dbase->RollBackTrans();
             return $mssg;
          }
         else{
            $Repository->Dbase->CreateLogEntry("The strain {$t['row_data']['name']} has been added successfully!", 'info');
         }

       }
       $Repository->Dbase->CommitTrans();
       return 0;
    }

    /**
     * Fills in blanks in the data if we have specified a labcollector sample and we need to copy all the metadata to this child sample
     */
    public function FillMissingData(){
       global $Repository;
       $cols = implode(', ', $this->cols2fill);
       $query = "select count, $cols from samples where {$this->lcReference} = :lc_ref";

       $cc = count($this->data);
       for($i = 0; $i < $cc; $i++){
          $t = $this->data[$i];
          $res = $Repository->Dbase->ExecuteQuery($query, array('lc_ref' => $t['lc_ref']));
          if($res == 1) $this->errors[] = $Repository->Dbase->lastError;

          $this->data[$i]['parent_count'] = $res[0]['count'];
          foreach($this->cols2fillMap as $key => $val){
             $this->data[$i][$val] = $res[0][$key];
          }
       }
    }

    /**
     * Check for anything which might be a duplicate and might fuck us up...
     *
     * @global object      $Repository        The main object of the system
     * @return string|int  Returns a string with an error message in case of an error, else it returns 0
     */
    public function CheckForDuplicates(){
      global $Repository;
      //duplicate samples
      $query = "select name, count from ". Config::$config['azizi_db'] .".strains where name = :name";
      $stmt = $Repository->Dbase->dbcon->prepare($query);
      if(!$stmt){
         $error = 'Error while preparing the query to check for duplicate strains.';
         $Repository->Dbase->CreateLogEntry($error, 'fatal', true, __FILE__, __LINE__);
         return "$error Please contact the system administrator.";
      }

      //duplicate trays
      $query = "select box_id, box_name from ". Config::$config['azizi_db'] .".boxes_def where box_name = :box_name";
      $tray = $Repository->Dbase->dbcon->prepare($query);
      if(!$tray){
         $error = 'Error while preparing the query to check for duplicate trays.';
         $Repository->Dbase->CreateLogEntry($error, 'fatal', true, __FILE__, __LINE__);
         return "$error Please contact the system administrator.";
      }

      foreach($this->data as $data) {
         if($stmt->execute(array('name' => $data['name']))) {
            if($stmt->num_rows != 0) $this->errors[] = "The strains <b>'{$data['label']}'</b> is already in the database!";
         }
         else{
            $err = $stmt->errorInfo();
            $error = 'Error while checking for duplicate strains.';
            $Repository->Dbase->CreateLogEntry($err[2], 'fatal', true, __FILE__, __LINE__);
            return "$error Please contact the system administrator.";
         }

         if($tray->execute(array('box_name' => $data['storage_box']))) {
            if($tray->num_rows != 0) $this->errors[] = "The tray <b>'{$data['storage_box']}'</b> is already in the database!";
         }
         else{
            $err = $tray->errorInfo();
            $error = 'Error while checking for duplicate boxes.';
            $Repository->Dbase->CreateLogEntry($err[2], 'fatal', true, __FILE__, __LINE__);
            return "$error Please contact the system administrator.";
         }
      }
      return 0;
   }

    /**
     * Checks whether we have a box saved with the same name. If there is a box saved with the same name, it returns its id, else if there is no saved tray, it saves the tray.
     *
     * @global object   $Repository  The object with the main class of the Lims uploader
     * @param  string   $box_name      The name of the box that we want to save
     * @return mixed    Returns the boxid of the box, whether saved or existing when all is ok, else it returns a string with the error message in case there was an error
     */
    private function IsBoxSaved($box_name){
       global $Repository;
       $boxId = $Repository->Dbase->GetSingleRowValue(Config::$config['azizi_db'] .'.boxes_def', 'box_id', 'box_name', $box_name);
       if($boxId == -2) return $Repository->Dbase->lastError;
       elseif(is_null($boxId)){
         //we assume all trays are 10x10
         $boxId = $Repository->Dbase->AddNewTray(Config::$config['azizi_db'], $box_name, 'A:1.J:10', 'box', $_SESSION['contact_id']);
         if(is_string($boxId)){
            if($Repository->Dbase->dbcon->errno == 1062) return $boxId;    //complaining of a duplicate box....lets continue
            else return $Repository->Dbase->lastError;   //we have an error while adding the tray, so we just return
         }
         else return $boxId;
       }
       else return $boxId;
    }

    /**
     * Checks whether the sample owner has been added to the database. If not adds the person and links them to the sample
     *
     * @global object   $Repository  The object with the main class of the Lims uploader
     * @param  string   $owner       The name of the owner that we want to save
     * @return mixed    Returns the ownerId of the sample owner, whether saved or existing when all is ok, else it returns a string with the error message in case there was an error
     */
    private function IsOwnerAdded($owner){
       global $Repository;
       $ownerId = $Repository->Dbase->GetSingleRowValue(Config::$config['azizi_db'] .'.contacts', 'count', 'name', $owner);
       if($ownerId == -2) return $Repository->Dbase->lastError;
       elseif(is_null($ownerId)){
         $ownerId = $Repository->Dbase->AddOwner(Config::$config['azizi_db'], $owner);
         if(!is_numeric($ownerId)){
            if($Repository->Dbase->dbcon->errno == 1062) return $ownerId;    //complaining of a duplicate box....lets continue
            else return $Repository->Dbase->lastError;   //we have an error while adding the tray, so we just return
         }
         else return $ownerId;
       }
       else return $ownerId;
   }

    /**
     * Checks whether a organism is already added to the database. If already saved, it returns the organism id
     *
     * @global object   $Repository  The object with the main class of the Lims uploader
     * @param  string   $organism       The name of the organism that we want to save
     * @return mixed    Returns the organism id of the organism, whether saved or existing when all is ok, else it returns a string with the error message in case there was an error
     */
    private function IsOrganismSaved($organism){
       global $Repository;
       $organismId = $Repository->Dbase->GetSingleRowValue(Config::$config['azizi_db'] .'.organisms', 'org_id', 'org_name', $organism);
       if($organismId == -2) return $Repository->Dbase->lastError;
       elseif(is_null($organismId)){
         $organismId = $Repository->Dbase->AddSpecies(Config::$config['azizi_db'], $organism);
         if(!is_numeric($organismId)){
            if($Repository->Dbase->dbcon->errno == 1062) return $organismId;    //complaining of a duplicate box....lets continue
            else return $Repository->Dbase->lastError;   //we have an error while adding the tray, so we just return
         }
         else return $organismId;
       }
       else return $organismId;
    }

    /**
     * Checks whether a custom value has been saved to the database
     *
     * @global object   $Repository    The object with the main class of the Lims uploader
     * @param  type     $valueName     The name of the value to be added
     * @param  type     $valueId       The value to be added
     * @return type
     */
    private function isCustomValueSaved($valueName, $valueId){
       global $Repository;
       $recordId = $Repository->Dbase->addCustomValues(Config::$config['azizi_db'], $valueName, $valueId);

       if(!is_numeric($recordId)){
         if($Repository->Dbase->dbcon->errno == 1062) return $recordId;    // complaining of a duplicate value....lets continue
         else return $Repository->Dbase->lastError;   //we have an error while adding the tray, so we just return
       }
       return $recordId;

    }
}
?>