<?php

final class PhabricatorAuthAuthFactorPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'AFTR';

  public function getTypeName() {
    return pht('Auth Factor');
  }

  public function newObject() {
    return new PhabricatorAuthFactorConfig();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    // TODO: Maybe we need this eventually?
    throw new PhutilMethodNotImplementedException();
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $factor = $objects[$phid];

      $handle->setName($factor->getFactorName());
    }
  }

}
