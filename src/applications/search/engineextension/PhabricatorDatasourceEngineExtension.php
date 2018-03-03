<?php

abstract class PhabricatorDatasourceEngineExtension extends Phobject {

  private $viewer;

  final public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  final public function getViewer() {
    return $this->viewer;
  }

  public function newQuickSearchDatasources() {
    return array();
  }

  public function newJumpURI($query) {
    return null;
  }

  public function newDatasourcesForCompositeDatasource(
    PhabricatorTypeaheadCompositeDatasource $datasource) {
    return array();
  }

  final public static function getAllExtensions() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->execute();
  }

  final public static function getAllQuickSearchDatasources() {
    $extensions = self::getAllExtensions();

    $datasources = array();
    foreach ($extensions as $extension) {
      $datasources[] = $extension->newQuickSearchDatasources();
    }

    return array_mergev($datasources);
  }
}
