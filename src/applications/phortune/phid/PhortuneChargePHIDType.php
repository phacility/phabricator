<?php

final class PhortuneChargePHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'CHRG';

  public function getTypeName() {
    return pht('Phortune Charge');
  }

  public function newObject() {
    return new PhortuneCharge();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorPhortuneApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhortuneChargeQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $charge = $objects[$phid];

      $id = $charge->getID();

      $handle->setName(pht('Charge %d', $id));
      $handle->setURI("/phortune/charge/{$id}/");
    }
  }

}
