<?php require_once 'modules/mod_startup.php'; ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
   <head>
      <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
      <title>Azizi Biorepository</title>
      <link rel='stylesheet' type='text/css' href='<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>bower/bootstrap/docs/assets/css/bootstrap.css'/>
      <link rel='stylesheet' type='text/css' href='css/repository.css'>
      <script type='text/javascript' src='<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>bower/jquery/jquery.min.js'></script>
      <script type='text/javascript' src='<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>bower/jquery-form/jquery.form.js'></script>
      <script type='text/javascript' src='<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>bower/jquery-json/dist/jquery.json.min.js'></script>
      <script type='text/javascript' src='<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>common_v0.3.js'></script>
      <script type='text/javascript' src='<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>notification.js'></script>
      <script type='text/javascript' src='<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>bower/jsencrypt/bin/jsencrypt.min.js'></script>
      <script type='text/javascript' src='<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>bower/bootstrap/docs/assets/js/bootstrap.min.js' /></script>
      <script type='text/javascript' src='js/repository.js'></script>
   </head>
   <body>
      <div id='repository'>
         <div id='avid_header'>&nbsp;</div>
         <?php $Repository->TrafficController(); ?>
         <div id='avid_footer'>Azizi Biorepository</div>
      </div>
     <div id='credits'>
        Designed and Developed By: <a href="mailto:a.kihara@cgiar.org" target="_top">Kihara Absolomon</a>, <a href="mailto:j.rogena@cgiar.org" target="_blank">Rogena Jason</a>
     </div>
   </body>
</html>