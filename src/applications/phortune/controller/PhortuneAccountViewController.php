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
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Account'))
        ->setHref($request->getRequestURI()));

    $header = id(new PhabricatorHeaderView())
      ->setHeader($title);

    $actions = id(new PhabricatorActionListView())
      ->setUser($user)
      ->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Edit Account'))
          ->setIcon('edit')
          ->setHref('#')
          ->setDisabled(true))
      ->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Edit Members'))
          ->setIcon('transcript')
          ->setHref('#')
          ->setDisabled(true));

    $crumbs->setActionList($actions);

    $properties = id(new PhabricatorPropertyListView())
      ->setObject($account)
      ->setUser($user);

    $properties->addProperty(pht('Balance'), $account->getBalanceInCents());

    $payment_methods = $this->buildPaymentMethodsSection($account);
    $purchase_history = $this->buildPurchaseHistorySection($account);
    $account_history = $this->buildAccountHistorySection($account);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $header,
        $actions,
        $properties,
        $payment_methods,
        $purchase_history,
        $account_history,
      ),
      array(
        'title' => $title,
        'device' => true,
        'dust' => true,
      ));
  }

  private function buildPaymentMethodsSection(PhortuneAccount $account) {
    $request = $this->getRequest();
    $user = $request->getUser();

    $header = id(new PhabricatorHeaderView())
      ->setHeader(pht('Payment Methods'));

    $id = $account->getID();
    $add_uri = $this->getApplicationURI($id.'/paymentmethod/edit/');

    $actions = id(new PhabricatorActionListView())
      ->setUser($user)
      ->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Add Payment Method'))
          ->setIcon('new')
          ->setHref($add_uri));

    $list = id(new PhabricatorObjectItemListView())
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
      $item = new PhabricatorObjectItemView();
      $item->setHeader($method->getName());

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

      if ($method->getExpiresEpoch() < time() + (60 * 60 * 24 * 30)) {
        $item->addAttribute(pht('Expires Soon!'));
      }

      $list->addItem($item);
    }

    return array(
      $header,
      $actions,
      $list,
    );
  }

  private function buildPurchaseHistorySection(PhortuneAccount $account) {
    $request = $this->getRequest();
    $user = $request->getUser();

    $header = id(new PhabricatorHeaderView())
      ->setHeader(pht('Purchase History'));

    return array(
      $header,

    );
  }

  private function buildAccountHistorySection(PhortuneAccount $account) {
    $request = $this->getRequest();
    $user = $request->getUser();

    $header = id(new PhabricatorHeaderView())
      ->setHeader(pht('Account History'));

    $xactions = id(new PhortuneAccountTransactionQuery())
      ->setViewer($user)
      ->withObjectPHIDs(array($account->getPHID()))
      ->execute();

    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($user);

    $xaction_view = id(new PhabricatorApplicationTransactionView())
      ->setUser($user)
      ->setTransactions($xactions)
      ->setMarkupEngine($engine);

    return array(
      $header,
      $xaction_view,
    );
  }

}
