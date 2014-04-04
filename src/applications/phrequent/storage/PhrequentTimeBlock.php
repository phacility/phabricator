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
        'event' => $event,
        'at' => $event->getDateStarted(),
        'type' => 'start',
      );
      $timeline[] = array(
        'event' => $event,
        'at' => nonempty($event->getDateEnded(), $now),
        'type' => 'end',
      );

      $base_phid = $event->getObjectPHID();

      $preempts = $event->getPreemptingEvents();

      foreach ($preempts as $preempt) {
        $same_object = ($preempt->getObjectPHID() == $base_phid);
        $timeline[] = array(
          'event' => $preempt,
          'at' => $preempt->getDateStarted(),
          'type' => $same_object ? 'start' : 'push',
        );
        $timeline[] = array(
          'event' => $preempt,
          'at' => nonempty($preempt->getDateEnded(), $now),
          'type' => $same_object ? 'end' : 'pop',
        );
      }

      // Now, figure out how much time was actually spent working on the
      // object.

      usort($timeline, array(__CLASS__, 'sortTimeline'));

      $stack = array();
      $depth = null;

      // NOTE: "Strata" track the separate layers between each event tracking
      // the object we care about. Events might look like this:
      //
      //             |xxxxxxxxxxxxxxxxx|
      //         |yyyyyyy|
      //    |xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx|
      //   9AM                                            5PM
      //
      // ...where we care about event "x". When "y" is popped, that shouldn't
      // pop the top stack -- we need to pop the stack a level down. Each
      // event tracking "x" creates a new stratum, and we keep track of where
      // timeline events are among the strata in order to keep stack depths
      // straight.

      $stratum = null;
      $strata = array();


      $ranges = array();
      foreach ($timeline as $timeline_event) {
        $id = $timeline_event['event']->getID();
        $type = $timeline_event['type'];

        switch ($type) {
          case 'start':
            $stack[] = $depth;
            $depth = 0;
            $stratum = count($stack);
            $strata[$id] = $stratum;
            $range_start = $timeline_event['at'];
            break;
          case 'end':
            if ($strata[$id] == $stratum) {
              if ($depth == 0) {
                $ranges[] = array($range_start, $timeline_event['at']);
                $depth = array_pop($stack);
              } else {
                // Here, we've prematurely ended the current stratum. Merge all
                // the higher strata into it. This looks like this:
                //
                //                 V
                //                 V
                //              |zzzzzzzz|
                //           |xxxxx|
                //        |yyyyyyyyyyyyy|
                //   |xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx|

                $depth = array_pop($stack) + $depth;
              }
            } else {
              // Here, we've prematurely ended a deeper stratum. Merge higher
              // stata. This looks like this:
              //
              //                V
              //                V
              //              |aaaaaaa|
              //            |xxxxxxxxxxxxxxxxxxx|
              //          |zzzzzzzzzzzzz|
              //        |xxxxxxx|
              //     |yyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyy|
              //   |xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx|

              $extra = $stack[$strata[$id]];
              unset($stack[$strata[$id] - 1]);
              $stack = array_values($stack);
              $stack[$strata[$id] - 1] += $extra;
            }

            // Regardless of how we got here, we need to merge down any higher
            // strata.
            $target = $strata[$id];
            foreach ($strata as $strata_id => $id_stratum) {
              if ($id_stratum >= $target) {
                $strata[$strata_id]--;
              }
            }
            $stratum = count($stack);

            unset($strata[$id]);
            break;
          case 'push':
            $strata[$id] = $stratum;
            if ($depth == 0) {
              $ranges[] = array($range_start, $timeline_event['at']);
            }
            $depth++;
            break;
          case 'pop':
            if ($strata[$id] == $stratum) {
              $depth--;
              if ($depth == 0) {
                $range_start = $timeline_event['at'];
              }
            } else {
              $stack[$strata[$id]]--;
            }
            unset($strata[$id]);
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


  /**
   * Sort events in timeline order. Notably, for events which occur on the same
   * second, we want to process end events after start events.
   */
  public static function sortTimeline(array $u, array $v) {
    // If these events occur at different times, ordering is obvious.
    if ($u['at'] != $v['at']) {
      return ($u['at'] < $v['at']) ? -1 : 1;
    }

    $u_end = ($u['type'] == 'end' || $u['type'] == 'pop');
    $v_end = ($v['type'] == 'end' || $v['type'] == 'pop');

    $u_id = $u['event']->getID();
    $v_id = $v['event']->getID();

    if ($u_end == $v_end) {
      // These are both start events or both end events. Sort them by ID.
      if (!$u_end) {
        return ($u_id < $v_id) ? -1 : 1;
      } else {
        return ($u_id < $v_id) ? 1 : -1;
      }
    } else {
      // Sort them (start, end) if they're the same event, and (end, start)
      // otherwise.
      if ($u_id == $v_id) {
        return $v_end ? -1 : 1;
      } else {
        return $v_end ? 1 : -1;
      }
    }

    return 0;
  }

}
