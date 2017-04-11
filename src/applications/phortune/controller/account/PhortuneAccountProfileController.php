<?php

abstract class PhortuneAccountProfileController
  extends PhortuneController {

  private $account;

  public function setAccount(PhortuneAccount $account) {
    $this->account = $account;
    return $this;
  }

  public function getAccount() {
    return $this->account;
  }

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
    $account = $this->getAccount();
    $id = $account->getID();
    $account_uri = $this->getApplicationURI("/{$id}/");

    $crumbs = parent::buildApplicationCrumbs();
    $crumbs->addTextCrumb($account->getName(), $account_uri);
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
      'subscriptions',
      pht('Subscriptions'),
      $this->getApplicationURI("/account/subscription/{$id}/"),
      'fa-retweet');

    $nav->addFilter(
      'billing',
      pht('Billing / History'),
      $this->getApplicationURI("/account/billing/{$id}/"),
      'fa-credit-card');

    $nav->addFilter(
      'managers',
      pht('Managers'),
      $this->getApplicationURI("/account/manager/{$id}/"),
      'fa-group');

    $nav->selectFilter($filter);

    return $nav;
  }

}
