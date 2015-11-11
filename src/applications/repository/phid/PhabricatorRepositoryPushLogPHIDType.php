<?php

final class PhabricatorRepositoryPushLogPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'PSHL';

  public function getTypeName() {
    return pht('Push Log');
  }

  public function newObject() {
    return new PhabricatorRepositoryPushLog();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorRepositoryPushLogQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $log = $objects[$phid];

      $handle->setName(pht('Push Log %d', $log->getID()));
    }
  }

}
