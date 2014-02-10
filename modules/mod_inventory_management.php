<?php
/**
 * The main class of the system. All other classes inherit from this main one
 *
 * @category   AVID
 * @package    LN2 Requests
 * @author     Kihara Absolomon <a.kihara@cgiar.org>
 * @author     Jason Rogena <j.rogena@cgiar.org>
 * @since      v0.2
 */

class InventoryManager extends Repository{

   public $Dbase;

   public function __construct($Dbase) {
      $this->Dbase = $Dbase;
   }

   public function TrafficController() {

      if(OPTIONS_REQUEST_TYPE == 'normal'){
         echo "<script type='text/javascript' src='js/inventory_management.js'></script>";
         echo "<script type='text/javascript' src='" . OPTIONS_COMMON_FOLDER_PATH . "jquery.form.js' /></script>";
         echo "<script type='text/javascript' src='" . OPTIONS_COMMON_FOLDER_PATH . "jquery.flexigrid/flexigrid.pack.js' /></script>";
         echo "<script type='text/javascript' src='" . OPTIONS_COMMON_FOLDER_PATH . "jquery.ui/js/jquery-ui.min.js' /></script>";
         echo "<link rel='stylesheet' type='text/css' href='" . OPTIONS_COMMON_FOLDER_PATH . "jquery.flexigrid/css/flexigrid.pack.css' />";
         echo "<link rel='stylesheet' type='text/css' href='" . OPTIONS_COMMON_FOLDER_PATH . "jquery.ui/css//smoothness/jquery-ui.css' />";
      }

      if (OPTIONS_REQUESTED_SUB_MODULE == '') $this->HomePage();
      if (OPTIONS_REQUESTED_SUB_MODULE == 'issue') $this->submitIssuance ();
      elseif (OPTIONS_REQUESTED_SUB_MODULE == 'fetch') $this->fetchInventoryHistory ();
   }

   /**
    * Create the home page for generating the labels
    */
   private function HomePage($addinfo = '') {
      $projects = $this->getProjects();
      if($projects == 1){
         $this->RepositoryHomePage("There was an error while fetching data from the database.");
         return;
      }
      $chargeCodes = array();
      $chargeCodesWP = array();
      foreach ($projects as $currentP) {
         $chargeCodes[] = $currentP['charge_code'];
         $chargeCodesWP[$currentP['charge_code']] = $currentP['name'];
      }
      $addinfo = ($addinfo != '') ? "<div id='addinfo'>$addinfo</div>" : '';

      ?>
<div id='home'>
   <h3 class="center">Inventory Management</h3>
   <hr />
   <?php echo $addinfo?>
   <form class="form-horizontal issue_inv" enctype="multipart/form-data" name="upload" method="POST" action="index.php?page=inventory&do=issue" onsubmit="return InventoryManager.submitNewIssueance();" >
       <div class="form-group">
           <label for="item" class="control-label">Name of issued item</label>
           <div class=""><input type="text" class="form-control" name="item" id="item" /></div>
        </div>
       <div class="form-group">
            <label for="date" class="control-label">Date Issued</label>
            <div class=""><input type="text" name="date" id="date" value="<?php echo date("d-m-Y")?>" class="form-control" /></div>
        </div>
       <div class="form-group">
           <label for="issued_to" class="control-label">Item issued to</label>
           <div class=""><input type="text" class="form-control" name="issued_to" id="issued_to" value="" /></div>
       </div>
       <div class="form-group">
           <label for="issued_by" class="control-label">Item issued by</label>
           <div class=""><input type="text" class="form-control" name="issued_by" id="issued_by" disabled="true" value="<?php echo $_SESSION['onames']." ".$_SESSION['surname'];?>" /></div>
       </div>
       <div class="form-group">
            <label for="borrowed" class="control-label">Item to be returned?</label>
            <select name="borrowed" id="borrowed" onchange="InventoryManager.toggleBorrowMode();" style="margin-left: 5px;">
                <option value="yes">Yes</option>
                <option value="no">No</option>
            </select>
        </div>
       <div id="borrowed_sec" class="form-group">
           <!--<label for="item_returned" class="control-label">Item returned?</label>
           <div class=""><input type="checkbox" class="form-control" name="item_returned" id="item_returned" style="width: 20px; height: 20px; margin-left: 5px;" /></div>-->
       </div>
       <div id="not_borrowed_sec" class="form-group" style="display: none;">
           <div><label for="pp_unit" class="control-label">Price per unit</label><input type="text" name="pp_unit" id="pp_unit" value="USD" /></div>
           <div><label for="project" class="control-label">Project</label><input type="text" name="project" id="project" value="" disabled="true" size="50" class="form-control" /></div>
           <div><label for="chargeCode" class="control-label">Charge Code</label><input type="text" name="chargeCode" id="chargeCode" value="" class="form-control" /></div>
        </div>
       <div class="form-group">
           <label for="comment" class="control-label">Comment</label>
           <textarea id="comment" name="comment" cols="30" rows="2" class="form-control" style="margin-left: 5px;"></textarea>
       </div>
        <div class="center">
           <input type="submit" value="Submit" name="submitButton" id="submitButton"/>
           <input type="reset" value="Cancel" name="cancelButton" id="cancelButton"/>
        </div>
   </form>
   <div id="issued_items">&nbsp;</div>
</div>
<script type="text/javascript">
   $('#whoisme .back').html('<a href=\'?page=home\'>Back</a>');
   $(function() {
      $( "#date" ).datepicker({maxDate: '0', dateFormat: 'dd-mm-yy'});
      var chargeCodes = <?php echo json_encode($chargeCodes); ?>;
      var projects = <?php echo json_encode($chargeCodesWP); ?>;
      for(var i = 0; i < chargeCodes.length; i++) {
         if(chargeCodes[i] === null) {
            chargeCodes.splice(i, 1);
            i--;
         }
      }
      $("#chargeCode").autocomplete({
         source: chargeCodes,
         minLength: 2,
         select: function (event, ui) {
            var value = ui.item.value;
            $("#project").val(projects[value]);
         }
      });
   });
   $("#issued_items").flexigrid({
      url: "mod_ajax.php?page=inventory&do=fetch",
      dataType: 'json',
      colModel : [
         {display: 'Date', name: 'date_issued', width: 100, sortable: true, align: 'center'},
         {display: 'Item', name: 'item', width: 100, sortable: false, align: 'center'},
         {display: 'Issued by', name: 'issued_by', width: 100, sortable: true, align: 'left'},
         {display: 'Issued to', name: 'issued_to', width: 100, sortable: false, align: 'center'},
         {display: 'Item borrowed', name: 'item_borrowed', width: 100, sortable: false, align: 'center'},
         {display: 'Item returned', name: 'item_returned', width: 100, sortable: false, align: 'left' },
         {display: 'Comment', name: 'comment', width: 220, sortable: false, align: 'center'},
      ],
      searchitems : [
         {display: 'Issued by', name : 'a.issued_by'},
         {display: 'Issued to', name : 'a.issued_to'},
         {display: 'Item', name : 'item'}
      ],
      sortname : 'date_issued',
      <?php
         if(isset($_SESSION['username']) && in_array($_SESSION['username'],Config::$inventory_managers)) {
            echo "buttons : [{name: 'Set returned', bclass: 'edit', onpress : InventoryManager.setReturned}],";
         }
      ?>
      sortorder : 'desc',
      usepager : true,
      title : 'Past Requests',
      useRp : true,
      rp : 10,
      showTableToggleBtn: false,
      rpOptions: [10, 20, 50], //allowed per-page values
      width: 900,
      height: 260,
      singleSelect: true
   });
</script>
      <?php
   }

   /**
    * Submits Nitrogen Acquisition Request to database
    */
   private function submitIssuance() {
      $message = "";
      //$userID = $this->addUserIfNotExists($_POST['user']);
      $date = date('Y-m-d', strtotime(str_replace("-", "/", $_POST['date'])));
      $projectID = $this->getProjectID($_POST['chargeCode']);
      if($projectID !== 0 && $_POST['borrowed'] === "no"){//item not borrowed and chargecode in database
         $cols = array("chargecode_id", "pp_unit","date_issued","item","issued_to","issued_by","comment", "item_borrowed");
         $colVals = array($projectID, $_POST['pp_unit'],$date,$_POST['item'],$_POST['issued_to'],$_SESSION['username'],$_POST['comment'], FALSE);
         $res = $this->Dbase->InsertOnDuplicateUpdate("inventory",$cols,$colVals);
         if($res === 0) {
            $message = "Unable to add the last request. Try again later";
         }
      }
      else if($projectID === 0 && $_POST['borrowed'] === "no") {//user entered a charge code not in the database
         $cols = array("alt_ccode", "pp_unit","date_issued","item","issued_to","issued_by","comment", "item_borrowed");
         $colVals = array($_POST['chargeCode'], $_POST['pp_unit'],$date,$_POST['item'],$_POST['issued_to'],$_SESSION['username'],$_POST['comment'], FALSE);
         $res = $this->Dbase->InsertOnDuplicateUpdate("inventory",$cols,$colVals);
         if($res === 0) {
            $message = "Unable to add the last request. Try again later";
         }

      }
      else {//the item has been borrowed
         $cols = array("date_issued","item","issued_to","issued_by","comment", "item_borrowed");
         $colVals = array($date,$_POST['item'],$_POST['issued_to'],$_SESSION['username'],$_POST['comment'], TRUE);
         $res = $this->Dbase->InsertOnDuplicateUpdate("inventory",$cols,$colVals);
         if($res === 0) {
            $message = "Unable to add the last request. Try again later";
         }
      }
      $this->HomePage($message);
   }

   /**
    * Fetches the requisitons in the database and formats these the way flexigrid likes it
    */
   private function fetchInventoryHistory() {
      //check if search criterial provided
      $criteriaArray = array();
      if($_POST['query'] != "") {
         $criteria = "WHERE {$_POST['qtype']} LIKE '%?%'";
         $criteriaArray[] = $_POST['query'];
         /*if(!in_array($_SESSION['username'],Config::$inventory_managers)) {
            $criteria = $criteria." AND a.`issued_by` = ?";
            $criteriaArray[] = $_SESSION['username'];
         }*/
      }
      else {
         $criteria = "";
         /*if(!in_array($_SESSION['username'],Config::$inventory_managers)) {
            $criteria = $criteria."WHERE a.`issued_by` = ?";
            $criteriaArray[] = $_SESSION['username'];
         }*/
      }

      $startRow = ($_POST['page'] - 1) * $_POST['rp'];
      $query = "SELECT a.*, b.name AS project, b.`charge_code`".
              " FROM inventory AS a".
              " LEFT JOIN ln2_chargecodes AS b ON a.`chargecode_id` = b.id".
              " $criteria".
              " ORDER BY {$_POST['sortname']} {$_POST['sortorder']}";
      //$this->Dbase->query = $query." LIMIT $startRow, {$_POST['rp']}";
      $data = $this->Dbase->ExecuteQuery($query." LIMIT $startRow, {$_POST['rp']}" , $criteriaArray);

      //check if any data was fetched
      if($data === 1)
         die (json_encode (array('error' => true)));
      //$this->Dbase->query = $query;
      $dataCount = $this->Dbase->ExecuteQuery($query,$criteriaArray);
      if($dataCount === 1)
         die (json_encode (array('error' => true)));
      else
         $dataCount = sizeof ($dataCount);

      //reformat rows fetched from first query
      $rows = array();
      foreach ($data as $row) {
         if($row['alt_ccode'] !== NULL){//check if row has no associated project
            $row["charge_code"] = $row["alt_ccode"];
         }
         if($row['comment'] === NULL) $row['comment'] = "";
         if($row['item_borrowed'] == TRUE) $row['item_borrowed'] = "Yes";
         else $row['item_borrowed'] = "No";
         if($row['item_borrowed'] === "No") $row['item_returned'] = "N/A";
         else if($row['item_returned'] == TRUE) $row['item_returned'] = "Yes";
         else if($row['item_returned'] == FALSE) $row['item_returned'] = "No";
         $rows[] = array("id" => $row['id'], "cell" => array("date_issued" => $row['date_issued'],"issued_by" => $row['issued_by'],"issued_to" => $row["issued_to"], "item" => $row["item"], "item_borrowed" => $row["item_borrowed"], "item_returned" => $row["item_returned"], "comment" => $row['comment']));
      }
      $response = array(
          'total' => $dataCount,
          'page' => $_POST['page'],
          'rows' => $rows
      );

      die(json_encode($response));
   }
   
   /**
    * Returns all the projects in the database in a associative array
    *
    * @return  assoc array   The fetched projects
    */
   private function  getProjects() {
      $query = "SELECT * FROM ln2_chargecodes";
      $result = $this->Dbase->ExecuteQuery($query);
      return $result;
   }
   
   /**
    * Gets the project ID corresponding to the specified charge code
    * @param   string   $chargeCode   The charge code for which the wanted project corresponds to
    * @return int       Returns the project ID or 0 if and error occures during execution
    */
   private function getProjectID($chargeCode) {
      $query = "SELECT id FROM ln2_chargecodes WHERE charge_code = ?";
      $result = $this->Dbase->ExecuteQuery($query,array($chargeCode));
      if ($result == 1){
         $this->Dbase->CreateLogEntry("There was an error while fetching data from the database.", 'fatal', true);
         $this->Dbase->lastError = "There was an error while fetching data from the session database.<br />Please try again later.";
         return 0;
      }
      else if(sizeof($result) > 0) {
         return $result[0]['id'];
      }
      else {
         return 0;
      }
   }
}
?>
