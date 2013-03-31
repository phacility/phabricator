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
        ->setUser($user)
        ->setName(pht('Start Tracking Time'))
        ->setIcon('history')
        ->setWorkflow(true)
        ->setRenderAsForm(true)
        ->setHref('/phrequent/track/start/'.$object->getPHID().'/');
    } else {
      $track_action = id(new PhabricatorActionView())
        ->setUser($user)
        ->setName(pht('Stop Tracking Time'))
        ->setIcon('history')
        ->setWorkflow(true)
        ->setRenderAsForm(true)
        ->setHref('/phrequent/track/stop/'.$object->getPHID().'/');
    }

    if (!$user->isLoggedIn()) {
      $track_action->setDisabled(true);
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

    $depth = false;

    $stack = PhrequentUserTimeQuery::loadUserStack($user);
    if ($stack) {
      $stack = array_values($stack);
      for ($ii = 0; $ii < count($stack); $ii++) {
        if ($stack[$ii]->getObjectPHID() == $object->getPHID()) {
          $depth = ($ii + 1);
          break;
        }
      }
    }

    $time_spent = PhrequentUserTimeQuery::getTotalTimeSpentOnObject(
      $object->getPHID());

    if (!$depth && !$time_spent) {
      return;
    }

    require_celerity_resource('phrequent-css');

    $property = array();
    if ($depth == 1) {
      $property[] = phutil_tag(
        'div',
        array(
          'class' => 'phrequent-tracking-property phrequent-active',
        ),
        pht('Currently Tracking'));
    } else if ($depth > 1) {
      $property[] = phutil_tag(
        'div',
        array(
          'class' => 'phrequent-tracking-property phrequent-on-stack',
        ),
        pht('On Stack'));
    }

    if ($time_spent) {
      $property[] = phabricator_format_relative_time_detailed($time_spent);
    }

    $view = $event->getValue('view');
    $view->addProperty(pht('Time Spent'), $property);
  }

}
