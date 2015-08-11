<?php

final class PhabricatorPeopleHovercardEventListener
  extends PhabricatorEventListener {

  public function register() {
    $this->listen(PhabricatorEventType::TYPE_UI_DIDRENDERHOVERCARD);
  }

  public function handleEvent(PhutilEvent $event) {
    switch ($event->getType()) {
      case PhabricatorEventType::TYPE_UI_DIDRENDERHOVERCARD:
        $this->handleHovercardEvent($event);
      break;
    }
  }

  private function handleHovercardEvent($event) {
    $viewer = $event->getUser();
    $hovercard = $event->getValue('hovercard');
    $object_handle = $event->getValue('handle');
    $phid = $object_handle->getPHID();
    $user = $event->getValue('object');

    if (!($user instanceof PhabricatorUser)) {
      return;
    }

    // Reload to get availability.
    $user = id(new PhabricatorPeopleQuery())
      ->setViewer($viewer)
      ->withIDs(array($user->getID()))
      ->needAvailability(true)
      ->needProfile(true)
      ->needBadges(true)
      ->executeOne();

    $hovercard->setTitle($user->getUsername());
    $profile = $user->getUserProfile();
    $detail = $user->getRealName();
    if ($profile->getTitle()) {
      $detail .= ' - '.$profile->getTitle().'.';
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

    $event->setValue('hovercard', $hovercard);
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
