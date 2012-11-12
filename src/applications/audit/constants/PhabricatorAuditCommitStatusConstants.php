<?php

final class PhabricatorAuditCommitStatusConstants {

  const NONE                = 0;
  const NEEDS_AUDIT         = 1;
  const CONCERN_RAISED      = 2;
  const PARTIALLY_AUDITED   = 3;
  const FULLY_AUDITED       = 4;

  public static function getStatusNameMap() {
    static $map = array(
      self::NONE                => 'None',
      self::NEEDS_AUDIT         => 'Audit Required',
      self::CONCERN_RAISED      => 'Concern Raised',
      self::PARTIALLY_AUDITED   => 'Partially Audited',
      self::FULLY_AUDITED       => 'Audited',
    );

    return $map;
  }

  public static function getStatusName($code) {
    return idx(self::getStatusNameMap(), $code, 'Unknown');
  }

}
