<?php

final class PhabricatorTime {

  private static $stack = array();
  private static $originalZone;

  public static function pushTime($epoch, $timezone) {
    if (empty(self::$stack)) {
      self::$originalZone = date_default_timezone_get();
    }

    $ok = date_default_timezone_set($timezone);
    if (!$ok) {
      throw new Exception("Invalid timezone '{$timezone}'!");
    }

    self::$stack[] = array(
      'epoch'       => $epoch,
      'timezone'    => $timezone,
    );

    return new PhabricatorTimeGuard(last_key(self::$stack));
  }

  public static function popTime($key) {
    if ($key !== last_key(self::$stack)) {
      throw new Exception('PhabricatorTime::popTime with bad key.');
    }
    array_pop(self::$stack);

    if (empty(self::$stack)) {
      date_default_timezone_set(self::$originalZone);
    } else {
      $frame = end(self::$stack);
      date_default_timezone_set($frame['timezone']);
    }
  }

  public static function getNow() {
    if (self::$stack) {
      $frame = end(self::$stack);
      return $frame['epoch'];
    }
    return time();
  }

  public static function parseLocalTime($time, PhabricatorUser $user) {
    $old_zone = date_default_timezone_get();

    date_default_timezone_set($user->getTimezoneIdentifier());
      $timestamp = (int)strtotime($time, PhabricatorTime::getNow());
      if ($timestamp <= 0) {
        $timestamp = null;
      }
    date_default_timezone_set($old_zone);

    return $timestamp;
  }

  public static function getTodayMidnightDateTime($viewer) {
    $timezone = new DateTimeZone($viewer->getTimezoneIdentifier());
    $today = new DateTime('@'.time());
    $today->setTimeZone($timezone);
    $year = $today->format('Y');
    $month = $today->format('m');
    $day = $today->format('d');
    $today = new DateTime("{$year}-{$month}-{$day}", $timezone);
    return $today;
  }

}
