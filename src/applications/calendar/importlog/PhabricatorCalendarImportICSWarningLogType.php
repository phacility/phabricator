<?php

final class PhabricatorCalendarImportICSWarningLogType
  extends PhabricatorCalendarImportLogType {

  const LOGTYPE = 'ics.warning';

  public function getDisplayType(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {
    return pht('ICS Parser Warning');
  }

  public function getDisplayDescription(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {
    return pht(
      'Warning ("%s") while parsing ICS data (near line %s): %s',
      $log->getParameter('ics.warning.code'),
      $log->getParameter('ics.warning.line'),
      $log->getParameter('ics.warning.message'));
  }


  public function getDisplayIcon(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {
    return 'fa-exclamation-triangle';
  }

  public function getDisplayColor(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {
    return 'yellow';
  }

}
