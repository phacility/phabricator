<?php

final class PhortuneAccountOverviewController
  extends PhortuneAccountProfileController {

  protected function shouldRequireAccountEditCapability() {
    return false;
  }

  protected function handleAccountRequest(AphrontRequest $request) {
    $account = $this->getAccount();
    $title = $account->getName();

    $viewer = $this->getViewer();

    $invoices = id(new PhortuneCartQuery())
      ->setViewer($viewer)
      ->withAccountPHIDs(array($account->getPHID()))
      ->needPurchases(true)
      ->withInvoices(true)
      ->execute();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->setBorder(true);

    $header = $this->buildHeaderView();

    $authority = $this->newAccountAuthorityView();
    $status = $this->buildStatusView($account, $invoices);
    $invoices = $this->buildInvoicesSection($account, $invoices);
    $purchase_history = $this->newRecentOrdersView($account, 10);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(
        array(
          $authority,
          $status,
          $invoices,
          $purchase_history,
        ));

    $navigation = $this->buildSideNavView('overview');

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setNavigation($navigation)
      ->appendChild($view);
  }

  private function buildStatusView(PhortuneAccount $account, $invoices) {
    $status_items = $this->getStatusItemsForAccount($account, $invoices);
    $view = array();
    foreach ($status_items as $item) {
      $view[] = id(new PHUIInfoView())
        ->setSeverity(idx($item, 'severity'))
        ->appendChild(idx($item, 'note'));
    }
    return $view;
  }

  private function buildInvoicesSection(
    PhortuneAccount $account,
    array $carts) {

    $viewer = $this->getViewer();

    $table = id(new PhortuneOrderTableView())
      ->setNoDataString(pht('You have no unpaid invoices.'))
      ->setIsInvoices(true)
      ->setUser($viewer)
      ->setCarts($carts);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Invoices Due'));

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setTable($table);
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setIcon('fa-exchange')
        ->setHref($this->getApplicationURI('account/'))
        ->setName(pht('Switch Accounts')));

    return $crumbs;
  }

  private function getStatusItemsForAccount(
    PhortuneAccount $account,
    array $invoices) {
    $viewer = $this->getViewer();

    assert_instances_of($invoices, 'PhortuneCart');
    $items = array();

    $methods = id(new PhortunePaymentMethodQuery())
      ->setViewer($viewer)
      ->withAccountPHIDs(array($account->getPHID()))
      ->withStatuses(
        array(
          PhortunePaymentMethod::STATUS_ACTIVE,
        ))
      ->execute();

    if ($invoices) {
      $items[] = array(
        'severity' => PHUIInfoView::SEVERITY_ERROR,
        'note' => pht('You have %d unpaid invoice(s).', count($invoices)),
      );
    }

    // TODO: If a payment method has expired or is expiring soon, we should
    // add a status check for it.

    return $items;
  }

}
