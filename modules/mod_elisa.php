<?php

class Elisa extends SpreadSheet {

   /**
    * @var  boolean     Whether or not to include the undefined columns in the comments field
    */
   protected $includeUndefinedColumns = false;

   /**
    * @var  boolean     Whether to attempt to link sheets within the worksheet or not
    */
   protected $linkedSheets = false;

   /**
    * @var   string   The column name in labcollector that has our refence value.
    */
   protected $lcReference = 'label';
   /**
    *
    * @var array  Holds possible metadata fields and columns and their validators and whether they are required or not
    */
   protected $metadata = array(
      'test_type' => array(
         'regex' => '/(test\s+type)(.+)/i',
         'required' => true
      ),
      'plate_name' => array(
         'regex' => '/(plate\s+name)(.+)/i',
         'required' => false
      ),
      'test_date' => array(
         'regex' => '/(test\s+date)(.+)/i',
         'required' => true
      ),
      'technician' => array(
         'regex' => '/(technician)(.+)/i',
         'required' => true
      ),
      'ref_no' => array(
         'regex' => '/(ref\s+no)(.+)/i',
         'required' => true
      ),
      'lot_no' => array(
         'regex' => '/(lot\s+no)(.+)/i',
         'required' => true
      ),
      'mean_od' => array(
         'regex' => '/(mean\s+od\s+value)(.+)/i',
         'required' => true
      ),
      'columns' => array(
         array('name' =>'sample', 'regex' => '/(tested\s+sample)/i', 'data_regex' => '/^(avaq[0-9]{5})$/i', 'required' => true, 'unique' => true),
         array('name' =>'sample_od', 'regex' => '/(sample\s+od)/i', 'data_regex' => '/^[0-9]{1,2}\.[0-9]{2,6}$/i', 'required' => true),
         array('name' =>'sample_pi', 'regex' => '/(sample\s+pi)/i', 'data_regex' => '/^[0-9]{1,2}\.[0-9]+$/i', 'required' => true),
         array('name' =>'status', 'regex' => '/(interpretation)/i', 'data_regex' => '/^positive|negative$/i', 'required' => true),
      )
   );

   private $allTrays = array();
   private $allSamples = array();
   private $extraCols = array();
   private $allProjects = array();

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

       //get the plate project
       $plateProject = 0;
       foreach($this->data as $key => $t){
          //get each sample project and sample id
          $query = 'select a.count, a.Project, b.value from '. Config::$config['azizi_db'] .'.samples as a inner join '. Config::$config['azizi_db'] .'.modules_custom_values as b on a.Project=b.val_id where a.label = :label';
          $label = $t['sample'];
          $res = $Repository->Dbase->ExecuteQuery($query, array('label' => $label));
          if($res == 1){
             return $Repository->Dbase->lastError;
          }
          elseif(count($res) == 0) $this->errors[] = "The sample '{$t['sample']}' has <b>No Record</b> in the database. Please contact the system administrator.";
          elseif(count($res) != 1) $this->errors[] = "The sample '{$t['sample']}' has multiple records in the database. Please contact the system administrator.";
          else{
             //we have the sample id and the project
             $this->data[$key]['projectName'] = $res[0]['value'];
             $this->data[$key]['sampleId'] = $res[0]['count'];
             $this->data[$key]['projectId'] = $res[0]['Project'];
             $this->data[$key]['status'] = ucfirst($this->data[$key]['status']);
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
                  foreach($sData as $col => $dt) if(preg_match("/$col/i", Config::$columns2exclude) === 0) $sdata_desc .= "$col = $dt<br />";
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
    public function DumpData(){
//       echo '<pre>'. print_r($this->metadata, true) .'</pre>';
       echo '<pre>'. print_r($this->data, true) .'</pre>';
    }

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

       //add the current plate setup
       //testID, testType, plateStatus, plateName, testDateTime, createBy, filter, technician, blankingValue, kitBatch, AcceptableODrange, PPthreshold, meanControlOD, project, filename
       $plateQuery = 'insert into '. Config::$config['azizi_db'] .'.elisaSetUp(testType, plateStatus, plateName, testDateTime, createBy, technician, kitBatch, meanControlOD, project, filename)'
         . 'values(:testType, :plateStatus, :plateName, :testDateTime, :createBy, :technician, :kitBatch, :meanControlOD, :project, :filename)';

       //sampleId, testID, WELLS, DESCRIPTION, ID, STATUS, OD1, OD2, ODAv, PP1, PP2, Var, PPav, SSID, AnimalID, Project
       $elisaQuery = 'insert into '. Config::$config['azizi_db'] .'.elisaTest(sampleId, testID, DESCRIPTION, ID, STATUS, ODAv, PI, Project)'
         . 'values(:sampleId, :testID, :DESCRIPTION, :ID, :STATUS, :ODAv, :pi, :project)';

       setlocale(LC_TIME, "en_GB");
       $Repository->Dbase->StartTrans();
       //lets add the plate
       $meta = $this->metadata;
       $platevals = array(
           'testType' => $meta['test_type']['data'], 'plateStatus' => 'WITHIN_LIMITS', 'plateName' => $meta['plate_name']['data'], 'testDateTime' => date_format(date_create_from_format('d-M-Y', $meta['test_date']['data']), 'Y-m-d H:i:s'),
           'createBy' => $meta['technician']['data'], 'technician' => $meta['technician']['data'], 'kitBatch' => "{$meta['ref_no']['data']} - {$meta['lot_no']['data']}",
           'meanControlOD' => $meta['mean_od']['data'], 'project' => $this->data[0]['projectId'], 'filename' => "$this->finalUploadedFile:$this->sheet_index"
       );
       $addedPlate = $Repository->Dbase->ExecuteQuery($plateQuery, $platevals);
       if($addedPlate == 1){
         $Repository->Dbase->RollBackTrans();
         return $Repository->Dbase->lastError;
       }
       else $plateId = $Repository->Dbase->dbcon->lastInsertId();

       foreach($this->data as $key => $t){
          echo '<pre>'. print_r($t, true) .'</pre>';

          $colvals = array(
             'sampleId' => $t['sampleId'], 'testID' => $plateId, 'DESCRIPTION' => $t['sample'], 'ID' => $key, 'STATUS' => $t['status'], 'ODAv' => $t['sample_od'], 'pi' => $t['sample_pi'], 'project' => $t['projectName']
          );
          $addedResult = $Repository->Dbase->ExecuteQuery($elisaQuery, $colvals);
          if($addedResult == 1){
             $Repository->Dbase->RollBackTrans();
             return $Repository->Dbase->lastError;
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
      $query = "select label, count from ". Config::$config['azizi_db'] .".samples where label = :label";
      $stmt = $Repository->Dbase->dbcon->prepare($query);
      if(!$stmt){
         $Repository->Dbase->CreateLogEntry('', 'fatal', true, __FILE__, __LINE__);
         return "There was an error while checking for duplicates. Please contact the system administrator.";
      }

      //duplicate trays
      $query = "select box_id, box_name from ". Config::$config['azizi_db'] .".boxes_def where box_name = :box_name";
      $tray = $Repository->Dbase->dbcon->prepare($query);
      if(!$tray){
         $Repository->Dbase->CreateLogEntry('', 'fatal', true, __FILE__, __LINE__);
          return "There was an error while checking for duplicate trays. Please contact the system administrator.1";
      }

      //duplicate parent sample
      $query = 'select count, label from '. Config::$config['azizi_db'] .'.samples where comments like :comments';
      $parent = $Repository->Dbase->dbcon->prepare($query);
      if(!$parent){
         $Repository->Dbase->CreateLogEntry('', 'fatal', true, __FILE__, __LINE__);
         return "There was an error while checking for duplicate parents. Please contact the system administrator.1";
      }

      foreach($this->data as $data) {
         if($stmt->execute(array('label' => $data['label']))) {
            if($stmt->num_rows != 0) $this->errors[] = "The sample <b>'{$data['label']}'</b> is already in the database!";
         }
         else{
            $Repository->Dbase->CreateLogEntry('', 'fatal', true, __FILE__, __LINE__);
            return "There was an error while checking for duplicate samples. Please contact the system administrator.";
         }

         if($tray->execute(array('box_name' => $data['storage_box']))) {
            if($stmt->num_rows != 0) $this->errors[] = "The tray <b>'{$data['storage_box']}'</b> is already in the database!";
         }
         else{
            $Repository->Dbase->CreateLogEntry('', 'fatal', true, __FILE__, __LINE__);
            return "There was an error while checking for duplicate samples. Please contact the system administrator.";
         }

         if($parent->execute(array('comments' => "%{$data['parent']}%"))) {
            if($parent->num_rows != 0) $this->errors[] = "The parent <b>'{$data['parent']}'</b> is already in the database!";
         }
         else{
            $Repository->Dbase->CreateLogEntry('', 'fatal', true, __FILE__, __LINE__);
            return "There was an error while checking for duplicate samples. Please contact the system administrator.";
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
}
?>
