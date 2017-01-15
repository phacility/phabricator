<?php

final class PhabricatorCalendarImportDuplicateLogType
  extends PhabricatorCalendarImportLogType {

  const LOGTYPE = 'duplicate';

  public function getDisplayType(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {
    return pht('Duplicate Event');
  }

  public function getDisplayDescription(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {
    $duplicate_uid = $log->getParameter('uid.full');
    return pht(
      'Ignored duplicate event "%s" present in source.',
      $duplicate_uid);
  }

}
