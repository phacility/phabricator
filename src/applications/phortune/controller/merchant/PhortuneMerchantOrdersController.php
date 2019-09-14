<?php

final class PhortuneMerchantOrdersController
  extends PhortuneMerchantProfileController {

  protected function shouldRequireMerchantEditCapability() {
    return true;
  }

  protected function handleMerchantRequest(AphrontRequest $request) {
    $merchant = $this->getMerchant();
    $title = $merchant->getName();

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb(pht('Orders'))
      ->setBorder(true);

    $header = $this->buildHeaderView();
    $order_history = $this->newRecentOrdersView($merchant, 100);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(
        array(
          $order_history,
        ));

    $navigation = $this->buildSideNavView('orders');

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setNavigation($navigation)
      ->appendChild($view);
  }

  private function newRecentOrdersView(
    PhortuneMerchant $merchant,
    $limit) {

    $viewer = $this->getViewer();

    $carts = id(new PhortuneCartQuery())
      ->setViewer($viewer)
      ->withMerchantPHIDs(array($merchant->getPHID()))
      ->needPurchases(true)
      ->withStatuses(
        array(
          PhortuneCart::STATUS_PURCHASING,
          PhortuneCart::STATUS_CHARGED,
          PhortuneCart::STATUS_HOLD,
          PhortuneCart::STATUS_REVIEW,
          PhortuneCart::STATUS_PURCHASED,
        ))
      ->setLimit($limit)
      ->execute();

    $orders_uri = $merchant->getOrderListURI();

    $table = id(new PhortuneOrderTableView())
      ->setUser($viewer)
      ->setCarts($carts);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Recent Orders'))
      ->addActionLink(
        id(new PHUIButtonView())
          ->setTag('a')
          ->setIcon('fa-list')
          ->setHref($orders_uri)
          ->setText(pht('View All Orders')));

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setTable($table);
  }
}
