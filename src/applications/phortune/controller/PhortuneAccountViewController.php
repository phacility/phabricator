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

    $properties = id(new PhabricatorPropertyListView())
      ->setObject($account)
      ->setUser($user);

    $properties->addProperty(pht('Balance'), $account->getBalanceInCents());

    $payment_methods = $this->buildPaymentMethodsSection($account);
    $account_history = $this->buildAccountHistorySection($account);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $header,
        $actions,
        $properties,
        $payment_methods,
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

    return array(
      $header,
      $actions,
      $list,
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
