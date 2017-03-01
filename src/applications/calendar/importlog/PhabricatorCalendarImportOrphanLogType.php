<?php

final class PhabricatorCalendarImportOrphanLogType
  extends PhabricatorCalendarImportLogType {

  const LOGTYPE = 'orphan';

  public function getDisplayType(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {
    return pht('Orphan');
  }

  public function getDisplayDescription(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {
    $child_uid = $log->getParameter('uid.full');
    $parent_uid = $log->getParameter('uid.parent');
    return pht(
      'Found orphaned child event ("%s") without a parent event ("%s").',
      $child_uid,
      $parent_uid);
  }

}
