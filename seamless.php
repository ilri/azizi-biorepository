<?php
require_once('label_printing_config');
include_once('../../bower/azizi-shared-libs/dbmodules/dbase_functions.php');
require_once('../../bower/azizi-shared-libs/mod_general/general.php');
session_save_path($config['session_dbase']);
session_name('sessions');
StartingSession();

$pageref = $_SERVER['PHP_SELF'];
$server_name=$_SERVER['SERVER_NAME'];
$queryString=$_SERVER['QUERY_STRING'];
if(isset($_REQUEST['page']) && $_REQUEST['page']!='')	$paging=$_REQUEST['page'];
else $paging='';
if(isset($_POST['flag']) && $_POST['flag']!='') $action=$_POST['flag'];
else $action='';
$content='';

//ensure that this user has logged in and is allowed to use the system
if(isset($_SESSION['username']) && isset($_SESSION['psswd'])){
//   $res=ConfirmUser($_SESSION['username'], $_SESSION['psswd']);
//   if($res==-1) die(ErrorPage());
}
else{
   die(ErrorPage("Error! The system does not recognise you. Please log in through <a href='/avid/'>Home</a>
         to access AVID system resources.<br />Please contact the system administrator if you have any problems."));
}

if($action=='printedLabels') FetchPrintedLabels();
elseif($action=='save_settings') SaveSettings();

function FetchPrintedLabels(){
global $query;
   $query= "select a.*, c.value as project_name, b.sname, b.onames from misc_db.printed_labels as a "
      ."inner join misc_db.users as b on a.user=b.id inner join azizi.modules_custom_values as c on a.project=c.val_id order by a.first_label, a.date";
   $res = GetQueryValues($query, MYSQL_ASSOC);
   if(is_string($res)){
      die('-1There was an error while fetching data from the database. Pleas try again later or contact the system administrator.');
   }
   $content="<table class='sortable'><thead>
      <tr><th>Date</th><th>Printed By</th><th>First Label</th><th>Last Label</th><th>Copies</th><th>Total</th><th>Project</th></tr>
   </thead><tbody>";
   foreach($res as $t){
      $content.="<tr><td>".$t['date']."</td><td>".$t['sname']." ".$t['onames']."</td><td>".$t['first_label']."</td><td>".$t['last_label']
      ."</td><td>".$t['copies']."</td><td>".$t['total']."</td><td>".$t['project_name']."</td></tr>";
//      if($t['usage']!='') $content.="<tr><td colspan='7'>Usage: ".$t['usage']."</td></tr>";
//      if($t['remarks']!='') $content.="<tr><td colspan='7'>Remarks: ".$t['remarks']."</td></tr>";
   }
   $content.="</tbody></table>";
   die($content);
}
//=============================================================================================================================================

function SaveSettings(){
global $query;
   //check all the values passed for data integrity
   if(Checks($_POST['project'], '', '^[a-zA-Z]+$')) die('-1Error in the input data. Please select the project the labels will be used for.'.$_POST['project']);
   if(Checks($_POST['prefix'], '', '^[a-zA-Z]{3,5}$')) die('-1Error in the input data. Please enter or check the prefix of the labels.');
   if(Checks($_POST['first'], '', '^[a-zA-Z]{3,5}[0-9]{5,6}$')) die('-1Error in the input data. Please enter or check the first label in the printed series.');
   if(Checks($_POST['last'], '', '^[a-zA-Z]{3,5}[0-9]{5,6}$')) die('-1Error in the input data. Please enter or check the last label in the printed series.');
   if(!is_numeric($_POST['copies'])) die('-1Error in the input data. Please enter or check the number of copies being printed.');
   if(!is_numeric($_POST['total'])) die('-1Error in the input data. Please enter or check the total number of labels printed.');
   if($_POST['remarks']!='' && Checks($_POST['remarks'], 12)) die('-1Error in the input data. Please refine the entered remarks.');
   //get the user doing this transaction
   $userId=GetSingleRowValue('misc_db.users','id','login',$_SESSION['username']);
   if($userId==-2){
      LogError(); die('-1There was an error in fetching data from the database. Please contact the system administrator.');
   }
   //get the project id
   $projectId = GetSingleRowValue('azizi.modules_custom_values', 'val_id', 'value', strtoupper($_POST['project']));
   if($projectId==-2 || is_null($projectId)){
      LogError(); die('-1There was an error while fetching data from the database. Please contact the system administrator');
   }
   $cols=array('user', 'prefix', 'first_label', 'last_label', 'copies', 'total', 'project', 'file_location', 'remarks');
   $colvals=array($userId,
      mysql_real_escape_string(strtoupper($_POST['prefix'])),
      mysql_real_escape_string(strtoupper($_POST['first'])),
      mysql_real_escape_string(strtoupper($_POST['last'])),
      $_POST['copies'], $_POST['total'], $projectId, '', mysql_real_escape_string($_POST['remarks']),
   );
   StartTrans();
   $res=InsertValues('misc_db.printed_labels', $cols, $colvals);
   if(is_string($res)){
      LogError(); die('-1There was an error while saving the changes to the database. Please contact the system administrator.');
   }
   CommitTrans();
   FetchPrintedLabels();
}
//=============================================================================================================================================
?>
