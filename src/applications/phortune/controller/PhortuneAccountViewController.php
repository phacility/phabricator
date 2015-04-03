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

    $header = id(new PHUIHeaderView())
      ->setHeader($title);

    $edit_uri = $this->getApplicationURI('account/edit/'.$account->getID().'/');

    $actions = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObjectURI($request->getRequestURI())
      ->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Edit Account'))
          ->setIcon('fa-pencil')
          ->setHref($edit_uri)
          ->setDisabled(!$can_edit)
          ->setWorkflow(!$can_edit));

    $properties = id(new PHUIPropertyListView())
      ->setObject($account)
      ->setUser($viewer);

    $properties->addProperty(
      pht('Members'),
      $viewer->renderHandleList($account->getMemberPHIDs()));

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
    $properties->addProperty(
      pht('Status'),
      $status_view);

    $properties->setActionList($actions);

    $invoices = $this->buildInvoicesSection($account, $invoices);
    $purchase_history = $this->buildPurchaseHistorySection($account);
    $charge_history = $this->buildChargeHistorySection($account);
    $subscriptions = $this->buildSubscriptionsSection($account);
    $payment_methods = $this->buildPaymentMethodsSection($account);

    $timeline = $this->buildTransactionTimeline(
      $account,
      new PhortuneAccountTransactionQuery());
    $timeline->setShouldTerminate(true);

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
        $invoices,
        $purchase_history,
        $charge_history,
        $subscriptions,
        $payment_methods,
        $timeline,
      ),
      array(
        'title' => $title,
      ));
  }

  private function buildPaymentMethodsSection(PhortuneAccount $account) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

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
      ->execute();

    foreach ($methods as $method) {
      $id = $method->getID();

      $item = new PHUIObjectItemView();
      $item->setHeader($method->getFullDisplayName());

      switch ($method->getStatus()) {
        case PhortunePaymentMethod::STATUS_ACTIVE:
          $item->setBarColor('green');

          $disable_uri = $this->getApplicationURI('card/'.$id.'/disable/');
          $item->addAction(
            id(new PHUIListItemView())
              ->setIcon('fa-times')
              ->setHref($disable_uri)
              ->setDisabled(!$can_edit)
              ->setWorkflow(true));
          break;
        case PhortunePaymentMethod::STATUS_DISABLED:
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
      ->appendChild($list);
  }

  private function buildInvoicesSection(
    PhortuneAccount $account,
    array $carts) {

    $request = $this->getRequest();
    $viewer = $request->getUser();

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
      ->appendChild($table);
  }

  private function buildPurchaseHistorySection(PhortuneAccount $account) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

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
          ->setIcon(
            id(new PHUIIconView())
              ->setIconFont('fa-list'))
          ->setHref($orders_uri)
          ->setText(pht('View All Orders')));

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->appendChild($table);
  }

  private function buildChargeHistorySection(PhortuneAccount $account) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

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
          ->setIcon(
            id(new PHUIIconView())
              ->setIconFont('fa-list'))
          ->setHref($charges_uri)
          ->setText(pht('View All Charges')));

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->appendChild($table);
  }

  private function buildSubscriptionsSection(PhortuneAccount $account) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

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
              ->setIconFont('fa-list'))
          ->setHref($subscriptions_uri)
          ->setText(pht('View All Subscriptions')));

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->appendChild($table);
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
