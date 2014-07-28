<?php

final class PhabricatorAuditActionConstants {

  const CONCERN   = 'concern';
  const ACCEPT    = 'accept';
  const COMMENT   = 'comment';
  const RESIGN    = 'resign';
  const CLOSE     = 'close';
  const ADD_CCS = 'add_ccs';
  const ADD_AUDITORS = 'add_auditors';
  const INLINE = 'audit:inline';
  const ACTION = 'audit:action';

  public static function getActionNameMap() {
    $map = array(
      self::COMMENT => pht('Comment'),
      self::CONCERN => pht("Raise Concern \xE2\x9C\x98"),
      self::ACCEPT  => pht("Accept Commit \xE2\x9C\x94"),
      self::RESIGN  => pht('Resign from Audit'),
      self::CLOSE   => pht('Close Audit'),
      self::ADD_CCS => pht('Add CCs'),
      self::ADD_AUDITORS => pht('Add Auditors'),
    );

    return $map;
  }

  public static function getActionName($constant) {
    $map = self::getActionNameMap();
    return idx($map, $constant, 'Unknown');
  }

  public static function getActionPastTenseVerb($action) {
    static $map = array(
      self::COMMENT => 'commented on',
      self::CONCERN => 'raised a concern with',
      self::ACCEPT  => 'accepted',
      self::RESIGN  => 'resigned from',
      self::CLOSE   => 'closed',
      self::ADD_CCS => 'added CCs to',
      self::ADD_AUDITORS => 'added auditors to',
    );
    return idx($map, $action, 'updated');
  }

}
