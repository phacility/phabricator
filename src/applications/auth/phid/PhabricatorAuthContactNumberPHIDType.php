<?php

final class PhabricatorAuthContactNumberPHIDType
  extends PhabricatorPHIDType {

  const TYPECONST = 'CTNM';

  public function getTypeName() {
    return pht('Contact Number');
  }

  public function newObject() {
    return new PhabricatorAuthContactNumber();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorAuthApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorAuthContactNumberQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $contact_number = $objects[$phid];
    }
  }

}
