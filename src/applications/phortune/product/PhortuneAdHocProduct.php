<?php

final class PhortuneAdHocProduct extends PhortuneProductImplementation {

  private $ref;

  public function loadImplementationsForRefs(
    PhabricatorUser $viewer,
    array $refs) {

    $results = array();
    foreach ($refs as $key => $ref) {
      $product = new PhortuneAdHocProduct();
      $product->ref = $ref;
      $results[$key] = $product;
    }

    return $results;
  }

  public function getRef() {
    return $this->ref;
  }

  public function getName(PhortuneProduct $product) {
    return pht('Ad-Hoc Product');
  }

  public function getPurchaseName(
    PhortuneProduct $product,
    PhortunePurchase $purchase) {

    return coalesce(
      $purchase->getMetadataValue('adhoc.name'),
      $this->getName($product));
  }

  public function getPriceAsCurrency(PhortuneProduct $product) {
    return PhortuneCurrency::newEmptyCurrency();
  }

}
