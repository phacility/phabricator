<?php

final class PhortunePaymentMethodPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'PAYM';

  public function getTypeName() {
    return pht('Phortune Payment Method');
  }

  public function newObject() {
    return new PhortunePaymentMethod();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorPhortuneApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhortunePaymentMethodQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $method = $objects[$phid];

      $handle
        ->setName($method->getFullDisplayName())
        ->setURI($method->getURI());
    }
  }

}
