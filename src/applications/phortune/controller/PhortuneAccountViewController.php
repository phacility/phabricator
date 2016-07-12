<?php

final class PhortuneAccountViewController extends PhortuneController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    // TODO: Currently, you must be able to edit an account to view the detail
    // page, because the account must be broadly visible so merchants can
    // process orders but merchants should not be able to see all the details
    // of an account. Ideally this page should be visible to merchants, too,
    // just with less information.
    $can_edit = true;

    $account = id(new PhortuneAccountQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('accountID')))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$account) {
      return new Aphront404Response();
    }

    $title = $account->getName();

    $invoices = id(new PhortuneCartQuery())
      ->setViewer($viewer)
      ->withAccountPHIDs(array($account->getPHID()))
      ->needPurchases(true)
      ->withInvoices(true)
      ->execute();

    $crumbs = $this->buildApplicationCrumbs();
    $this->addAccountCrumb($crumbs, $account, $link = false);
    $crumbs->setBorder(true);

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setHeaderIcon('fa-credit-card');

    $curtain = $this->buildCurtainView($account, $invoices);
    $invoices = $this->buildInvoicesSection($account, $invoices);
    $purchase_history = $this->buildPurchaseHistorySection($account);
    $charge_history = $this->buildChargeHistorySection($account);
    $subscriptions = $this->buildSubscriptionsSection($account);
    $payment_methods = $this->buildPaymentMethodsSection($account);

    $timeline = $this->buildTransactionTimeline(
      $account,
      new PhortuneAccountTransactionQuery());
    $timeline->setShouldTerminate(true);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(array(
        $invoices,
        $purchase_history,
        $charge_history,
        $subscriptions,
        $payment_methods,
        $timeline,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);

  }

  private function buildCurtainView(PhortuneAccount $account, $invoices) {
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

    $status_items = $this->getStatusItemsForAccount($account, $invoices);
    $status_view = new PHUIStatusListView();
    foreach ($status_items as $item) {
      $status_view->addItem(
        id(new PHUIStatusItemView())
          ->setIcon(
            idx($item, 'icon'),
            idx($item, 'color'),
            idx($item, 'label'))
          ->setTarget(idx($item, 'target'))
          ->setNote(idx($item, 'note')));
    }

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
      ->setHeaderText(pht('Status'))
      ->appendChild($status_view);

    $curtain->newPanel()
      ->setHeaderText(pht('Members'))
      ->appendChild($member_list);

    return $curtain;
  }

  private function buildPaymentMethodsSection(PhortuneAccount $account) {
    $viewer = $this->getViewer();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $account,
      PhabricatorPolicyCapability::CAN_EDIT);

    $id = $account->getID();

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Payment Methods'));

    $list = id(new PHUIObjectItemListView())
      ->setUser($viewer)
      ->setFlush(true)
      ->setNoDataString(
        pht('No payment methods associated with this account.'));

    $methods = id(new PhortunePaymentMethodQuery())
      ->setViewer($viewer)
      ->withAccountPHIDs(array($account->getPHID()))
      ->withStatuses(
        array(
          PhortunePaymentMethod::STATUS_ACTIVE,
        ))
      ->execute();

    foreach ($methods as $method) {
      $id = $method->getID();

      $item = new PHUIObjectItemView();
      $item->setHeader($method->getFullDisplayName());

      switch ($method->getStatus()) {
        case PhortunePaymentMethod::STATUS_ACTIVE:
          $item->setStatusIcon('fa-check green');

          $disable_uri = $this->getApplicationURI('card/'.$id.'/disable/');
          $item->addAction(
            id(new PHUIListItemView())
              ->setIcon('fa-times')
              ->setHref($disable_uri)
              ->setDisabled(!$can_edit)
              ->setWorkflow(true));
          break;
        case PhortunePaymentMethod::STATUS_DISABLED:
          $item->setStatusIcon('fa-ban lightbluetext');
          $item->setDisabled(true);
          break;
      }

      $provider = $method->buildPaymentProvider();
      $item->addAttribute($provider->getPaymentMethodProviderDescription());

      $edit_uri = $this->getApplicationURI('card/'.$id.'/edit/');

      $item->addAction(
        id(new PHUIListItemView())
          ->setIcon('fa-pencil')
          ->setHref($edit_uri)
          ->setDisabled(!$can_edit)
          ->setWorkflow(!$can_edit));

      $list->addItem($item);
    }

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setObjectList($list);
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

  private function buildChargeHistorySection(PhortuneAccount $account) {
    $viewer = $this->getViewer();

    $charges = id(new PhortuneChargeQuery())
      ->setViewer($viewer)
      ->withAccountPHIDs(array($account->getPHID()))
      ->needCarts(true)
      ->setLimit(10)
      ->execute();

    $phids = array();
    foreach ($charges as $charge) {
      $phids[] = $charge->getProviderPHID();
      $phids[] = $charge->getCartPHID();
      $phids[] = $charge->getMerchantPHID();
      $phids[] = $charge->getPaymentMethodPHID();
    }

    $handles = $this->loadViewerHandles($phids);

    $charges_uri = $this->getApplicationURI($account->getID().'/charge/');

    $table = id(new PhortuneChargeTableView())
      ->setUser($viewer)
      ->setCharges($charges)
      ->setHandles($handles);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Recent Charges'))
      ->addActionLink(
        id(new PHUIButtonView())
          ->setTag('a')
          ->setIcon('fa-list')
          ->setHref($charges_uri)
          ->setText(pht('View All Charges')));

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setTable($table);
  }

  private function buildSubscriptionsSection(PhortuneAccount $account) {
    $viewer = $this->getViewer();

    $subscriptions = id(new PhortuneSubscriptionQuery())
      ->setViewer($viewer)
      ->withAccountPHIDs(array($account->getPHID()))
      ->setLimit(10)
      ->execute();

    $subscriptions_uri = $this->getApplicationURI(
      $account->getID().'/subscription/');

    $handles = $this->loadViewerHandles(mpull($subscriptions, 'getPHID'));

    $table = id(new PhortuneSubscriptionTableView())
      ->setUser($viewer)
      ->setHandles($handles)
      ->setSubscriptions($subscriptions);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Recent Subscriptions'))
      ->addActionLink(
        id(new PHUIButtonView())
          ->setTag('a')
          ->setIcon(
            id(new PHUIIconView())
              ->setIcon('fa-list'))
          ->setHref($subscriptions_uri)
          ->setText(pht('View All Subscriptions')));

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

    assert_instances_of($invoices, 'PhortuneCart');

    $items = array();

    if ($invoices) {
      $items[] = array(
        'icon' => PHUIStatusItemView::ICON_WARNING,
        'color' => 'yellow',
        'target' => pht('Invoices'),
        'note' => pht('You have %d unpaid invoice(s).', count($invoices)),
      );
    } else {
      $items[] = array(
        'icon' => PHUIStatusItemView::ICON_ACCEPT,
        'color' => 'green',
        'target' => pht('Invoices'),
        'note' => pht('This account has no unpaid invoices.'),
      );
    }

    // TODO: If a payment method has expired or is expiring soon, we should
    // add a status check for it.

    return $items;
  }

}
