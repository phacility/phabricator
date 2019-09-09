<?php

final class PhutilCalendarRecurrenceSet
  extends Phobject {

  private $sources = array();
  private $viewerTimezone = 'UTC';

  public function addSource(PhutilCalendarRecurrenceSource $source) {
    $this->sources[] = $source;
    return $this;
  }

  public function setViewerTimezone($viewer_timezone) {
    $this->viewerTimezone = $viewer_timezone;
    return $this;
  }

  public function getViewerTimezone() {
    return $this->viewerTimezone;
  }

  public function getEventsBetween(
    PhutilCalendarDateTime $start = null,
    PhutilCalendarDateTime $end = null,
    $limit = null) {

    if ($end === null && $limit === null) {
      throw new Exception(
        pht(
          'Recurring event range queries must have an end date, a limit, or '.
          'both.'));
    }

    $timezone = $this->getViewerTimezone();

    $sources = array();
    foreach ($this->sources as $source) {
      $source = clone $source;
      $source->setViewerTimezone($timezone);
      $source->resetSource();

      $sources[] = array(
        'source' => $source,
        'state' => null,
        'epoch' => null,
      );
    }

    if ($start) {
      $start = clone $start;
      $start->setViewerTimezone($timezone);
      $min_epoch = $start->getEpoch();
    } else {
      $min_epoch = 0;
    }

    if ($end) {
      $end = clone $end;
      $end->setViewerTimezone($timezone);
      $end_epoch = $end->getEpoch();
    } else {
      $end_epoch = null;
    }

    $results = array();
    $index = 0;
    $cursor = 0;
    while (true) {
      // Get the next event for each source which we don't have a future
      // event for.
      foreach ($sources as $key => $source) {
        $state = $source['state'];
        $epoch = $source['epoch'];

        if ($state !== null && $epoch >= $cursor) {
          // We have an event for this source, and it's a future event, so
          // we don't need to do anything.
          continue;
        }

        $next = $source['source']->getNextEvent($cursor);
        if ($next === null) {
          // This source doesn't have any more events, so we're all done.
          unset($sources[$key]);
          continue;
        }

        $next_epoch = $next->getEpoch();

        if ($end_epoch !== null && $next_epoch > $end_epoch) {
          // We have an end time and the next event from this source is
          // past that end, so we know there are no more relevant events
          // coming from this source.
          unset($sources[$key]);
          continue;
        }

        $sources[$key]['state'] = $next;
        $sources[$key]['epoch'] = $next_epoch;
      }

      if (!$sources) {
        // We've run out of sources which can produce valid events in the
        // window, so we're all done.
        break;
      }

      // Find the minimum event time across all sources.
      $next_epoch = null;
      foreach ($sources as $source) {
        if ($next_epoch === null) {
          $next_epoch = $source['epoch'];
        } else {
          $next_epoch = min($next_epoch, $source['epoch']);
        }
      }

      $is_exception = false;
      $next_source = null;
      foreach ($sources as $source) {
        if ($source['epoch'] == $next_epoch) {
          if ($source['source']->getIsExceptionSource()) {
            $is_exception = true;
          } else {
            $next_source = $source;
          }
        }
      }

      // If this is an exception, it means the event does NOT occur. We
      // skip it and move on. If it's not an exception, it does occur, so
      // we record it.
      if (!$is_exception) {

        // Only actually include this event in the results if it starts after
        // any specified start time. We increment the index regardless, so we
        // return results with proper offsets.
        if ($next_source['epoch'] >= $min_epoch) {
          $results[$index] = $next_source['state'];
        }
        $index++;

        if ($limit !== null && (count($results) >= $limit)) {
          break;
        }
      }

      $cursor = $next_epoch + 1;

      // If we have an end of the window and we've reached it, we're done.
      if ($end_epoch) {
        if ($cursor > $end_epoch) {
          break;
        }
      }
    }

    return $results;
  }

}
