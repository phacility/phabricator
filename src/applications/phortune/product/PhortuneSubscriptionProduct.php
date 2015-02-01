<?php

final class PhortuneSubscriptionProduct
  extends  PhortuneProductImplementation {

  private $viewer;
  private $subscriptionPHID;
  private $subscription;

  public function setSubscriptionPHID($subscription_phid) {
    $this->subscriptionPHID = $subscription_phid;
    return $this;
  }

  public function getSubscriptionPHID() {
    return $this->subscriptionPHID;
  }

  public function setSubscription(PhortuneSubscription $subscription) {
    $this->subscription = $subscription;
    return $this;
  }

  public function getSubscription() {
    return $this->subscription;
  }

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function getRef() {
    return $this->getSubscriptionPHID();
  }

  public function getName(PhortuneProduct $product) {
    return $this->getSubscription()->getSubscriptionName();
  }

  public function getPriceAsCurrency(PhortuneProduct $product) {
    // Prices are calculated by the SubscriptionImplementation.
    return PhortuneCurrency::newEmptyCurrency();
  }

  public function didPurchaseProduct(
    PhortuneProduct $product,
    PhortunePurchase $purchase) {
    // TODO: Callback the subscription.
    return;
  }

  public function didRefundProduct(
    PhortuneProduct $product,
    PhortunePurchase $purchase,
    PhortuneCurrency $amount) {
    // TODO: Callback the subscription.
    return;
  }

  public function getPurchaseName(
    PhortuneProduct $product,
    PhortunePurchase $purchase) {
    return $this->getSubscription()->getPurchaseName(
      $product,
      $purchase);
  }

  public function getPurchaseURI(
    PhortuneProduct $product,
    PhortunePurchase $purchase) {
    return $this->getSubscription()->getPurchaseURI(
      $product,
      $purchase);
  }

  public function loadImplementationsForRefs(
    PhabricatorUser $viewer,
    array $refs) {

    $subscriptions = id(new PhortuneSubscriptionQuery())
      ->setViewer($viewer)
      ->withPHIDs($refs)
      ->execute();
    $subscriptions = mpull($subscriptions, null, 'getPHID');

    $objects = array();
    foreach ($refs as $ref) {
      $subscription = idx($subscriptions, $ref);
      if (!$subscription) {
        continue;
      }

      $objects[] = id(new PhortuneSubscriptionProduct())
        ->setViewer($viewer)
        ->setSubscriptionPHID($ref)
        ->setSubscription($subscription);
    }

    return $objects;
  }

}
