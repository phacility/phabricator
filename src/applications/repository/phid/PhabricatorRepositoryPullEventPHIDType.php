<?php

final class PhabricatorRepositoryPullEventPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'PULE';

  public function getTypeName() {
    return pht('Pull Event');
  }

  public function newObject() {
    return new PhabricatorRepositoryPullEvent();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorRepositoryPullEventQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $event = $objects[$phid];

      $handle->setName(pht('Pull Event %d', $event->getID()));
    }
  }

}
