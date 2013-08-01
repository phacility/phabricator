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
    $actions = $event->getValue('actions');

    $action = id(new PhabricatorActionView())
      ->setIcon('maniphest-dark')
      ->setIconSheet(PHUIIconView::SPRITE_APPS)
      ->setName(pht('View Tasks'));

    $object = $event->getValue('object');
    if ($object instanceof PhabricatorUser) {
      $href = '/maniphest/view/action/?users='.$object->getPHID();
      $actions[] = $action->setHref($href);
    } else if ($object instanceof PhabricatorProject) {
      $href = '/maniphest/view/all/?projects='.$object->getPHID();
      $actions[] = $action->setHref($href);
    }

    $event->setValue('actions', $actions);
  }

}
