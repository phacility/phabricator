<?php

final class PhabricatorCalendarImportFrequencyLogType
  extends PhabricatorCalendarImportLogType {

  const LOGTYPE = 'frequency';

  public function getDisplayType(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {
    return pht('Too Frequent');
  }

  public function getDisplayDescription(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {

    $frequency = $log->getParameter('frequency');

    return pht(
      'Ignored an event with an unsupported frequency rule ("%s"). Events '.
      'which repeat more frequently than daily are not supported.',
      $frequency);
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
