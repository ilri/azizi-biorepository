<?php

class Primers extends SpreadSheet {

   /**
    * @var type
    */
   protected $includeUndefinedColumns = true;

   /**
    * @var  string   The path to where the file will be uploaded to finally
    */
   public $finalUploadedFile;

   /**
    * @var array  Holds possible metadata fields and columns and their validators and whether they are required or not
    */
   protected $metadata = array(
      'columns' => array(
         array('name' =>'name', 'regex' => '/^primer\s+name$/i', 'required' => true, 'unique' => true),
         array('name' =>'orientation', 'regex' => '/^orientation$/i', 'data_regex' => '/^f|r|forward|reverse$/i', 'required' => true),
         array('name' =>'sequence', 'regex' => '/^primer\s+sequence$/i', 'data_regex' => '/^[gactyrwkmnidshv]+$/i', 'required' => true),
         array('name' =>'concentration', 'regex' => '/concentration\s+\(pmoles\)/i', 'data_regex' => '/^[0-9]{1,3}$/i', 'required' => true),
         array('name' =>'quality', 'regex' => '/quality/i', 'data_regex' => '/^bulk|purified$/i', 'required' => true),
         array('name' =>'tray', 'regex' => '/storage\s+box/i', 'required' => true),
         array('name' =>'stock', 'regex' => '/stock/i', 'data_regex' => '/^in\s+stock|out\s+of\+stock$/i', 'required' => true),
         array('name' =>'label3', 'regex' => '/label\s+3/i', 'required' => false),
         array('name' =>'related_primer', 'regex' => '/related\s+primer/i', 'required' => false),
         array('name' =>'label5', 'regex' => '/label\s+5/i', 'required' => false),
         array('name' =>'tray_pos', 'regex' => '/position\s+in\s+box/i', 'data_regex' => '/^[0-9]{1,2}|100|[a-z][0-9]0?$/i', 'required' => true)
      )
   );

   public function __construct($path, $name, $data) {
        parent::__construct($path, $name, $data);
    }

    /**
     *Normalize the primers data from the users to have consistent data.
     */
    public function NormalizeData(){
       foreach($this->data as $key => $t){
          //change the orientation to either f or r (small case)
          $this->data[$key]['dir'] = (preg_match('/^f|forward$/i', $this->data[$key]['orientation'])) ? 1 : 2;
          $this->data[$key]['qual'] = (preg_match('/^bulk$/i', $this->data[$key]['quality'])) ? 16 : 17;
          $this->data[$key]['in_stock'] = (preg_match('/^in\s+stock$/i', $this->data[$key]['stock'])) ? 1 : 2;
          $this->data[$key]['pos'] = (preg_match('/^[a-z][0-9]0?$/i', $this->data[$key]['tray_pos'])) ? $this->data[$key]['tray_pos'] : GeneralTasks::NumericPosition2LCPosition($this->data[$key]['tray_pos'], 100);
          $this->data[$key]['seq_length'] = strlen($this->data[$key]['sequence']);
          $this->data[$key]['sequence'] = strtoupper($this->data[$key]['sequence']);
       }
    }

    public function DumpData(){
//       echo '<pre>'. print_r($this->metadata, true) .'</pre>';
       echo '<pre>'. print_r($this->data, true) .'</pre>';
    }

    public function UploadData(){
       global $Repository;
       $cols = array(
          'name', 'features', 'orientation', 'conc', 'label3', 'label5', 'quality_id', 'stock', 'sequence', 'relatedseq',
          'box_id', 'box_details', 'date_created', 'keeper', 'wait', 'secret'
       );
       $rel_cols = array('module_from', 'id_from', 'module_to', 'id_to');

       //get the extra details which we shall add
       $extras = array();
       foreach($this->metadata['columns'] as $t) if(!$t['predefined']) $extras[] = $t['name'];
//       echo print_r($extras, true);
//       echo '<pre>'. print_r($this->metadata['columns'], true) .'</pre>';

       $Repository->Dbase->StartTrans();
//       echo '<pre>'. print_r($this->data[0], true) .'</pre>';
       foreach($this->data as $t){
          $boxId = $this->IsBoxSaved($t['tray']);
          if(!is_numeric($boxId)){
             $Repository->Dbase->RollBackTrans();
             return $boxId;
          }
          //add the extra fanarts
          $features = '';
          foreach($extras as $key){
             if($t[$key] != ''){
               $features .= ($features != '') ? '<br />' : '';
               $features .= "$key = {$t[$key]}";
             }
          }
//          $Repository->Dbase->CreateLogEntry($t['related_primer'], 'debug');
          $relPrimer = $this->CheckIfRelatedPrimerIsSaved($t['related_primer']);
          if(is_string($relPrimer)){
            $Repository->Dbase->RollBackTrans();
            return $relPrimer;
          }
          if($relPrimer['addedFeatures'] != '') $features .= "<br />Related Primer = {$relPrimer['addedFeatures']}";
          $features = $Repository->Dbase->dbcon->real_escape_string($features);

          $now = date('Y-m-d H:i:s');
          $colvals = array(
             $t['name'], $features, $t['dir'], $t['concentration'].'pm/ul', $t['label3'], $t['label5'], $t['qual'], $t['in_stock'],
             $t['sequence'], $relPrimer['savedRelatedPrimer'], $boxId, $t['pos'], $now, $_SESSION['contact_id'], 0, 0
          );
//          $Repository->Dbase->CreateLogEntry("Adding {$t['name']}", 'debug');
//          echo '<pre>'. print_r($colvals, true) .'</pre>';

          $addedPrimer = $Repository->Dbase->InsertData('primers', $cols, $colvals);
          if($addedPrimer == 0){
             if($Repository->Dbase->dbcon->errno == 1062) $mssg = "The primer '{$t['name']}'is a duplicate and has already been uploaded before.";
             else $mssg = $Repository->Dbase->lastError;
             $Repository->Dbase->RollBackTrans();
             return $mssg;
          }

          if($relPrimer['updateRelatedPrimer']){
             $updated = $Repository->Dbase->UpdateRecords('primers', 'relatedseq', $addedPrimer, 'count', $relPrimer['savedRelatedPrimer']);
             if($updated){
                $mssg = $Repository->Dbase->lastError;
                $Repository->Dbase->RollBackTrans();
                return $mssg;
             }

             //add the link within labcollector
             $colvals = array('PR', $addedPrimer, 'PR', $relPrimer['savedRelatedPrimer']);
             $addedRel = $Repository->Dbase->InsertData('modules_relation', $rel_cols, $colvals);
             if($addedRel == 0){
               $mssg = $Repository->Dbase->lastError;
               $Repository->Dbase->RollBackTrans();
               return $mssg;
             }
          }
       }
       $Repository->Dbase->CommitTrans();
       return 0;
    }

    /**
     * Given a related primer name, it checks if the related primer is already saved in the database. If it is not, it adds a flag for it to
     * be saved later on and saves its details in the array that will be returned. If it is saved, it adds the primer as additional data in the
     * comments
     *
     * @global object   $Repository        The main object of the system
     * @param  string   $relatedPrimerName   The name of the related primer that we are interested in
     * @return mixed    It returns a string if something happens, else it returns an array with the various variables to be used later on
     */
    private function CheckIfRelatedPrimerIsSaved($relatedPrimerName){
       global $Repository;
      //check if we have saved the related primer
      $updateRelatedPrimer = false;
      $addedFeatures = '';
      $savedRelatedPrimer = NULL;
      if($relatedPrimerName != '') {
         $savedRelatedPrimer = $Repository->Dbase->GetSingleRowValue('primers', 'count', 'name', $relatedPrimerName);
         if($savedRelatedPrimer == -2) return $Repository->Dbase->lastError;
         elseif(is_null($savedRelatedPrimer)) { /* we dont have the related primer, hence we dont have anything to do */ }
         elseif(is_numeric($savedRelatedPrimer)) {
            //we have a related primer, lets update it...., bt first lets check if we already have a related primer
            $savedRelatedPrimerSeq = $Repository->Dbase->GetSingleRowValue('primers', 'relatedseq', 'count', $savedRelatedPrimer);
//            var_dump($savedRelatedPrimerSeq);
            if($savedRelatedPrimerSeq == -2) return $Repository->Dbase->lastError;
            elseif($savedRelatedPrimerSeq == '') {    //we dont have the related primer, so queue it for update
               $updateRelatedPrimer = true;
            }
            elseif(is_numeric($savedRelatedPrimerSeq)) {    //we already have a related primer, so add this related primer to the comments
               $addedFeatures = "<br />Related Primer = $relatedPrimerName";
            }
         }
         else {
            //the impossible happened
            echo '<pre>the impossible happened! congratulations you have made history</pre>';
            return '<pre>the impossible happened! congratulations you have made history</pre>';
         }
      }
       return array('updateRelatedPrimer' => $updateRelatedPrimer, 'addedFeatures' => $addedFeatures, 'savedRelatedPrimer' => $savedRelatedPrimer);
    }

    public function CheckForDuplicates(){
      global $Repository;
      echo '<pre>'. print_r($Repository, true) .'</pre>'; // die();
      $query = "select name, count from primers where name = ?";
      if($stmt = $Repository->Dbase->dbcon->prepare($query)) {
         foreach($this->data as $data) {
            $name = NULL;
            $count = NULL;
            $stmt->bind_param('s', $data['name']);
            $stmt->execute();
            $stmt->bind_result($name, $count);
            $stmt->fetch();
            if(!is_null($count) && $count != '') $this->errors[] = "The primer <b>'$name'</b> is already in the database!";
         }
      }
      else {
         return "There was an error while checking for duplicates. Please contact the system administrator.";
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
       $boxId = $Repository->Dbase->GetSingleRowValue('boxes_def', 'box_id', 'box_name', $box_name);
       if($boxId == -2) return $Repository->Dbase->lastError;
       elseif(is_null($boxId)){
         //we assume all trays are 10x10
         $boxId = $Repository->Dbase->AddNewTray('azizi', $box_name, 'A:1.J:10', 'box', $_SESSION['contact_id']);
         if(is_string($boxId)){
            if($this->FinalDbase->dbcon->errno == 1062) continue;    //complaining of a duplicate box....lets continue
            else return $boxId;   //we have an error while adding the tray, so we just return
         }
         else return $boxId;
       }
       else return $boxId;
    }
}
?>
