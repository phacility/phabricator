<?php

final class PhortunePaymentProviderPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'PHPR';

  public function getTypeName() {
    return pht('Phortune Payment Provider');
  }

  public function newObject() {
    return new PhortunePaymentProviderConfig();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorPhortuneApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhortunePaymentProviderConfigQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $provider_config = $objects[$phid];

      $id = $provider_config->getID();

      $handle->setName($provider_config->buildProvider()->getName());
    }
  }

}
