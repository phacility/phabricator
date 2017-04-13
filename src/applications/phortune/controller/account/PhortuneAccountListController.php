<?php

final class PhortuneAccountListController extends PhortuneController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

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
      ->needProfileImage(true)
      ->execute();

    $title = pht('Accounts');

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Accounts'));
    $crumbs->setBorder(true);

    $payment_list = id(new PHUIObjectItemListView())
      ->setUser($viewer)
      ->setNoDataString(
        pht(
          'You are not a member of any payment accounts. Payment '.
          'accounts are used to make purchases.'));

    foreach ($accounts as $account) {
      $item = id(new PHUIObjectItemView())
        ->setSubhead(pht('Account %d', $account->getID()))
        ->setHeader($account->getName())
        ->setHref($this->getApplicationURI($account->getID().'/'))
        ->setObject($account)
        ->setImageIcon('fa-user-circle');

      $payment_list->addItem($item);
    }

    $payment_header = id(new PHUIHeaderView())
      ->setHeader(pht('Payment Accounts'))
      ->addActionLink(
        id(new PHUIButtonView())
          ->setTag('a')
          ->setHref($this->getApplicationURI('account/edit/'))
          ->setIcon('fa-plus')
          ->setText(pht('Create Account')));

    $payment_box = id(new PHUIObjectBoxView())
      ->setHeader($payment_header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setObjectList($payment_list);

    $merchant_list = id(new PHUIObjectItemListView())
      ->setUser($viewer)
      ->setNoDataString(
        pht(
          'You do not control any merchant accounts. Merchant accounts are '.
          'used to receive payments.'));

    foreach ($merchants as $merchant) {
      $item = id(new PHUIObjectItemView())
        ->setSubhead(pht('Merchant %d', $merchant->getID()))
        ->setHeader($merchant->getName())
        ->setHref($this->getApplicationURI('/merchant/'.$merchant->getID().'/'))
        ->setObject($merchant)
        ->setImageURI($merchant->getProfileImageURI());

      $merchant_list->addItem($item);
    }

    $merchant_header = id(new PHUIHeaderView())
      ->setHeader(pht('Merchant Accounts'))
      ->addActionLink(
        id(new PHUIButtonView())
          ->setTag('a')
          ->setHref($this->getApplicationURI('merchant/'))
          ->setIcon('fa-list')
          ->setText(pht('View All Merchants')));

    $merchant_box = id(new PHUIObjectBoxView())
      ->setHeader($merchant_header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setObjectList($merchant_list);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Accounts'));

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(
        $payment_box,
        $merchant_box,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);

  }

}
