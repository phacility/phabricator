<?php

final class AuditPeopleMenuEventListener extends PhutilEventListener {

  public function register() {
    $this->listen(PhabricatorEventType::TYPE_UI_DIDRENDERACTIONS);
  }

  public function handleEvent(PhutilEvent $event) {
    switch ($event->getType()) {
      case PhabricatorEventType::TYPE_UI_DIDRENDERACTIONS:
        $this->handleActionsEvent($event);
      break;
    }
  }

  private function handleActionsEvent($event) {
    $person = $event->getValue('object');
    if (!($person instanceof PhabricatorUser)) {
      return;
    }

    $actions = $event->getValue('actions');

    $username = phutil_escape_uri($person->getUsername());
    $href = '/audit/view/author/'.$username.'/';

    $actions[] = id(new PhabricatorActionView())
      ->setIcon('audit-dark')
      ->setIconSheet(PHUIIconView::SPRITE_APPS)
      ->setName(pht('View Commits'))
      ->setHref($href);

    $event->setValue('actions', $actions);
  }

}

