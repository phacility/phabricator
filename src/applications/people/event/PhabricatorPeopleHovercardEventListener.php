<?php

final class PhabricatorPeopleHovercardEventListener
  extends PhutilEventListener {

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

    $hovercard->addField(pht('User since'),
      phabricator_date($user->getDateCreated(), $user));

    if ($user->getIsDisabled()) {
      $hovercard->addTag(id(new PhabricatorTagView())
        ->setBackgroundColor(PhabricatorTagView::COLOR_BLACK)
        ->setName(pht('Disabled'))
        ->setType(PhabricatorTagView::TYPE_STATE));
    } else {
      $statuses = id(new PhabricatorUserStatus())->loadCurrentStatuses(
        array($user->getPHID()));
      if ($statuses) {
        $current_status = reset($statuses);
        $hovercard->addField(pht('Status'),
          $current_status->getDescription());
        $hovercard->addTag(id(new PhabricatorTagView())
          ->setName($current_status->getHumanStatus())
          ->setBackgroundColor(PhabricatorTagView::COLOR_BLUE)
          ->setType(PhabricatorTagView::TYPE_STATE));
      } else {
        $hovercard->addField(pht('Status'), pht('Available'));
      }
    }

    if ($profile->getBlurb()) {
      $hovercard->addField(pht('Blurb'),
        phutil_utf8_shorten($profile->getBlurb(), 120));
    }

    $event->setValue('hovercard', $hovercard);
  }


}
