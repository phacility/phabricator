<?php

final class PhortuneAccountSubscriptionController
  extends PhortuneAccountProfileController {

  public function handleRequest(AphrontRequest $request) {
    $response = $this->loadAccount();
    if ($response) {
      return $response;
    }

    $account = $this->getAccount();
    $title = $account->getName();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Subscriptions'));

    $header = $this->buildHeaderView();
    $subscriptions = $this->buildSubscriptionsSection($account);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(
        $subscriptions,
      ));

    $navigation = $this->buildSideNavView('subscriptions');

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setNavigation($navigation)
      ->appendChild($view);

  }

  private function buildSubscriptionsSection(PhortuneAccount $account) {
    $viewer = $this->getViewer();

    $subscriptions = id(new PhortuneSubscriptionQuery())
      ->setViewer($viewer)
      ->withAccountPHIDs(array($account->getPHID()))
      ->setLimit(25)
      ->execute();

    $handles = $this->loadViewerHandles(mpull($subscriptions, 'getPHID'));

    $table = id(new PhortuneSubscriptionTableView())
      ->setUser($viewer)
      ->setHandles($handles)
      ->setSubscriptions($subscriptions);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Subscriptions'));

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setTable($table);
  }

}
