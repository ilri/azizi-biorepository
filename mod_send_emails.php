<?php

define ('DOCUMENT_ROOT', __DIR__);
define('OPTIONS_COMMON_FOLDER_PATH', DOCUMENT_ROOT. '/../bower/');
include_once OPTIONS_COMMON_FOLDER_PATH . 'azizi-shared-libs/mod_general/mod_general_v0.7.php';
include_once 'repository_config';
include_once OPTIONS_COMMON_FOLDER_PATH . 'azizi-shared-libs/dbmodules/mod_objectbased_dbase_v1.1.php';

/**
 * A module for sending routine emails
 *
 * @author Absolomon Kihara   a.kihara@cgiar.org
 */
class FarmEmailDigest{

   private $Dbase;
   private $settings;

   /**
    *
    * @param type $Dbase   Create a new class for farm animals
    */
   public function __construct(){
      // before creating the db connection, lets import the db settings
      $this->settings = parse_ini_file(Config::$configFile, true);

      $this->Dbase = new DBase('mysql');
      $this->Dbase->InitializeConnection($this->settings['azizi_ro']);
      if(is_null($this->Dbase->dbcon)) {
         ob_start();
         $this->LoginPage(OPTIONS_MSSG_DB_CON_ERROR);
         $this->errorPage = ob_get_contents();
         ob_end_clean();
         return;
      }
      $this->Dbase->InitializeLogs();
   }

   /**
    * This function determines what user wants to do
    */
   public function trafficController(){
      if(OPTIONS_REQUESTED_MODULE == 'farm_animals'){
         if(OPTIONS_REQUESTED_SUB_MODULE == 'send_weekly_digest') $this->emailEventsDigest ('weekly');
         else if(OPTIONS_REQUESTED_SUB_MODULE == 'send_daily_digest') $this->emailEventsDigest ('daily');
      }
   }

   /**
    * Compile and send an email to the users of the day's events
    *
    * @todo Define the farm manager email as a setting
    */
   private function emailEventsDigest($email_type){
      // load the settings from the main file
      $settings = $this->loadSettings();
      $this->Dbase->CreateLogEntry('sending '.OPTIONS_REQUESTED_SUB_MODULE.' digest', 'info');

      // get a list of all the day's events and send them to the concerned user
      if($email_type == 'weekly'){
         $eventsQuery = 'select a.event_type_id, a.sub_event_type_id, if(d.id is null, c.event_name, concat(c.event_name, " >> ", d.sub_event_name)) as event_name, b.current_owner, '
               . 'a.event_date, record_date as time_recorded, recorded_by, performed_by, b.animal_id, b.sex, event_value, a.comments  '
             . 'from '. Config::$farm_db .'.farm_animal_events as a inner join '. Config::$farm_db .'.farm_animals as b on a.animal_id=b.id '
             . 'inner join '. Config::$farm_db .'.farm_events as c on a.event_type_id=c.id '
             . 'left join '. Config::$farm_db .'.farm_sub_events as d on a.sub_event_type_id=d.id where event_date < :end_date and event_date > :start_date';
         $events = $this->Dbase->ExecuteQuery($eventsQuery, array('start_date' => date('Y-m-d', strtotime('-7 days')), 'end_date' => date('Y-m-d', strtotime('+1 days'))));
         $intro = 'Below is a digest of activities carried out on animals assigned to you and <b>RECORDED IN THE LAST 7 DAYS</b><b></b>. If you have any questions, kindly contact the farm manager through '. Config::$farmManagerEmail .'<br /><br />';
      }
      elseif($email_type == 'daily'){
         $eventsQuery = 'select a.event_type_id, a.sub_event_type_id, if(d.id is null, c.event_name, concat(c.event_name, " >> ", d.sub_event_name)) as event_name, b.current_owner, '
               . 'a.event_date, record_date as time_recorded, recorded_by, performed_by, b.animal_id, b.sex, event_value, a.comments  '
             . 'from '. Config::$farm_db .'.farm_animal_events as a inner join '. Config::$farm_db .'.farm_animals as b on a.animal_id=b.id '
             . 'inner join '. Config::$farm_db .'.farm_events as c on a.event_type_id=c.id '
             . 'left join '. Config::$farm_db .'.farm_sub_events as d on a.sub_event_type_id=d.id where event_date = :event_date and c.event_category = :cat';
         $events = $this->Dbase->ExecuteQuery($eventsQuery, array('event_date' => date('Y-m-d'), 'cat' => 'heightened'));
         $intro = "Below are the activities carried out on your animals and were recorded <b>". date('dS M Y') .'</b>. If you have any questions, kindly contact the farm manager through '. Config::$farmManagerEmail .'<br /><br />';
      }

      if($events == 1) { die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastError))); }
      $owners = $this->getAllOwners(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE);
      if(is_string($owners)) { die(json_encode(array('error' => true, 'mssg' => $this->Dbase->lastError))); }

      $uniqueOwners = array();
      foreach($events as $id => $ev){
         $events[$id]['performed_by'] = $owners[$ev['performed_by']]['name'];
         $events[$id]['recorded_by'] = $owners[$ev['recorded_by']]['name'];
         $events[$id]['owner'] = $owners[$ev['current_owner']]['name'];
         $uniqueOwners[] = $ev['current_owner'];
      }
      $uniqueOwners = array_unique($uniqueOwners);

      // loop thru all the owners and create a report for them
      foreach ($uniqueOwners as $owner){
         $animalsByOwner = array();
         // loop through all the affected animals and create the report
         $style = '<style type="text/css">';
         $style .= 'table{font-family: verdana,arial,sans-serif; font-size:10px; color:#333333; border-width: 1px; border-color: #666666; border-collapse: collapse; }';
         $style .= 'th { border-width: 1px; padding: 8px; border-style: solid; border-color: #666666; background-color: #dedede; }';
         $style .= 'td { border-width: 1px; padding: 8px; border-style: solid; border-color: #666666; background-color: #ffffff; }';
         $style .= '</style>';
         $content = "Dear {$owners[$owner]['name']}, <br /><br />$intro";
         $content .= "$style";
         $content .= '<table><tr><th>Animal ID</th><th>Event Date</th><th>Event</th><th>Event Value</th><th>Recorded By</th><th>Performed By</th><th>Time Recorded</th><th>Comments</th></tr>';
         foreach($events as $id => $event){
            if($event['current_owner'] == $owner){
               $animalsByOwner[$id] = $event;
               $content .= "<tr><td>{$event['animal_id']} ({$event['sex']})</td><td>{$event['event_date']}</td><td>{$event['event_name']}</td><td>{$event['event_value']}</td><td>{$event['recorded_by']}</td>"
               . "<td>{$event['performed_by']}</td><td>{$event['time_recorded']}</td><td>{$event['comments']}</td></tr>";
            }
         }
         $content .= "</table><br /><br />Regards<br />The Farm team";
         // email this user with the animal report

         $this->Dbase->CreateLogEntry("sending an email to {$owners[$owner]['name']} with the daily digest\n\n$content", 'info');
//         shell_exec("echo '$content' | {$settings['mutt_bin']} -e 'set content_type=text/html' -c 'azizibiorepository@cgiar.org' -c 's.kemp@cgiar.org' -F {$settings['mutt_config']} -s 'Farm animals activities digest' -- ". Config::$farmManagerEmail);
         shell_exec("echo '$content' | {$settings['mutt_bin']} -e 'set content_type=text/html' -c '".Config::$farmManagerEmail."' -c '".Config::$farmRecordKeeperEmail."' -c '".Config::$farmManagerSupervisorEmail."' -c '".Config::$farmSystemDeveloperEmail."' -F {$settings['mutt_config']} -s 'Farm animals activities digest' -- {$owners[$owner]['email']}");
//         shell_exec("echo '$content' | {$settings['mutt_bin']} -e 'set content_type=text/html' -c '".Config::$farmSystemDeveloperEmail."' -F {$settings['mutt_config']} -s 'Farm animals activities digest' -- a.kihara@cgiar.org");
      }
      $this->Dbase->CreateLogEntry('The email digest have been sent successfully', 'info');
   }


   /*
    * This function loads settings from the main ini file
    */
   public function loadSettings() {
      if(file_exists(OPTIONS_MODULE_EMAIL_CONFIG_FILE)) {
         $emailSettings = parse_ini_file(OPTIONS_MODULE_EMAIL_CONFIG_FILE);
         return $emailSettings;
      }
      else return 'The file config/main.ini doesnt exist';
   }

   /**
    * Gets all the farm users as defined in the system
    *
    * @return  string|array   Returns a string with the error in case of an error, else it returns an array with the defined farm users
    */
   private function getAllOwners($fetchAs = PDO::FETCH_ASSOC){
      $allUsersQuery = 'SELECT a.id, concat(sname, " ", onames) as `name`, email '
            . 'FROM '. $this->settings['azizi_ro']['session_dbase'] .'.users as a inner join '. $this->settings['azizi_ro']['session_dbase'] .'.user_groups as b on a.id=b.user_id '
            . 'inner join '. $this->settings['azizi_ro']['session_dbase'] .'.groups as c on b.group_id=c.id '
            . 'where c.name in (:farm_module_admin, :farm_module_users)';
      $vals = array('farm_module_admin' => Config::$farm_module_admin, 'farm_module_users' => Config::$farm_module_users);

      $res = $this->Dbase->ExecuteQuery($allUsersQuery, $vals, $fetchAs);
      if($res == 1) return $this->Dbase->lastError;
      else return $res;
   }
}

//setting the date settings
date_default_timezone_set ('Africa/Nairobi');

/**
 * @var string    What the user wants
 */
define('OPTIONS_REQUESTED_MODULE', $argv[1]);
define('OPTIONS_REQUESTED_SUB_MODULE', $argv[2]);
define('OPTIONS_MODULE_EMAIL_CONFIG_FILE', '/www/repository/config/main.ini');

$EmailDigest = new FarmEmailDigest();
$EmailDigest->trafficController();
?>