<?php

abstract class PhortuneCartImplementation {

  /**
   * Load implementations for a given set of carts.
   *
   * Note that this method should return a map using the original keys to
   * identify which implementation corresponds to which cart.
   */
  abstract public function loadImplementationsForCarts(
    PhabricatorUser $viewer,
    array $carts);

  abstract public function getName();

  abstract public function getCancelURI(PhortuneCart $cart);
  abstract public function getDoneURI(PhortuneCart $cart);

  abstract public function willCreateCart(
    PhabricatorUser $viewer,
    PhortuneCart $cart);

}
