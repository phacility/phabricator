<?php

final class PhabricatorAuditCommitStatusConstants {

  const NONE                = 0;
  const NEEDS_AUDIT         = 1;
  const CONCERN_RAISED      = 2;
  const PARTIALLY_AUDITED   = 3;
  const FULLY_AUDITED       = 4;

  public static function getStatusNameMap() {
    $map = array(
      self::NONE                => pht('None'),
      self::NEEDS_AUDIT         => pht('Audit Required'),
      self::CONCERN_RAISED      => pht('Concern Raised'),
      self::PARTIALLY_AUDITED   => pht('Partially Audited'),
      self::FULLY_AUDITED       => pht('Audited'),
    );

    return $map;
  }

  public static function getStatusName($code) {
    return idx(self::getStatusNameMap(), $code, 'Unknown');
  }

  public static function getOpenStatusConstants() {
    return array(
      self::CONCERN_RAISED,
      self::NEEDS_AUDIT,
    );
  }

}
