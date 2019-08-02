<?php

final class PhortuneAccountEmailAddressesController
  extends PhortuneAccountProfileController {

  public function handleRequest(AphrontRequest $request) {
    $response = $this->loadAccount();
    if ($response) {
      return $response;
    }

    $account = $this->getAccount();
    $title = $account->getName();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Email Addresses'));

    $header = $this->buildHeaderView();
    $addresses = $this->buildAddressesSection($account);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(
        array(
          $addresses,
        ));

    $navigation = $this->buildSideNavView('addresses');

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setNavigation($navigation)
      ->appendChild($view);
  }

  private function buildAddressesSection(PhortuneAccount $account) {
    $viewer = $this->getViewer();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $account,
      PhabricatorPolicyCapability::CAN_EDIT);

    $id = $account->getID();

    $add = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('Add Address'))
      ->setIcon('fa-plus')
      ->setWorkflow(!$can_edit)
      ->setDisabled(!$can_edit)
      ->setHref("/phortune/account/{$id}/addresses/edit/");

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Billing Email Addresses'))
      ->addActionLink($add);

    $addresses = id(new PhortuneAccountEmailQuery())
      ->setViewer($viewer)
      ->withAccountPHIDs(array($account->getPHID()))
      ->execute();

    $list = id(new PHUIObjectItemListView())
      ->setUser($viewer)
      ->setNoDataString(
        pht(
          'There are no billing email addresses associated '.
          'with this account.'));

    $addresses = id(new PhortuneAccountEmailQuery())
      ->setViewer($viewer)
      ->withAccountPHIDs(array($account->getPHID()))
      ->execute();
    foreach ($addresses as $address) {
      $item = id(new PHUIObjectItemView())
        ->setObjectName($address->getObjectName())
        ->setHeader($address->getAddress())
        ->setHref($address->getURI());

      $list->addItem($item);
    }

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setObjectList($list);
  }

}
