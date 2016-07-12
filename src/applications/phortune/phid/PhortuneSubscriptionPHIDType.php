<?php

final class PhortuneSubscriptionPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'PSUB';

  public function getTypeName() {
    return pht('Phortune Subscription');
  }

  public function newObject() {
    return new PhortuneSubscription();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorPhortuneApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhortuneSubscriptionQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $subscription = $objects[$phid];

      $id = $subscription->getID();

      $handle->setName($subscription->getSubscriptionName());
      $handle->setURI($subscription->getURI());

    }
  }

}
