<?php

final class PhortuneAccountPaymentMethodController
  extends PhortuneAccountProfileController {

  protected function shouldRequireAccountEditCapability() {
    return false;
  }

  protected function handleAccountRequest(AphrontRequest $request) {
    $account = $this->getAccount();
    $title = $account->getName();

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb(pht('Payment Methods'))
      ->setBorder(true);

    $authority = $this->newAccountAuthorityView();
    $header = $this->buildHeaderView();
    $methods = $this->buildPaymentMethodsSection($account);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(
        array(
          $authority,
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

    $add = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('Add Payment Method'))
      ->setIcon('fa-plus')
      ->setHref($this->getApplicationURI("account/{$id}/methods/new/"))
      ->setDisabled(!$can_edit)
      ->setWorkflow(!$can_edit);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Payment Methods'))
      ->addActionLink($add);

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

      $item = id(new PHUIObjectItemView())
        ->setObjectName($method->getObjectName())
        ->setHeader($method->getFullDisplayName())
        ->setHref($method->getURI());

      $provider = $method->buildPaymentProvider();
      $item->addAttribute($provider->getPaymentMethodProviderDescription());

      $list->addItem($item);
    }

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setObjectList($list);
  }

}
