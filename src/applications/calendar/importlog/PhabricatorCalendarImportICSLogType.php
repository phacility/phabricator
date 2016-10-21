<?php

final class PhabricatorCalendarImportICSLogType
  extends PhabricatorCalendarImportLogType {

  const LOGTYPE = 'ics';

  public function getDisplayType(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {
    return pht('ICS Parse Error');
  }

  public function getDisplayDescription(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {
    return pht(
      'Failed to parse ICS data ("%s"): %s',
      $log->getParameter('ics.code'),
      $log->getParameter('ics.message'));
  }


  public function getDisplayIcon(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {
    return 'fa-file';
  }

  public function getDisplayColor(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {
    return 'red';
  }

}
