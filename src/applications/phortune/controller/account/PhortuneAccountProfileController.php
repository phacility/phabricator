<?php

abstract class PhortuneAccountProfileController
  extends PhortuneAccountController {

  public function buildApplicationMenu() {
    return $this->buildSideNavView()->getMenu();
  }

  protected function buildHeaderView() {
    $viewer = $this->getViewer();
    $account = $this->getAccount();
    $title = $account->getName();

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($title)
      ->setHeaderIcon('fa-user-circle');

    return $header;
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();
    $crumbs->setBorder(true);
    return $crumbs;
  }

  protected function buildSideNavView($filter = null) {
    $viewer = $this->getViewer();
    $account = $this->getAccount();
    $id = $account->getID();

    $nav = id(new AphrontSideNavFilterView())
      ->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $nav->addLabel(pht('Account'));

    $nav->addFilter(
      'overview',
      pht('Overview'),
      $this->getApplicationURI("/{$id}/"),
      'fa-user-circle');

    $nav->addFilter(
      'details',
      pht('Account Details'),
      $this->getApplicationURI("/account/{$id}/details/"),
      'fa-address-card-o');

    $nav->addLabel(pht('Payments'));

    $nav->addFilter(
      'methods',
      pht('Payment Methods'),
      $this->getApplicationURI("/account/{$id}/methods/"),
      'fa-credit-card');

    $nav->addFilter(
      'subscriptions',
      pht('Subscriptions'),
      $this->getApplicationURI("/account/{$id}/subscriptions/"),
      'fa-retweet');

    $nav->addFilter(
      'orders',
      pht('Order History'),
      $this->getApplicationURI("/account/{$id}/orders/"),
      'fa-shopping-bag');

    $nav->addFilter(
      'charges',
      pht('Charge History'),
      $this->getApplicationURI("/account/{$id}/charges/"),
      'fa-calculator');

    $nav->addLabel(pht('Personnel'));

    $nav->addFilter(
      'managers',
      pht('Account Managers'),
      $this->getApplicationURI("/account/{$id}/managers/"),
      'fa-group');

    $nav->addFilter(
      'addresses',
      pht('Email Addresses'),
      $this->getApplicationURI("/account/{$id}/addresses/"),
      'fa-envelope-o');

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

    $phids = array();
    foreach ($carts as $cart) {
      $phids[] = $cart->getPHID();
      foreach ($cart->getPurchases() as $purchase) {
        $phids[] = $purchase->getPHID();
      }
    }
    $handles = $this->loadViewerHandles($phids);

    $orders_uri = $this->getApplicationURI($account->getID().'/order/');

    $table = id(new PhortuneOrderTableView())
      ->setUser($viewer)
      ->setCarts($carts)
      ->setHandles($handles);

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
