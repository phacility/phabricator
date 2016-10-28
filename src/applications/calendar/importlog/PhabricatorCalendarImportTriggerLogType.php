<?php

final class PhabricatorCalendarImportTriggerLogType
  extends PhabricatorCalendarImportLogType {

  const LOGTYPE = 'trigger';

  public function getDisplayType(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {
    return pht('Import Triggered');
  }

  public function getDisplayDescription(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {
    return pht('Triggered a periodic update.');
  }

  public function getDisplayIcon(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {
    return 'fa-clock-o';
  }

  public function getDisplayColor(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {
    return 'blue';
  }

}
