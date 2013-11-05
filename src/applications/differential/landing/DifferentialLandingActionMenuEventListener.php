<?php

final class DifferentialLandingActionMenuEventListener
  extends PhabricatorEventListener {

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

  private function handleActionsEvent(PhutilEvent $event) {
    $object = $event->getValue('object');

    $actions = null;
    if ($object instanceof DifferentialRevision) {
      $actions = $this->renderRevisionAction($event);
    }

    $this->addActionMenuItems($event, $actions);
  }

  private function renderRevisionAction(PhutilEvent $event) {
    if (!$this->canUseApplication($event->getUser())) {
      return null;
    }

    $revision = $event->getValue('object');

    $repository = $revision->getRepository();
    if ($repository === null) {
      return null;
    }

    $strategies = id(new PhutilSymbolLoader())
      ->setAncestorClass('DifferentialLandingStrategy')
      ->loadObjects();
    foreach ($strategies as $strategy) {
      $actions = $strategy->createMenuItems(
          $event->getUser(),
          $revision,
          $repository);
      $this->addActionMenuItems($event, $actions);
    }
  }

}

