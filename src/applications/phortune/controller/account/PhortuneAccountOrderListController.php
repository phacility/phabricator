<?php

final class PhortuneAccountOrderListController
  extends PhortuneAccountProfileController {

  private $subscription;

  protected function shouldRequireAccountEditCapability() {
    return false;
  }

  protected function handleAccountRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $account = $this->getAccount();

    $engine = id(new PhortuneCartSearchEngine())
      ->setController($this)
      ->setAccount($account);

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
    } else if ($this->hasAccount()) {
      $account = $this->getAccount();

      $crumbs->addTextCrumb(pht('Orders'), $account->getOrdersURI());
    }

    return $crumbs;
  }


}
