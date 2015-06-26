<?php

class Strains extends SpreadSheet {

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
   protected $lcReference = 'label';

   /**
    * @var array  Holds possible metadata fields and columns and their validators and whether they are required or not
    */
   protected $metadata = array(
      'columns' => array(
         array('name' =>'name', 'regex' => '/^strain\s+name$/i', 'required' => true, 'unique' => true, 'lc_ref' => 'name'),
         array('name' =>'organism', 'regex' => '/^species$/i', 'required' => true, 'lc_ref' => 'org'),
         array('name' =>'origin', 'regex' => '/^country\s+of\s+isolation/i', 'required' => false, 'lc_ref' => 'origin'),
         array('name' =>'animal_id', 'regex' => '/^animal\s+id/i', 'required' => false, 'lc_ref' => 'Animal_ID'),
         array('name' =>'provider', 'regex' => '/^provider$/i', 'required' => false),
         array('name' =>'project', 'regex' => '/^project$/i', 'required' => true, 'lc_ref' => 'Project'),
         array('name' =>'batch_name', 'regex' => '/^batch\s+name$/i', 'required' => false, 'lc_ref' => 'Batch_Name'),
         array('name' =>'storage_box', 'regex' => '/^storage\s+box$/i', 'data_regex' => '/^[a-z]{4,5}[0-9]{2,5}|p67b[0-9]{3}$/i', 'required' => true),
         array('name' =>'sample_pos', 'regex' => '/^position\s+in\s+box$/i', 'data_regex' => '/^[1-9]?[0-9]|100|[a-z][0-9]0?$/i', 'required' => true),
         array('name' =>'comments', 'regex' => '/^comments$/i', 'required' => false),
         array('name' =>'owner', 'regex' => '/^owner$/i', 'data_regex' => '/^[a-z\s\']+$/i', 'required' => true)
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

          //project
          if(isset($t['project']) && !is_numeric($t['project'])){
            if(!array_key_exists($t['project'], $this->allProjects)){
               $projectId = $Repository->Dbase->AddProject(Config::$config['azizi_db'], $t['project'], 47);
               if(!is_numeric($projectId)) $this->errors[] = $projectId;
               else{
                  $this->allProjects[$t['project']] = $projectId;
                  $this->data[$key]['project'] = $projectId;
               }
            }
            else $this->data[$key]['project'] = $this->allProjects[$t['project']];
          }

          // batch name
          if(!isset($t['batch_name'])) $this->data[$key]['project'] = NULL;

          $this->data[$key]['box_details'] = $Repository->NumericPosition2LCPosition($t['sample_pos'], 100);

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
          $this->data[$key]['descr'] = "<div style='float:left; width:380px; margin-right:20px;'>$descr</div>";
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
       $strainsQuery = 'insert into '. Config::$config['azizi_db'] .'.strains(name, genotype, origin, plasmids, org, org2, keeper, box_id, box_details, date_created, date_updated, Project, Batch_Name)'
         . 'values(:name, :genotype, :origin, :plasmids, :org, :org2, :keeper, :box_id, :box_details, :date_created, :date_updated, :project, :batch_name)';
       //check for already saved samples
       $isUploadedQuery = "select count from ". Config::$config['azizi_db'] .".strains where name = :name and box_id = :box_id and box_details = :box_details";
       //check 4 duplicates
       $isDupQuery = "select count from ". Config::$config['azizi_db'] .".strains where name = :name and (box_id != :box_id or box_details != :box_details)";

       setlocale(LC_TIME, "en_GB");
       $Repository->Dbase->StartTrans();
       foreach($this->data as $t){
          //check whether this sample is already saved
         $label = $t['name'];
         $isUploadedValues = array('name' => $label, 'box_id' => $this->allTrays[$t['storage_box']], 'box_details' => $t['box_details']);
         $res = $Repository->Dbase->ExecuteQuery($isUploadedQuery, $isUploadedValues);
         if($res == 1){
            $Repository->Dbase->RollBackTrans();
            return $Repository->Dbase->lastError;
         }
         elseif(count($res) != 0){
            $Repository->Dbase->CreateLogEntry("The strain '$label' has already been uploaded before. Skipping it...", 'debug');
            continue;
         }

         //check for duplicates
         $res = $Repository->Dbase->ExecuteQuery($isDupQuery, $isUploadedValues);
         if($res == 1){
            $Repository->Dbase->RollBackTrans();
            return $Repository->Dbase->lastError;
         }
         elseif(count($res) != 0){
            $Repository->Dbase->CreateLogEntry("The strain '$label' is a duplicate of a strain with id '{$res[0]['count']}'.", 'fatal');
            $Repository->Dbase->RollBackTrans();
            return "The strain '$label' is a duplicate of a strain with id '{$res[0]['count']}'.";
         }

         $colvals = array('name' => $label, 'genotype' => NULL, 'plasmids' => NULL, 'org' => $t['organism'], 'org2' => NULL, 'keeper' => $t['owner'],
            'box_id' => $this->allTrays[$t['storage_box']], 'box_details' => $t['box_details'], 'date_created' => date('Y-m-d H:i:s'), 'date_updated' => date('Y-m-d H:i:s'),
            'origin' => $t['descr'], 'project' => $t['project'], 'batch_name' => $t['batch_name']);

          $addedStrain = $Repository->Dbase->ExecuteQuery($strainsQuery, $colvals);
          if($addedStrain == 1){
             if($Repository->Dbase->dbcon->errno == 1062) $mssg = "The strain '$label' is a duplicate and has already been uploaded before.";
             else $mssg = $Repository->Dbase->lastError;
             $Repository->Dbase->RollBackTrans();
             return $mssg;
          }
         else{
            $Repository->Dbase->CreateLogEntry(print_r($colvals, true), 'debug');
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
       $orgQuery = 'select org_id from '. Config::$config['azizi_db'] .'.organisms where org_name = :org_name';
       $result = $Repository->Dbase->ExecuteQuery($orgQuery, array('org_name' => $organism));

       if($result == 1) return $Repository->Dbase->lastError;
       elseif(count($result) == 0){
         $insertQuery = 'insert into '. Config::$config['azizi_db'] .'.organisms(org_name) values(:org_name)';
         $organismId = $Repository->Dbase->ExecuteQuery($insertQuery, array('org_name' => $organism));
         if($organismId == 1) return $Repository->Dbase->lastError;   //we have an error while adding the tray, so we just return
         else return $Repository->Dbase->dbcon->lastInsertId();
       }
       else return $result[0]['org_id'];
    }
}
?>
