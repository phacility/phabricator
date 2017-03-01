<?php

final class PhabricatorCalendarImportOriginalLogType
  extends PhabricatorCalendarImportLogType {

  const LOGTYPE = 'original';

  public function getDisplayType(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {
    return pht('Original Event');
  }

  public function getDisplayDescription(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {

    $phid = $log->getParameter('phid');

    return pht(
      'Ignored an event (%s) because the original version of this event '.
      'was created here.',
      $viewer->renderHandle($phid));
  }

}
