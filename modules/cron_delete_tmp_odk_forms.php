<?php
/**
 * This class is responsible for regularly deleting temporary ODK forms on ODK
 * Aggregate. Tested with Aggregate v1.3.4 Production.
 * 
 * Set the file to run as a cron job by running:
 * 
 *  crontab -e
 * 
 * Then add it to the list of cron jobs. Recommended to run as the root user
 */
class ODKPruner {
   public function __construct() {
      require_once 'repository_config';
      include_once Config::$config['httpd_root']."bower/azizi-shared-libs/dbmodules/mod_objectbased_dbase_v1.1.php";
      include_once Config::$config['httpd_root']."bower/azizi-shared-libs/mod_general/mod_general_v0.6.php";
      $Dbase = new DBase('mysql');
      $Dbase->InitializeConnection();
      $Dbase->InitializeLogs();
      
      $this->authCookies = "aggregate_auth";
      $this->authUser();//authenticate local prune user on Aggregate   
      $getXMLURL = Config::$config['odkDeleteURL'];//URL that handles the GWT Request for deleting Aggregate forms
      $query = "select a.instance_id, a.id from azizi_miscdb.odk_forms as a inner join azizi_miscdb.odk_deleted_forms as b on a.id = b.form where b.time_to_delete < now() and b.status = 'not_deleted'";//time to delete is in the past and status not deleted
      $forms = $Dbase->ExecuteQuery($query);
      print_r($forms);
      $this->authUser();
      foreach($forms as $currForm){
         $instanceId = $currForm['instance_id'];
         $formId = $currForm['id'];
         echo "About to delete $instanceId from the Aggregate server \n";
         $ch = curl_init($getXMLURL);
         curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (X11; Linux x86_64; rv:26.0) Gecko/20100101 Firefox/26.0");
         curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
         curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
         curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, TRUE); 
         curl_setopt($ch, CURLOPT_COOKIEFILE, $this->authCookies);
         curl_setopt($ch, CURLOPT_POST, TRUE);
         //insert the GWT RPC payload into the request. GWT Whaaat? Refer to -> http://blog.gdssecurity.com/labs/2009/10/8/gwt-rpc-in-a-nutshell.html
         curl_setopt($ch, CURLOPT_POSTFIELDS, "7|0|6|".Config::$config['odkUIURL']."|1BAF2E8ED0CEB731FEA73A25EDD25330|org.opendatakit.aggregate.client.form.FormAdminService|deleteForm|java.lang.String/2004016611|".$instanceId."|1|2|3|4|1|5|6|");
         curl_setopt($ch, CURLOPT_HTTPHEADER, array(
             "Connection: Keep-Alive",
             "Keep-Alive: 300",
             "Content-Type: text/x-gwt-rpc; charset=UTF-8",
             "X-GWT-Module-Base: ".Config::$config['odkUIURL'],
             "X-GWT-Permutation: 131B412388B99E0A845272E4C5B3CDC8",
             "X-opendatakit-gwt: yes",
             "Accept: */*",
             "Origin: ".Config::$config['repositoryURL'],
             "Accept-Encoding: gzip, deflate",
             "Accept-Language: en-US,en;q=0.8,en-GB;q=0.6,sw;q=0.4"
         ));
         curl_setopt($ch, CURLINFO_HEADER_OUT, TRUE);
         curl_setopt($ch, CURLOPT_REFERER, Config::$config['odkBaseURL']);
         
         $curlResult = curl_exec($ch);
         $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
         curl_close($ch); 
         
         //the server should return a status code 200 if it was able to process request.
         if($http_status == 200) {//form was successfully deleted
            echo "Updating database record for $instanceId \n";
            $query = "update azizi_miscdb.odk_deleted_forms set status = 'deleted' where form = $formId";
            $Dbase->ExecuteQuery($query);
            $query = "update azizi_miscdb.odk_forms set is_active = 0 where id = $formId";
            $Dbase->ExecuteQuery($query);
         }
         else echo "Aggregate server did not delete the form. HTTP status = '$http_status' \n";
      }
   }
   
   private function authUser(){
      if(file_exists($this->authCookies) === FALSE){
         echo "about to auth \n";
         $authURL = Config::$config['odkAuthURL'];
         touch($this->authCookies);
         chmod($this->authCookies, 0777);
         $authCh = curl_init($authURL);

         curl_setopt($authCh, CURLOPT_USERAGENT, "Mozilla/5.0 (X11; Linux x86_64; rv:26.0) Gecko/20100101 Firefox/26.0");
         curl_setopt($authCh, CURLOPT_RETURNTRANSFER, TRUE);
         curl_setopt($authCh, CURLOPT_FOLLOWLOCATION, TRUE);
         curl_setopt($authCh, CURLOPT_CONNECTTIMEOUT, TRUE);
         curl_setopt($authCh, CURLOPT_COOKIEJAR, $this->authCookies);
         curl_setopt($authCh, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
         curl_setopt($authCh, CURLOPT_USERPWD, Config::$config['odk_pruner_user'].":".Config::$config['odk_pruner_pass']);

         $result = curl_exec($authCh);
         $http_status = curl_getinfo($authCh, CURLINFO_HTTP_CODE);
         curl_close($authCh);
         if($http_status == 401){//user not authenticated
            echo "The ODK user is not authorised to upload ODK forms \n";
         }
         else {
            echo " \n **".$http_status."** \n";
         }
      }
   }
}

$odkPruner = new ODKPruner();//initiate the deleting process
?>
