<?php

final class PhrequentTimeBlock extends Phobject {

  private $events;

  public function __construct(array $events) {
    assert_instances_of($events, 'PhrequentUserTime');
    $this->events = $events;
  }

  public function getTimeSpentOnObject($phid, $now) {
    $ranges = idx($this->getObjectTimeRanges($now), $phid, array());

    $sum = 0;
    foreach ($ranges as $range) {
      $sum += ($range[1] - $range[0]);
    }

    return $sum;
  }

  public function getObjectTimeRanges($now) {
    $ranges = array();

    $object_ranges = array();
    foreach ($this->events as $event) {

      // First, convert each event's preempting stack into a linear timeline
      // of events.

      $timeline = array();
      $timeline[] = array(
        'at' => $event->getDateStarted(),
        'type' => 'start',
      );
      $timeline[] = array(
        'at' => nonempty($event->getDateEnded(), $now),
        'type' => 'end',
      );

      $base_phid = $event->getObjectPHID();

      $preempts = $event->getPreemptingEvents();

      foreach ($preempts as $preempt) {
        $same_object = ($preempt->getObjectPHID() == $base_phid);
        $timeline[] = array(
          'at' => $preempt->getDateStarted(),
          'type' => $same_object ? 'start' : 'push',
        );
        $timeline[] = array(
          'at' => nonempty($preempt->getDateEnded(), $now),
          'type' => $same_object ? 'end' : 'pop',
        );
      }

      // Now, figure out how much time was actually spent working on the
      // object.

      $timeline = isort($timeline, 'at');

      $stack = array();
      $depth = null;

      $ranges = array();
      foreach ($timeline as $timeline_event) {
        switch ($timeline_event['type']) {
          case 'start':
            $stack[] = $depth;
            $depth = 0;
            $range_start = $timeline_event['at'];
            break;
          case 'end':
            if ($depth == 0) {
              $ranges[] = array($range_start, $timeline_event['at']);
            }
            $depth = array_pop($stack);
            break;
          case 'push':
            if ($depth == 0) {
              $ranges[] = array($range_start, $timeline_event['at']);
            }
            $depth++;
            break;
          case 'pop':
            $depth--;
            if ($depth == 0) {
              $range_start = $timeline_event['at'];
            }
            break;
        }
      }

      $object_ranges[$base_phid][] = $ranges;
    }

    // Finally, collapse all the ranges so we don't double-count time.

    foreach ($object_ranges as $phid => $ranges) {
      $object_ranges[$phid] = self::mergeTimeRanges(array_mergev($ranges));
    }

    return $object_ranges;
  }


  /**
   * Merge a list of time ranges (pairs of `<start, end>` epochs) so that no
   * elements overlap. For example, the ranges:
   *
   *   array(
   *     array(50, 150),
   *     array(100, 175),
   *   );
   *
   * ...are merged to:
   *
   *   array(
   *     array(50, 175),
   *   );
   *
   * This is used to avoid double-counting time on objects which had timers
   * started multiple times.
   *
   * @param list<pair<int, int>> List of possibly overlapping time ranges.
   * @return list<pair<int, int>> Nonoverlapping time ranges.
   */
  public static function mergeTimeRanges(array $ranges) {
    $ranges = isort($ranges, 0);

    $result = array();

    $current = null;
    foreach ($ranges as $key => $range) {
      if ($current === null) {
        $current = $range;
        continue;
      }

      if ($range[0] <= $current[1]) {
        $current[1] = max($range[1], $current[1]);
        continue;
      }

      $result[] = $current;
      $current = $range;
    }

    $result[] = $current;

    return $result;
  }

}
