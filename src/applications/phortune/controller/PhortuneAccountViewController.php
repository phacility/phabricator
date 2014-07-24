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
    $user = $request->getUser();

    $id = $account->getID();

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Payment Methods'))
      ->addActionLink(
        id(new PHUIButtonView())
          ->setTag('a')
          ->setHref($this->getApplicationURI($id.'/paymentmethod/edit/'))
          ->setText(pht('Add Payment Method'))
          ->setIcon(id(new PHUIIconView())->setIconFont('fa-plus')));

    $list = id(new PHUIObjectItemListView())
      ->setUser($user)
      ->setNoDataString(
        pht('No payment methods associated with this account.'));

    $methods = id(new PhortunePaymentMethodQuery())
      ->setViewer($user)
      ->withAccountPHIDs(array($account->getPHID()))
      ->withStatus(PhortunePaymentMethodQuery::STATUS_OPEN)
      ->execute();

    if ($methods) {
      $this->loadHandles(mpull($methods, 'getAuthorPHID'));
    }

    foreach ($methods as $method) {
      $item = new PHUIObjectItemView();
      $item->setHeader($method->getBrand().' / '.$method->getLastFourDigits());

      switch ($method->getStatus()) {
        case PhortunePaymentMethod::STATUS_ACTIVE:
          $item->addAttribute(pht('Active'));
          $item->setBarColor('green');
          break;
      }

      $item->addAttribute(
        pht(
          'Added %s by %s',
          phabricator_datetime($method->getDateCreated(), $user),
          $this->getHandle($method->getAuthorPHID())->renderLink()));

      $list->addItem($item);
    }

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->appendChild($list);
  }

  private function buildPurchaseHistorySection(PhortuneAccount $account) {
    $request = $this->getRequest();
    $user = $request->getUser();

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Purchase History'));

    return id(new PHUIObjectBoxView())
      ->setHeader($header);
  }

  private function buildChargeHistorySection(PhortuneAccount $account) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $charges = id(new PhortuneChargeQuery())
      ->setViewer($viewer)
      ->withAccountPHIDs(array($account->getPHID()))
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
