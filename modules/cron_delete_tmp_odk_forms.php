<?php
try{
   $dumpDir = "/tmp/";
   $user = "azizi_repository";
   $password = "JfQf967u94qK";
   $database = "azizi_odk";
   $host = "boran.ilri.cgiar.org";
	$db = new PDO("mysql:host=".$host.";dbname=".$database.";charset=utf8", $user, $password);
	$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
   
   $query = "select a.instance_id, b.id from azizi_miscdb.odk_forms as a inner join azizi_miscdb.odk_deleted_forms as b on a.id = b.form where b.time_to_delete < now() and b.status = 'not_deleted'";//time to delete is in the past and status not deleted
   $stmt = $db->query($query);
   $forms = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($forms);
   foreach($forms as $currForm){
      $instanceId = $currForm['instance_id'];
      $formDir = $dumpDir.$instanceId;
      mkdir($formDir);
      $query = "show tables like '$instanceId%'";
      $stmt = $db->query($query);
      $tables = $stmt->fetchAll(PDO::FETCH_NUM);
      foreach($tables as $currTable){
         $mysqldump = "mysqldump -u ".$user." -p".$password." -h ".$host." ".$database." ".$currTable[0]." > ".$formDir."/".$currTable[0].".sql";
         exec($mysqldump);
         
         $query = "DROP TABLE ".$currTable[0];
         //$stmt = $db->query($query);
         echo "done ".$query." \n";
      }
      $query = "UPDATE azizi_miscdb.odk_deleted_forms SET status = 'deleted' WHERE id = ".$currForm['id'];
      $stmt = $db->query($query);
   }
}
catch(PDOException $ex){
	echo $ex;
}
?>
