<?php

final class PhabricatorWorkerTriggerPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'TRIG';

  public function getTypeName() {
    return pht('Trigger');
  }

  public function newObject() {
    return new PhabricatorWorkerTriggerPHIDType();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    // TODO: Maybe straighten this out eventually, but these aren't policy
    // objects and don't have an applicable query which we can return here.
    // Since we should never call this normally, just leave it stubbed for
    // now.

    throw new PhutilMethodNotImplementedException();
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
