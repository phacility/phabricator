<?php

abstract class PhortuneSubscriptionImplementation extends Phobject {

  abstract public function loadImplementationsForRefs(
    PhabricatorUser $viewer,
    array $refs);

  abstract public function getRef();
  abstract public function getName(PhortuneSubscription $subscription);

  public function getFullName(PhortuneSubscription $subscription) {
    return $this->getName($subscription);
  }

  public function getCrumbName(PhortuneSubscription $subscription) {
    return $this->getName($subscription);
  }

  abstract public function getCostForBillingPeriodAsCurrency(
    PhortuneSubscription $subscription,
    $start_epoch,
    $end_epoch);

  public function shouldInvoiceForBillingPeriod(
    PhortuneSubscription $subscription,
    $start_epoch,
    $end_epoch) {
    return true;
  }

  public function getCartName(
    PhortuneSubscription $subscription,
    PhortuneCart $cart) {
    return pht('Subscription');
  }

  public function getPurchaseName(
    PhortuneSubscription $subscription,
    PhortuneProduct $product,
    PhortunePurchase $purchase) {
    return $product->getProductName();
  }

  public function getPurchaseURI(
    PhortuneSubscription $subscription,
    PhortuneProduct $product,
    PhortunePurchase $purchase) {
    return null;
  }
}
