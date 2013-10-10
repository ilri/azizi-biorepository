<?php

/**
 * The main class of the system. All other classes inherit from this main one
 *
 * @category   AVID
 * @package    LN2Transferer
 * @author     Jason Rogena <j.rogena@cgiar.org>
 * @since      v0.1
 */
class LN2Transferer extends Repository{

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

   public function __construct($Dbase) {
      $this->Dbase = $Dbase;
   }

   public function TrafficController() {
      if(OPTIONS_REQUEST_TYPE == 'normal'){
         echo "<script type='text/javascript' src='js/ln2_transfers.js'></script>";
         echo "<script type='text/javascript' src='" . OPTIONS_COMMON_FOLDER_PATH . "jquery.form.js' /></script>";
         echo "<script type='text/javascript' src='" . OPTIONS_COMMON_FOLDER_PATH . "jquery.flexigrid/flexigrid.pack.js' /></script>";
         echo "<script type='text/javascript' src='" . OPTIONS_COMMON_FOLDER_PATH . "jquery.ui/js/jquery-ui.min.js' /></script>";
         echo "<link rel='stylesheet' type='text/css' href='" . OPTIONS_COMMON_FOLDER_PATH . "jquery.flexigrid/css/flexigrid.pack.css' />";
         echo "<link rel='stylesheet' type='text/css' href='" . OPTIONS_COMMON_FOLDER_PATH . "jquery.ui/css//smoothness/jquery-ui.css' />";
      }
      
      if (OPTIONS_REQUESTED_SUB_MODULE == '') $this->HomePage();
      elseif (OPTIONS_REQUESTED_SUB_MODULE == 'submit') $this->submitNitrogenTrasfer ();
      else if (OPTIONS_REQUESTED_SUB_MODULE == 'fetch') $this->fetchTransferHistory ();
   }

   /**
    * Create the home page for transfering Nitrogen
    */
   private function HomePage($addinfo = '') {
      $addinfo = ($addinfo != '') ? "<div id='addinfo'>$addinfo</div>" : '';
      
      
      ?>
<div id='home'>
   <h3>Nitrogen Transfer</h3>
   <?php echo $addinfo?>
   <form enctype="multipart/form-data" name="upload" method="POST" action="index.php?page=ln2_transfers&do=submit" onsubmit="return LN2Transferer.submitNewTransfer();" >
      <div id="generate">
         <fieldset>
            <legend>Add a new Transfer</legend>
               <table id="mainField">
                  <tr><td class="label">Technician</td><td><input type="text" name="technician" id="technician" value="<?php echo $_SESSION['onames']." ".$_SESSION['surname'];?>"/></td></tr>
                  <tr><td class="label">Date of transfer</td><td><input type="text" name="date" id="date" value="<?php echo date("d-m-Y")?>"/></td></tr>
                  <script>
                     $(function() {
                        $( "#date" ).datepicker({maxDate: '0', dateFormat: 'dd-mm-yy',minDate:'-2'});
                     });
                  </script>
                  <tr><td class="label">Litres transfered</td><td><input type="text" name="litres" id="litres" value="" size="4" disabled="true"/></td></tr>
                  <tr><td colspan="2">
                        <fieldset>
                           <legend>Production Levels</legend>
                           <table>
                              <tr>
                                 <td class="label">Before Transfer</td><td><input type="text" name="pBeforeTransfer" id="pBeforeTransfer" value="" size="4"/></td>
                                 <td class="label">After Transfer</td><td><input type="text" name="pAfterTransfer" id="pAfterTransfer" value="" size="4"/></td>
                                 <td class="label">Pressure Loss</td><td><input type="text" name="pressureLoss" id="pressureLoss" value="" size="4"/></td>
                              </tr>
                              <script>
                                 $("#pAfterTransfer").change(function (){
                                    if($("#pBeforeTransfer").val() !== "" && !isNaN($("#pBeforeTransfer").val())) {
                                       if($("#pAfterTransfer").val() !== "" && !isNaN($("#pAfterTransfer").val())){
                                          var diff = $("#pBeforeTransfer").val() - $("#pAfterTransfer").val();
                                          $("#litres").val(diff*10);
                                       }
                                    }
                                 });
                                 $("#pBeforeTransfer").change(function (){
                                    if($("#pBeforeTransfer").val() !== "" && !isNaN($("#pBeforeTransfer").val())) {
                                       if($("#pAfterTransfer").val() !== "" && !isNaN($("#pAfterTransfer").val())){
                                          var diff = $("#pBeforeTransfer").val() - $("#pAfterTransfer").val();
                                          $("#litres").val(diff*10);
                                       }
                                    }
                                 });
                              </script>
                           </table>
                        </fieldset>
                  </td></tr>
                  <tr><td colspan="2">
                        <input type="submit" value="Submit" name="submitButton" id="submitButton"/>
                        <input type="reset" value="Cancel" name="cancelButton" id="cancelButton"/>
                  </td></tr>
               </table>
         </fieldset>
      </div>
   </form>
   <div id="past_transfers">&nbsp;</div>
</div>
<script type="text/javascript">
   $("#past_transfers").flexigrid({
      url: "mod_ajax.php?page=ln2_transfers&do=fetch",
      dataType: 'json',
      colModel : [
         {display: 'Date', name: 'date', width: 100, sortable: true, align: 'center'},
         {display: 'Technician', name: 'username', width: 300, sortable: true, align: 'left'},
         {display: 'Prod. Before Transfer', name: 'pb_transfer', width: 150, sortable: false, align: 'center'},
         {display: 'Prod. After Transfer', name: 'pa_transfer', width: 150, sortable: false, align: 'center'},
         {display: 'Pressure Loss', name: 'pressure_loss', width: 100, sortable: false, align: 'center'}
      ],
      searchitems : [
         {display: 'Technician', name : 'username'}
      ],
      sortname : 'date',
      sortorder : 'desc',
      usepager : true,
      title : 'Past Transfers',
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
    * Adds last form input database
    */
   private function submitNitrogenTrasfer() {
      //$userID = $this->addUserIfNotExists($_POST['technician']);
      $message = "";
      $cols = array("date","user","pb_transfer","pa_transfer","pressure_loss");
      $date = DateTime::createFromFormat('d-m-Y',$_POST['date']);
      $colVals = array($date->format("Y-m-d"), $_POST['technician'], $_POST["pBeforeTransfer"], $_POST["pAfterTransfer"], $_POST["pressureLoss"]);
      $res = $this->Dbase->InsertOnDuplicateUpdate("transfers",$cols,$colVals);
      if($res === 0) {
         $message = "Unable to add the last nitrogen transfer. Try again later";
      }
      $this->HomePage($message);
   }
   
   /**
    * Fetches data related to Nitorgen Transfers made and formats the data the way flexigrid likes it
    */
   private function fetchTransferHistory() {
      //check if search criteria provided
      $criteriaArray = array();
      if($_POST['query'] != ""){
         $criteria = "WHERE {$_POST['qtype']} LIKE '%?%";
         $criteriaArray[] = $_POST['query'];
      }
      else
         $criteria = "";
      
      $startRow = ($_POST['page'] - 1) * $_POST['rp'];
      $query = "SELECT transfers.*".
              " FROM `transfers`".
              " $criteria".
              " ORDER BY {$_POST['sortname']} {$_POST['sortorder']}";
      $query2 = $query." LIMIT $startRow, {$_POST['rp']}";
      $data = $this->Dbase->ExecuteQuery($query2,$criteriaArray);
      
      //check if any data was fetched
      if($data === 1)
         die (json_encode (array('error' => true)));
      $dataCount = $this->Dbase->ExecuteQuery($query,$criteriaArray);
      if($dataCount === 1) 
         die (json_encode (array('error' => true)));
      else 
         $dataCount = sizeof ($dataCount);
      
      //reformat rows fetched from first query
      $rows = array();
      foreach ($data as $row) {
         $rows[] = array("id" => $row['id'], "cell" => array("date" => $row['date'],"username" => $row['user'],"pb_transfer" => $row["pb_transfer"], "pa_transfer" => $row["pa_transfer"], "pressure_loss" => $row["pressure_loss"]));
      }
      $response = array(
          'total' => $dataCount,
          'page' => $_POST['page'],
          'rows' => $rows
      );
      
      die(json_encode($response));
   }
}
?>
