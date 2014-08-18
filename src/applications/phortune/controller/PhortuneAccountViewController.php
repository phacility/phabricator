<?php

final class PhortuneAccountViewController extends PhortuneController {

  private $accountID;

  public function willProcessRequest(array $data) {
    $this->accountID = $data['accountID'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $account = id(new PhortuneAccountQuery())
      ->setViewer($user)
      ->withIDs(array($this->accountID))
      ->executeOne();

    if (!$account) {
      return new Aphront404Response();
    }

    $title = $account->getName();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Account'), $request->getRequestURI());

    $header = id(new PHUIHeaderView())
      ->setHeader($title);

    $actions = id(new PhabricatorActionListView())
      ->setUser($user)
      ->setObjectURI($request->getRequestURI())
      ->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Edit Account'))
          ->setIcon('fa-pencil')
          ->setHref('#')
          ->setDisabled(true))
      ->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Edit Members'))
          ->setIcon('fa-users')
          ->setHref('#')
          ->setDisabled(true));

    $crumbs->setActionList($actions);

    $properties = id(new PHUIPropertyListView())
      ->setObject($account)
      ->setUser($user);

    $properties->addProperty(pht('Balance'), $account->getBalanceInCents());
    $properties->setActionList($actions);

    $payment_methods = $this->buildPaymentMethodsSection($account);
    $purchase_history = $this->buildPurchaseHistorySection($account);
    $charge_history = $this->buildChargeHistorySection($account);
    $account_history = $this->buildAccountHistorySection($account);

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
        $account_history,
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
      ->setHeader(pht('Payment Methods'))
      ->addActionLink(
        id(new PHUIButtonView())
          ->setTag('a')
          ->setHref($this->getApplicationURI($id.'/card/new/'))
          ->setText(pht('Add Payment Method'))
          ->setIcon(id(new PHUIIconView())->setIconFont('fa-plus')));

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
      $item->setImageURI($provider->getPaymentMethodIcon());

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
          PhortuneCart::STATUS_PURCHASED,
        ))
      ->execute();

    $rows = array();
    $rowc = array();
    foreach ($carts as $cart) {
      $cart_link = phutil_tag(
        'a',
        array(
          'href' => $this->getApplicationURI('cart/'.$cart->getID().'/'),
        ),
        pht('Cart %d', $cart->getID()));

      $rowc[] = 'highlighted';
      $rows[] = array(
        phutil_tag('strong', array(), $cart_link),
        '',
        '',
      );
      foreach ($cart->getPurchases() as $purchase) {
        $id = $purchase->getID();

        $price = $purchase->getTotalPriceInCents();
        $price = PhortuneCurrency::newFromUSDCents($price)->formatForDisplay();

        $purchase_link = phutil_tag(
          'a',
          array(
            'href' => $this->getApplicationURI('purchase/'.$id.'/'),
          ),
          $purchase->getFullDisplayName());

        $rowc[] = '';
        $rows[] = array(
          '',
          $purchase_link,
          $price,
        );
      }
    }

    $table = id(new AphrontTableView($rows))
      ->setRowClasses($rowc)
      ->setHeaders(
        array(
          pht('Cart'),
          pht('Purchase'),
          pht('Amount'),
        ))
      ->setColumnClasses(
        array(
          '',
          'wide',
          'right',
        ));

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Purchase History'));

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
      ->execute();

    return $this->buildChargesTable($charges);
  }

  private function buildAccountHistorySection(PhortuneAccount $account) {
    $request = $this->getRequest();
    $user = $request->getUser();

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Account History'));

    $xactions = id(new PhortuneAccountTransactionQuery())
      ->setViewer($user)
      ->withObjectPHIDs(array($account->getPHID()))
      ->execute();

    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($user);

    $xaction_view = id(new PhabricatorApplicationTransactionView())
      ->setUser($user)
      ->setObjectPHID($account->getPHID())
      ->setTransactions($xactions)
      ->setMarkupEngine($engine);

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header);

    return array(
      $box,
      $xaction_view,
    );
  }

}
