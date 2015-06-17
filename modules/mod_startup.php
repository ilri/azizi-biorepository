<?php
/**
 * This is the worker who is never recognised. Includes all the necessary files. Initializes a session if need be. Processes the main GET or POST
 * elements and includes necessary files. Calls the necessary functions/methods
 *
 * @author Kihara Absolomon <a.kihara@cgiar.org>
 * @since v0.2
 */

define('OPTIONS_COMMON_FOLDER_PATH', '../bower/');

require_once OPTIONS_COMMON_FOLDER_PATH . 'azizi-shared-libs/mod_general/mod_general_v0.7.php';
require_once 'repository_config';
require_once OPTIONS_COMMON_FOLDER_PATH . 'azizi-shared-libs/dbmodules/mod_objectbased_dbase_v1.1.php';
require_once OPTIONS_COMMON_FOLDER_PATH . 'azizi-shared-libs/mod_messages/mod_messages_v0.1.php';
require_once OPTIONS_COMMON_FOLDER_PATH . 'azizi-shared-libs/authmodules/mod_security_v0.1.php';


//setting the date settings
date_default_timezone_set ('Africa/Nairobi');

//get what the user wants
$paging = (isset($_GET['page']) && $_GET['page']!='') ? $_GET['page'] : '';
$sub_module = (isset($_GET['do']) && $_GET['do']!='') ? $_GET['do'] : '';
$action = (isset($_POST['action']) && $_POST['action']!='') ? $_POST['action'] : '';
$user = isset($_SESSION['user']) ? $_SESSION['user'] : '';
if($action == '' && (isset($_GET['action']) && $_GET['action']!='')) $action = $_GET['action'];

/**
 * @var string    What the user wants
 */
define('OPTIONS_HOME_PAGE', $_SERVER['PHP_SELF']);
define('OPTIONS_REQUESTED_MODULE', $paging);
define('OPTIONS_CURRENT_USER', $user);
define('OPTIONS_REQUESTED_SUB_MODULE', $sub_module);
define('OPTIONS_REQUESTED_ACTION', $action);

//check if we want to download
if(in_array($paging, Config::$downloadableModules) && in_array($sub_module, Config::$downloadableSubModules)){
   Config::$downloadFile = true;
}

$t = pathinfo($_SERVER['SCRIPT_FILENAME']);
$requestType = ($t['basename'] == 'mod_ajax.php') ? 'ajax' : 'normal';
define('OPTIONS_REQUEST_TYPE', $requestType);

require_once 'mod_repository_general.php';
$Repository = new Repository();

session_save_path(Config::$config['dbase']);
session_name('repository');
//$Repository->Dbase->SessionStart();
$Repository->sessionStart();

$Repository->Dbase->CreateLogEntry("Post User request: \n".print_r($_POST, true), 'debug');
$Repository->Dbase->CreateLogEntry("Files: \n".print_r($_FILES, true), 'debug');

if(Config::$downloadFile) $Repository->TrafficController();

?>