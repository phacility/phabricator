<?php

final class PhabricatorAuthInvitePHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'AINV';

  public function getTypeName() {
    return pht('Auth Invite');
  }

  public function newObject() {
    return new PhabricatorAuthInvite();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorAuthApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {
    throw new PhutilMethodNotImplementedException();
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $invite = $objects[$phid];
    }
  }

}
