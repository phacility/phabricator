<?php

abstract class PhutilRemarkupHyperlinkEngineExtension
  extends Phobject {

  private $engine;

  final public function getHyperlinkEngineKey() {
    return $this->getPhobjectClassConstant('LINKENGINEKEY', 32);
  }

  final public static function getAllLinkEngines() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getHyperlinkEngineKey')
      ->execute();
  }

  final public function setEngine(PhutilRemarkupEngine $engine) {
    $this->engine = $engine;
    return $this;
  }

  final public function getEngine() {
    return $this->engine;
  }

  abstract public function processHyperlinks(array $hyperlinks);

}
