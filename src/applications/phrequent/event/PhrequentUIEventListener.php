<?php

final class PhrequentUIEventListener
  extends PhutilEventListener {

  public function register() {
    $this->listen(PhabricatorEventType::TYPE_UI_DIDRENDERACTIONS);
    $this->listen(PhabricatorEventType::TYPE_UI_WILLRENDERPROPERTIES);
  }

  public function handleEvent(PhutilEvent $event) {
    switch ($event->getType()) {
      case PhabricatorEventType::TYPE_UI_DIDRENDERACTIONS:
        $this->handleActionEvent($event);
        break;
      case PhabricatorEventType::TYPE_UI_WILLRENDERPROPERTIES:
        $this->handlePropertyEvent($event);
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

    $tracking = PhrequentUserTimeQuery::isUserTrackingObject(
      $user,
      $object->getPHID());
    if (!$tracking) {
      $track_action = id(new PhabricatorActionView())
          ->setName(pht('Track Time'))
          ->setIcon('history')
          ->setWorkflow(true)
          ->setHref('/phrequent/track/start/'.$object->getPHID().'/');
    } else {
      $track_action = id(new PhabricatorActionView())
          ->setName(pht('Stop Tracking'))
          ->setIcon('history')
          ->setWorkflow(true)
          ->setHref('/phrequent/track/stop/'.$object->getPHID().'/');
    }

    $actions = $event->getValue('actions');
    $actions[] = $track_action;
    $event->setValue('actions', $actions);
  }

  private function handlePropertyEvent($event) {
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

    $time_spent = PhrequentUserTimeQuery::getTotalTimeSpentOnObject(
      $object->getPHID());
    $view = $event->getValue('view');
    $view->addProperty(
      pht('Time Spent'),
      $time_spent == 0 ? 'none' :
        phabricator_format_relative_time_detailed($time_spent));
  }

}
