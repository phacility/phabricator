<?php

final class PhabricatorAuthChallengePHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'CHAL';

  public function getTypeName() {
    return pht('Auth Challenge');
  }

  public function newObject() {
    return new PhabricatorAuthChallenge();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorAuthApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {
    return new PhabricatorAuthChallengeQuery();
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {
    return;
  }

}
