<?php

class Item {
   
   private $jsonObject;
   private $children;
   private $type;
   private $value;
   private $database;
   
   public function __construct($jsonObject, $queryResults, $database){
      $this->jsonObject = $jsonObject;
      
      $this->type = $jsonObject['name'];
      $this->value = $queryResults[0][$this->type];
      $this->database = $database;
      
      //get all direct children
      if(isset($jsonObject['children'])){
         $this->children = $this->getChildren($jsonObject['children'], $queryResults);
      }
      else{
         $this->children = null;
      }
   }
   
   private function getChildren($jsonArray, $queryResults){
      $children = array();
      //if(is_array($jsonArray)){
         foreach($jsonArray as $currChildType){
            $children = array_merge($children, $this->getChildrenFromType($currChildType, $queryResults));
         }
      //}
      
      return $children;
   }
   
   private function getChildrenFromType($jsonObject, $queryResult){
      $subResults = array();
      
      //divide the rows in queryResult based on the value of $jsonObject['name']
      foreach($queryResult as $currRow){
         if(isset($currRow[$jsonObject['name']])){
            if(!isset($subResults[$currRow[$jsonObject['name']]])) $subResults[$currRow[$jsonObject['name']]] = array();
            
            array_push($subResults[$currRow[$jsonObject['name']]], $currRow);
         }
      }
     
      $children = array();
      foreach($subResults AS $currChildGroup){
         array_push($children, new Item($jsonObject, $currChildGroup));
      }
      
      return $children;
   }
   
   public function getCSVRows(){
      $csvRow = array(array("list_name" => $this->type, "label" => $this->value, "name" => $this->generateName($this->value)));
      if($this->children == null){
         return $csvRow;
      }
      else{
         $childRows = array();
         
         foreach($this->children as $currChild){
            $tmpRows = array_merge($childRows, $currChild->getCSVRows());
            $childRows = $tmpRows;
         }
         
         for($index = 0; $index < count($childRows); $index++){
            $childRows[$index][$this->type] = $this->generateName($this->value);
         }
         
         return array_merge($csvRow, $childRows);
      }
   }
   
   private function generateName($label){
      $name = strtolower($label);
      return preg_replace("/[^a-z0-9]/", "_", $name);
   }
}
