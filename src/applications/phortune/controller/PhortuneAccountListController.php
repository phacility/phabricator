<?php

final class PhortuneAccountListController extends PhortuneController {

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $accounts = id(new PhortuneAccountQuery())
      ->setViewer($viewer)
      ->withMemberPHIDs(array($viewer->getPHID()))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->execute();

    $merchants = id(new PhortuneMerchantQuery())
      ->setViewer($viewer)
      ->withMemberPHIDs(array($viewer->getPHID()))
      ->execute();

    $title = pht('Accounts');

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Accounts'));

    $payment_list = id(new PHUIObjectItemListView())
      ->setStackable(true)
      ->setUser($viewer)
      ->setNoDataString(
        pht(
          'You are not a member of any payment accounts. Payment '.
          'accounts are used to make purchases.'));

    foreach ($accounts as $account) {
      $item = id(new PHUIObjectItemView())
        ->setObjectName(pht('Account %d', $account->getID()))
        ->setHeader($account->getName())
        ->setHref($this->getApplicationURI($account->getID().'/'))
        ->setObject($account);

      $payment_list->addItem($item);
    }

    $payment_header = id(new PHUIHeaderView())
      ->setHeader(pht('Payment Accounts'))
      ->addActionLink(
        id(new PHUIButtonView())
          ->setTag('a')
          ->setHref($this->getApplicationURI('account/edit/'))
          ->setIcon(
            id(new PHUIIconView())
              ->setIconFont('fa-plus'))
          ->setText(pht('Create Account')));

    $payment_box = id(new PHUIObjectBoxView())
      ->setHeader($payment_header)
      ->appendChild($payment_list);

    $merchant_list = id(new PHUIObjectItemListView())
      ->setStackable(true)
      ->setUser($viewer)
      ->setNoDataString(
        pht(
          'You do not control any merchant accounts. Merchant accounts are '.
          'used to receive payments.'));

    foreach ($merchants as $merchant) {
      $item = id(new PHUIObjectItemView())
        ->setObjectName(pht('Merchant %d', $merchant->getID()))
        ->setHeader($merchant->getName())
        ->setHref($this->getApplicationURI('/merchant/'.$merchant->getID().'/'))
        ->setObject($merchant);

      $merchant_list->addItem($item);
    }

    $merchant_header = id(new PHUIHeaderView())
      ->setHeader(pht('Merchant Accounts'))
      ->addActionLink(
        id(new PHUIButtonView())
          ->setTag('a')
          ->setHref($this->getApplicationURI('merchant/'))
          ->setIcon(
            id(new PHUIIconView())
              ->setIconFont('fa-list'))
          ->setText(pht('View All Merchants')));

    $merchant_box = id(new PHUIObjectBoxView())
      ->setHeader($merchant_header)
      ->appendChild($merchant_list);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $payment_box,
        $merchant_box,
      ),
      array(
        'title' => $title,
      ));
  }

}
