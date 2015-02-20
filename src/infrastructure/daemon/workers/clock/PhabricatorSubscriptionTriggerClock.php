<?php

/**
 * Triggers an event every month on the same day of the month, like the 12th
 * of the month.
 *
 * If a given month does not have such a day (for instance, the clock triggers
 * on the 30th of each month and the month in question is February, which never
 * has a 30th day), it will trigger on the last day of the month instead.
 *
 * Choosing this strategy for subscriptions is predictable (it's easy to
 * anticipate when a subscription period will end) and fair (billing
 * periods always have nearly equal length). It also spreads subscriptions
 * out evenly. If there are issues with billing, this provides an opportunity
 * for them to be corrected after only a few customers are affected, instead of
 * (for example) having every subscription fail all at once on the 1st of the
 * month.
 */
final class PhabricatorSubscriptionTriggerClock
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

    // Constructing DateTime objects like this implies UTC, so we don't need
    // to set that explicitly.
    $start = new DateTime('@'.$start_epoch);
    $last = new DateTime('@'.$last_epoch);

    $year = (int)$last->format('Y');
    $month = (int)$last->format('n');

    // Note that we're getting the day of the month from the start date, not
    // from the last event date. This lets us schedule on March 31 after moving
    // the date back to Feb 28.
    $day = (int)$start->format('j');

    // We trigger at the same time of day as the original event. Generally,
    // this means that you should get invoiced at a reasonable local time in
    // most cases, unless you subscribed at 1AM or something.
    $hms = $start->format('G:i:s');

    // Increment the month by 1.
    $month = $month + 1;

    // If we ran off the end of the calendar, set the month back to January
    // and increment the year by 1.
    if ($month > 12) {
      $month = 1;
      $year = $year + 1;
    }

    // Now, move the day backward until it falls in the correct month. If we
    // pass an invalid date like "2014-2-31", it will internally be parsed
    // as though we had passed "2014-3-3".
    while (true) {
      $next = new DateTime("{$year}-{$month}-{$day} {$hms} UTC");
      if ($next->format('n') == $month) {
        // The month didn't get corrected forward, so we're all set.
        break;
      } else {
        // The month did get corrected forward, so back off a day.
        $day--;
      }
    }

    return (int)$next->format('U');
  }

}
