<?php

class ODKPuller {
   private $isDir; //directory where the itemsets are defined
   private $rootItems;
   private $database;

   public function __construct($dBase) {
      $this->database = $dBase;
      $request = $_GET['req'];
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
      else if($request === "get_csv"){//user wants the csv corresponding to a form
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
               array_merge($values, $queryRes);
               foreach($currKeys as $currKey) {
                  if(!in_array($currKey, $keys)) {
                     array_push($keys, $currKey);
                  }
               }
            }
         }
         $preloads[$currResult['name']] = $this->generateCSV($keys, $values);
      }
      return $preloads;
   }

   /**
    * This function generates a csv string based on the provided data
    * 
    * @param Array $keys   An array of all the expected column titles
    * @param Array $values A multidimensional array with first level being indexes
    *                      of rows and the second level being column headings
    *                      corresponding to those in $keys
    * 
    * @return A the CSV string corresponding to the provided data
    */
   private function generateCSV($keys, $values) {
      $csv = implode(",", $keys);
      foreach($values as $currValue) {
         $csv .= "\n";
         $currRow = "";
         $keyIndex = 1;
         foreach($keys as $currKey) {
            if(isset($currValue[$currKey])){
               $currRow .= $currValue[$currKey];
            }
            if($keyIndex < count($keyIndex)){//not yet in the last column in the row
               $currRow .=",";
            }
            $keyIndex++;
         }
         $csv .= $currRow;
      }
      return $csv;
   }
}
?>
