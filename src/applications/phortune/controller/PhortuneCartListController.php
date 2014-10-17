<?php

final class PhortuneCartListController
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

    $engine = new PhortuneCartSearchEngine();

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

    id(new PhortuneCartSearchEngine())
      ->setViewer($viewer)
      ->addNavigationItems($nav->getMenu());

    $nav->selectFilter(null);

    return $nav;
  }

  public function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $merchant = $this->merchant;
    if ($merchant) {
      $id = $merchant->getID();
      $crumbs->addTextCrumb(
        $merchant->getName(),
        $this->getApplicationURI("merchant/{$id}/"));
      $crumbs->addTextCrumb(
        pht('Orders'),
        $this->getApplicationURI("merchant/orders/{$id}/"));
    }

    $account = $this->account;
    if ($account) {
      $id = $account->getID();
      $crumbs->addTextCrumb(
        $account->getName(),
        $this->getApplicationURI("{$id}/"));
      $crumbs->addTextCrumb(
        pht('Orders'),
        $this->getApplicationURI("{$id}/order/"));
    }

    return $crumbs;
  }

}
