<?php

final class ConpherenceTimeUtil {

  public static function getCalendarEventEpochs(
    PhabricatorUser $user,
    $start_day_str = 'Sunday') {

    $objects = self::getStartDateTimeObjects($user, $start_day_str);
    $start_day = $objects['start_day'];
    $end_day = clone $start_day;
    $end_day->modify('+9 days');

    return array(
      'start_epoch' => $start_day->format('U'),
      'end_epoch' => $end_day->format('U'));
  }

  public static function getCalendarWidgetTimestamps(
    PhabricatorUser $user,
    $start_day_str = 'Sunday') {

    $objects = self::getStartDateTimeObjects($user, $start_day_str);
    $start_day = $objects['start_day'];
    $timestamps = array();
    for ($day = 0; $day < 9; $day++) {
      $timestamp = clone $start_day;
      $timestamp->modify(sprintf('+%d days', $day));
      $timestamps[] = $timestamp;
    }
    return array(
      'today' => $objects['today'],
      'epoch_stamps' => $timestamps
    );
  }

  private static function getStartDateTimeObjects(
    PhabricatorUser $user,
    $start_day_str) {
    $timezone = new DateTimeZone($user->getTimezoneIdentifier());

    $today_epoch = PhabricatorTime::parseLocalTime('today', $user);
    $today = new DateTime('@'.$today_epoch);
    $today->setTimeZone($timezone);

    if ($today->format('l') == $start_day_str) {
      $start_day = clone $today;
    } else {
      $start_epoch = PhabricatorTime::parseLocalTime(
        'last '.$start_day_str,
        $user);
      $start_day = new DateTime('@'.$start_epoch);
      $start_day->setTimeZone($timezone);
    }
    return array(
      'today' => $today,
      'start_day' => $start_day);
  }

}
