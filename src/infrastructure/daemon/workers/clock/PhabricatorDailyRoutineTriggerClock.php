<?php

/**
 * Triggers a daily routine, like server backups.
 *
 * This clock triggers events every 24 hours, using UTC. It does not use a
 * locale, and is intended for technical processes like backing up a server
 * every night.
 *
 * Because UTC does not have daylight savings, the local hour when this event
 * occurs will change over the course of the year. For example, from the
 * perspective of a user in California, it might run backups at 3AM in the
 * winter and 2AM in the summer. This is desirable for maintenance processes,
 * but problematic for some human processes. Use a different clock if you're
 * triggering a human-oriented event.
 *
 * The clock uses the time of day of the `start` epoch to calculate the time
 * of day of the next event, so you can change the time of day when the event
 * occurs by adjusting the `start` time of day.
 */
final class PhabricatorDailyRoutineTriggerClock
  extends PhabricatorTriggerClock {

  public function validateProperties(array $properties) {
    PhutilTypeSpec::checkMap(
      $properties,
      array(
        'start' => 'int',
      ));
  }

  public function getNextEventEpoch($last_epoch, $is_reschedule) {
    $start_epoch = $this->getProperty('start');
    if (!$last_epoch) {
      $last_epoch = $start_epoch;
    }

    $start = new DateTime('@'.$start_epoch);
    $last = new DateTime('@'.$last_epoch);

    // NOTE: We're choosing the date from the last event, but the time of day
    // from the start event. This allows callers to change when the event
    // occurs by updating the trigger's start parameter.
    $ymd = $last->format('Y-m-d');
    $hms = $start->format('G:i:s');

    $next = new DateTime("{$ymd} {$hms} UTC");

    // Add a day.
    // NOTE: DateInterval doesn't exist until PHP 5.3.0, and we currently
    // target PHP 5.2.3.
    $next->modify('+1 day');

    return (int)$next->format('U');
  }

}
