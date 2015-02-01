<?php

abstract class PhortuneSubscriptionImplementation {

  abstract public function loadImplementationsForRefs(
    PhabricatorUser $viewer,
    array $refs);

  abstract public function getRef();
  abstract public function getName(PhortuneSubscription $subscription);
  abstract public function getCostForBillingPeriodAsCurrency(
    PhortuneSubscription $subscription,
    $start_epoch,
    $end_epoch);

  protected function getContentSource() {
    return PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_PHORTUNE,
      array());
  }

  public function getCartName(
    PhortuneSubscription $subscription,
    PhortuneCart $cart) {
    return pht('Subscription');
  }

}
