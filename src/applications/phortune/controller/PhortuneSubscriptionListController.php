<?php

final class PhortuneSubscriptionListController
  extends PhortuneController {

  private $accountID;
  private $merchantID;
  private $queryKey;

  private $merchant;
  private $account;

  public function willProcessRequest(array $data) {
    $this->merchantID = idx($data, 'merchantID');
    $this->accountID = idx($data, 'accountID');
    $this->queryKey = idx($data, 'queryKey');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $engine = new PhortuneSubscriptionSearchEngine();

    if ($this->merchantID) {
      $merchant = id(new PhortuneMerchantQuery())
        ->setViewer($viewer)
        ->withIDs(array($this->merchantID))
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
    } else if ($this->accountID) {
      $account = id(new PhortuneAccountQuery())
        ->setViewer($viewer)
        ->withIDs(array($this->accountID))
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
      ->setQueryKey($this->queryKey)
      ->setSearchEngine($engine)
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function buildSideNavView() {
    $viewer = $this->getRequest()->getUser();

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
