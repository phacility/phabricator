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
    $viewer = $event->getUser();

    if (!$this->canUseApplication($viewer)) {
      return null;
    }

    $revision = $event->getValue('object');

    $repository = $revision->getRepository();
    if ($repository === null) {
      return null;
    }

    if ($repository->canPerformAutomation()) {
      $revision_id = $revision->getID();

      $op = new DrydockLandRepositoryOperation();
      $barrier = $op->getBarrierToLanding($viewer, $revision);

      if ($barrier) {
        $can_land = false;
      } else {
        $can_land = true;
      }

      $action = id(new PhabricatorActionView())
        ->setName(pht('Land Revision'))
        ->setIcon('fa-fighter-jet')
        ->setHref("/differential/revision/operation/{$revision_id}/")
        ->setWorkflow(true)
        ->setDisabled(!$can_land);


      $this->addActionMenuItems($event, $action);
    }

    $strategies = id(new PhutilClassMapQuery())
      ->setAncestorClass('DifferentialLandingStrategy')
      ->execute();

    foreach ($strategies as $strategy) {
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
