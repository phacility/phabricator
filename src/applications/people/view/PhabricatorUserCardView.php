<?php

final class PhabricatorUserCardView extends AphrontTagView {

  private $profile;
  private $viewer;
  private $tag;

  public function setProfile(PhabricatorUser $profile) {
    $this->profile = $profile;
    return $this;
  }

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function setTag($tag) {
    $this->tag = $tag;
    return $this;
  }

  protected function getTagName() {
    if ($this->tag) {
      return $this->tag;
    }
    return 'div';
  }

  protected function getTagAttributes() {
    $classes = array();
    $classes[] = 'project-card-view';

    if ($this->profile->getIsDisabled()) {
      $classes[] = 'project-card-grey';
    } else {
      $classes[] = 'project-card-blue';
    }

    return array(
      'class' => implode($classes, ' '),
    );
  }

  protected function getTagContent() {

    $user = $this->profile;
    $profile = $user->loadUserProfile();
    $picture = $user->getProfileImageURI();
    $viewer = $this->viewer;

    require_celerity_resource('project-card-view-css');

    $profile_icon = PhabricatorPeopleIconSet::getIconIcon($profile->getIcon());
    $profile_title = $profile->getDisplayTitle();

    $tag = id(new PHUITagView())
      ->setIcon($profile_icon)
      ->setName($profile_title)
      ->addClass('project-view-header-tag')
      ->setType(PHUITagView::TYPE_SHADE);

    $header = id(new PHUIHeaderView())
      ->setHeader(array($user->getFullName(), $tag))
      ->setUser($viewer)
      ->setImage($picture);

    $body = array();

    $body[] = $this->addItem(
      pht('User Since'),
      phabricator_date($profile->getDateCreated(), $viewer));

    if (PhabricatorApplication::isClassInstalledForViewer(
        'PhabricatorCalendarApplication',
        $viewer)) {
      $body[] = $this->addItem(
        pht('Availability'),
        id(new PHUIUserAvailabilityView())
          ->setViewer($viewer)
          ->setAvailableUser($user));
    }

    $badges = $this->buildBadges($user, $viewer);
    if ($badges) {
      $badges = id(new PHUIBadgeBoxView())
        ->addItems($badges)
        ->setCollapsed(true);
      $body[] = phutil_tag(
        'div',
        array(
          'class' => 'phui-hovercard-body-item hovercard-badges',
        ),
        $badges);
    }

    $body = phutil_tag(
      'div',
      array(
        'class' => 'project-card-body',
      ),
      $body);

    $card = phutil_tag(
      'div',
      array(
        'class' => 'project-card-inner',
      ),
      array(
        $header,
        $body,
      ));

    return $card;
  }

  private function addItem($label, $value) {
    $item = array(
      phutil_tag('strong', array(), $label),
      ': ',
      phutil_tag('span', array(), $value),
    );
    return phutil_tag_div('project-card-item', $item);
  }

  private function buildBadges(
    PhabricatorUser $user,
    $viewer) {

    $class = 'PhabricatorBadgesApplication';
    $items = array();

    if (PhabricatorApplication::isClassInstalledForViewer($class, $viewer)) {
      $badge_phids = $user->getBadgePHIDs();
      if ($badge_phids) {
        $badges = id(new PhabricatorBadgesQuery())
          ->setViewer($viewer)
          ->withPHIDs($badge_phids)
          ->withStatuses(array(PhabricatorBadgesBadge::STATUS_ACTIVE))
          ->execute();

        foreach ($badges as $badge) {
          $items[] = id(new PHUIBadgeMiniView())
            ->setIcon($badge->getIcon())
            ->setHeader($badge->getName())
            ->setQuality($badge->getQuality());
        }
      }
    }
    return $items;
  }

}
