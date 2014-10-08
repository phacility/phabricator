<?php

final class ConpherenceActionMenuEventListener
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
    }

    $this->addActionMenuItems($event, $actions);
  }

  private function renderUserItems(PhutilEvent $event) {
    if (!$this->canUseApplication($event->getUser())) {
      return null;
    }

    $user = $event->getValue('object');
    $href = '/conpherence/new/?participant='.$user->getPHID();

    return id(new PhabricatorActionView())
      ->setIcon('fa-envelope')
      ->setName(pht('Send Message'))
      ->setWorkflow(true)
      ->setHref($href);
  }

}
