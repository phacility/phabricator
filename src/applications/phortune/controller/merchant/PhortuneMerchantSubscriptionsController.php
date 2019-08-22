<?php

final class PhortuneMerchantSubscriptionsController
  extends PhortuneMerchantProfileController {

  protected function shouldRequireMerchantEditCapability() {
    return true;
  }

  protected function handleMerchantRequest(AphrontRequest $request) {
    $merchant = $this->getMerchant();
    $title = $merchant->getName();

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb(pht('Subscriptions'))
      ->setBorder(true);

    $header = $this->buildHeaderView();

    $subscriptions = $this->buildSubscriptionsSection($merchant);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(
        array(
          $subscriptions,
        ));

    $navigation = $this->buildSideNavView('subscriptions');

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setNavigation($navigation)
      ->appendChild($view);
  }

  private function buildSubscriptionsSection(PhortuneMerchant $merchant) {
    $viewer = $this->getViewer();

    $subscriptions = id(new PhortuneSubscriptionQuery())
      ->setViewer($viewer)
      ->withMerchantPHIDs(array($merchant->getPHID()))
      ->setLimit(25)
      ->execute();

    $subscriptions_uri = $merchant->getSubscriptionListURI();

    $table = id(new PhortuneSubscriptionTableView())
      ->setUser($viewer)
      ->setSubscriptions($subscriptions);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Subscriptions'))
      ->addActionLink(
        id(new PHUIButtonView())
          ->setTag('a')
          ->setIcon('fa-list')
          ->setHref($subscriptions_uri)
          ->setText(pht('View All Subscriptions')));

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setTable($table);
  }

}
