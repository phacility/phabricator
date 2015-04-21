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

  abstract public function getName(PhortuneCart $cart);
  abstract public function getCancelURI(PhortuneCart $cart);
  abstract public function getDoneURI(PhortuneCart $cart);

  public function getDescription(PhortuneCart $cart) {
    return null;
  }

  public function getDoneActionName(PhortuneCart $cart) {
    return pht('Return to Application');
  }

  public function assertCanCancelOrder(PhortuneCart $cart) {
    switch ($cart->getStatus()) {
      case PhortuneCart::STATUS_PURCHASED:
        throw new Exception(
          pht(
            'This order can not be cancelled because it has already been '.
            'completed.'));
        break;
    }
  }

  public function assertCanRefundOrder(PhortuneCart $cart) {
    return;
  }

  abstract public function willCreateCart(
    PhabricatorUser $viewer,
    PhortuneCart $cart);

}
