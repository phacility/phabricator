<?php

abstract class PhabricatorFact extends Phobject {

  private $key;

  public static function getAllFacts() {
    $engines = PhabricatorFactEngine::loadAllEngines();

    $map = array();
    foreach ($engines as $engine) {
      $facts = $engine->newFacts();
      $facts = mpull($facts, null, 'getKey');
      $map += $facts;
    }

    return $map;
  }

  final public function setKey($key) {
    $this->key = $key;
    return $this;
  }

  final public function getKey() {
    return $this->key;
  }

  final public function getName() {
    return pht('Fact "%s"', $this->getKey());
  }

  final public function newDatapoint() {
    return $this->newTemplateDatapoint()
      ->setKey($this->getKey());
  }

  abstract protected function newTemplateDatapoint();

}
