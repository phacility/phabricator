<?php

final class PhrequentCurtainExtension
  extends PHUICurtainExtension {

  const EXTENSIONKEY = 'phrequent.time';

  public function shouldEnableForObject($object) {
    return ($object instanceof PhrequentTrackableInterface);
  }

  public function getExtensionApplication() {
    return new PhabricatorPhrequentApplication();
  }

  public function buildCurtainPanel($object) {
    $viewer = $this->getViewer();

    $events = id(new PhrequentUserTimeQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($object->getPHID()))
      ->needPreemptingEvents(true)
      ->execute();
    $event_groups = mgroup($events, 'getUserPHID');

    if (!$events) {
      return;
    }

    $handles = $viewer->loadHandles(array_keys($event_groups));
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


    return $this->newPanel()
      ->setHeaderText(pht('Time Spent'))
      ->setOrder(40000)
      ->appendChild($status_view);
  }

}
