<?php

final class PhabricatorPeopleProfileBadgesController
  extends PhabricatorPeopleProfileController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');

    $user = id(new PhabricatorPeopleQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needProfileImage(true)
      ->executeOne();
    if (!$user) {
      return new Aphront404Response();
    }

    $class = 'PhabricatorBadgesApplication';
    if (!PhabricatorApplication::isClassInstalledForViewer($class, $viewer)) {
      return new Aphront404Response();
    }

    $this->setUser($user);
    $title = array(pht('Badges'), $user->getUsername());
    $header = $this->buildProfileHeader();
    $badges = $this->buildBadgesView($user);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Badges'));
    $crumbs->setBorder(true);

    $nav = $this->getProfileMenu();
    $nav->selectFilter(PhabricatorPeopleProfileMenuEngine::ITEM_BADGES);

    // Best option?
    $badges = id(new PhabricatorBadgesQuery())
      ->setViewer($viewer)
      ->withStatuses(array(
        PhabricatorBadgesBadge::STATUS_ACTIVE,
      ))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->setLimit(1)
      ->execute();

    $button = id(new PHUIButtonView())
      ->setTag('a')
      ->setIcon('fa-plus')
      ->setText(pht('Award Badge'))
      ->setWorkflow(true)
      ->setHref('/badges/award/'.$user->getID().'/');

    if ($badges) {
      $header->addActionLink($button);
    }

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->addClass('project-view-home')
      ->addClass('project-view-people-home')
      ->setFooter(array(
        $this->buildBadgesView($user)
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setNavigation($nav)
      ->appendChild($view);
  }

  private function buildBadgesView(PhabricatorUser $user) {
    $viewer = $this->getViewer();
    $request = $this->getRequest();

    $pager = id(new AphrontCursorPagerView())
      ->readFromRequest($request);

    $query = id(new PhabricatorBadgesAwardQuery())
      ->setViewer($viewer)
      ->withRecipientPHIDs(array($user->getPHID()))
      ->withBadgeStatuses(array(PhabricatorBadgesBadge::STATUS_ACTIVE));

    $awards = $query->executeWithCursorPager($pager);

    if ($awards) {
      $flex = new PHUIBadgeBoxView();
      foreach ($awards as $award) {
        $badge = $award->getBadge();

        $awarder_info = array();

        $awarder_phid = $award->getAwarderPHID();
        $awarder_handle = $viewer->renderHandle($awarder_phid);
        $awarded_date = phabricator_date($award->getDateCreated(), $viewer);

        $awarder_info = pht(
          'Awarded by %s',
          $awarder_handle->render());

        $item = id(new PHUIBadgeView())
          ->setIcon($badge->getIcon())
          ->setHeader($badge->getName())
          ->setSubhead($badge->getFlavor())
          ->setQuality($badge->getQuality())
          ->setHref($badge->getViewURI())
          ->addByLine($awarder_info)
          ->addByLine($awarded_date);

        $flex->addItem($item);
      }
    } else {
      $flex = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_NOTICE)
        ->appendChild(pht('User has not been awarded any badges.'));
    }

    return array(
      $flex,
      $pager,
    );
  }
}
