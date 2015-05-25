<?php
class ODKDeletor {
   public function __construct() {
      require_once 'repository_config';
      include_once Config::$config['httpd_root']."common/dbmodules/mod_objectbased_dbase_v1.1.php";
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
         echo "entering the danger zone \n";
         curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (X11; Linux x86_64; rv:26.0) Gecko/20100101 Firefox/26.0");
         curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
         curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
         curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, TRUE); 
         curl_setopt($ch, CURLOPT_COOKIEFILE, $this->authCookies);
         curl_setopt($ch, CURLOPT_POST, TRUE);
         //insert the GWT RPC payload into the request. GWT Whaaat? Refer to -> http://blog.gdssecurity.com/labs/2009/10/8/gwt-rpc-in-a-nutshell.html
         curl_setopt($ch, CURLOPT_POSTFIELDS, "7|0|6|http://azizi.ilri.cgiar.org/aggregate/aggregateui/|1BAF2E8ED0CEB731FEA73A25EDD25330|org.opendatakit.aggregate.client.form.FormAdminService|deleteForm|java.lang.String/2004016611|".$instanceId."|1|2|3|4|1|5|6|");
         curl_setopt($ch, CURLOPT_HTTPHEADER, array(
             "Connection: Keep-Alive",
             "Keep-Alive: 300",
             "Content-Type: text/x-gwt-rpc; charset=UTF-8",
             "X-GWT-Module-Base: http://azizi.ilri.cgiar.org/aggregate/aggregateui/",
             "X-GWT-Permutation: 131B412388B99E0A845272E4C5B3CDC8",
             "X-opendatakit-gwt: yes",
             "Accept: */*",
             "Origin: http://azizi.ilri.cgiar.org",
             "Accept-Encoding: gzip, deflate",
             "Accept-Language: en-US,en;q=0.8,en-GB;q=0.6,sw;q=0.4"
         ));
         curl_setopt($ch, CURLINFO_HEADER_OUT, TRUE);
         curl_setopt($ch, CURLOPT_REFERER, "http://azizi.ilri.cgiar.org/aggregate/Aggregate.html");
         
         $curlResult = curl_exec($ch);
         $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
         curl_close($ch); 
         
         //the server should return a status code 200 if it was able to process request.
         echo "HTTP STATUS from deleteForm = ".$http_status." \n";
         $query = "update azizi_miscdb.odk_deleted_forms set status = 'deleted' where form = $formId";
         $Dbase->ExecuteQuery($query);
      }
   }
   
   private function authUser(){
      if(file_exists($this->authCookies) === FALSE){
         echo "about to auth \n";
         $authURL = "http://azizi.ilri.cgiar.org/aggregate/local_login.html";
         touch($this->authCookies);
         chmod($this->authCookies, 0777);
         $authCh = curl_init($authURL);

         curl_setopt($authCh, CURLOPT_USERAGENT, "Mozilla/5.0 (X11; Linux x86_64; rv:26.0) Gecko/20100101 Firefox/26.0");
         curl_setopt($authCh, CURLOPT_RETURNTRANSFER, TRUE);
         curl_setopt($authCh, CURLOPT_FOLLOWLOCATION, TRUE);
         curl_setopt($authCh, CURLOPT_CONNECTTIMEOUT, TRUE);
         curl_setopt($authCh, CURLOPT_COOKIEJAR, $this->authCookies);
         curl_setopt($authCh, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
         curl_setopt($authCh, CURLOPT_USERPWD, "pruner".":"."eu4SedbKuH2wLw6HSAEs");

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

$odkDeletor = new ODKDeletor();//initiate the deleting process
?>
