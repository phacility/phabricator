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
    $rows = array();

    foreach ($hours as $hour) {
      // time slot
      $cell_time = phutil_tag(
        'td',
        array('class' => 'phui-calendar-day-hour'),
        $hour->format('g A'));

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
      $event_boxes = array();
      $n = 0;
      foreach ($events as $event) {
        $offset = (($n / $count_events) * 100).'%';
        $width = ((1 / $count_events) * 100).'%';
        $event_boxes[] = $this->drawEvent($event, $offset, $width);
        $n++;
      }

      // events starting in time slot
      $cell_event = phutil_tag(
        'td',
        array('class' => 'phui-calendar-day-events'),
        $event_boxes);


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

  private function drawEvent(
    AphrontCalendarDayEventView $event,
    $offset,
    $width) {
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
        'style' => 'left: '.$offset.'; width: '.$width.';',
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
}
