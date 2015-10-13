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

    if ($repository->canPerformAutomation()) {
      $revision_id = $revision->getID();

      $action = id(new PhabricatorActionView())
        ->setWorkflow(true)
        ->setName(pht('Land Revision'))
        ->setIcon('fa-fighter-jet')
        ->setHref("/differential/revision/operation/{$revision_id}/");

      $this->addActionMenuItems($event, $action);
    }

    $strategies = id(new PhutilClassMapQuery())
      ->setAncestorClass('DifferentialLandingStrategy')
      ->execute();

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
