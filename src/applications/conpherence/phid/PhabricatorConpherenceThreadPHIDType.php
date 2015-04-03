<?php

final class PhabricatorConpherenceThreadPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'CONP';

  public function getTypeName() {
    return pht('Conpherence Thread');
  }

  public function newObject() {
    return new ConpherenceThread();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new ConpherenceThreadQuery())
      ->needParticipantCache(true)
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $thread = $objects[$phid];
      $data = $thread->getDisplayData($query->getViewer());
      $handle->setName($data['title']);
      $handle->setFullName($data['title']);
      $handle->setURI('/conpherence/'.$thread->getID().'/');
    }
  }

}
