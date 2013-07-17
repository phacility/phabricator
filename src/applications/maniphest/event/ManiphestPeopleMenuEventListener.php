<?php

final class ManiphestPeopleMenuEventListener extends PhutilEventListener {

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

    $href = '/maniphest/view/action/?users='.$person->getPHID();

    $actions = $event->getValue('actions');

    $actions[] = id(new PhabricatorActionView())
      ->setIcon('maniphest-dark')
      ->setIconSheet(PHUIIconView::SPRITE_APPS)
      ->setName(pht('View Tasks'))
      ->setHref($href);

    $event->setValue('actions', $actions);
  }

}
