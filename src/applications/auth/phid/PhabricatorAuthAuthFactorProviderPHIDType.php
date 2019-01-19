<?php

final class PhabricatorAuthAuthFactorProviderPHIDType
  extends PhabricatorPHIDType {

  const TYPECONST = 'FPRV';

  public function getTypeName() {
    return pht('MFA Provider');
  }

  public function newObject() {
    return new PhabricatorAuthFactorProvider();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorAuthApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorAuthFactorProviderQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $provider = $objects[$phid];

      $handle->setURI($provider->getURI());
    }
  }

}
