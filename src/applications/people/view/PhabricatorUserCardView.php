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

    // We don't have a ton of room on the hovercard, so we're trying to show
    // the most important tag. Users can click through to the profile to get
    // more details.

    if ($user->getIsDisabled()) {
      $tag_icon = 'fa-ban';
      $tag_title = pht('Disabled');
      $tag_shade = PHUITagView::COLOR_RED;
    } else if (!$user->getIsApproved()) {
      $tag_icon = 'fa-ban';
      $tag_title = pht('Unapproved Account');
      $tag_shade = PHUITagView::COLOR_RED;
    } else if (!$user->getIsEmailVerified()) {
      $tag_icon = 'fa-envelope';
      $tag_title = pht('Email Not Verified');
      $tag_shade = PHUITagView::COLOR_RED;
    } else if ($user->getIsAdmin()) {
      $tag_icon = 'fa-star';
      $tag_title = pht('Administrator');
      $tag_shade = PHUITagView::COLOR_INDIGO;
    } else {
      $tag_icon = PhabricatorPeopleIconSet::getIconIcon($profile->getIcon());
      $tag_title = $profile->getDisplayTitle();
      $tag_shade = null;
    }

    $tag = id(new PHUITagView())
      ->setIcon($tag_icon)
      ->setName($tag_title)
      ->setType(PHUITagView::TYPE_SHADE);

    if ($tag_shade !== null) {
      $tag->setShade($tag_shade);
    }

    $header = id(new PHUIHeaderView())
      ->setHeader($user->getFullName())
      ->addTag($tag)
      ->setUser($viewer)
      ->setImage($picture);

    $body = array();

    $body[] = $this->addItem(
      pht('User Since'),
      phabricator_date($user->getDateCreated(), $viewer));

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
