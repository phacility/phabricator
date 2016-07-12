<?php

final class PhortunePurchasePHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'PRCH';

  public function getTypeName() {
    return pht('Phortune Purchase');
  }

  public function newObject() {
    return new PhortunePurchase();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorPhortuneApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhortunePurchaseQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $purchase = $objects[$phid];

      $id = $purchase->getID();

      $handle->setName($purchase->getFullDisplayName());
      $handle->setURI($purchase->getURI());
    }
  }

}
