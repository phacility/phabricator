<?php

final class PhortuneAccountOrdersController
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
    $order_history = $this->newRecentOrdersView($account, 100);

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

}
