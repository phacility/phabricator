<?php

final class PhabricatorPeopleHovercardEngineExtension
  extends PhabricatorHovercardEngineExtension {

  const EXTENSIONKEY = 'people';

  public function isExtensionEnabled() {
    return true;
  }

  public function getExtensionName() {
    return pht('User Accounts');
  }

  public function canRenderObjectHovercard($object) {
    return ($object instanceof PhabricatorUser);
  }

  public function willRenderHovercards(array $objects) {
    $viewer = $this->getViewer();
    $phids = mpull($objects, 'getPHID');

    $users = id(new PhabricatorPeopleQuery())
      ->setViewer($viewer)
      ->withPHIDs($phids)
      ->needAvailability(true)
      ->needProfile(true)
      ->needBadges(true)
      ->execute();
    $users = mpull($users, null, 'getPHID');

    return array(
      'users' => $users,
    );
  }

  public function renderHovercard(
    PhabricatorHovercardView $hovercard,
    PhabricatorObjectHandle $handle,
    $object,
    $data) {
    $viewer = $this->getViewer();

    $user = idx($data['users'], $object->getPHID());
    if (!$user) {
      return;
    }

    $hovercard->setTitle($user->getUsername());

    $profile = $user->getUserProfile();
    $detail = $user->getRealName();
    if ($profile->getTitle()) {
      $detail .= ' - '.$profile->getTitle();
    }
    $hovercard->setDetail($detail);

    if ($user->getIsDisabled()) {
      $hovercard->addField(pht('Account'), pht('Disabled'));
    } else if (!$user->isUserActivated()) {
      $hovercard->addField(pht('Account'), pht('Not Activated'));
    } else if (PhabricatorApplication::isClassInstalledForViewer(
        'PhabricatorCalendarApplication',
        $viewer)) {
      $hovercard->addField(
        pht('Status'),
        $user->getAvailabilityDescription($viewer));
    }

    $hovercard->addField(
      pht('User Since'),
      phabricator_date($user->getDateCreated(), $viewer));

    if ($profile->getBlurb()) {
      $hovercard->addField(pht('Blurb'),
        id(new PhutilUTF8StringTruncator())
        ->setMaximumGlyphs(120)
        ->truncateString($profile->getBlurb()));
    }

    $badges = $this->buildBadges($user, $viewer);
    foreach ($badges as $badge) {
      $hovercard->addBadge($badge);
    }
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
