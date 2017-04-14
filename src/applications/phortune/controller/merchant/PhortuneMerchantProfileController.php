<?php

abstract class PhortuneMerchantProfileController
  extends PhortuneController {

  private $merchant;

  public function setMerchant(PhortuneMerchant $merchant) {
    $this->merchant = $merchant;
    return $this;
  }

  public function getMerchant() {
    return $this->merchant;
  }

  public function buildApplicationMenu() {
    return $this->buildSideNavView()->getMenu();
  }

  protected function buildHeaderView() {
    $viewer = $this->getViewer();
    $merchant = $this->getMerchant();
    $title = $merchant->getName();

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setUser($viewer)
      ->setPolicyObject($merchant)
      ->setImage($merchant->getProfileImageURI());

    return $header;
  }

  protected function buildApplicationCrumbs() {
    $merchant = $this->getMerchant();
    $id = $merchant->getID();
    $merchant_uri = $this->getApplicationURI("/merchant/{$id}/");

    $crumbs = parent::buildApplicationCrumbs();
    $crumbs->addTextCrumb($merchant->getName(), $merchant_uri);
    $crumbs->setBorder(true);
    return $crumbs;
  }

  protected function buildSideNavView($filter = null) {
    $viewer = $this->getViewer();
    $merchant = $this->getMerchant();
    $id = $merchant->getID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $merchant,
      PhabricatorPolicyCapability::CAN_EDIT);

    $nav = id(new AphrontSideNavFilterView())
      ->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $nav->addLabel(pht('Merchant'));

    $nav->addFilter(
      'overview',
      pht('Overview'),
      $this->getApplicationURI("/merchant/{$id}/"),
      'fa-building-o');

    if ($can_edit) {
      $nav->addFilter(
        'orders',
        pht('Orders'),
        $this->getApplicationURI("merchant/orders/{$id}/"),
        'fa-retweet');

      $nav->addFilter(
        'subscriptions',
        pht('Subscriptions'),
        $this->getApplicationURI("merchant/{$id}/subscription/"),
        'fa-shopping-cart');

      $nav->addFilter(
        'managers',
        pht('Managers'),
        $this->getApplicationURI("/merchant/manager/{$id}/"),
        'fa-group');
    }

    $nav->selectFilter($filter);

    return $nav;
  }

}
