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

    $profile = $user->loadUserProfile();

    $hovercard->setTitle($user->getUsername());
    $hovercard->setDetail(pht('%s - %s.', $user->getRealname(),
      nonempty($profile->getTitle(),
        pht('No title was found befitting of this rare specimen'))));

    if ($user->getIsDisabled()) {
      $hovercard->addField(pht('Account'), pht('Disabled'));
    } else if (!$user->isUserActivated()) {
      $hovercard->addField(pht('Account'), pht('Not Activated'));
    } else if (PhabricatorApplication::isClassInstalledForViewer(
        'PhabricatorApplicationCalendar',
        $viewer)) {
      $statuses = id(new PhabricatorCalendarEvent())->loadCurrentStatuses(
        array($user->getPHID()));
      if ($statuses) {
        $current_status = reset($statuses);
        $dateto = phabricator_datetime($current_status->getDateTo(), $user);
        $hovercard->addField(pht('Status'),
          $current_status->getDescription());
        $hovercard->addField(pht('Until'),
          $dateto);
      } else {
        $hovercard->addField(pht('Status'), pht('Available'));
      }
    }

    $hovercard->addField(pht('User since'),
      phabricator_date($user->getDateCreated(), $user));

    if ($profile->getBlurb()) {
      $hovercard->addField(pht('Blurb'),
        phutil_utf8_shorten($profile->getBlurb(), 120));
    }

    $event->setValue('hovercard', $hovercard);
  }


}
