<?php

final class PhabricatorCalendarImportQueueLogType
  extends PhabricatorCalendarImportLogType {

  const LOGTYPE = 'queue';

  public function getDisplayType(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {
    return pht('Queued');
  }

  public function getDisplayDescription(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {

    $size = $log->getParameter('data.size');
    $limit = $log->getParameter('data.limit');

    return pht(
      'Queued for background import: data size (%s) exceeds limit for '.
      'immediate processing (%s).',
      phutil_format_bytes($size),
      phutil_format_bytes($limit));
  }

  public function getDisplayIcon(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {
    return 'fa-sort-amount-desc';
  }

  public function getDisplayColor(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {
    return 'blue';
  }

}
