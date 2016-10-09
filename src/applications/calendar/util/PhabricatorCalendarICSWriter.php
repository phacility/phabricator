<?php

final class PhabricatorCalendarICSWriter extends Phobject {

  private $viewer;
  private $events = array();

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function setEvents(array $events) {
    assert_instances_of($events, 'PhabricatorCalendarEvent');
    $this->events = $events;
    return $this;
  }

  public function getEvents() {
    return $this->events;
  }

  public function writeICSDocument() {
    $viewer = $this->getViewer();
    $events = $this->getEvents();

    $events = mpull($events, null, 'getPHID');

    if ($events) {
      $child_map = id(new PhabricatorCalendarEventQuery())
        ->setViewer($viewer)
        ->withParentEventPHIDs(array_keys($events))
        ->execute();
      $child_map = mpull($child_map, null, 'getPHID');
    } else {
      $child_map = array();
    }

    $all_events = $events + $child_map;
    $child_groups = mgroup($child_map, 'getInstanceOfEventPHID');

    $document_node = new PhutilCalendarDocumentNode();

    foreach ($all_events as $event) {
      $child_events = idx($child_groups, $event->getPHID(), array());
      $event_node = $event->newIntermediateEventNode($viewer, $child_events);
      $document_node->appendChild($event_node);
    }

    $root_node = id(new PhutilCalendarRootNode())
      ->appendChild($document_node);

    return id(new PhutilICSWriter())
      ->writeICSDocument($root_node);
  }
}
