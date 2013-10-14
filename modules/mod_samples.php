<?php

class Samples extends SpreadSheet {

   /**
    * @var type
    */
   protected $includeUndefinedColumns = true;

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
         array('name' =>'latitude', 'regex' => '/^latitude$/i', 'data_regex' => '/^-?[0-9]{1,2}\.[0-9]+$/i', 'required' => true, 'lc_ref' => 'Longitude'),
         array('name' =>'longitude', 'regex' => '/^longitude$/i', 'data_regex' => '/^-?[0-9]{2,3}\.[0-9]+$/i', 'required' => true, 'lc_ref' => 'Latitude'),
         array('name' =>'project', 'regex' => '/^project$/i', 'required' => true, 'lc_ref' => 'Project'),
         array('name' =>'animal_id', 'regex' => '/^animal\s+id$/i', 'required' => false, 'lc_ref' => 'AnimalID'),
         array('name' =>'storage_box', 'regex' => '/^storage\s+box$/i', 'data_regex' => '/^[a-z]{4,5}[0-9]{2,5}$/i', 'required' => true),
         array('name' =>'sample_pos', 'regex' => '/^position\s+in\s+box$/i', 'data_regex' => '/^[1-9]?[0-9]|100|[a-z][0-9]0?$/i', 'required' => true),
         array('name' =>'parent', 'regex' => '/^parent\s+sample$/i', 'required' => false),
         array('name' =>'comments', 'regex' => '/^comments$/i', 'required' => false),
         array('name' =>'lc_ref', 'regex' => '/^labcollector\s+reference$/i', 'required' => false)
      )
   );

   private $allTrays = array();
   private $allOrganism = array();
   private $allSamples = array();
   private $extraCols = array();
   private $allProjects = array();

   public function __construct($path, $name, $data) {
        parent::__construct($path, $name, $data);
    }

    /**
     *Normalize the samples data from the users to have consistent data.
     */
    public function NormalizeData(){
       global $Repository;
       //get the extra columns
       foreach($this->metadata['columns'] as $key => $t){
          if($t['predefined'] != 1 && (!isset($t['lc_ref']) || $t['lc_ref'] == '')) $this->extraCols[] = $t['name'];
       }

       //save all the boxes, organism and create a nested array from this data for later use
       foreach($this->data as $key => $t){
//       echo '<pre>'. print_r($t, true) .'</pre>'; die();
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
          $descr = ($this->data[$key]['parent'] != '') ? $descr = "Parent Sample = {$this->data[$key]['parent']}" : '';
          foreach($this->extraCols as $col){
             if($t[$col] == '') continue;
             $descr .= ($descr == '') ? '' : '<br />';
             $descr .= "$col = {$t[$col]}";
          }
          //"http://azizi.ilri.cgiar.org/viewSpreadSheet.php?file=entomology_uploads/zip_upload_2012-02-22_101035/CBG/Wakabhare%20%20LT%20Msqt%20CBG%2027.10.09.xls&focused=CBG000109#focused"
          $descr .= "<br /><br />Other Comments:<br />{$t['comments']}";
          //add a link to the original file we uploaded
          $descr .= "<br />Field File = <a target='_blank' href='http://azizi.ilri.cgiar.org/viewSpreadSheet.php?file={$this->finalUploadedFileLink}&sheet={$this->sheet_index}&focused={$t['name']}#focused'>Field Data.xls</a>";
          $image = ($t['latitude'] == '') ? '' : "<div><img alt='This sample was collected from Lat:{$t['latitude']}, Long:{$t['longitude']}' src='http://maps.googleapis.com/maps/api/staticmap?center={$t['latitude']},{$t['longitude']}&zoom=7&size=300x300&markers=color:blue%7Clabel:S%7C{$t['latitude']},{$t['longitude']}&sensor=false' /><div>";
          $this->data[$key]['descr'] = "<div style='float:left; width:380px; margin-right:20px;'>$descr</div>$image";
       }
    }

    public function DumpData(){
//       echo '<pre>'. print_r($this->metadata, true) .'</pre>';
       echo '<pre>'. print_r($this->data, true) .'</pre>';
    }

    public function DumpMetaData(){
//       echo '<pre>'. print_r($this->metadata, true) .'</pre>';
       echo '<pre>'. print_r($this->metadata, true) .'</pre>';
    }

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
       $samplesQuery = 'insert into '. Config::$config['azizi_db'] .'.samples(label, comments, date_created, date_updated, sample_type, origin, org, main_operator, box_id, box_details, Project, SampleID, VisitID, VisitDate, AnimalID, TrayID, Longitude, Latitude)'
         . 'values(:label, :comments, :date_created, :date_updated, :sample_type, :origin, :org, :main_operator, :box_id, :box_details, :Project, :label, :origin, :date_created, :animal_id, :storage_box, :longitude, :latitude)';
       //for linked samples
       $relationQuery = 'insert into '. Config::$config['azizi_db'] .'.modules_relation(module_from, id_from, module_to, id_to) values(:module_from, :id_from, :module_to, :id_to)';

       setlocale(LC_TIME, "en_GB");
       $Repository->Dbase->StartTrans();
       foreach($this->data as $t){
          //check whether this sample is already saved
         $query = "select * from ". Config::$config['azizi_db'] .".samples where label = :label";
         $label = $t['name'];
         $res = $Repository->Dbase->ExecuteQuery($query, array('label' => $label));
         if($res == 1){
            $Repository->Dbase->RollBackTrans();
            return $Repository->Dbase->lastError;
         }
         elseif(count($res) != 0){
            $Repository->Dbase->RollBackTrans();
            return "The sample '$label'is a duplicate and has already been uploaded before.";
         }

         $colvals = array(
            'label' => $label, 'comments' => $t['descr'], 'date_created' => strftime('%Y-%m-%d %H:%M:%S', strtotime($t['collection_date'])), 'date_updated' => date('Y-m-d H:i:s'),
            'sample_type' => $t['type'], 'origin' => $t['origin'], 'org' => $t['organism'],
            'main_operator' => $_SESSION['contact_id'], 'box_id' => $this->allTrays[$t['storage_box']], 'box_details' => $t['box_details'],
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
      if(!$stmt) return "There was an error while checking for duplicates. Please contact the system administrator.1";

      //duplicate trays
      $query = "select box_id, box_name from ". Config::$config['azizi_db'] .".boxes_def where box_name = :box_name";
      $tray = $Repository->Dbase->dbcon->prepare($query);
      if(!$tray) return "There was an error while checking for duplicate trays. Please contact the system administrator.1";

      //duplicate parent sample
      $query = 'select count, label from '. Config::$config['azizi_db'] .'.samples where comments like :comments';
      $parent = $Repository->Dbase->dbcon->prepare($query);
      if(!$parent) return "There was an error while checking for duplicate parents. Please contact the system administrator.1";

      foreach($this->data as $data) {
         if($stmt->execute(array('label' => $data['label']))) {
            if($stmt->num_rows != 0) $this->errors[] = "The sample <b>'{$data['label']}'</b> is already in the database!";
         }
         else return "There was an error while checking for duplicate samples. Please contact the system administrator.";


         if($tray->execute(array('box_name' => $data['storage_box']))) {
            if($stmt->num_rows != 0) $this->errors[] = "The tray <b>'{$data['storage_box']}'</b> is already in the database!";
         }
         else return "There was an error while checking for duplicate samples. Please contact the system administrator.";


         if($parent->execute(array('comments' => "%{$data['parent']}%"))) {
            if($parent->num_rows != 0) $this->errors[] = "The parent <b>'{$data['parent']}'</b> is already in the database!";
         }
         else return "There was an error while checking for duplicate samples. Please contact the system administrator.";
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