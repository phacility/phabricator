<?php

final class PhrequentUIEventListener
  extends PhabricatorEventListener {

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

  private function handlePropertyEvent($ui_event) {
    $user = $ui_event->getUser();
    $object = $ui_event->getValue('object');

    if (!$object || !$object->getPHID()) {
      // No object, or the object has no PHID yet..
      return;
    }

    if (!($object instanceof PhrequentTrackableInterface)) {
      // This object isn't a time trackable object.
      return;
    }

    if (!$this->canUseApplication($ui_event->getUser())) {
      return;
    }

    $events = id(new PhrequentUserTimeQuery())
      ->setViewer($user)
      ->withObjectPHIDs(array($object->getPHID()))
      ->needPreemptingEvents(true)
      ->execute();
    $event_groups = mgroup($events, 'getUserPHID');

    if (!$events) {
      return;
    }

    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($user)
      ->withPHIDs(array_keys($event_groups))
      ->execute();

    $status_view = new PHUIStatusListView();

    foreach ($event_groups as $user_phid => $event_group) {
      $item = new PHUIStatusItemView();
      $item->setTarget($handles[$user_phid]->renderLink());

      $state = 'stopped';
      foreach ($event_group as $event) {
        if ($event->getDateEnded() === null) {
          if ($event->isPreempted()) {
            $state = 'suspended';
          } else {
            $state = 'active';
            break;
          }
        }
      }

      switch ($state) {
        case 'active':
          $item->setIcon(
            PHUIStatusItemView::ICON_CLOCK,
            'green',
            pht('Working Now'));
          break;
        case 'suspended':
          $item->setIcon(
            PHUIStatusItemView::ICON_CLOCK,
            'yellow',
            pht('Interrupted'));
          break;
        case 'stopped':
          $item->setIcon(
            PHUIStatusItemView::ICON_CLOCK,
            'bluegrey',
            pht('Not Working Now'));
          break;
      }

      $block = new PhrequentTimeBlock($event_group);
      $item->setNote(
        phutil_format_relative_time(
          $block->getTimeSpentOnObject(
            $object->getPHID(),
            time())));

      $status_view->addItem($item);
    }

    $view = $ui_event->getValue('view');
    $view->addProperty(pht('Time Spent'), $status_view);
  }

}
