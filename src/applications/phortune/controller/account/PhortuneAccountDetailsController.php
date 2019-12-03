<?php

final class PhortuneAccountDetailsController
  extends PhortuneAccountProfileController {

  protected function shouldRequireAccountEditCapability() {
    return true;
  }

  protected function handleAccountRequest(AphrontRequest $request) {
    $account = $this->getAccount();
    $title = $account->getName();

    $viewer = $this->getViewer();

    $invoices = id(new PhortuneCartQuery())
      ->setViewer($viewer)
      ->withAccountPHIDs(array($account->getPHID()))
      ->needPurchases(true)
      ->withInvoices(true)
      ->execute();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->setBorder(true);

    $header = $this->buildHeaderView();

    $authority = $this->newAccountAuthorityView();
    $details = $this->newDetailsView($account);

    $curtain = $this->buildCurtainView($account);

    $timeline = $this->buildTransactionTimeline(
      $account,
      new PhortuneAccountTransactionQuery());
    $timeline->setShouldTerminate(true);


    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(
        array(
          $authority,
          $details,
          $timeline,
        ));

    $navigation = $this->buildSideNavView('details');

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setNavigation($navigation)
      ->appendChild($view);

  }

  private function buildCurtainView(PhortuneAccount $account) {
    $viewer = $this->getViewer();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $account,
      PhabricatorPolicyCapability::CAN_EDIT);

    $edit_uri = $this->getApplicationURI('account/edit/'.$account->getID().'/');

    $curtain = $this->newCurtainView($account);
    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Account'))
        ->setIcon('fa-pencil')
        ->setHref($edit_uri)
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $member_phids = $account->getMemberPHIDs();
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

    $merchant_list = id(new PHUIObjectItemListView())
      ->setSimple(true)
      ->setNoDataString(pht('No purchase history.'));

    $merchant_phids = $account->getMerchantPHIDs();
    $handles = $viewer->loadHandles($merchant_phids);

    foreach ($merchant_phids as $merchant_phid) {
      $handle = $handles[$merchant_phid];

      $merchant = id(new PHUIObjectItemView())
        ->setImageURI($handle->getImageURI())
        ->setHref($handle->getURI())
        ->setHeader($handle->getFullName());

      $merchant_list->addItem($merchant);
    }

    $curtain->newPanel()
      ->setHeaderText(pht('Merchants'))
      ->appendChild($merchant_list);

    return $curtain;
  }

  private function newDetailsView(PhortuneAccount $account) {
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer);

    $view->addProperty(pht('Account Name'), $account->getName());

    $display_name = $account->getBillingName();
    if (!strlen($display_name)) {
      $display_name = phutil_tag('em', array(), pht('None'));
    }

    $display_address = $account->getBillingAddress();
    if (!strlen($display_address)) {
      $display_address = phutil_tag('em', array(), pht('None'));
    } else {
      $display_address = phutil_escape_html_newlines($display_address);
    }

    $view->addProperty(pht('Billing Name'), $display_name);
    $view->addProperty(pht('Billing Address'), $display_address);

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Account Details'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->addPropertyList($view);
  }

}
