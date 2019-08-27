<?php

final class PhortuneMerchantSubscriptionListController
  extends PhortuneMerchantProfileController {

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

    if ($this->hasMerchant()) {
      $merchant = $this->getMerchant();

      $crumbs->addTextCrumb(
        pht('Subscriptions'),
        $merchant->getSubscriptionsURI());
    }

    return $crumbs;
  }


}
