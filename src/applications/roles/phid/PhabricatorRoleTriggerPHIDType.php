<?php

final class PhabricatorRoleTriggerPHIDType
  extends PhabricatorPHIDType {

  const TYPECONST = 'WTRR';

  public function getTypeName() {
    return pht('Trigger');
  }

  public function getTypeIcon() {
    return 'fa-exclamation-triangle';
  }

  public function newObject() {
    return new PhabricatorRoleTrigger();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorRoleApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorRoleTriggerQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $trigger = $objects[$phid];

      $handle->setName($trigger->getDisplayName());
      $handle->setURI($trigger->getURI());
    }
  }

}
