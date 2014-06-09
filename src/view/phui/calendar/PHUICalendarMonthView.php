<?php

final class PHUICalendarMonthView extends AphrontView {

  private $day;
  private $month;
  private $year;
  private $holidays = array();
  private $events   = array();
  private $browseURI;
  private $image;

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

  public function setImage($uri) {
    $this->image = $uri;
    return $this;
  }

  public function setHolidays(array $holidays) {
    assert_instances_of($holidays, 'PhabricatorCalendarHoliday');
    $this->holidays = mpull($holidays, null, 'getDay');
    return $this;
  }

  public function __construct($month, $year, $day = null) {
    $this->day = $day;
    $this->month = $month;
    $this->year = $year;
  }

  public function render() {
    if (empty($this->user)) {
      throw new Exception('Call setUser() before render()!');
    }

    $events = msort($this->events, 'getEpochStart');

    $days = $this->getDatesInMonth();

    require_celerity_resource('phui-calendar-month-css');

    $first = reset($days);
    $empty = $first->format('w');

    $markup = array();

    $empty_box = phutil_tag(
      'div',
      array('class' => 'phui-calendar-day phui-calendar-empty'),
      '');

    for ($ii = 0; $ii < $empty; $ii++) {
      $markup[] = $empty_box;
    }

    $show_events = array();

    foreach ($days as $day) {
      $day_number = $day->format('j');

      $holiday = idx($this->holidays, $day->format('Y-m-d'));
      $class = 'phui-calendar-day';
      $weekday = $day->format('w');

      if ($day_number == $this->day) {
        $class .= ' phui-calendar-today';
      }

      if ($holiday || $weekday == 0 || $weekday == 6) {
        $class .= ' phui-calendar-not-work-day';
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
          phutil_tag_div(
            'phui-calendar-event phui-calendar-event-empty',
            "\xC2\xA0")); // &nbsp;
      }

      $list_events = array();
      foreach ($events as $event) {
        if ($event->getEpochStart() >= $epoch_end) {
          // This list is sorted, so we can stop looking.
          break;
        }
        if ($event->getEpochStart() < $epoch_end &&
            $event->getEpochEnd() > $epoch_start) {
          $list_events[] = $event;
        }
      }

      $list = new PHUICalendarListView();
      $list->setUser($this->user);
      foreach ($list_events as $item) {
        $list->addEvent($item);
      }

      $holiday_markup = null;
      if ($holiday) {
        $name = $holiday->getName();
        $holiday_markup = phutil_tag(
          'div',
          array(
            'class' => 'phui-calendar-holiday',
            'title' => $name,
          ),
          $name);
      }

      $markup[] = phutil_tag_div(
        $class,
        array(
          phutil_tag_div('phui-calendar-date-number', $day_number),
          $holiday_markup,
          $list,
        ));
    }

    $table = array();
    $rows = array_chunk($markup, 7);
    foreach ($rows as $row) {
      $cells = array();
      while (count($row) < 7) {
        $row[] = $empty_box;
      }
      $j = 0;
      foreach ($row as $cell) {
        if ($j == 0) {
          $cells[] = phutil_tag(
            'td',
            array(
              'class' => 'phui-calendar-month-weekstart'),
            $cell);
        } else {
          $cells[] = phutil_tag('td', array(), $cell);
        }
        $j++;
      }
      $table[] = phutil_tag('tr', array(), $cells);
    }

    $header = phutil_tag(
      'tr',
      array('class' => 'phui-calendar-day-of-week-header'),
      array(
        phutil_tag('th', array(), pht('Sun')),
        phutil_tag('th', array(), pht('Mon')),
        phutil_tag('th', array(), pht('Tue')),
        phutil_tag('th', array(), pht('Wed')),
        phutil_tag('th', array(), pht('Thu')),
        phutil_tag('th', array(), pht('Fri')),
        phutil_tag('th', array(), pht('Sat')),
      ));

    $table = phutil_tag(
      'table',
      array('class' => 'phui-calendar-view'),
      array(
        $header,
        phutil_implode_html("\n", $table),
      ));

    $box = id(new PHUIObjectBoxView())
      ->setHeader($this->renderCalendarHeader($first))
      ->appendChild($table);

    return $box;
  }

  private function renderCalendarHeader(DateTime $date) {
    $button_bar = null;

    // check for a browseURI, which means we need "fancy" prev / next UI
    $uri = $this->getBrowseURI();
    if ($uri) {
      $uri = new PhutilURI($uri);
      list($prev_year, $prev_month) = $this->getPrevYearAndMonth();
      $query = array('year' => $prev_year, 'month' => $prev_month);
      $prev_uri = (string) $uri->setQueryParams($query);

      list($next_year, $next_month) = $this->getNextYearAndMonth();
      $query = array('year' => $next_year, 'month' => $next_month);
      $next_uri = (string) $uri->setQueryParams($query);

      $button_bar = new PHUIButtonBarView();

      $left_icon = id(new PHUIIconView())
          ->setIconFont('fa-chevron-left bluegrey');
      $left = id(new PHUIButtonView())
        ->setTag('a')
        ->setColor(PHUIButtonView::GREY)
        ->setHref($prev_uri)
        ->setTitle(pht('Previous Month'))
        ->setIcon($left_icon);

      $right_icon = id(new PHUIIconView())
          ->setIconFont('fa-chevron-right bluegrey');
      $right = id(new PHUIButtonView())
        ->setTag('a')
        ->setColor(PHUIButtonView::GREY)
        ->setHref($next_uri)
        ->setTitle(pht('Next Month'))
        ->setIcon($right_icon);

      $button_bar->addButton($left);
      $button_bar->addButton($right);

    }

    $header = id(new PHUIHeaderView())
      ->setHeader($date->format('F Y'));

    if ($button_bar) {
      $header->setButtonBar($button_bar);
    }

    if ($this->image) {
      $header->setImage($this->image);
    }

    return $header;
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

}
