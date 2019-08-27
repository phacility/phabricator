<?php

final class PhortuneMerchantOrderListController
  extends PhortuneMerchantProfileController {

  private $subscription;

  protected function shouldRequireMerchantEditCapability() {
    return true;
  }

  protected function handleMerchantRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $merchant = $this->getMerchant();

    $engine = id(new PhortuneCartSearchEngine())
      ->setController($this)
      ->setMerchant($merchant);

    $subscription_id = $request->getURIData('subscriptionID');
    if ($subscription_id) {
      $subscription = id(new PhortuneSubscriptionQuery())
        ->setViewer($viewer)
        ->withIDs(array($subscription_id))
        ->executeOne();
      if (!$subscription) {
        return new Aphront404Response();
      }

      $engine->setSubscription($subscription);
      $this->subscription = $subscription;
    }

    return $engine->buildResponse();
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $subscription = $this->subscription;
    if ($subscription) {
      $crumbs->addTextCrumb(
        $subscription->getObjectName(),
        $subscription->getURI());
    } else if ($this->hasMerchant()) {
      $merchant = $this->getMerchant();

      $crumbs->addTextCrumb(pht('Orders'), $merchant->getOrdersURI());
    }

    return $crumbs;
  }


}
