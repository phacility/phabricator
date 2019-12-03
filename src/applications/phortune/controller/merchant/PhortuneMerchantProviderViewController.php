<?php

final class PhortuneMerchantProviderViewController
  extends PhortuneMerchantController {

  protected function shouldRequireMerchantEditCapability() {
    return true;
  }

  protected function handleMerchantRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $merchant = $this->getMerchant();

    $provider = id(new PhortunePaymentProviderConfigQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('providerID')))
      ->executeOne();
    if (!$provider) {
      return new Aphront404Response();
    }

    $provider_type = $provider->buildProvider();

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb($merchant->getName(), $merchant->getURI())
      ->addTextCrumb(
        pht('Payment Providers'),
        $merchant->getPaymentProvidersURI())
      ->addTextCrumb($provider->getObjectName())
      ->setBorder(true);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Payment Provider: %s', $provider_type->getName()));

    $details = $this->newDetailsView($provider);

    $timeline = $this->buildTransactionTimeline(
      $provider,
      new PhortunePaymentProviderConfigTransactionQuery());
    $timeline->setShouldTerminate(true);

    $curtain = $this->buildCurtainView($provider);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(
        array(
          $details,
          $timeline,
        ));

    return $this->newPage()
      ->setTitle($provider->getObjectName())
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  private function buildCurtainView(PhortunePaymentProviderConfig $provider) {
    $viewer = $this->getViewer();
    $merchant = $this->getMerchant();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $provider,
      PhabricatorPolicyCapability::CAN_EDIT);

    $edit_uri = $this->getApplicationURI(
      urisprintf(
        'merchant/%d/providers/edit/%d/',
        $merchant->getID(),
        $provider->getID()));

    $disable_uri = $this->getApplicationURI(
      urisprintf(
        'merchant/%d/providers/%d/disable/',
        $merchant->getID(),
        $provider->getID()));

    $curtain = $this->newCurtainView($provider);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Provider'))
        ->setIcon('fa-pencil')
        ->setHref($edit_uri)
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $provider_type = $provider->buildProvider();

    if ($provider_type->isEnabled()) {
      $disable_icon = 'fa-times';
      $disable_name = pht('Disable Provider');
    } else {
      $disable_icon = 'fa-check';
      $disable_name = pht('Enable Provider');
    }

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName($disable_name)
        ->setIcon($disable_icon)
        ->setHref($disable_uri)
        ->setDisabled(!$can_edit)
        ->setWorkflow(true));

    return $curtain;
  }

  private function newDetailsView(PhortunePaymentProviderConfig $provider) {
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer);

    $provider_type = $provider->buildProvider();

    $view->addProperty(pht('Provider Type'), $provider_type->getName());

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Payment Provider Details'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->addPropertyList($view);
  }

}
