<?php

final class PhortuneMerchantOverviewController
  extends PhortuneMerchantProfileController {

  protected function shouldRequireMerchantEditCapability() {
    return false;
  }

  protected function handleMerchantRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $merchant = $this->getMerchant();

    $crumbs = $this->buildApplicationCrumbs()
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

    $details = $this->buildDetailsView($merchant, $providers);
    $navigation = $this->buildSideNavView('overview');

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(
        array(
          $details,
        ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setNavigation($navigation)
      ->appendChild($view);
  }

  private function buildDetailsView(
    PhortuneMerchant $merchant,
    array $providers) {

    $viewer = $this->getRequest()->getUser();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($merchant);

    $status_view = new PHUIStatusListView();

    $have_any = false;
    $any_test = false;
    foreach ($providers as $provider_config) {
      $provider = $provider_config->buildProvider();
      if ($provider->isEnabled()) {
        $have_any = true;
      }
      if (!$provider->isAcceptingLivePayments()) {
        $any_test = true;
      }
    }

    if ($have_any) {
      $status_view->addItem(
        id(new PHUIStatusItemView())
          ->setIcon(PHUIStatusItemView::ICON_ACCEPT, 'green')
          ->setTarget(pht('Accepts Payments'))
          ->setNote(pht('This merchant can accept payments.')));

      if ($any_test) {
        $status_view->addItem(
          id(new PHUIStatusItemView())
            ->setIcon(PHUIStatusItemView::ICON_WARNING, 'yellow')
            ->setTarget(pht('Test Mode'))
            ->setNote(pht('This merchant is accepting test payments.')));
      } else {
        $status_view->addItem(
          id(new PHUIStatusItemView())
          ->setIcon(PHUIStatusItemView::ICON_ACCEPT, 'green')
            ->setTarget(pht('Live Mode'))
            ->setNote(pht('This merchant is accepting live payments.')));
      }
    } else if ($providers) {
      $status_view->addItem(
        id(new PHUIStatusItemView())
          ->setIcon(PHUIStatusItemView::ICON_REJECT, 'red')
          ->setTarget(pht('No Enabled Providers'))
          ->setNote(
            pht(
              'All of the payment providers for this merchant are '.
              'disabled.')));
    } else {
      $status_view->addItem(
        id(new PHUIStatusItemView())
          ->setIcon(PHUIStatusItemView::ICON_WARNING, 'yellow')
          ->setTarget(pht('No Providers'))
          ->setNote(
            pht(
              'This merchant does not have any payment providers configured '.
              'yet, so it can not accept payments. Add a provider.')));
    }

    $view->addProperty(pht('Status'), $status_view);

    $description = $merchant->getDescription();
    if (strlen($description)) {
      $description = new PHUIRemarkupView($viewer, $description);
      $view->addSectionHeader(
        pht('Description'),
        PHUIPropertyListView::ICON_SUMMARY);
      $view->addTextContent($description);
    }

    $contact_info = $merchant->getContactInfo();
    if (strlen($contact_info)) {
      $contact_info = new PHUIRemarkupView($viewer, $contact_info);
      $view->addSectionHeader(
        pht('Contact Information'),
        PHUIPropertyListView::ICON_SUMMARY);
      $view->addTextContent($contact_info);
    }

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Details'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($view);
  }

}
