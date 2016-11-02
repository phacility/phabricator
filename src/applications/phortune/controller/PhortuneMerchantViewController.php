<?php

final class PhortuneMerchantViewController
  extends PhortuneMerchantController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $merchant = id(new PhortuneMerchantQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needProfileImage(true)
      ->executeOne();
    if (!$merchant) {
      return new Aphront404Response();
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($merchant->getName());
    $crumbs->setBorder(true);

    $title = pht(
      'Merchant %d %s',
      $merchant->getID(),
      $merchant->getName());

    $header = id(new PHUIHeaderView())
      ->setHeader($merchant->getName())
      ->setUser($viewer)
      ->setPolicyObject($merchant)
      ->setImage($merchant->getProfileImageURI());

    $providers = id(new PhortunePaymentProviderConfigQuery())
      ->setViewer($viewer)
      ->withMerchantPHIDs(array($merchant->getPHID()))
      ->execute();

    $details = $this->buildDetailsView($merchant, $providers);
    $curtain = $this->buildCurtainView($merchant);

    $provider_list = $this->buildProviderList(
      $merchant,
      $providers);

    $timeline = $this->buildTransactionTimeline(
      $merchant,
      new PhortuneMerchantTransactionQuery());
    $timeline->setShouldTerminate(true);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(array(
        $details,
        $provider_list,
        $timeline,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
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

    $invoice_from = $merchant->getInvoiceEmail();
    if (!$invoice_from) {
      $invoice_from = pht('No email address set');
      $invoice_from = phutil_tag('em', array(), $invoice_from);
    }
    $view->addProperty(pht('Invoice From'), $invoice_from);

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
        pht('Contact Info'),
        PHUIPropertyListView::ICON_SUMMARY);
      $view->addTextContent($contact_info);
    }

    $footer_info = $merchant->getInvoiceFooter();
    if (strlen($footer_info)) {
      $footer_info = new PHUIRemarkupView($viewer, $footer_info);
      $view->addSectionHeader(
        pht('Invoice Footer'),
        PHUIPropertyListView::ICON_SUMMARY);
      $view->addTextContent($footer_info);
    }

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Details'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($view);
  }

  private function buildCurtainView(PhortuneMerchant $merchant) {
    $viewer = $this->getRequest()->getUser();
    $id = $merchant->getID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $merchant,
      PhabricatorPolicyCapability::CAN_EDIT);

    $curtain = $this->newCurtainView($merchant);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Merchant'))
        ->setIcon('fa-pencil')
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit)
        ->setHref($this->getApplicationURI("merchant/edit/{$id}/")));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Logo'))
        ->setIcon('fa-camera')
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit)
        ->setHref($this->getApplicationURI("merchant/picture/{$id}/")));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('View Orders'))
        ->setIcon('fa-shopping-cart')
        ->setHref($this->getApplicationURI("merchant/orders/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('View Subscriptions'))
        ->setIcon('fa-moon-o')
        ->setHref($this->getApplicationURI("merchant/{$id}/subscription/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('New Invoice'))
        ->setIcon('fa-fax')
        ->setHref($this->getApplicationURI("merchant/{$id}/invoice/new/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $member_phids = $merchant->getMemberPHIDs();
    $handles = $viewer->loadHandles($member_phids);

    $member_list = id(new PHUIObjectItemListView())
      ->setSimple(true);

    foreach ($member_phids as $member_phid) {
      $image_uri = $handles[$member_phid]->getImageURI();
      $image_href = $handles[$member_phid]->getURI();
      $person = $handles[$member_phid];

      $member = id(new PHUIObjectItemView())
        ->setImageURI($image_uri)
        ->setHref($image_href)
        ->setHeader($person->getFullName());

      $member_list->addItem($member);
    }

    $curtain->newPanel()
      ->setHeaderText(pht('Members'))
      ->appendChild($member_list);

    return $curtain;
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
      ->setFlush(true)
      ->setNoDataString(pht('This merchant has no payment providers.'));

    foreach ($providers as $provider_config) {
      $provider = $provider_config->buildProvider();
      $provider_id = $provider_config->getID();

      $item = id(new PHUIObjectItemView())
        ->setHeader($provider->getName());

      if ($provider->isEnabled()) {
        if ($provider->isAcceptingLivePayments()) {
          $item->setStatusIcon('fa-check green');
        } else {
          $item->setStatusIcon('fa-warning yellow');
          $item->addIcon('fa-exclamation-triangle', pht('Test Mode'));
        }

        $item->addAttribute($provider->getConfigureProvidesDescription());
      } else {
        // Don't show disabled providers to users who can't manage the merchant
        // account.
        if (!$can_edit) {
          continue;
        }
        $item->setDisabled(true);
        $item->addAttribute(
          phutil_tag('em', array(), pht('This payment provider is disabled.')));
      }


      if ($can_edit) {
        $edit_uri = $this->getApplicationURI(
          "/provider/edit/{$provider_id}/");
        $disable_uri = $this->getApplicationURI(
          "/provider/disable/{$provider_id}/");

        if ($provider->isEnabled()) {
          $disable_icon = 'fa-times';
          $disable_name = pht('Disable');
        } else {
          $disable_icon = 'fa-check';
          $disable_name = pht('Enable');
        }

        $item->addAction(
          id(new PHUIListItemView())
            ->setIcon($disable_icon)
            ->setHref($disable_uri)
            ->setName($disable_name)
            ->setWorkflow(true));

        $item->addAction(
          id(new PHUIListItemView())
            ->setIcon('fa-pencil')
            ->setHref($edit_uri)
            ->setName(pht('Edit')));
      }

      $provider_list->addItem($item);
    }

    $add_action = id(new PHUIButtonView())
      ->setTag('a')
      ->setHref($this->getApplicationURI('provider/edit/?merchantID='.$id))
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
