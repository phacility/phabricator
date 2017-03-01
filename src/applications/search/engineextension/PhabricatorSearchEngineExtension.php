<?php

abstract class PhabricatorSearchEngineExtension extends Phobject {

  private $viewer;
  private $searchEngine;

  final public function getExtensionKey() {
    return $this->getPhobjectClassConstant('EXTENSIONKEY');
  }

  final public function setViewer($viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  final public function getViewer() {
    return $this->viewer;
  }

  final public function setSearchEngine(
    PhabricatorApplicationSearchEngine $engine) {
    $this->searchEngine = $engine;
    return $this;
  }

  final public function getSearchEngine() {
    return $this->searchEngine;
  }

  abstract public function isExtensionEnabled();
  abstract public function getExtensionName();
  abstract public function supportsObject($object);

  public function getExtensionOrder() {
    return 7000;
  }

  public function getSearchFields($object) {
    return array();
  }

  public function getSearchAttachments($object) {
    return array();
  }

  public function applyConstraintsToQuery(
    $object,
    $query,
    PhabricatorSavedQuery $saved,
    array $map) {
    return;
  }

  public function getFieldSpecificationsForConduit($object) {
    return array();
  }

  public function loadExtensionConduitData(array $objects) {
    return null;
  }

  public function getFieldValuesForConduit($object, $data) {
    return array();
  }

  final public static function getAllExtensions() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getExtensionKey')
      ->setSortMethod('getExtensionOrder')
      ->execute();
  }

  final public static function getAllEnabledExtensions() {
    $extensions = self::getAllExtensions();

    foreach ($extensions as $key => $extension) {
      if (!$extension->isExtensionEnabled()) {
        unset($extensions[$key]);
      }
    }

    return $extensions;
  }

}
