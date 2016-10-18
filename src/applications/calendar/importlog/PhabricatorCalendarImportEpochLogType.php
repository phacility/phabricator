<?php

final class PhabricatorCalendarImportEpochLogType
  extends PhabricatorCalendarImportLogType {

  const LOGTYPE = 'epoch';

  public function getDisplayType(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {
    return pht('Out of Range');
  }

  public function getDisplayDescription(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {
    return pht(
      'Ignored an event with an out-of-range date. Only dates between '.
      '1970 and 2037 are supported.');
  }

  public function getDisplayIcon(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {
    return 'fa-clock-o';
  }

  public function getDisplayColor(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {
    return 'red';
  }

}
