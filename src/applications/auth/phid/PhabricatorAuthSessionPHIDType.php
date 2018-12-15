<?php

final class PhabricatorAuthSessionPHIDType
  extends PhabricatorPHIDType {

  const TYPECONST = 'SSSN';

  public function getTypeName() {
    return pht('Session');
  }

  public function newObject() {
    return new PhabricatorAuthSession();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorAuthApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {
    return id(new PhabricatorAuthSessionQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {
    return;
  }

}
