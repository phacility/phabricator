<?php

final class AphrontCalendarMonthView extends AphrontView {

  private $month;
  private $year;
  private $holidays = array();
  private $events   = array();
  private $browseURI;

  public function setBrowseURI($browse_uri) {
    $this->browseURI = $browse_uri;
    return $this;
  }
  private function getBrowseURI() {
    return $this->browseURI;
  }

  public function addEvent(AphrontCalendarEventView $event) {
    $this->events[] = $event;
    return $this;
  }

  public function setHolidays(array $holidays) {
    assert_instances_of($holidays, 'PhabricatorCalendarHoliday');
    $this->holidays = mpull($holidays, null, 'getDay');
    return $this;
  }

  public function __construct($month, $year) {
    $this->month = $month;
    $this->year = $year;
  }

  public function render() {
    if (empty($this->user)) {
      throw new Exception("Call setUser() before render()!");
    }

    $events = msort($this->events, 'getEpochStart');

    $days = $this->getDatesInMonth();

    require_celerity_resource('aphront-calendar-view-css');

    $first = reset($days);
    $empty = $first->format('w');

    $markup = array();

    $empty_box = phutil_tag(
      'div',
      array('class' => 'aphront-calendar-day aphront-calendar-empty'),
      '');

    for ($ii = 0; $ii < $empty; $ii++) {
      $markup[] = $empty_box;
    }

    $show_events = array();

    foreach ($days as $day) {
      $day_number = $day->format('j');

      $holiday = idx($this->holidays, $day->format('Y-m-d'));
      $class = 'aphront-calendar-day';
      $weekday = $day->format('w');
      if ($holiday || $weekday == 0 || $weekday == 6) {
        $class .= ' aphront-calendar-not-work-day';
      }

      $day->setTime(0, 0, 0);
      $epoch_start = $day->format('U');

      $day->modify('+1 day');
      $epoch_end = $day->format('U');

      if ($weekday == 0) {
        $show_events = array();
      } else {
        $show_events = array_fill_keys(
          array_keys($show_events),
          hsprintf(
            '<div class="aphront-calendar-event aphront-calendar-event-empty">'.
              '&nbsp;'.
            '</div>'));
      }

      foreach ($events as $event) {
        if ($event->getEpochStart() >= $epoch_end) {
          // This list is sorted, so we can stop looking.
          break;
        }
        if ($event->getEpochStart() < $epoch_end &&
            $event->getEpochEnd() > $epoch_start) {
          $show_events[$event->getUserPHID()] = $this->renderEvent(
            $event,
            $epoch_start,
            $epoch_end);
        }
      }

      $holiday_markup = null;
      if ($holiday) {
        $name = $holiday->getName();
        $holiday_markup = phutil_tag(
          'div',
          array(
            'class' => 'aphront-calendar-holiday',
            'title' => $name,
          ),
          $name);
      }

      $markup[] = hsprintf(
        '<div class="%s">'.
          '<div class="aphront-calendar-date-number">%s</div>'.
          '%s%s'.
        '</div>',
        $class,
        $day_number,
        $holiday_markup,
        phutil_implode_html("\n", $show_events));
    }

    $table = array();
    $rows = array_chunk($markup, 7);
    foreach ($rows as $row) {
      $table[] = hsprintf('<tr>');
      while (count($row) < 7) {
        $row[] = $empty_box;
      }
      foreach ($row as $cell) {
        $table[] = phutil_tag('td', array(), $cell);
      }
      $table[] = hsprintf('</tr>');
    }
    $table = hsprintf(
      '<table class="aphront-calendar-view">'.
       '%s'.
       '<tr class="aphront-calendar-day-of-week-header">'.
          '<th>Sun</th>'.
          '<th>Mon</th>'.
          '<th>Tue</th>'.
          '<th>Wed</th>'.
          '<th>Thu</th>'.
          '<th>Fri</th>'.
          '<th>Sat</th>'.
        '</tr>'.
        '%s'.
      '</table>',
      $this->renderCalendarHeader($first),
      phutil_implode_html("\n", $table));

    return $table;
  }

  private function renderCalendarHeader(DateTime $date) {
    $colspan = 7;
    $left_th = '';
    $right_th = '';

    // check for a browseURI, which means we need "fancy" prev / next UI
    $uri = $this->getBrowseURI();
    if ($uri) {
      $colspan = 5;
      $uri = new PhutilURI($uri);
      list($prev_year, $prev_month) = $this->getPrevYearAndMonth();
      $query = array('year' => $prev_year, 'month' => $prev_month);
      $prev_link = phutil_tag(
        'a',
        array('href' => (string) $uri->setQueryParams($query)),
        "\xE2\x86\x90");

      list($next_year, $next_month) = $this->getNextYearAndMonth();
      $query = array('year' => $next_year, 'month' => $next_month);
      $next_link = phutil_tag(
        'a',
        array('href' => (string) $uri->setQueryParams($query)),
        "\xE2\x86\x92");

      $left_th = phutil_tag('th', array(), $prev_link);
      $right_th = phutil_tag('th', array(), $next_link);
    }

    return hsprintf(
      '<tr class="aphront-calendar-month-year-header">%s%s%s</tr>',
      $left_th,
      phutil_tag('th', array('colspan' => $colspan), $date->format('F Y')),
      $right_th);
  }

  private function getNextYearAndMonth() {
    $month = $this->month;
    $year = $this->year;

    $next_year = $year;
    $next_month = $month + 1;
    if ($next_month == 13) {
      $next_year = $year + 1;
      $next_month = 1;
    }

    return array($next_year, $next_month);
  }

  private function getPrevYearAndMonth() {
    $month = $this->month;
    $year = $this->year;

    $prev_year = $year;
    $prev_month = $month - 1;
    if ($prev_month == 0) {
      $prev_year = $year - 1;
      $prev_month = 12;
    }

    return array($prev_year, $prev_month);
  }

  /**
   * Return a DateTime object representing the first moment in each day in the
   * month, according to the user's locale.
   *
   * @return list List of DateTimes, one for each day.
   */
  private function getDatesInMonth() {
    $user = $this->user;

    $timezone = new DateTimeZone($user->getTimezoneIdentifier());

    $month = $this->month;
    $year = $this->year;

    // Get the year and month numbers of the following month, so we can
    // determine when this month ends.
    list($next_year, $next_month) = $this->getNextYearAndMonth();

    $end_date = new DateTime("{$next_year}-{$next_month}-01", $timezone);
    $end_epoch = $end_date->format('U');

    $days = array();
    for ($day = 1; $day <= 31; $day++) {
      $day_date = new DateTime("{$year}-{$month}-{$day}", $timezone);
      $day_epoch = $day_date->format('U');
      if ($day_epoch >= $end_epoch) {
        break;
      } else {
        $days[] = $day_date;
      }
    }

    return $days;
  }

  private function renderEvent(
    AphrontCalendarEventView $event,
    $epoch_start,
    $epoch_end) {

    $user = $this->user;

    $event_start = $event->getEpochStart();
    $event_end   = $event->getEpochEnd();

    $classes = array();
    $when = array();

    $classes[] = 'aphront-calendar-event';
    if ($event_start < $epoch_start) {
      $classes[] = 'aphront-calendar-event-continues-before';
      $when[] = 'Started '.phabricator_datetime($event_start, $user);
    } else {
      $when[] = 'Starts at '.phabricator_time($event_start, $user);
    }

    if ($event_end > $epoch_end) {
      $classes[] = 'aphront-calendar-event-continues-after';
      $when[] = 'Ends '.phabricator_datetime($event_end, $user);
    } else {
      $when[] = 'Ends at '.phabricator_time($event_end, $user);
    }

    Javelin::initBehavior('phabricator-tooltips');

    $info = $event->getName();
    if ($event->getDescription()) {
      $info .= "\n\n".$event->getDescription();
    }

    if ($user->getPHID() == $event->getUserPHID()) {
      $tag  = 'a';
      $href = '/calendar/status/edit/'.$event->getEventID().'/';
    } else {
      $tag  = 'div';
      $href = null;
    }

    $text_div = javelin_tag(
      $tag,
      array(
        'sigil' => 'has-tooltip',
        'meta'  => array(
          'tip'  => $info."\n\n".implode("\n", $when),
          'size' => 240,
        ),
        'class' => 'aphront-calendar-event-text',
        'href'  => $href,
      ),
      phutil_utf8_shorten($event->getName(), 32));

    return javelin_tag(
      'div',
      array(
        'class' => implode(' ', $classes),
      ),
      $text_div);
  }

}
