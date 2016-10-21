<?php

final class PhabricatorCalendarImportDefaultLogType
  extends PhabricatorCalendarImportLogType {

  const LOGTYPE = 'default';

  public function getDisplayType(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {

    $type = $log->getParameter('type');
    if (strlen($type)) {
      return pht('Unknown Message "%s"', $type);
    } else {
      return pht('Unknown Message');
    }
  }

}
