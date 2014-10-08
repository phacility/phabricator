<?php

final class PhortuneCartListController
  extends PhortuneController {

  private $merchantID;
  private $queryKey;

  private $merchant;

  public function willProcessRequest(array $data) {
    $this->merchantID = idx($data, 'merchantID');
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
    }

    $controller = id(new PhabricatorApplicationSearchController($request))
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

    return $crumbs;
  }

}
