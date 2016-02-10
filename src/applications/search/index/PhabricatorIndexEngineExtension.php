<?php

abstract class PhabricatorIndexEngineExtension extends Phobject {

  private $parameters;
  private $forceFullReindex;

  public function setParameters(array $parameters) {
    $this->parameters = $parameters;
    return $this;
  }

  public function getParameter($key, $default = null) {
    return idx($this->parameters, $key, $default);
  }

  final public function getExtensionKey() {
    return $this->getPhobjectClassConstant('EXTENSIONKEY');
  }

  final protected function getViewer() {
    return PhabricatorUser::getOmnipotentUser();
  }

  abstract public function getExtensionName();

  abstract public function shouldIndexObject($object);

  abstract public function indexObject(
    PhabricatorIndexEngine $engine,
    $object);

  public function getIndexVersion($object) {
    return null;
  }

  final public static function getAllExtensions() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getExtensionKey')
      ->execute();
  }

  final public function shouldForceFullReindex() {
    return $this->getParameter('force');
  }

}
