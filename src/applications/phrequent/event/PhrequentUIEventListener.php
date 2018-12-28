<?php

final class PhrequentUIEventListener
  extends PhabricatorEventListener {

  public function register() {
    $this->listen(PhabricatorEventType::TYPE_UI_DIDRENDERACTIONS);
  }

  public function handleEvent(PhutilEvent $event) {
    switch ($event->getType()) {
      case PhabricatorEventType::TYPE_UI_DIDRENDERACTIONS:
        $this->handleActionEvent($event);
        break;
    }
  }

  private function handleActionEvent($event) {
    $user = $event->getUser();
    $object = $event->getValue('object');

    if (!$object || !$object->getPHID()) {
      // No object, or the object has no PHID yet..
      return;
    }

    if (!($object instanceof PhrequentTrackableInterface)) {
      // This object isn't a time trackable object.
      return;
    }

    if (!$this->canUseApplication($event->getUser())) {
      return;
    }

    $tracking = PhrequentUserTimeQuery::isUserTrackingObject(
      $user,
      $object->getPHID());
    if (!$tracking) {
      $track_action = id(new PhabricatorActionView())
        ->setName(pht('Start Tracking Time'))
        ->setIcon('fa-clock-o')
        ->setWorkflow(true)
        ->setHref('/phrequent/track/start/'.$object->getPHID().'/');
    } else {
      $track_action = id(new PhabricatorActionView())
        ->setName(pht('Stop Tracking Time'))
        ->setIcon('fa-clock-o red')
        ->setWorkflow(true)
        ->setHref('/phrequent/track/stop/'.$object->getPHID().'/');
    }

    if (!$user->isLoggedIn()) {
      $track_action->setDisabled(true);
    }

    $this->addActionMenuItems($event, $track_action);
  }

}
