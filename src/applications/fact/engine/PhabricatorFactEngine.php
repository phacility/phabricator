<?php

abstract class PhabricatorFactEngine extends Phobject {

  final public static function loadAllEngines() {
    $classes = id(new PhutilSymbolLoader())
      ->setAncestorClass(__CLASS__)
      ->setConcreteOnly(true)
      ->selectAndLoadSymbols();

    $objects = array();
    foreach ($classes as $class) {
      $objects[] = newv($class['name'], array());
    }

    return $objects;
  }

  public function getFactSpecs(array $fact_types) {
    return array();
  }

  public function shouldComputeRawFactsForObject(PhabricatorLiskDAO $object) {
    return false;
  }

  public function computeRawFactsForObject(PhabricatorLiskDAO $object) {
    return array();
  }

  public function shouldComputeAggregateFacts() {
    return false;
  }

  public function computeAggregateFacts() {
    return array();
  }

}
