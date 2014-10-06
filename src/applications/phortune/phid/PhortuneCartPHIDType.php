<?php

final class PhortuneCartPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'CART';

  public function getTypeName() {
    return pht('Phortune Cart');
  }

  public function newObject() {
    return new PhortuneCart();
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

      $handle->setName(pht('Cart %d', $id));
      $handle->setURI("/phortune/cart/{$id}/");
    }
  }

}
