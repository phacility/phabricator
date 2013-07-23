<?php

final class PhabricatorDaemonLogQuery {

  public static function getTimeUntilUnknown() {
    return 3 * PhutilDaemonOverseer::HEARTBEAT_WAIT;
  }

  public static function getTimeUntilDead() {
    return 30 * PhutilDaemonOverseer::HEARTBEAT_WAIT;
  }

}
