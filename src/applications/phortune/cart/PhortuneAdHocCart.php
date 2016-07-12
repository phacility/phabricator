<?php

final class PhortuneAdHocCart extends PhortuneCartImplementation {

  public function loadImplementationsForCarts(
    PhabricatorUser $viewer,
    array $carts) {

    $results = array();
    foreach ($carts as $key => $cart) {
      $results[$key] = new PhortuneAdHocCart();
    }

    return $results;
  }

  public function getName(PhortuneCart $cart) {
    return $cart->getMetadataValue('adhoc.title');
  }

  public function getDescription(PhortuneCart $cart) {
    return $cart->getMetadataValue('adhoc.description');
  }

  public function getCancelURI(PhortuneCart $cart) {
    return null;
  }

  public function getDoneURI(PhortuneCart $cart) {
    return null;
  }

  public function willCreateCart(
    PhabricatorUser $viewer,
    PhortuneCart $cart) {
    return;
  }

}
