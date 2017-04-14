<?php

final class PhortuneAccountViewController
  extends PhortuneAccountProfileController {

  public function handleRequest(AphrontRequest $request) {
    $response = $this->loadAccount();
    if ($response) {
      return $response;
    }

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

    $curtain = $this->buildCurtainView($account);
    $status = $this->buildStatusView($account, $invoices);
    $invoices = $this->buildInvoicesSection($account, $invoices);
    $purchase_history = $this->buildPurchaseHistorySection($account);

    $timeline = $this->buildTransactionTimeline(
      $account,
      new PhortuneAccountTransactionQuery());
    $timeline->setShouldTerminate(true);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(array(
        $status,
        $invoices,
        $purchase_history,
        $timeline,
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

  private function buildCurtainView(PhortuneAccount $account) {
    $viewer = $this->getViewer();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $account,
      PhabricatorPolicyCapability::CAN_EDIT);

    $edit_uri = $this->getApplicationURI('account/edit/'.$account->getID().'/');

    $curtain = $this->newCurtainView($account);
    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Account'))
        ->setIcon('fa-pencil')
        ->setHref($edit_uri)
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $member_phids = $account->getMemberPHIDs();
    $handles = $viewer->loadHandles($member_phids);

    $member_list = id(new PHUIObjectItemListView())
      ->setSimple(true);

    foreach ($member_phids as $member_phid) {
      $image_uri = $handles[$member_phid]->getImageURI();
      $image_href = $handles[$member_phid]->getURI();
      $person = $handles[$member_phid];

      $member = id(new PHUIObjectItemView())
        ->setImageURI($image_uri)
        ->setHref($image_href)
        ->setHeader($person->getFullName());

      $member_list->addItem($member);
    }

    $curtain->newPanel()
      ->setHeaderText(pht('Managers'))
      ->appendChild($member_list);

    return $curtain;
  }

  private function buildInvoicesSection(
    PhortuneAccount $account,
    array $carts) {

    $viewer = $this->getViewer();

    $phids = array();
    foreach ($carts as $cart) {
      $phids[] = $cart->getPHID();
      $phids[] = $cart->getMerchantPHID();
      foreach ($cart->getPurchases() as $purchase) {
        $phids[] = $purchase->getPHID();
      }
    }
    $handles = $this->loadViewerHandles($phids);

    $table = id(new PhortuneOrderTableView())
      ->setNoDataString(pht('You have no unpaid invoices.'))
      ->setIsInvoices(true)
      ->setUser($viewer)
      ->setCarts($carts)
      ->setHandles($handles);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Invoices Due'));

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setTable($table);
  }

  private function buildPurchaseHistorySection(PhortuneAccount $account) {
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
      ->setLimit(10)
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
