<?php

abstract class PhabricatorFactEngine extends Phobject {

  private $factMap;

  final public static function loadAllEngines() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->execute();
  }

  abstract public function newFacts();

  abstract public function supportsDatapointsForObject(
    PhabricatorLiskDAO $object);

  abstract public function newDatapointsForObject(PhabricatorLiskDAO $object);

  final protected function getFact($key) {
    if ($this->factMap === null) {
      $facts = $this->newFacts();
      $facts = mpull($facts, null, 'getKey');
      $this->factMap = $facts;
    }

    if (!isset($this->factMap[$key])) {
      throw new Exception(
        pht(
          'Unknown fact ("%s") for engine "%s".',
          $key,
          get_class($this)));
    }

    return $this->factMap[$key];
  }

  final protected function getViewer() {
    return PhabricatorUser::getOmnipotentUser();
  }

}
