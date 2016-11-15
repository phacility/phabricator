<?php

abstract class PhabricatorGuidanceEngineExtension
  extends Phobject {

  final public function getExtensionKey() {
    return $this->getPhobjectClassConstant('GUIDANCEKEY', 64);
  }

  abstract public function canGenerateGuidance(
    PhabricatorGuidanceContext $context);

  abstract public function generateGuidance(
    PhabricatorGuidanceContext $context);

  public function didGenerateGuidance(
    PhabricatorGuidanceContext $context,
    array $guidance) {
    return $guidance;
  }

  final protected function newGuidance($key) {
    return id(new PhabricatorGuidanceMessage())
      ->setKey($key);
  }

  final protected function newWarning($key) {
    return $this->newGuidance($key)
      ->setSeverity(PhabricatorGuidanceMessage::SEVERITY_WARNING);
  }

  final public static function getAllExtensions() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getExtensionKey')
      ->execute();
  }

}
