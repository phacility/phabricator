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

    $via = $log->getParameter('via');
    switch ($via) {
      case PhabricatorCalendarImportReloadWorker::VIA_BACKGROUND:
        return pht('Started background processing.');
      case PhabricatorCalendarImportReloadWorker::VIA_TRIGGER:
      default:
        return pht('Triggered a periodic update.');
    }
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
