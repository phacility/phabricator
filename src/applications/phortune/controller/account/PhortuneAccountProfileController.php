<?php

abstract class PhortuneAccountProfileController
  extends PhortuneAccountController {

  protected function buildHeaderView() {
    $viewer = $this->getViewer();
    $account = $this->getAccount();
    $title = $account->getName();

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($title)
      ->setHeaderIcon('fa-user-circle');

    if ($this->getMerchants()) {
      $customer_tag = id(new PHUITagView())
        ->setType(PHUITagView::TYPE_SHADE)
        ->setName(pht('Customer Account'))
        ->setColor('indigo')
        ->setIcon('fa-credit-card');
      $header->addTag($customer_tag);
    }

    return $header;
  }

  protected function buildSideNavView($filter = null) {
    $viewer = $this->getViewer();
    $account = $this->getAccount();
    $id = $account->getID();

    $can_edit = !$this->getMerchants();

    $nav = id(new AphrontSideNavFilterView())
      ->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $nav->addLabel(pht('Account'));

    $nav->addFilter(
      'overview',
      pht('Overview'),
      $account->getURI(),
      'fa-user-circle');

    $nav->newLink('details')
      ->setName(pht('Account Details'))
      ->setHref($this->getApplicationURI("/account/{$id}/details/"))
      ->setIcon('fa-address-card-o')
      ->setWorkflow(!$can_edit)
      ->setDisabled(!$can_edit);

    $nav->addLabel(pht('Payments'));

    $nav->addFilter(
      'methods',
      pht('Payment Methods'),
      $account->getPaymentMethodsURI(),
      'fa-credit-card');

    $nav->addFilter(
      'subscriptions',
      pht('Subscriptions'),
      $account->getSubscriptionsURI(),
      'fa-retweet');

    $nav->addFilter(
      'orders',
      pht('Orders'),
      $account->getOrdersURI(),
      'fa-shopping-bag');

    $nav->addFilter(
      'charges',
      pht('Charges'),
      $account->getChargesURI(),
      'fa-calculator');

    $nav->addLabel(pht('Personnel'));

    $nav->addFilter(
      'managers',
      pht('Account Managers'),
      $this->getApplicationURI("/account/{$id}/managers/"),
      'fa-group');

    $nav->newLink('addresses')
      ->setname(pht('Email Addresses'))
      ->setHref($account->getEmailAddressesURI())
      ->setIcon('fa-envelope-o')
      ->setWorkflow(!$can_edit)
      ->setDisabled(!$can_edit);

    $nav->selectFilter($filter);

    return $nav;
  }

  final protected function newRecentOrdersView(
    PhortuneAccount $account,
    $limit) {

    $viewer = $this->getViewer();

    $carts = id(new PhortuneCartQuery())
      ->setViewer($viewer)
      ->withAccountPHIDs(array($account->getPHID()))
      ->needPurchases(true)
      ->withStatuses(
        array(
          PhortuneCart::STATUS_PURCHASING,
          PhortuneCart::STATUS_CHARGED,
          PhortuneCart::STATUS_HOLD,
          PhortuneCart::STATUS_REVIEW,
          PhortuneCart::STATUS_PURCHASED,
        ))
      ->setLimit($limit)
      ->execute();

    $orders_uri = $account->getOrderListURI();

    $table = id(new PhortuneOrderTableView())
      ->setUser($viewer)
      ->setCarts($carts);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Recent Orders'))
      ->addActionLink(
        id(new PHUIButtonView())
          ->setTag('a')
          ->setIcon('fa-list')
          ->setHref($orders_uri)
          ->setText(pht('View All Orders')));

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setTable($table);
  }


}
