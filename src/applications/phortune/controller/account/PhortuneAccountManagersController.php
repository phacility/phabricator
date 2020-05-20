<?php

final class PhortuneAccountManagersController
  extends PhortuneAccountProfileController {

  protected function shouldRequireAccountEditCapability() {
    return false;
  }

  protected function handleAccountRequest(AphrontRequest $request) {
    $account = $this->getAccount();
    $title = $account->getName();

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb(pht('Managers'))
      ->setBorder(true);

    $header = $this->buildHeaderView();
    $authority = $this->newAccountAuthorityView();
    $members = $this->buildMembersSection($account);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(
        array(
          $authority,
          $members,
        ));

    $navigation = $this->buildSideNavView('managers');

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setNavigation($navigation)
      ->appendChild($view);

  }

  private function buildMembersSection(PhortuneAccount $account) {
    $viewer = $this->getViewer();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $account,
      PhabricatorPolicyCapability::CAN_EDIT);

    $id = $account->getID();

    $add = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('Add Managers'))
      ->setIcon('fa-plus')
      ->setWorkflow(true)
      ->setDisabled(!$can_edit)
      ->setHref("/phortune/account/{$id}/managers/add/");

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Account Managers'))
      ->addActionLink($add);

    $list = id(new PHUIObjectItemListView())
      ->setUser($viewer);

    $member_phids = $account->getMemberPHIDs();
    $handles = $viewer->loadHandles($member_phids);

    foreach ($member_phids as $member_phid) {
      $image_uri = $handles[$member_phid]->getImageURI();
      $image_href = $handles[$member_phid]->getURI();
      $person = $handles[$member_phid];

      $member = id(new PHUIObjectItemView())
        ->setImageURI($image_uri)
        ->setHref($image_href)
        ->setHeader($person->getFullName())
        ->addAttribute(pht('Account Manager'));

      $list->addItem($member);
    }

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setObjectList($list);
  }

}
