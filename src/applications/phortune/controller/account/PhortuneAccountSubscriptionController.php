<?php

final class PhortuneAccountSubscriptionController
  extends PhortuneAccountProfileController {

  protected function shouldRequireAccountEditCapability() {
    return false;
  }

  protected function handleAccountRequest(AphrontRequest $request) {
    $account = $this->getAccount();
    $title = $account->getName();

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb(pht('Subscriptions'))
      ->setBorder(true);

    $header = $this->buildHeaderView();
    $authority = $this->newAccountAuthorityView();

    $subscriptions = $this->buildSubscriptionsSection($account);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(
        array(
          $authority,
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

    $table = id(new PhortuneSubscriptionTableView())
      ->setUser($viewer)
      ->setSubscriptions($subscriptions);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Subscriptions'));

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setTable($table);
  }

}
