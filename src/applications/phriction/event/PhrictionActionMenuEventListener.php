<?php

final class PhrictionActionMenuEventListener extends PhutilEventListener {

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
      ->setIcon('phriction-dark')
      ->setIconSheet(PHUIIconView::SPRITE_APPS)
      ->setName(pht('View Wiki'));

    $object = $event->getValue('object');
    if ($object instanceof PhabricatorProject) {
      $slug = PhabricatorSlug::normalize($object->getPhrictionSlug());
      $href = '/w/projects/'.$slug;
      $actions[] = $action->setHref($href);
    }

    $event->setValue('actions', $actions);
  }

}
