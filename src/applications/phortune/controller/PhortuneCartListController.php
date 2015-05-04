<?php

final class PhortuneCartListController
  extends PhortuneController {

  private $merchant;
  private $account;
  private $subscription;
  private $engine;

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $merchant_id = $request->getURIData('merchantID');
    $account_id = $request->getURIData('accountID');
    $subscription_id = $request->getURIData('subscriptionID');

    $engine = id(new PhortuneCartSearchEngine())
      ->setViewer($viewer);

    if ($merchant_id) {
      $merchant = id(new PhortuneMerchantQuery())
        ->setViewer($viewer)
        ->withIDs(array($merchant_id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$merchant) {
        return new Aphront404Response();
      }
      $this->merchant = $merchant;
      $viewer->grantAuthority($merchant);
      $engine->setMerchant($merchant);
    } else if ($account_id) {
      $account = id(new PhortuneAccountQuery())
        ->setViewer($viewer)
        ->withIDs(array($account_id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$account) {
        return new Aphront404Response();
      }
      $this->account = $account;
      $engine->setAccount($account);
    } else {
      return new Aphront404Response();
    }

    // NOTE: We must process this after processing the merchant authority, so
    // it becomes visible in merchant contexts.
    if ($subscription_id) {
      $subscription = id(new PhortuneSubscriptionQuery())
        ->setViewer($viewer)
        ->withIDs(array($subscription_id))
        ->executeOne();
      if (!$subscription) {
        return new Aphront404Response();
      }
      $this->subscription = $subscription;
      $engine->setSubscription($subscription);
    }

    $this->engine = $engine;

    $controller = id(new PhabricatorApplicationSearchController())
      ->setQueryKey($request->getURIData('queryKey'))
      ->setSearchEngine($engine)
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function buildSideNavView() {
    $viewer = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $this->engine->addNavigationItems($nav->getMenu());

    $nav->selectFilter(null);

    return $nav;
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $subscription = $this->subscription;

    $merchant = $this->merchant;
    if ($merchant) {
      $id = $merchant->getID();
      $this->addMerchantCrumb($crumbs, $merchant);
      if (!$subscription) {
        $crumbs->addTextCrumb(
          pht('Orders'),
          $this->getApplicationURI("merchant/orders/{$id}/"));
      }
    }

    $account = $this->account;
    if ($account) {
      $id = $account->getID();
      $this->addAccountCrumb($crumbs, $account);
      if (!$subscription) {
        $crumbs->addTextCrumb(
          pht('Orders'),
          $this->getApplicationURI("{$id}/order/"));
      }
    }

    if ($subscription) {
      if ($merchant) {
        $subscription_uri = $subscription->getMerchantURI();
      } else {
        $subscription_uri = $subscription->getURI();
      }
      $crumbs->addTextCrumb(
        $subscription->getSubscriptionName(),
        $subscription_uri);
    }

    return $crumbs;
  }

}
