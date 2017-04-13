<?php

final class PhortuneAccountManagerController
  extends PhortuneAccountProfileController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    // TODO: Currently, you must be able to edit an account to view the detail
    // page, because the account must be broadly visible so merchants can
    // process orders but merchants should not be able to see all the details
    // of an account. Ideally this page should be visible to merchants, too,
    // just with less information.
    $can_edit = true;

    $account = id(new PhortuneAccountQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$account) {
      return new Aphront404Response();
    }

    $this->setAccount($account);
    $title = $account->getName();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Managers'));

    $header = $this->buildHeaderView();
    $members = $this->buildMembersSection($account);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(
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
      ->setText(pht('New Manager'))
      ->setIcon('fa-plus')
      ->setWorkflow(true)
      ->setHref("/phortune/account/manager/add/{$id}/");

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
