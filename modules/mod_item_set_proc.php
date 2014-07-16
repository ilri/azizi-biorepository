<?php

class ItemSetProcessor {

   private $ROOT = "../../";
   private $isDir; //directory where the itemsets are defined
   private $rootItems;
   private $database;

   public function __construct($isDir) {
      $this->isDir = $isDir;
      
      $request = $_GET['req'];
      if($request === "get_list"){//user wants a list of all forms that have external data
         $jsonFiles = $this->getAllJSONFiles($this->isDir);
         
         $forms = array();
         foreach($jsonFiles as $currFile){
            $currJsonObject = $this->initJsonFile($isDir . '/' . $currFile);
            
            if(isset($currJsonObject['forms'])){
               $currForms = $currJsonObject['forms'];
               foreach($currForms as $currTForm){
                  if(array_search($currTForm['name'], $forms) === FALSE){
                     array_push($forms, $currTForm['name']);
                  }
               }
            }
         }
         
         $returnObject = array("forms" => $forms);
         echo json_encode($returnObject);
      }
      else if($request === "get_csv"){//user wants the csv corresponding to a form
         
         $requestedForm = $_GET['form'];
         
         include_once 'push_config';
         include_once '../../common/dbmodules/mod_objectbased_dbase_v1.1.php';
         include_once '../../common/mod_general_v0.6.php';
         include_once './mod_item.php';

         $this->database = new DBase('mysql');

         //$config['dbase']};host={$config['dbloc']}", $config['user'], $config['pass']
         //$dbaseSettings = array("dbase" => "azizi_miscdb", "dbloc" => "boran.ilri.cgiar.org", "user" => "azizi_repository", "pass" => "JfQf967u94qK");
         $this->database->InitializeConnection();

         $this->database->InitializeLogs();
         
         $this->database->CreateLogEntry("requested form = ".$requestedForm, "fatal");

         //get all the json files in $isDir
         $jsonFiles = $this->getAllJSONFiles($this->isDir);

         //try initializing arrays for all the json files
         $index = 0;
         $jsonObjects = array();
         foreach ($jsonFiles as $currJsonFile) {
            $tmpJson = $this->initJsonFile($isDir . '/' . $currJsonFile);
            $this->database->CreateLogEntry(" Json object = ".print_r($tmpJson, true), "fatal");
            if($this->jsonContainsForm($tmpJson, $requestedForm)){
               $jsonObjects[$index] = $tmpJson;
               $index++;
            }
         }

         $this->rootItems = array();
         foreach ($jsonObjects as $currObject) {
            $queryResult = $this->getQueryResult($currObject);

            $rootItemType = $currObject['name'];
            //group the query results based on the root item type
            $itemTypes = array();
            foreach ($queryResult as $currRow) {
               if (!isset($itemTypes[$currRow[$rootItemType]]))
                  $itemTypes[$currRow[$rootItemType]] = array();
               array_push($itemTypes[$currRow[$rootItemType]], $currRow);
            }

            foreach ($itemTypes as $currItemType) {
               $currRootItem = new Item($currObject, $currItemType, $this->database);
               array_push($this->rootItems, $currRootItem);
            }
         }
         //print_r($this->rootItems);//TODO:remove
         $this->database->CreateLogEntry(print_r($this->rootItems, true), "fatal");
         $csv = $this->generateCSV($this->rootItems);
         $returnObject = array("csv_length" => strlen($csv), "csv" => $csv);
         echo json_encode($returnObject);
      }
   }

   private function getAllJSONFiles($dir) {
      $fileNames = array();
      if ($handler = opendir($dir)) {
         while (false !== ($file = readdir($handler))) {
            if ($file !== "." && $file !== ".." && strtolower(substr($file, strrpos($file, '.') + 1)) == "json") {
               array_push($fileNames, $file);
            }
         }
      }

      return $fileNames;
   }

   private function initJsonFile($fileName) {
      $jsonString = file_get_contents($fileName);
      //$this->database->CreateLogEntry(" Json string = ".$jsonString, "fatal");
      $jsonArray = json_decode($jsonString, true);

      return $jsonArray;
   }

   private function getQuery($jsonObject) {
      $query = "SELECT " . $this->getSelect($jsonObject) . " FROM " . $this->getFrom($jsonObject);
      if (strlen($this->getWhere($jsonObject)) > 0)
         $query .= " WHERE " . $this->getWhere($jsonObject);
      
      return $query;
   }

   private function getSelect($jsonObject) {
      $select = '`' . $jsonObject['db_origin'] . '`.`' . $jsonObject['table_origin'] . '`.`' . $jsonObject['column_origin'] . "` AS " . $jsonObject['name'];
      if (isset($jsonObject['children'])) {
         $children = $jsonObject['children'];
         foreach ($children as $currChild) {
            $currChildSelect = $this->getSelect($currChild);
            if (strlen($currChildSelect) > 0) {
               $select .= ", " . $currChildSelect;
            }
         }
      }

      return $select;
   }

   private function getFrom($jsonObject, $parentTable = "") {
      $from = "";
      $tableOrigin = '`' . $jsonObject['db_origin'] . '`.`' . $jsonObject['table_origin'] . '`';
      if($tableOrigin != $parentTable){
         $from = $tableOrigin;
         if (isset($jsonObject['parent_assoc']) && strlen($jsonObject['parent_assoc']) > 0) {
            $from .= " ON " . $jsonObject['parent_assoc'];
         }
      }
      
      if (isset($jsonObject['children'])) {
         $children = $jsonObject['children'];
         foreach ($children as $currChild) {
            $childFrom = $this->getFrom($currChild, $tableOrigin);
            if (strlen($childFrom) > 0) {
               $from .= " LEFT JOIN " . $childFrom;
            }
         }
      }

      return $from;
   }

   private function getWhere($jsonObject) {
      $where = "";
      if (isset($jsonObject['filter']) && strlen($jsonObject['filter']) > 0) {
         $where = $jsonObject['filter'];
      }

      if (isset($jsonObject['children'])) {
         $children = $jsonObject['children'];
         foreach ($children as $currChild) {
            $childWhere = $this->getWhere($currChild);
            if (strlen($childWhere) > 0) {
               $where = " AND " . $childWhere;
            }
         }
      }
      return $where;
   }

   private function getQueryResult($jsonObject) {
      $query = $this->getQuery($jsonObject);

      return $this->database->ExecuteQuery($query);
   }

   private function generateCSV($items) {
      $unmergedCSVS = array();
      foreach ($items as $currItem) {
         $unmergedCSVS = array_merge($unmergedCSVS, $currItem->getCSVRows());
      }
      $csvHeadings = array();
      foreach ($unmergedCSVS as $currItem) {
         $headings = array_keys($currItem);
         foreach ($headings as $currHeading) {
            if (array_search($currHeading, $csvHeadings) === FALSE) {
               array_push($csvHeadings, $currHeading);
            }
         }
      }

      for ($index = 0; $index < count($unmergedCSVS); $index++) {
         foreach ($csvHeadings as $currHeading) {
            if (!isset($unmergedCSVS[$index][$currHeading])) {
               $unmergedCSVS[$index][$currHeading] = "";
            }
         }
      }

      $csv = "";
      for ($index = 0; $index < count($csvHeadings); $index++) {
         if (strlen($csv) == 0)
            $csv .= $csvHeadings[$index];
         else
            $csv .= "," . $csvHeadings[$index];
      }
      $csv .= "\n";

      foreach ($unmergedCSVS as $currRow) {
         for ($index = 0; $index < count($csvHeadings); $index++) {
            if ($index == 0)
               $csv .= $currRow[$csvHeadings[$index]];
            else
               $csv .= "," . $currRow[$csvHeadings[$index]];
         }
         $csv .= "\n";
      }

      return $csv;
   }
   
   private function jsonContainsForm($jsonObject, $formName){
      if(isset($jsonObject['forms'])){
         $allForms = $jsonObject['forms'];
         $this->database->CreateLogEntry("All forms = ".print_r($allForms, true), "fatal");
         foreach($allForms as $currForm){
            if($currForm['name'] == $formName) return TRUE;
         }
      }
      return false;
   }

}

$obj = new ItemSetProcessor("../item_sets");
?>
