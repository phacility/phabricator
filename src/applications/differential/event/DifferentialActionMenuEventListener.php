<?php

final class DifferentialActionMenuEventListener
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
    if ($object instanceof PhabricatorUser) {
      $actions = $this->renderUserItems($event);
    } else if ($object instanceof ManiphestTask) {
      $actions = $this->renderTaskItems($event);
    }

    $this->addActionMenuItems($event, $actions);
  }

  private function renderUserItems(PhutilEvent $event) {
    if (!$this->canUseApplication($event->getUser())) {
      return null;
    }

    $person = $event->getValue('object');
    $href = '/differential/?authorPHIDs[]='.$person->getPHID();

    return id(new PhabricatorActionView())
      ->setRenderAsForm(true)
      ->setIcon('differential-dark')
      ->setIconSheet(PHUIIconView::SPRITE_APPS)
      ->setName(pht('View Revisions'))
      ->setHref($href);
  }

  private function renderTaskItems(PhutilEvent $event) {
    if (!$this->canUseApplication($event->getUser())) {
      return null;
    }

    $task = $event->getValue('object');
    $phid = $task->getPHID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $event->getUser(),
      $task,
      PhabricatorPolicyCapability::CAN_EDIT);

    return id(new PhabricatorActionView())
      ->setName(pht('Edit Differential Revisions'))
      ->setHref("/search/attach/{$phid}/DREV/")
      ->setWorkflow(true)
      ->setIcon('attach')
      ->setDisabled(!$can_edit)
      ->setWorkflow(true);
  }

}

