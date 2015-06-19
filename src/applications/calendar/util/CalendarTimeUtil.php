<?php
/**
 * This class is useful for generating various time objects, relative to the
 * user and their timezone.
 *
 * For now, the class exposes two sets of static methods for the two main
 * calendar views - one for the conpherence calendar widget and one for the
 * user profile calendar view. These have slight differences such as
 * conpherence showing both a three day "today 'til 2 days from now" *and*
 * a Sunday -> Saturday list, whilest the profile view shows a more simple
 * seven day rolling list of events.
 */
final class CalendarTimeUtil extends Phobject {

  public static function getCalendarEventEpochs(
    PhabricatorUser $user,
    $start_day_str = 'Sunday',
    $days = 9) {

    $objects = self::getStartDateTimeObjects($user, $start_day_str);
    $start_day = $objects['start_day'];
    $end_day = clone $start_day;
    $end_day->modify('+'.$days.' days');

    return array(
      'start_epoch' => $start_day->format('U'),
      'end_epoch' => $end_day->format('U'),
    );
  }

  public static function getCalendarWeekTimestamps(
    PhabricatorUser $user) {
    return self::getTimestamps($user, 'Today', 7);
  }

  public static function getCalendarWidgetTimestamps(
    PhabricatorUser $user) {
    return self::getTimestamps($user, 'Sunday', 9);
  }

  /**
   * Public for testing purposes only. You should probably use one of the
   * functions above.
   */
  public static function getTimestamps(
    PhabricatorUser $user,
    $start_day_str,
    $days) {

    $objects = self::getStartDateTimeObjects($user, $start_day_str);
    $start_day = $objects['start_day'];
    $timestamps = array();
    for ($day = 0; $day < $days; $day++) {
      $timestamp = clone $start_day;
      $timestamp->modify(sprintf('+%d days', $day));
      $timestamps[] = $timestamp;
    }
    return array(
      'today' => $objects['today'],
      'epoch_stamps' => $timestamps,
    );
  }

  private static function getStartDateTimeObjects(
    PhabricatorUser $user,
    $start_day_str) {
    $timezone = new DateTimeZone($user->getTimezoneIdentifier());

    $today_epoch = PhabricatorTime::parseLocalTime('today', $user);
    $today = new DateTime('@'.$today_epoch);
    $today->setTimeZone($timezone);

    if (strtolower($start_day_str) == 'today' ||
        $today->format('l') == $start_day_str) {
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
      'start_day' => $start_day,
    );
  }

}
