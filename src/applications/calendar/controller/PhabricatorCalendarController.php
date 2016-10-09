<?php

abstract class PhabricatorCalendarController extends PhabricatorController {

  protected function newICSResponse(
    PhabricatorUser $viewer,
    $file_name,
    array $events) {

    $ics_data = id(new PhabricatorCalendarICSWriter())
      ->setViewer($viewer)
      ->setEvents($events)
      ->writeICSDocument();

    return id(new AphrontFileResponse())
      ->setDownload($file_name)
      ->setMimeType('text/calendar')
      ->setContent($ics_data);
  }

}
