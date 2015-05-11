<?php

class ODKPuller {
   private $database;

   public function __construct($dBase) {
      $this->database = $dBase;
   }
   
   public function trafficController() {
      $request = OPTIONS_REQUESTED_SUB_MODULE;
      if($request === "get_list"){//user wants a list of all forms that have external data
         $forms = array();
         $query = "select b.form_name from odk_preloads as a"
                 . " inner join odk_forms as b on a.form_id = b.id"
                 . " where b.is_active = 1";
         $result = $this->database->ExecuteQuery($query);
         foreach($result as $currForm){
            array_push($forms, $currForm['form_name']);
         }
         
         $returnObject = array("forms" => $forms);
         echo json_encode($returnObject);
      }
      else if($request === "get_data"){//user wants the csv corresponding to a form
         $returnObject = array("files" => array());
         $requestedForm = $_GET['form'];
         $query = "select id from odk_forms where form_name = :form_name order by id desc limit 1";
         $result = $this->database->ExecuteQuery($query, array("form_name" => $requestedForm));
         if(is_array($result) && count($result) == 1) {
            $preloads = $this->getPreloadData($result[0]['id']);
            $returnObject['files'] = $preloads;
         }
         echo json_encode($returnObject);
      }
   }
   
   /**
    * This function gets preload data corresponding to the provided form id
    * 
    * @param type $formId
    */
   private function getPreloadData($formId) {
      $preloads = array();//an associative array with all preload file names as child elements
      $query = "select id, name from odk_preloads where form_id = :formId";
      $result = $this->database->ExecuteQuery($query, array("formId" => $formId));
      if($_GET['complete'] == 0 || $_GET['complete'] == '0'){//just get the names of the preload files
         foreach ($result as $currResult) {
            $preloads[] = $currResult['name'];
         }
      }
      else {
         foreach($result as $currResult) {
            $preloads[$currResult['name']] = "";
            $keys = array();
            $values = array();
            $query = "select query from preload_queries where preload_id = :id";
            $queries = $this->database->ExecuteQuery($query, array("id" => $currResult['id']));
            foreach($queries as $currQuery) {
               $actualQuery = $currQuery['query'];
               $queryRes = $this->database->ExecuteQuery($actualQuery);
               if(is_array($queryRes) && count($queryRes) > 0) {
                  $currKeys = array_keys($queryRes[0]);
                  $values = array_merge($values, $queryRes);
                  foreach($currKeys as $currKey) {
                     if(!in_array($currKey, $keys)) {
                        array_push($keys, $currKey);
                     }
                  }
               }
            }
            $preloads[$currResult['name']] = $this->formatValues($keys, $values);
         }
      }
      return $preloads;
   }

   /**
    * This function formats the provided values based on the keys found with the
    * assumption that most rows in the $values array will not have all the keys 
    * defined in $keys
    * 
    * @param Array $keys   An array of all the expected keys
    * @param Array $values A multidimensional array with first level being indexes
    *                      of rows and the second level being keys corresponding
    *                      to those in $keys
    * 
    * @return A the CSV string corresponding to the provided data
    */
   private function formatValues($keys, $values) {
      $formatted = array();
      for($index = 0; $index < count($values); $index++) {
         $formatted[$index] = array();
         foreach($keys as $currKey) {
            if(isset($values[$index][$currKey])){
               $formatted[$index][$currKey] = $values[$index][$currKey];
            }
            else $formatted[$index][$currKey] = null;
         }
      }
      return $formatted;
   }
}
?>
