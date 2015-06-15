<?php

final class PhabricatorAuditCommitStatusConstants extends Phobject {

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
    return idx(self::getStatusNameMap(), $code, pht('Unknown'));
  }

  public static function getOpenStatusConstants() {
    return array(
      self::CONCERN_RAISED,
      self::NEEDS_AUDIT,
    );
  }

  public static function getStatusColor($code) {
    switch ($code) {
      case self::CONCERN_RAISED:
        $color = 'red';
        break;
      case self::NEEDS_AUDIT:
      case self::PARTIALLY_AUDITED:
        $color = 'orange';
        break;
      case self::FULLY_AUDITED:
        $color = 'green';
        break;
      default:
        $color = null;
        break;
    }
    return $color;
  }

}
