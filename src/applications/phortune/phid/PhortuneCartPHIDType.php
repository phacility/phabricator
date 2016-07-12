<?php

final class PhortuneCartPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'CART';

  public function getTypeName() {
    return pht('Phortune Cart');
  }

  public function newObject() {
    return new PhortuneCart();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorPhortuneApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhortuneCartQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $cart = $objects[$phid];

      $id = $cart->getID();
      $name = $cart->getName();

      $handle->setName($name);
      $handle->setURI("/phortune/cart/{$id}/");
    }
  }

}
