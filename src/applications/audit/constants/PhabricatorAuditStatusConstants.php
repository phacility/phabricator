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
    static $map = array(
      self::NONE                => 'Not Applicable',
      self::AUDIT_NOT_REQUIRED  => 'Audit Not Required',
      self::AUDIT_REQUIRED      => 'Audit Required',
      self::CONCERNED           => 'Concern Raised',
      self::ACCEPTED            => 'Accepted',
      self::AUDIT_REQUESTED     => 'Audit Requested',
      self::RESIGNED            => 'Resigned',
      self::CLOSED              => 'Closed',
      self::CC                  => "Was CC'd",
    );

    return $map;
  }

  public static function getStatusName($code) {
    return idx(self::getStatusNameMap(), $code, 'Unknown');
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
