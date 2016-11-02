<?php

final class PhabricatorCalendarImportUpdateLogType
  extends PhabricatorCalendarImportLogType {

  const LOGTYPE = 'update';

  public function getDisplayType(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {
    $is_new = $log->getParameter('new');
    if ($is_new) {
      return pht('Imported Event');
    } else {
      return pht('Updated Event');
    }
  }

  public function getDisplayDescription(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {
    $event_phid = $log->getParameter('phid');
    return $viewer->renderHandle($event_phid);
  }

  public function getDisplayIcon(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {
    return 'fa-calendar';
  }

  public function getDisplayColor(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {
    return 'green';
  }

}
