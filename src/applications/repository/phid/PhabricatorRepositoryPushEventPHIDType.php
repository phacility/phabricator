<?php

final class PhabricatorRepositoryPushEventPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'PSHE';

  public function getTypeName() {
    return pht('Push Event');
  }

  public function newObject() {
    return new PhabricatorRepositoryPushEvent();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorRepositoryPushEventQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $event = $objects[$phid];

      $handle->setName(pht('Push Event %d', $event->getID()));
    }
  }

}
