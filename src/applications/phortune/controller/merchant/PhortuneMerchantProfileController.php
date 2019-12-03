<?php

abstract class PhortuneMerchantProfileController
  extends PhortuneMerchantController {

  protected function buildHeaderView() {
    $viewer = $this->getViewer();
    $merchant = $this->getMerchant();
    $title = $merchant->getName();

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setUser($viewer)
      ->setImage($merchant->getProfileImageURI());

    return $header;
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    if ($this->hasMerchant()) {
      $merchant = $this->getMerchant();
      $merchant_uri = $merchant->getURI();
      $crumbs->addTextCrumb($merchant->getName(), $merchant_uri);
    }

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

    $nav->newLink('overview')
      ->setName(pht('Overview'))
      ->setHref($merchant->getURI())
      ->setIcon('fa-building-o');

    $nav->newLink('details')
      ->setName(pht('Account Details'))
      ->setHref($merchant->getDetailsURI())
      ->setIcon('fa-address-card-o')
      ->setDisabled(!$can_edit)
      ->setWorkflow(!$can_edit);

    $nav->addLabel(pht('Payments'));

    $nav->newLink('providers')
      ->setName(pht('Payment Providers'))
      ->setHref($merchant->getPaymentProvidersURI())
      ->setIcon('fa-credit-card')
      ->setDisabled(!$can_edit)
      ->setWorkflow(!$can_edit);

    $nav->newLink('orders')
      ->setName(pht('Orders'))
      ->setHref($merchant->getOrdersURI())
      ->setIcon('fa-shopping-bag')
      ->setDisabled(!$can_edit)
      ->setWorkflow(!$can_edit);

    $nav->newLink('subscriptions')
      ->setName(pht('Subscriptions'))
      ->setHref($merchant->getSubscriptionsURI())
      ->setIcon('fa-retweet')
      ->setDisabled(!$can_edit)
      ->setWorkflow(!$can_edit);

    $nav->addLabel(pht('Personnel'));

    $nav->newLink('managers')
      ->setName(pht('Managers'))
      ->setHref($merchant->getManagersURI())
      ->setIcon('fa-group');

    $nav->selectFilter($filter);

    return $nav;
  }

}
