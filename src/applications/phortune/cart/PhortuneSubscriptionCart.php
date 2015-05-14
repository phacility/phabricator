<?php

final class PhortuneSubscriptionCart
   extends PhortuneCartImplementation {

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

  public function getName(PhortuneCart $cart) {
    return $this->getSubscription()->getCartName($cart);
  }

  public function willCreateCart(
    PhabricatorUser $viewer,
    PhortuneCart $cart) {

    $subscription = $this->getSubscription();
    if (!$subscription) {
      throw new PhutilInvalidStateException('setSubscription');
    }

    $cart->setMetadataValue('subscriptionPHID', $subscription->getPHID());
  }

  public function loadImplementationsForCarts(
    PhabricatorUser $viewer,
    array $carts) {

    $phids = array();
    foreach ($carts as $cart) {
      $phids[] = $cart->getMetadataValue('subscriptionPHID');
    }

    $subscriptions = id(new PhortuneSubscriptionQuery())
      ->setViewer($viewer)
      ->withPHIDs($phids)
      ->execute();
    $subscriptions = mpull($subscriptions, null, 'getPHID');

    $objects = array();
    foreach ($carts as $key => $cart) {
      $subscription_phid = $cart->getMetadataValue('subscriptionPHID');
      $subscription = idx($subscriptions, $subscription_phid);
      if (!$subscription) {
        continue;
      }

      $object = id(new PhortuneSubscriptionCart())
        ->setSubscriptionPHID($subscription_phid)
        ->setSubscription($subscription);

      $objects[$key] = $object;
    }

    return $objects;
  }

  public function getCancelURI(PhortuneCart $cart) {
    return $this->getSubscription()->getURI();
  }

  public function getDoneURI(PhortuneCart $cart) {
    return $this->getSubscription()->getURI();
  }

  public function getDoneActionName(PhortuneCart $cart) {
    return pht('Return to Subscription');
  }

}
