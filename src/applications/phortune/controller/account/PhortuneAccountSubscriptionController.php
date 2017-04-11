<?php

final class PhortuneAccountSubscriptionController
  extends PhortuneAccountProfileController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    // TODO: Currently, you must be able to edit an account to view the detail
    // page, because the account must be broadly visible so merchants can
    // process orders but merchants should not be able to see all the details
    // of an account. Ideally this page should be visible to merchants, too,
    // just with less information.
    $can_edit = true;

    $account = id(new PhortuneAccountQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$account) {
      return new Aphront404Response();
    }

    $this->setAccount($account);
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
