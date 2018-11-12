<?php

final class PhabricatorRepositorySyncEventPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'SYNE';

  public function getTypeName() {
    return pht('Sync Event');
  }

  public function newObject() {
    return new PhabricatorRepositorySyncEvent();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorRepositorySyncEventQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $event = $objects[$phid];

      $handle->setName(pht('Sync Event %d', $event->getID()));
    }
  }

}
