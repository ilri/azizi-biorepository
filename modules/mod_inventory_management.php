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
      /*
       * Request heirarchy looks something like
       *    - issue
       *    - fetch
       */

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
      elseif (OPTIONS_REQUESTED_SUB_MODULE == 'return') $this->returnItem();
      elseif (OPTIONS_REQUESTED_SUB_MODULE == 'ajax' && OPTIONS_REQUESTED_ACTION == "download_recharge_file") $this->downloadRechargeFile();
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
   <form class="form-horizontal issue_inv" id="inventory_form" enctype="multipart/form-data" name="upload" method="POST" action="index.php?page=inventory&do=issue" onsubmit="return InventoryManager.submitNewIssueance();" >
       <div class="form-group left-align">
           <label for="item">Name of issued item</label>
           <input type="text" name="item" id="item" />
       </div>
       <div class="form-group left-align">
           <label for="item">Quantity</label>
           <input type="text" class="input-medium" name="quantity" id="quantity" />
        </div>
       <div class="form-group left-align">
            <label for="date">Date Issued</label>
            <input type="text" class="input-medium" name="date" id="date" value="<?php echo date("d-m-Y")?>" class="form-control" />
        </div>
       <div class="form-group left-align">
           <label for="issued_to">Item issued to</label>
           <input type="text" class="input-medium" name="issued_to" id="issued_to" value="" />
       </div>
       <div class="form-group left-align">
           <label for="issued_by">Item issued by</label>
           <input type="text" class="input-medium" name="issued_by" id="issued_by" disabled="true" value="<?php echo $_SESSION['onames']." ".$_SESSION['surname'];?>" />
       </div>
       <div class="form-group left-align">
            <label for="borrowed">Item to be returned?</label>
            <select name="borrowed" id="borrowed" class="input-medium" onchange="InventoryManager.toggleBorrowMode();" style="margin-left: 5px;">
                <option value="yes">Yes</option>
                <option value="no">No</option>
            </select>
        </div>
       <div id="borrowed_sec" class="form-group">
           <!--<label for="item_returned" >Item returned?</label>
           <div class=""><input type="checkbox" class="form-control" name="item_returned" id="item_returned" style="width: 20px; height: 20px; margin-left: 5px;" /></div>-->
       </div>
       <div id="not_borrowed_sec" style="display: none;">
          <div class="form-group left-align"><label for="pp_unit">Price per unit</label><input type="text" name="pp_unit" id="pp_unit" value="KES" class="input-medium" /></div>
           <div class="form-group left-align"><label for="project">Full Charge Code</label><input type="text" name="project" id="project" value="" disabled="true" size="50" class="form-control" class="input-medium" /></div>
           <div class="form-group left-align"><label for="chargeCode">Activity Code</label><input type="text" name="chargeCode" id="chargeCode" value="" class="form-control" class="input-medium" /></div>
        </div>
       <div class="form-group left-align">
           <label for="comment" >Comment</label>
           <textarea id="comment" name="comment" cols="30" rows="2" class="form-control" style="margin-left: 5px;"></textarea>
       </div>
        <div class="center">
           <input type="submit" value="Submit" name="submitButton" id="submitButton"/>
        </div>
   </form>
   <?php
      if(isset($_SESSION['user_type']) && (in_array("Biorepository Manager", $_SESSION['user_type']) || in_array("Super Administrator", $_SESSION['user_type']))) {
         echo "<div class='center' style='margin-top:10px;margin-left:700px;margin-bottom:10px;'><button id='recharge_btn' type='button' class='btn btn-primary'>Recharge Items</button></div>";
      }
   ?>
   <div id="issued_items">&nbsp;</div>
   <div id="return_comment_div" style="display: none; position: absolute; width: auto; height: auto; background: white; box-shadow:0 1px 2px #aaa; padding: 1rem;">
      Provide comments if any<br />
      <textarea id="return_comment" style="width: 300px;"></textarea><br />
      <input type="submit" value="Okay" id="return_comment_btn" style="float:right;" />
   </div>
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
         {display: 'Quantity', name: 'quantity', width: 100, sortable: false, align: 'center'},
         {display: 'Issued by', name: 'issued_by', width: 150, sortable: true, align: 'left'},
         {display: 'Issued to', name: 'issued_to', width: 150, sortable: false, align: 'center'},
         {display: 'Item borrowed', name: 'item_borrowed', width: 100, sortable: false, align: 'center'},
         {display: 'Item returned', name: 'item_returned', width: 100, sortable: false, align: 'left' }
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
   $("#recharge_btn").click(function(){
      InventoryManager.downloadRechargeFile();
   });
</script>
      <?php
   }

   /**
    * This function writes borrow/asset issuance to the database
    */
   private function submitIssuance() {
      $message = "";
      //$userID = $this->addUserIfNotExists($_POST['user']);
      $date = date('Y-m-d', strtotime($_POST['date']));
      $this->Dbase->CreateLogEntry("mod_inventory_management: date = ".$date, 'debug', true);
      $projectID = $this->getProjectID($_POST['chargeCode']);
      if($projectID !== 0 && $_POST['borrowed'] === "no"){//item not borrowed and chargecode in database
         $cols = array("chargecode_id", "pp_unit","date_issued","item","issued_to","issued_by","comment", "item_borrowed", "quantity");
         $colVals = array($projectID, $_POST['pp_unit'],$date,$_POST['item'],$_POST['issued_to'],$_SESSION['username'],$_POST['comment'], FALSE, $_POST['quantity']);
         $res = $this->Dbase->InsertOnDuplicateUpdate("inventory",$cols,$colVals);
         if($res === 0) {
            $message = "Unable to add the last request. Try again later";
         }
      }
      else if($projectID === 0 && $_POST['borrowed'] === "no") {//user entered a charge code not in the database
         $cols = array("alt_ccode", "pp_unit","date_issued","item","issued_to","issued_by","comment", "item_borrowed", "quantity");
         $colVals = array($_POST['chargeCode'], $_POST['pp_unit'],$date,$_POST['item'],$_POST['issued_to'],$_SESSION['username'],$_POST['comment'], FALSE, $_POST['quantity']);
         $res = $this->Dbase->InsertOnDuplicateUpdate("inventory",$cols,$colVals);
         if($res === 0) {
            $message = "Unable to add the last request. Try again later";
         }

      }
      else {//the item has been borrowed
         $cols = array("date_issued","item","issued_to","issued_by","comment", "item_borrowed", "quantity");
         $colVals = array($date,$_POST['item'],$_POST['issued_to'],$_SESSION['username'],$_POST['comment'], TRUE, $_POST['quantity']);
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
      
      $this->Dbase->CreateLogEntry("mod_inventory_management: About to run the following query ".$query, 'debug', true);
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
         $rows[] = array("id" => $row['id'], "cell" => array("date_issued" => $row['date_issued'],"issued_by" => $row['issued_by'],"issued_to" => $row["issued_to"], "item" => $row["item"], "item_borrowed" => $row["item_borrowed"], "item_returned" => $row["item_returned"], "comment" => $row['comment'], "quantity" => $row['quantity']));
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
   
   /**
    * This function writes a return of a borrowed item to the database
    */
   private function returnItem(){
      $itemID = $_POST['id'];
      $comment = $_POST['comment'];
      
      $this->Dbase->CreateLogEntry($_POST['comment'], "fatal");
      
      $query = "SELECT item_borrowed FROM inventory WHERE id = ? AND item_borrowed = 1";//check if there is an item with the same 
      $result = $this->Dbase->ExecuteQuery($query, array($itemID));
      
      if(is_array($result) && count($result) == 1){
         $query = "UPDATE inventory SET item_returned = 1, ret_comment = :comment WHERE id = :id";
         $this->Dbase->ExecuteQuery($query, array("id" => $itemID, "comment" => $comment));
      }
   }
   
   /**
    * This function generates a csv file for recharging items acquired from the biorepository
    */
   private function downloadRechargeFile(){
      if(isset($_SESSION['user_type']) && (in_array("Biorepository Manager", $_SESSION['user_type']) || in_array("Super Administrator", $_SESSION['user_type']))) {
         $query = "select a.id, a.item, a.issued_by, a.issued_to, a.date_issued, b.name as charge_code, a.alt_ccode, a.pp_unit, a.quantity"
                 . " from inventory as a"
                 . " left join ln2_chargecodes as b on a.chargecode_id=b.id"
                 . " where item_borrowed = 0 and rc_timestamp is null";
         $result = $this->Dbase->ExecuteQuery($query);
         
         if(is_array($result)){
            for($i = 0 ; $i < count($result); $i++){
               if($result[$i]['charge_code'] == null){
                  $result[$i]['charge_code'] = $result[$i]['alt_ccode'];
               }
               
               $query = "update inventory"
                       . " set rc_timestamp = now(), rc_charge_code = :charge_code"
                       . " where id = :id";
               $this->Dbase->ExecuteQuery($query, array("charge_code" => $result[$i]['charge_code'], "id" => $result[$i]['id']));
               
               unset($result[$i]['alt_ccode']);
            }
            $headings = array(
                "id" => "Item ID",
                "item" => "Item",
                "issued_by" => "Issued By",
                "issued_to" => "Issued To",
                "date_issued" => "Date Issued",
                "charge_code" => "Charge Code",
                "pp_unit" => "Price per Unit",
                "quantity" => "Quantity"
            );
            
            $csv = $this->generateCSV(array_merge(array($headings), $result), FALSE);
            $fileName = "item_recharge_".date('Y_m_d').".csv";
            
            file_put_contents("/tmp/".$fileName, $csv);
            header('Content-type: document');
            header('Content-Disposition: attachment; filename='. $fileName);
            header("Expires: 0"); 
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0"); 
            header("Content-length: " . filesize("/tmp/".$fileName));
            header('Content-Transfer-Encoding: binary');
            readfile("/tmp/" . $fileName);
            
            if(count($result) > 0){   
               $emailSubject = "Item Recharge";
               $emailBody = "Find attached a csv file containing data for item recharges.";
               $this->sendRechargeEmail(Config::$managerEmail, $emailSubject, $emailBody, "/tmp/".$fileName);
            }
            else {
               $emailSubject = "Item Recharge";
               $emailBody = "No items found that can be recharged.";
               $this->sendRechargeEmail(Config::$managerEmail, $emailSubject, $emailBody);
            }
            
            $this->Dbase->CreateLogEntry("Recharging file at /tmp/".$fileName, "info");
            unlink("/tmp/" . $fileName);
         }
      }
   }
   
   /**
    * This function generates a CSV string from a two dimensional array.
    * Make sure each of the second level associative arrays the same size.
    * The following array will not be parsed correctly:
    *  [
    *    [0,1,2]
    *    [0,1]
    *    [0,1,2]
    *  ]
    * 
    * @param type $array
    * @param type $headingsFromKeys
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
