<?php

final class PhabricatorAuditStatusConstants {

  const NONE                    = '';
  const AUDIT_NOT_REQUIRED      = 'audit-not-required';
  const AUDIT_REQUIRED          = 'audit-required';
  const CONCERNED               = 'concerned';
  const ACCEPTED                = 'accepted';
  const AUDIT_REQUESTED         = 'requested';
  const RESIGNED                = 'resigned';
  const CLOSED                  = 'closed';
  const CC                      = 'cc';

  public static function getStatusNameMap() {
    $map = array(
      self::NONE                => pht('Not Applicable'),
      self::AUDIT_NOT_REQUIRED  => pht('Audit Not Required'),
      self::AUDIT_REQUIRED      => pht('Audit Required'),
      self::CONCERNED           => pht('Concern Raised'),
      self::ACCEPTED            => pht('Accepted'),
      self::AUDIT_REQUESTED     => pht('Audit Requested'),
      self::RESIGNED            => pht('Resigned'),
      self::CLOSED              => pht('Closed'),
      self::CC                  => pht("Was CC'd"),
    );

    return $map;
  }

  public static function getStatusName($code) {
    return idx(self::getStatusNameMap(), $code, pht('Unknown'));
  }

  public static function getStatusColor($code) {
    switch ($code) {
      case self::CONCERNED:
        $color = 'red';
        break;
      case self::AUDIT_REQUIRED:
        $color = 'orange';
        break;
      default:
        $color = null;
        break;
    }
    return $color;
  }

  public static function getOpenStatusConstants() {
    return array(
      self::AUDIT_REQUIRED,
      self::AUDIT_REQUESTED,
      self::CONCERNED,
    );
  }

  public static function isOpenStatus($status) {
    return in_array($status, self::getOpenStatusConstants());
  }

}
