<?php

final class PhortuneAccountPaymentMethodsController
  extends PhortuneAccountProfileController {

  public function handleRequest(AphrontRequest $request) {
    $response = $this->loadAccount();
    if ($response) {
      return $response;
    }

    $account = $this->getAccount();
    $title = $account->getName();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Payment Methods'));

    $header = $this->buildHeaderView();
    $methods = $this->buildPaymentMethodsSection($account);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(
        array(
          $methods,
        ));

    $navigation = $this->buildSideNavView('methods');

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setNavigation($navigation)
      ->appendChild($view);

  }

  private function buildPaymentMethodsSection(PhortuneAccount $account) {
    $viewer = $this->getViewer();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $account,
      PhabricatorPolicyCapability::CAN_EDIT);

    $id = $account->getID();

    // TODO: Allow adding a card here directly
    $add = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('New Payment Method'))
      ->setIcon('fa-plus')
      ->setHref($this->getApplicationURI("{$id}/card/new/"));

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Payment Methods'));

    $list = id(new PHUIObjectItemListView())
      ->setUser($viewer)
      ->setFlush(true)
      ->setNoDataString(
        pht('There are no payment methods associated with this account.'));

    $methods = id(new PhortunePaymentMethodQuery())
      ->setViewer($viewer)
      ->withAccountPHIDs(array($account->getPHID()))
      ->withStatuses(
        array(
          PhortunePaymentMethod::STATUS_ACTIVE,
        ))
      ->execute();

    foreach ($methods as $method) {
      $id = $method->getID();

      $item = new PHUIObjectItemView();
      $item->setHeader($method->getFullDisplayName());

      switch ($method->getStatus()) {
        case PhortunePaymentMethod::STATUS_ACTIVE:
          $item->setStatusIcon('fa-check green');

          $disable_uri = $this->getApplicationURI('card/'.$id.'/disable/');
          $item->addAction(
            id(new PHUIListItemView())
              ->setIcon('fa-times')
              ->setHref($disable_uri)
              ->setDisabled(!$can_edit)
              ->setWorkflow(true));
          break;
        case PhortunePaymentMethod::STATUS_DISABLED:
          $item->setStatusIcon('fa-ban lightbluetext');
          $item->setDisabled(true);
          break;
      }

      $provider = $method->buildPaymentProvider();
      $item->addAttribute($provider->getPaymentMethodProviderDescription());

      $edit_uri = $this->getApplicationURI('card/'.$id.'/edit/');

      $item->addAction(
        id(new PHUIListItemView())
          ->setIcon('fa-pencil')
          ->setHref($edit_uri)
          ->setDisabled(!$can_edit)
          ->setWorkflow(!$can_edit));

      $list->addItem($item);
    }

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setObjectList($list);
  }

}
