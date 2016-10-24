<?php

final class PhabricatorCalendarImportIgnoredNodeLogType
  extends PhabricatorCalendarImportLogType {

  const LOGTYPE = 'nodetype';

  public function getDisplayType(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {
    return pht('Ignored Node');
  }

  public function getDisplayDescription(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {
    $node_type = $log->getParameter('node.type');
    return pht(
      'Ignored unsupported "%s" node present in source.',
      $node_type);
  }

}
