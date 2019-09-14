<?php

final class PhortuneMerchantDetailsController
  extends PhortuneMerchantProfileController {

  protected function shouldRequireMerchantEditCapability() {
    return true;
  }

  protected function handleMerchantRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $merchant = $this->getMerchant();

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb(pht('Account Details'))
      ->setBorder(true);
    $header = $this->buildHeaderView();

    $title = pht(
      '%s %s',
      $merchant->getObjectName(),
      $merchant->getName());

    $details = $this->buildDetailsView($merchant);
    $curtain = $this->buildCurtainView($merchant);

    $timeline = $this->buildTransactionTimeline(
      $merchant,
      new PhortuneMerchantTransactionQuery());
    $timeline->setShouldTerminate(true);

    $navigation = $this->buildSideNavView('details');

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(array(
        $details,
        $timeline,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setNavigation($navigation)
      ->appendChild($view);
  }

  private function buildDetailsView(PhortuneMerchant $merchant) {
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($merchant);

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
        pht('Contact Information'),
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
        ->setIcon('fa-picture-o')
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit)
        ->setHref($this->getApplicationURI("merchant/{$id}/picture/edit/")));

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
      ->setHeaderText(pht('Managers'))
      ->appendChild($member_list);

    return $curtain;
  }

}
