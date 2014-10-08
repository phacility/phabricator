<?php

final class NuanceQueuePHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'NUAQ';

  public function getTypeName() {
    return pht('Queue');
  }

  public function newObject() {
    return new NuanceQueue();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new NuanceQueueQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    $viewer = $query->getViewer();
    foreach ($handles as $phid => $handle) {
      $queue = $objects[$phid];

      $handle->setName($queue->getName());
      $handle->setURI($queue->getURI());
    }
  }

}
