<?php

class Samples extends SpreadSheet {

   /**
    * @var type
    */
   protected $includeUndefinedColumns = true;

   /**
    * @var  boolean     Whether to attempt to link sheets within the worksheet or not
    */
   protected $linkedSheets = true;

   /**
    * @var   string   The column name in labcollector that has our refence value.
    */
   protected $lcReference = 'label';

   /**
    * @var array  Holds possible metadata fields and columns and their validators and whether they are required or not
    */
   protected $metadata = array(
      'columns' => array(
         array('name' =>'name', 'regex' => '/^sample\s+name$/i', 'required' => true, 'unique' => true),
         array('name' =>'type', 'regex' => '/^sample\s+type$/i', 'data_regex' => '/^[a-z\s]+$/i', 'required' => true),
         array('name' =>'organism', 'regex' => '/^organism$/i', 'data_regex' => '/^[a-z\s]+$/i', 'required' => true, 'lc_ref' => 'org'),
         array('name' =>'origin', 'regex' => '/^sample\s+origin/i', 'data_regex' => '/^[a-z\s]+$/i', 'required' => true, 'lc_ref' => 'origin'),
         array('name' =>'collection_date', 'regex' => '/^date\s+collected$/i', 'data_regex' => '/^(0?[1-9]|[12][0-9]|3[01])[\/\-](0?[1-9]|1[012])[\/\-]20\d{2}$/', 'required' => true),
         array('name' =>'latitude', 'regex' => '/^latitude$/i', 'data_regex' => '/^-?[0-9]{1,2}\.[0-9]+$/i', 'required' => true),
         array('name' =>'longitude', 'regex' => '/^longitude$/i', 'data_regex' => '/^-?[0-9]{2,3}\.[0-9]+$/i', 'required' => true),
         array('name' =>'project', 'regex' => '/^project$/i', 'required' => true, 'lc_ref' => 'Project'),
         array('name' =>'animal_id', 'regex' => '/^animal\s+id$/i', 'required' => false, 'lc_ref' => 'AnimalID'),
         array('name' =>'storage_box', 'regex' => '/^storage\s+box$/i', 'data_regex' => '/^p69[a-c][0-9]{3}|[a-z]{4,5}[0-9]{2,5}$/i', 'required' => true),
         array('name' =>'sample_pos', 'regex' => '/^position\s+in\s+box$/i', 'data_regex' => '/^[1-9]?[0-9]|100|[a-z][0-9]0?$/i', 'required' => true),
         array('name' =>'parent', 'regex' => '/^parent\s+sample$/i', 'required' => false),
         array('name' =>'comments', 'regex' => '/^comments$/i', 'required' => false),
         array('name' =>'owner', 'regex' => '/^owner$/i', 'data_regex' => '/^[a-z\s\']+$/i', 'required' => true),
         array('name' =>'lc_ref', 'regex' => '/^labcollector\s+reference$/i', 'required' => false),
         array('name' =>'pri_key', 'regex' => '/^([a-z\s]+)(\(main\s+key\))$/i', 'required' => false, 'unique' => true),
         array('name' =>'foreign_key', 'regex' => '/^([a-z\s]+)(\(secondary\s+key\))$/i', 'required' => false),
         array('name' =>'assay', 'regex' => '/^assay$/i', 'required' => false),
         array('name' =>'experiment', 'regex' => '/^experiment$/i', 'required' => false)
      )
   );

   private $allTrays = array();
   private $allOrganism = array();
   private $allSamples = array();
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
       //get the extra columns
       foreach($this->metadata['columns'] as $key => $t){
          if($t['predefined'] != 1 && (!isset($t['lc_ref']) || $t['lc_ref'] == '')) $this->extraCols[] = $t['name'];
       }

       //save all the boxes, organism and create a nested array from this data for later use
       foreach($this->data as $key => $t){
          //trays
          if(!array_key_exists($t['storage_box'], $this->allTrays)){
             $boxId = $this->IsBoxSaved($t['storage_box']);
             if(!is_numeric($boxId)) $this->errors[] = $boxId;
             else $this->allTrays[$t['storage_box']] = $boxId;
          }
          //organism
          if(isset($t['organism']) && !is_numeric($t['organism'])){
            if(!array_key_exists($t['organism'], $this->allOrganism)){
               $organismId = $this->IsOrganismSaved($t['organism']);
               if(!is_numeric($organismId)) $this->errors[] = $organismId;
               else{
                  //add the org_id to the organism variable
                  $this->allOrganism[$t['organism']] = $organismId;
                  $this->data[$key]['organism'] = $organismId;
               }
            }
            else $this->data[$key]['organism'] = $this->allOrganism[$t['organism']];
          }
          //owner
          if(!array_key_exists($t['owner'], $this->owners)){
             $ownerId = $this->IsOwnerAdded($t['owner']);
             if(!is_numeric($ownerId)) $this->errors[] = $ownerId;
             else $this->owners[$t['owner']] = $ownerId;
          }
          $this->data[$key]['owner'] = $this->owners[$t['owner']];
          //sample types
          if(isset($t['type']) && !is_numeric($t['type'])){
            if(!array_key_exists($t['type'], $this->allSamples)){
               $samplesId = $Repository->Dbase->AddSampleType(Config::$config['azizi_db'], $t['type']);
               if(!is_numeric($samplesId)) $this->errors[] = $samplesId;
               else{
                  $this->allSamples[$t['type']] = $samplesId;
                  $this->data[$key]['type'] = $samplesId;
               }
            }
            else $this->data[$key]['type'] = $this->allSamples[$t['type']];
          }
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

          $this->data[$key]['collection_date'] = str_replace('/', '-', $t['collection_date']);
          $this->data[$key]['box_details'] = $Repository->NumericPosition2LCPosition($t['sample_pos'], 100);

          //create the comments from the columns which go nowhere
          $descr = (isset($this->data[$key]['parent'])) ? "Parent Sample = {$this->data[$key]['parent']}" : '';
          foreach($this->extraCols as $col){
             if(preg_match('/'. preg_quote($t[$col], '/') .'/i', Config::$emptyValues) === 1) continue;
             if(preg_match('/'. preg_quote($col, '/') .'/i', Config::$columns2exclude) === 1) continue;
             $descr .= ($descr == '') ? '' : '<br />';
             $descr .= "$col = {$t[$col]}";
          }
          //all the data in the extra spreadsheets are loaded into the description field
//          echo '<pre>'. print_r($t, true) .'</pre>';

          $linkKey = (isset($t['pri_key'])) ? $t['pri_key'] : $t['foreign_key'];
          foreach($additions as $sheet_name => $sheet){
            $sdata_desc = '';
            if(isset($sheet[$linkKey])){
               $sdata_desc = "<br /><br /><b><u>$sheet_name</u></b><br />";
               foreach($sheet[$linkKey] as $sData){
                  foreach($sData as $col => $dt) if(preg_match("/$col/i", Config::$columns2exclude) === 0) $sdata_desc .= "$col = $dt<br />";
                  $sdata_desc .= "<br />";
               }
            }
            $descr .= $sdata_desc;
          }

          // check if we have the experiment and assay values set, if not, we equate them to null
          if(!isset($t['experiment'])) $this->data[$key]['experiment'] = NULL;
          if(!isset($t['assay'])) $this->data[$key]['assay'] = NULL;

          //"http://azizi.ilri.cgiar.org/viewSpreadSheet.php?file=entomology_uploads/zip_upload_2012-02-22_101035/CBG/Wakabhare%20%20LT%20Msqt%20CBG%2027.10.09.xls&focused=CBG000109#focused"
          $descr .= "<br /><br /><b>Other Comments:</b>{$t['comments']}";
          //add a link to the original file we uploaded
          $zoom_factor = isset($t['Zoom Factor'])? $t['Zoom Factor'] : 9;
          $descr .= "<br />Field File = <a target='_blank' href='http://azizi.ilri.cgiar.org/viewSpreadSheet.php?file={$this->finalUploadedFileLink}&sheet={$this->sheet_index}&focused={$t['name']}#focused'>Field Data.xls</a>";
          $image = ($t['latitude'] == '') ? '' : "<div><img alt='This sample was collected from Lat:{$t['latitude']}, Long:{$t['longitude']}' src='http://maps.googleapis.com/maps/api/staticmap?center={$t['latitude']},{$t['longitude']}&zoom=$zoom_factor&size=300x300&markers=color:blue%7Clabel:S%7C{$t['latitude']},{$t['longitude']}&sensor=false' /><div>";
          $this->data[$key]['descr'] = "<div style='float:left; width:380px; margin-right:20px;'>$descr</div>$image";
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
       $samplesQuery = 'insert into '. Config::$config['azizi_db'] .'.samples(label, comments, date_created, date_updated, sample_type, origin, org, main_operator, box_id, box_details, Project, SampleID, VisitID, VisitDate, AnimalID, TrayID, Longitude, Latitude, Experiment, Assay)'
         . 'values(:label, :comments, :date_created, :date_updated, :sample_type, :origin, :org, :main_operator, :box_id, :box_details, :Project, :label, :origin, :date_created, :animal_id, :storage_box, :longitude, :latitude, :experiment, :assay)';
       //for linked samples
       $relationQuery = 'insert into '. Config::$config['azizi_db'] .'.modules_relation(module_from, id_from, module_to, id_to) values(:module_from, :id_from, :module_to, :id_to)';
       //check for already saved samples
       $isUploadedQuery = "select count from ". Config::$config['azizi_db'] .".samples where label = :label and box_id = :box_id and box_details = :box_details";
       //check 4 duplicates
       $isDupQuery = "select count from ". Config::$config['azizi_db'] .".samples where label = :label and (box_id != :box_id or box_details != :box_details)";

       setlocale(LC_TIME, "en_GB");
       $Repository->Dbase->StartTrans();
       foreach($this->data as $t){
          //check whether this sample is already saved
         $label = $t['name'];
         $isUploadedValues = array('label' => $label, 'box_id' => $this->allTrays[$t['storage_box']], 'box_details' => $t['box_details']);
         $res = $Repository->Dbase->ExecuteQuery($isUploadedQuery, $isUploadedValues);
         if($res == 1){
            $Repository->Dbase->RollBackTrans();
            return $Repository->Dbase->lastError;
         }
         elseif(count($res) != 0){
            $Repository->Dbase->CreateLogEntry("The sample '$label' has already been uploaded before. Skipping it...", 'debug');
            continue;
         }

         //check for duplicates
         $res = $Repository->Dbase->ExecuteQuery($isDupQuery, $isUploadedValues);
         if($res == 1){
            $Repository->Dbase->RollBackTrans();
            return $Repository->Dbase->lastError;
         }
         elseif(count($res) != 0){
            $Repository->Dbase->CreateLogEntry("The sample '$label' is a duplicate of a sample with id '{$res[0]['count']}'.", 'fatal');
            $Repository->Dbase->RollBackTrans();
            return "The sample '$label' is a duplicate of a sample with id '{$res[0]['count']}'.";
         }

         $colvals = array(
            'label' => $label, 'comments' => $t['descr'], 'date_created' => strftime('%Y-%m-%d %H:%M:%S', strtotime($t['collection_date'])), 'date_updated' => date('Y-m-d H:i:s'),
            'sample_type' => $t['type'], 'origin' => $t['origin'], 'org' => $t['organism'], 'assay' => $t['assay'], 'experiment' => $t['experiment'],
            'main_operator' => $t['owner'], 'box_id' => $this->allTrays[$t['storage_box']], 'box_details' => $t['box_details'],
            'Project' => $t['project'], 'animal_id' => $t['animal_id'], 'storage_box' => $t['storage_box'], 'longitude' => $t['longitude'], 'latitude' => $t['latitude']
         );

          $addedSample = $Repository->Dbase->ExecuteQuery($samplesQuery, $colvals);
          if($addedSample == 1){
             if($Repository->Dbase->dbcon->errno == 1062) $mssg = "The sample '$label' is a duplicate and has already been uploaded before.";
             else $mssg = $Repository->Dbase->lastError;
             $Repository->Dbase->RollBackTrans();
             return $mssg;
          }

          //if we have a parent sample, we need to create a link between the parent and the child
          if(isset($t['parent_count']) && is_numeric($t['parent_count'])){
             $colvals1 = array('module_from' => 'SP', 'id_from' => $t['parent_count'], 'module_to' => 'SP', 'id_to' => $addedSample);
             $addedRel = $Repository->Dbase->UpdateRecords($relationQuery, $colvals1);
             if($addedRel == 0){
                $Repository->Dbase->RollBackTrans();
                return $Repository->Dbase->lastError;
             }
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
