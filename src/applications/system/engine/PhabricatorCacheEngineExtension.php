<?php

abstract class PhabricatorCacheEngineExtension extends Phobject {

  final public function getExtensionKey() {
    return $this->getPhobjectClassConstant('EXTENSIONKEY');
  }

  abstract public function getExtensionName();

  public function discoverLinkedObjects(
    PhabricatorCacheEngine $engine,
    array $objects) {
    return array();
  }

  public function deleteCaches(
    PhabricatorCacheEngine $engine,
    array $objects) {
    return null;
  }

  final public static function getAllExtensions() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getExtensionKey')
      ->execute();
  }

  final public function selectObjects(array $objects, $class_name) {
    $results = array();

    foreach ($objects as $phid => $object) {
      if ($object instanceof $class_name) {
        $results[$phid] = $object;
      }
    }

    return $results;
  }

}
