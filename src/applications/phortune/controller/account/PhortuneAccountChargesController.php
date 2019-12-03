<?php

final class PhortuneAccountChargesController
  extends PhortuneAccountProfileController {

  protected function shouldRequireAccountEditCapability() {
    return false;
  }

  protected function handleAccountRequest(AphrontRequest $request) {
    $account = $this->getAccount();
    $title = $account->getName();

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb(pht('Orders'))
      ->setBorder(true);

    $header = $this->buildHeaderView();
    $authority = $this->newAccountAuthorityView();
    $charge_history = $this->buildChargeHistorySection($account);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(
        array(
          $authority,
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

    $charges_uri = $account->getChargeListURI();

    $table = id(new PhortuneChargeTableView())
      ->setUser($viewer)
      ->setCharges($charges);

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
