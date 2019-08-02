<?php

final class PhortuneAccountChargesController
  extends PhortuneAccountProfileController {

  public function handleRequest(AphrontRequest $request) {
    $response = $this->loadAccount();
    if ($response) {
      return $response;
    }

    $account = $this->getAccount();
    $title = $account->getName();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Order History'));

    $header = $this->buildHeaderView();
    $charge_history = $this->buildChargeHistorySection($account);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(
        array(
          $charge_history,
        ));

    $navigation = $this->buildSideNavView('charges');

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setNavigation($navigation)
      ->appendChild($view);
  }

  private function buildChargeHistorySection(PhortuneAccount $account) {
    $viewer = $this->getViewer();

    $charges = id(new PhortuneChargeQuery())
      ->setViewer($viewer)
      ->withAccountPHIDs(array($account->getPHID()))
      ->needCarts(true)
      ->setLimit(100)
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

}
