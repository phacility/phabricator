<?php

final class PhabricatorAuditActionConstants extends Phobject {

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
      self::COMMENT      => pht('Comment'),
      self::CONCERN      => pht("Raise Concern \xE2\x9C\x98"),
      self::ACCEPT       => pht("Accept Commit \xE2\x9C\x94"),
      self::RESIGN       => pht('Resign from Audit'),
      self::CLOSE        => pht('Close Audit'),
      self::ADD_CCS      => pht('Add Subscribers'),
      self::ADD_AUDITORS => pht('Add Auditors'),
    );

    return $map;
  }

  public static function getActionName($constant) {
    $map = self::getActionNameMap();
    return idx($map, $constant, pht('Unknown'));
  }

  public static function getActionPastTenseVerb($action) {
    $map = array(
      self::COMMENT      => pht('commented on'),
      self::CONCERN      => pht('raised a concern with'),
      self::ACCEPT       => pht('accepted'),
      self::RESIGN       => pht('resigned from'),
      self::CLOSE        => pht('closed'),
      self::ADD_CCS      => pht('added CCs to'),
      self::ADD_AUDITORS => pht('added auditors to'),
    );
    return idx($map, $action, pht('updated'));
  }

}
