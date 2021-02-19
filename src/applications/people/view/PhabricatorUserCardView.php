<?php

final class PhabricatorUserCardView extends AphrontTagView {

  private $profile;
  private $viewer;
  private $tag;
  private $isExiled;

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
    $classes[] = 'people-card-view';

    if ($this->profile->getIsDisabled()) {
      $classes[] = 'project-card-disabled';
    }

    return array(
      'class' => implode(' ', $classes),
    );
  }

  public function setIsExiled($is_exiled) {
    $this->isExiled = $is_exiled;
    return $this;
  }

  public function getIsExiled() {
    return $this->isExiled;
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

    $classes = array();
    if ($user->getIsDisabled()) {
      $tag_icon = 'fa-ban';
      $tag_title = pht('Disabled');
      $tag_shade = PHUITagView::COLOR_RED;
      $classes[] = 'phui-image-disabled';
    } else if (!$user->getIsApproved()) {
      $tag_icon = 'fa-ban';
      $tag_title = pht('Unapproved Account');
      $tag_shade = PHUITagView::COLOR_RED;
    } else if (!$user->getIsEmailVerified()) {
      $tag_icon = 'fa-envelope';
      $tag_title = pht('Email Not Verified');
      $tag_shade = PHUITagView::COLOR_VIOLET;
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
      $tag->setColor($tag_shade);
    }

    $body = array();

    /* TODO: Replace with Conpherence Availability if we ship it */
    $body[] = $this->addItem(
      'fa-user-plus',
      phabricator_date($user->getDateCreated(), $viewer));

    $has_calendar = PhabricatorApplication::isClassInstalledForViewer(
      'PhabricatorCalendarApplication',
      $viewer);
    if ($has_calendar) {
      if (!$user->getIsDisabled()) {
        $body[] = $this->addItem(
          'fa-calendar-o',
          id(new PHUIUserAvailabilityView())
            ->setViewer($viewer)
            ->setAvailableUser($user));
      }
    }

    if ($this->getIsExiled()) {
      $body[] = $this->addItem(
        'fa-eye-slash red',
        pht('This user can not see this object.'),
        array(
          'project-card-item-exiled',
        ));
    }

    $classes[] = 'project-card-image';
    $image = phutil_tag(
      'img',
      array(
        'src' => $picture,
        'class' => implode(' ', $classes),
      ));

    $href = urisprintf(
      '/p/%s/',
      $user->getUsername());

    $image = phutil_tag(
      'a',
      array(
        'href' => $href,
        'class' => 'project-card-image-href',
      ),
      $image);

    $name = phutil_tag_div('project-card-name',
      $user->getRealname());
    $username = phutil_tag_div('project-card-username',
      '@'.$user->getUsername());
    $tag = phutil_tag_div('phui-header-subheader',
      $tag);

    $header = phutil_tag(
      'div',
      array(
        'class' => 'project-card-header',
      ),
      array(
        $name,
        $username,
        $tag,
        $body,
      ));

    $card = phutil_tag(
      'div',
      array(
        'class' => 'project-card-inner',
      ),
      array(
        $header,
        $image,
      ));

    return $card;
  }

  private function addItem($icon, $value, $classes = array()) {
    $classes[] = 'project-card-item';

    $icon = id(new PHUIIconView())
      ->addClass('project-card-item-icon')
      ->setIcon($icon);

    $text = phutil_tag(
      'span',
      array(
        'class' => 'project-card-item-text',
      ),
      $value);

    return phutil_tag(
      'div',
      array(
        'class' => implode(' ', $classes),
      ),
      array($icon, $text));
  }

}
