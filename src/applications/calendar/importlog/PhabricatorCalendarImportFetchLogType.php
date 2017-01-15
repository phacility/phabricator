<?php

final class PhabricatorCalendarImportFetchLogType
  extends PhabricatorCalendarImportLogType {

  const LOGTYPE = 'fetch';

  public function getDisplayType(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {
    return pht('Fetched Calendar');
  }

  public function getDisplayDescription(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {

    return $viewer->renderHandle($log->getParameter('file.phid'));
  }

  public function getDisplayIcon(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {
    return 'fa-download';
  }

  public function getDisplayColor(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {
    return 'green';
  }

}
