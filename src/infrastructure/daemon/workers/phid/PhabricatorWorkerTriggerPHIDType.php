<?php

final class PhabricatorWorkerTriggerPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'TRIG';

  public function getTypeName() {
    return pht('Trigger');
  }

  public function newObject() {
    return new PhabricatorWorkerTrigger();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorDaemonsApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorWorkerTriggerQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $trigger = $objects[$phid];

      $id = $trigger->getID();

      $handle->setName(pht('Trigger %d', $id));
    }
  }

}
