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
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $thread = $objects[$phid];
      $name = $thread->getTitle();
      if (!strlen($name)) {
        $name = pht('[No Title]');
      }
      $handle->setName($name);
      $handle->setFullName($name);
      $handle->setURI('/conpherence/'.$thread->getID().'/');
    }
  }

}
