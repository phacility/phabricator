<?php

final class PhortuneSubscriptionListController
  extends PhortuneController {

  private $merchant;
  private $account;

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $querykey = $request->getURIData('queryKey');
    $merchant_id = $request->getURIData('merchantID');
    $account_id = $request->getURIData('accountID');

    $engine = new PhortuneSubscriptionSearchEngine();

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

    $controller = id(new PhabricatorApplicationSearchController())
      ->setQueryKey($querykey)
      ->setSearchEngine($engine)
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function buildSideNavView() {
    $viewer = $this->getViewer();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    id(new PhortuneSubscriptionSearchEngine())
      ->setViewer($viewer)
      ->addNavigationItems($nav->getMenu());

    $nav->selectFilter(null);

    return $nav;
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $merchant = $this->merchant;
    if ($merchant) {
      $id = $merchant->getID();
      $this->addMerchantCrumb($crumbs, $merchant);
      $crumbs->addTextCrumb(
        pht('Subscriptions'),
        $this->getApplicationURI("merchant/subscriptions/{$id}/"));
    }

    $account = $this->account;
    if ($account) {
      $id = $account->getID();
      $this->addAccountCrumb($crumbs, $account);
      $crumbs->addTextCrumb(
        pht('Subscriptions'),
        $this->getApplicationURI("{$id}/subscription/"));
    }

    return $crumbs;
  }

}
