<?php

final class PhortuneMerchantManagersController
  extends PhortuneMerchantProfileController {

  protected function shouldRequireMerchantEditCapability() {
    return false;
  }

  protected function handleMerchantRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $merchant = $this->getMerchant();

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb(pht('Managers'))
      ->setBorder(true);

    $header = $this->buildHeaderView();
    $members = $this->buildMembersSection($merchant);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(
        array(
          $members,
        ));

    $navigation = $this->buildSideNavView('managers');

    return $this->newPage()
      ->setTitle(pht('Managers'))
      ->setCrumbs($crumbs)
      ->setNavigation($navigation)
      ->appendChild($view);
  }

  private function buildMembersSection(PhortuneMerchant $merchant) {
    $viewer = $this->getViewer();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $merchant,
      PhabricatorPolicyCapability::CAN_EDIT);

    $id = $merchant->getID();

    $add_uri = urisprintf(
      'merchant/%d/managers/new/',
      $merchant->getID());
    $add_uri = $this->getApplicationURI($add_uri);

    $add = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('New Manager'))
      ->setIcon('fa-plus')
      ->setWorkflow(true)
      ->setDisabled(!$can_edit)
      ->setHref($add_uri);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Merchant Account Managers'))
      ->addActionLink($add);

    $list = id(new PHUIObjectItemListView())
      ->setUser($viewer);

    $member_phids = $merchant->getMemberPHIDs();
    $handles = $viewer->loadHandles($member_phids);

    foreach ($member_phids as $member_phid) {
      $image_uri = $handles[$member_phid]->getImageURI();
      $image_href = $handles[$member_phid]->getURI();
      $person = $handles[$member_phid];

      $member = id(new PHUIObjectItemView())
        ->setImageURI($image_uri)
        ->setHref($image_href)
        ->setHeader($person->getFullName())
        ->addAttribute(pht('Merchant Manager'));

      $list->addItem($member);
    }

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setObjectList($list);
  }

}
