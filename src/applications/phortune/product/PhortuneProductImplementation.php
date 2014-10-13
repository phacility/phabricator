<?php

abstract class PhortuneProductImplementation {

  abstract public function loadImplementationsForRefs(
    PhabricatorUser $viewer,
    array $refs);

  abstract public function getRef();
  abstract public function getName(PhortuneProduct $product);
  abstract public function getPriceAsCurrency(PhortuneProduct $product);

  protected function getContentSource() {
    return PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_PHORTUNE,
      array());
  }

  public function getPurchaseName(
    PhortuneProduct $product,
    PhortunePurchase $purchase) {
    return $this->getName($product);
  }

  public function didPurchaseProduct(
    PhortuneProduct $product,
    PhortunePurchase $purchase) {
    return;
  }

  public function didRefundProduct(
    PhortuneProduct $product,
    PhortunePurchase $purchase,
    PhortuneCurrency $amount) {
    return;
  }

}
