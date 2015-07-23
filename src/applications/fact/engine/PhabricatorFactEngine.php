<?php

abstract class PhabricatorFactEngine extends Phobject {

  final public static function loadAllEngines() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->execute();
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
