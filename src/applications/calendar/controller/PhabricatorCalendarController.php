<?php

abstract class PhabricatorCalendarController extends PhabricatorController {

  protected function newICSResponse(
    PhabricatorUser $viewer,
    $file_name,
    array $events) {
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

    $ics_data = id(new PhutilICSWriter())
      ->writeICSDocument($root_node);

    return id(new AphrontFileResponse())
      ->setDownload($file_name)
      ->setMimeType('text/calendar')
      ->setContent($ics_data);
  }

}
