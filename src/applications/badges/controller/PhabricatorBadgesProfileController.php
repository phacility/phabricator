<?php

abstract class PhabricatorBadgesProfileController
  extends PhabricatorController {

  private $badge;

  public function setBadge(PhabricatorBadgesBadge $badge) {
    $this->badge = $badge;
    return $this;
  }

  public function getBadge() {
    return $this->badge;
  }

  public function buildApplicationMenu() {
    return $this->buildSideNavView()->getMenu();
  }

  protected function buildHeaderView() {
    $viewer = $this->getViewer();
    $badge = $this->getBadge();
    $id = $badge->getID();

    if ($badge->isArchived()) {
      $status_icon = 'fa-ban';
      $status_color = 'dark';
    } else {
      $status_icon = 'fa-check';
      $status_color = 'bluegrey';
    }
    $status_name = idx(
      PhabricatorBadgesBadge::getStatusNameMap(),
      $badge->getStatus());

    return id(new PHUIHeaderView())
      ->setHeader($badge->getName())
      ->setUser($viewer)
      ->setPolicyObject($badge)
      ->setStatus($status_icon, $status_color, $status_name)
      ->setHeaderIcon('fa-trophy');
  }

  protected function buildApplicationCrumbs() {
    $badge = $this->getBadge();
    $id = $badge->getID();
    $badge_uri = $this->getApplicationURI("/view/{$id}/");

    $crumbs = parent::buildApplicationCrumbs();
    $crumbs->addTextCrumb($badge->getName(), $badge_uri);
    $crumbs->setBorder(true);
    return $crumbs;
  }

  protected function buildSideNavView($filter = null) {
    $viewer = $this->getViewer();
    $badge = $this->getBadge();
    $id = $badge->getID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $badge,
      PhabricatorPolicyCapability::CAN_EDIT);

    $nav = id(new AphrontSideNavFilterView())
      ->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $nav->addLabel(pht('Badge'));

    $nav->addFilter(
      'view',
      pht('View Badge'),
      $this->getApplicationURI("/view/{$id}/"),
      'fa-trophy');

    $nav->addFilter(
      'recipients',
      pht('View Recipients'),
      $this->getApplicationURI("/recipients/{$id}/"),
      'fa-group');

    $nav->selectFilter($filter);

    return $nav;
  }

}
