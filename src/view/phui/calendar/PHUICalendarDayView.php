<?php

final class PHUICalendarDayView extends AphrontView {

  private $day;
  private $month;
  private $year;
  private $events = array();

  public function addEvent(AphrontCalendarDayEventView $event) {
    $this->events[] = $event;
    return $this;
  }

  public function __construct($year, $month, $day = null) {
    $this->day = $day;
    $this->month = $month;
    $this->year = $year;
  }

  public function render() {
    require_celerity_resource('phui-calendar-day-css');

    $day_box = new PHUIObjectBoxView();
    $day_of_week = $this->getDayOfWeek();
    $header_text = $this->getDateTime()->format('F j, Y');
    $header_text = $day_of_week.', '.$header_text;
    $day_box->setHeaderText($header_text);
    $hours = $this->getHoursOfDay();
    $hourly_events = array();
    $rows = array();

    // sort events into buckets by their start time
    // pretend no events overlap
    foreach ($hours as $hour) {
      $events = array();
      $hour_start = $hour->format('U');
      $hour_end = id(clone $hour)->modify('+1 hour')->format('U');
      foreach ($this->events as $event) {
        if ($event->getEpochStart() >= $hour_start
          && $event->getEpochStart() < $hour_end) {
          $events[] = $event;
        }
      }
      $count_events = count($events);
      $n = 0;
      foreach ($events as $event) {
        $event_start = $event->getEpochStart();
        $event_end = $event->getEpochEnd();

        $top = ((($event_start - $hour_start) / ($hour_end - $hour_start))
          * 100).'%';
        $height = ((($event_end - $event_start) / ($hour_end - $hour_start))
          * 100).'%';

        $hourly_events[$event->getEventID()] = array(
          'hour' => $hour,
          'event' => $event,
          'offset' => '0',
          'width' => '100%',
          'top' => $top,
          'height' => $height,
        );

        $n++;
      }
    }

    $clusters = $this->findClusters();
    foreach ($clusters as $cluster) {
      $hourly_events = $this->updateEventsFromCluster(
        $cluster,
        $hourly_events);
    }

    // actually construct table
    foreach ($hours as $hour) {
      $drawn_hourly_events = array();
      $cell_time = phutil_tag(
        'td',
        array('class' => 'phui-calendar-day-hour'),
        $hour->format('g A'));

      foreach ($hourly_events as $hourly_event) {
        if ($hourly_event['hour'] == $hour) {
          $drawn_hourly_events[] = $this->drawEvent(
            $hourly_event['event'],
            $hourly_event['offset'],
            $hourly_event['width'],
            $hourly_event['top'],
            $hourly_event['height']);
        }
      }
      $cell_event = phutil_tag(
        'td',
        array('class' => 'phui-calendar-day-events'),
        $drawn_hourly_events);

      $row = phutil_tag(
        'tr',
        array(),
        array($cell_time, $cell_event));

      $rows[] = $row;
    }

    $table = phutil_tag(
      'table',
      array('class' => 'phui-calendar-day-view'),
      array(
        '',
        $rows,
      ));

    $day_box->appendChild($table);
    return $day_box;

  }

  private function updateEventsFromCluster($cluster, $hourly_events) {
    $cluster_size = count($cluster);

    $n = 0;
    foreach ($cluster as $cluster_member) {
      $event_id = $cluster_member->getEventID();
      $offset = (($n / $cluster_size) * 100).'%';
      $width = ((1 / $cluster_size) * 100).'%';

      if (isset($hourly_events[$event_id])) {
        $hourly_events[$event_id]['offset'] = $offset;
        $hourly_events[$event_id]['width'] = $width;
      }
      $n++;
    }

    return $hourly_events;
  }

  private function drawEvent(
    AphrontCalendarDayEventView $event,
    $offset,
    $width,
    $top,
    $height) {
    $name = phutil_tag(
      'a',
      array(
        'class' => 'phui-calendar-day-event-link',
        'href' => $event->getURI(),
      ),
      $event->getName());

    $div = phutil_tag(
      'div',
      array(
        'class' => 'phui-calendar-day-event',
        'style' => 'left: '.$offset
          .'; width: '.$width
          .'; top: '.$top
          .'; height: '.$height
          .';',
      ),
      $name);

    return $div;
  }

  private function getDayOfWeek() {
    $date = $this->getDateTime();
    $day_of_week = $date->format('l');
    return $day_of_week;
  }

  // returns DateTime of each hour in the day
  private function getHoursOfDay() {
    $included_datetimes = array();

    $day_datetime = $this->getDateTime();
    $day_epoch = $day_datetime->format('U');

    $day_datetime->modify('+1 day');
    $next_day_epoch = $day_datetime->format('U');

    $included_time = $day_epoch;
    $included_datetime = $this->getDateTime();

    while ($included_time < $next_day_epoch) {
      $included_datetimes[] = clone $included_datetime;

      $included_datetime->modify('+1 hour');
      $included_time = $included_datetime->format('U');
    }

    return $included_datetimes;
  }

  private function getDateTime() {
    $user = $this->user;

    $timezone = new DateTimeZone($user->getTimezoneIdentifier());

    $day = $this->day;
    $month = $this->month;
    $year = $this->year;

    $date = new DateTime("{$year}-{$month}-{$day} ", $timezone);

    return $date;
  }

  private function findClusters() {
    $events = msort($this->events, 'getEpochStart');
    $clusters = array();


    foreach ($events as $event) {
      $destination_cluster_key = null;
      $event_start = $event->getEpochStart();
      $event_end = $event->getEpochEnd();

      foreach ($clusters as $key => $cluster) {
        foreach ($cluster as $clustered_event) {
          $compare_event_start = $clustered_event->getEpochStart();
          $compare_event_end = $clustered_event->getEpochEnd();

          if ($event_start < $compare_event_end
            && $event_end > $compare_event_start) {
            $destination_cluster_key = $key;
            break;
          }
        }
      }

      if ($destination_cluster_key !== null) {
        $clusters[$destination_cluster_key][] = $event;
      } else {
        $next_cluster = array();
        $next_cluster[] = $event;
        $clusters[] = $next_cluster;
      }
    }

    return $clusters;
  }
}
