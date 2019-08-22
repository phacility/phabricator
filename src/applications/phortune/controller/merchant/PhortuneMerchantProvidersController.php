<?php

final class PhortuneMerchantProvidersController
  extends PhortuneMerchantProfileController {

  protected function shouldRequireMerchantEditCapability() {
    return true;
  }

  protected function handleMerchantRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $merchant = $this->getMerchant();

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb(pht('Payment Providers'))
      ->setBorder(true);

    $header = $this->buildHeaderView();

    $title = pht(
      '%s %s',
      $merchant->getObjectName(),
      $merchant->getName());

    $providers = id(new PhortunePaymentProviderConfigQuery())
      ->setViewer($viewer)
      ->withMerchantPHIDs(array($merchant->getPHID()))
      ->execute();

    $provider_list = $this->buildProviderList(
      $merchant,
      $providers);

    $navigation = $this->buildSideNavView('providers');

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(
        array(
          $provider_list,
        ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setNavigation($navigation)
      ->appendChild($view);
  }

  private function buildProviderList(
    PhortuneMerchant $merchant,
    array $providers) {

    $viewer = $this->getRequest()->getUser();
    $id = $merchant->getID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $merchant,
      PhabricatorPolicyCapability::CAN_EDIT);

    $provider_list = id(new PHUIObjectItemListView())
      ->setNoDataString(pht('This merchant has no payment providers.'));

    foreach ($providers as $provider_config) {
      $provider = $provider_config->buildProvider();
      $provider_id = $provider_config->getID();

      $item = id(new PHUIObjectItemView())
        ->setObjectName($provider_config->getObjectName())
        ->setHeader($provider->getName())
        ->setHref($provider_config->getURI());

      if ($provider->isEnabled()) {
        if ($provider->isAcceptingLivePayments()) {
          $item->setStatusIcon('fa-check green');
        } else {
          $item->setStatusIcon('fa-warning yellow');
          $item->addIcon('fa-exclamation-triangle', pht('Test Mode'));
        }

        $item->addAttribute($provider->getConfigureProvidesDescription());
      } else {
        $item->setDisabled(true);
        $item->addAttribute(
          phutil_tag('em', array(), pht('This payment provider is disabled.')));
      }

      $provider_list->addItem($item);
    }

    $add_uri = urisprintf(
      'merchant/%d/providers/edit/',
      $merchant->getID());
    $add_uri = $this->getApplicationURI($add_uri);

    $add_action = id(new PHUIButtonView())
      ->setTag('a')
      ->setHref($add_uri)
      ->setText(pht('Add Payment Provider'))
      ->setDisabled(!$can_edit)
      ->setWorkflow(!$can_edit)
      ->setIcon('fa-plus');

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Payment Providers'))
      ->addActionLink($add_action);

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setObjectList($provider_list);
  }


}
