<?php

final class PhabricatorCalendarImportDeleteLogType
  extends PhabricatorCalendarImportLogType {

  const LOGTYPE = 'delete';

  public function getDisplayType(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {
    return pht('Deleted Event');
  }

  public function getDisplayDescription(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {
    return pht(
      'Deleted event "%s" which is no longer present in the source.',
      $log->getParameter('name'));
  }

  public function getDisplayIcon(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {
    return 'fa-times';
  }

  public function getDisplayColor(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {
    return 'grey';
  }

}
