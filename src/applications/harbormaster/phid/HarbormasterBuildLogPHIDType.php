<?php

final class HarbormasterBuildLogPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'HMCL';

  public function getTypeName() {
    return pht('Build Log');
  }

  public function newObject() {
    return new HarbormasterBuildLog();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorHarbormasterApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new HarbormasterBuildLogQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $build_log = $objects[$phid];

      $handle
        ->setName(pht('Build Log %d', $build_log->getID()))
        ->setURI($build_log->getURI());
    }
  }

}
