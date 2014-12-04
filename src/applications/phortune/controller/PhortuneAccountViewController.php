<?php

final class PhortuneAccountViewController extends PhortuneController {

  private $accountID;

  public function willProcessRequest(array $data) {
    $this->accountID = $data['accountID'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    // TODO: Currently, you must be able to edit an account to view the detail
    // page, because the account must be broadly visible so merchants can
    // process orders but merchants should not be able to see all the details
    // of an account. Ideally this page should be visible to merchants, too,
    // just with less information.
    $can_edit = true;

    $account = id(new PhortuneAccountQuery())
      ->setViewer($user)
      ->withIDs(array($this->accountID))
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

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      $account->getName(),
      $request->getRequestURI());

    $header = id(new PHUIHeaderView())
      ->setHeader($title);

    $edit_uri = $this->getApplicationURI('account/edit/'.$account->getID().'/');

    $actions = id(new PhabricatorActionListView())
      ->setUser($user)
      ->setObjectURI($request->getRequestURI())
      ->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Edit Account'))
          ->setIcon('fa-pencil')
          ->setHref($edit_uri)
          ->setDisabled(!$can_edit)
          ->setWorkflow(!$can_edit));

    $crumbs->setActionList($actions);

    $properties = id(new PHUIPropertyListView())
      ->setObject($account)
      ->setUser($user);

    $this->loadHandles($account->getMemberPHIDs());

    $properties->addProperty(
      pht('Members'),
      $this->renderHandlesForPHIDs($account->getMemberPHIDs()));

    $properties->setActionList($actions);

    $payment_methods = $this->buildPaymentMethodsSection($account);
    $purchase_history = $this->buildPurchaseHistorySection($account);
    $charge_history = $this->buildChargeHistorySection($account);
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
        $payment_methods,
        $purchase_history,
        $charge_history,
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
      ->setNoDataString(
        pht('No payment methods associated with this account.'));

    $methods = id(new PhortunePaymentMethodQuery())
      ->setViewer($viewer)
      ->withAccountPHIDs(array($account->getPHID()))
      ->execute();

    if ($methods) {
      $this->loadHandles(mpull($methods, 'getAuthorPHID'));
    }

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

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setIcon('fa-exchange')
        ->setHref($this->getApplicationURI('account/'))
        ->setName(pht('Switch Accounts')));

    return $crumbs;
  }

}
