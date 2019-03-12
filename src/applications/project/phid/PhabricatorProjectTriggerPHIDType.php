<?php

final class PhabricatorProjectTriggerPHIDType
  extends PhabricatorPHIDType {

  const TYPECONST = 'WTRG';

  public function getTypeName() {
    return pht('Trigger');
  }

  public function getTypeIcon() {
    return 'fa-exclamation-triangle';
  }

  public function newObject() {
    return new PhabricatorProjectTrigger();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorProjectApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorProjectTriggerQuery())
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
