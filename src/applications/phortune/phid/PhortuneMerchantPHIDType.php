<?php

final class PhortuneMerchantPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'PMRC';

  public function getTypeName() {
    return pht('Phortune Merchant');
  }

  public function newObject() {
    return new PhortuneMerchant();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorPhortuneApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhortuneMerchantQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $merchant = $objects[$phid];

      $id = $merchant->getID();

      $handle->setName($merchant->getName());
      $handle->setURI("/phortune/merchant/{$id}/");
    }
  }

}
