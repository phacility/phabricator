<?php

/**
 * This class adds a "Land this" button to revision view.
 */
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
    if ($object instanceof DifferentialRevision) {
      $this->renderRevisionAction($event);
    }
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
      $viewer = $event->getUser();
      $action = $strategy->createMenuItem($viewer, $revision, $repository);
      if ($action == null) {
        continue;
      }
      if ($strategy->isActionDisabled($viewer, $revision, $repository)) {
        $action->setDisabled(true);
      }
      $this->addActionMenuItems($event, $action);
    }
  }

}
