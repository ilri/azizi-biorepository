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
   
   $query = "select a.instance_id, a.id from azizi_miscdb.odk_forms as a inner join azizi_miscdb.odk_deleted_forms as b on a.id = b.form where b.time_to_delete < now() and a.is_active = 1";//time to delete is in the past and status not deleted
   $stmt = $db->query($query);
   $forms = $stmt->fetchAll(PDO::FETCH_ASSOC);
   print_r($forms);
   foreach($forms as $currForm){
      $instanceId = $currForm['instance_id'];
      $query = "select * from _form_info where FORM_ID = '{$instanceId}'";
      $stmt = $db->query($query);
      $form_info = $stmt->fetchAll(PDO::FETCH_ASSOC);
      foreach($form_info as $currInstance){
         $formURI = $currInstance['_URI'];
         $query = "update _form_info_fileset set IS_DOWNLOAD_ALLOWED = 0 where _PARENT_AURI = '{$formURI}'";
         echo $query;
         $stmt = $db->query($query);
         $stmt->execute();
      }
      $query = "update azizi_miscdb.odk_forms set is_active = 0 where id = '{$currForm['id']}'";
      echo $query;
      $stmt = $db->query($query);
      $stmt->execute();
   }
}
catch(PDOException $ex){
	echo $ex;
}
?>
