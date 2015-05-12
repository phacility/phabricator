<?php

final class PHUICalendarMonthView extends AphrontView {
  private $rangeStart;
  private $rangeEnd;

  private $day;
  private $month;
  private $year;
  private $holidays = array();
  private $events   = array();
  private $browseURI;
  private $image;
  private $error;

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

  public function setInfoView(PHUIInfoView $error) {
    $this->error = $error;
    return $this;
  }

  public function setHolidays(array $holidays) {
    assert_instances_of($holidays, 'PhabricatorCalendarHoliday');
    $this->holidays = mpull($holidays, null, 'getDay');
    return $this;
  }

  public function __construct(
    $range_start,
    $range_end,
    $month,
    $year,
    $day = null) {

    $this->rangeStart = $range_start;
    $this->rangeEnd = $range_end;

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
    $empty_cell = array(
        'list' => null,
        'date' => null,
        'class' => 'phui-calendar-empty',
      );

    for ($ii = 0; $ii < $empty; $ii++) {
      $markup[] = $empty_cell;
    }

    $show_events = array();

    foreach ($days as $day) {
      $day_number = $day->format('j');

      $holiday = idx($this->holidays, $day->format('Y-m-d'));
      $class = 'phui-calendar-month-day';
      $weekday = $day->format('w');

      if ($day_number == $this->day) {
        $class .= ' phui-calendar-today';
      }

      if ($holiday || $weekday == 0 || $weekday == 6) {
        $class .= ' phui-calendar-not-work-day';
      }

      $day->setTime(0, 0, 0);
      $epoch_start = $day->format('U');


      $epoch_end = id(clone $day)->modify('+1 day')->format('U');

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
      $all_day_events = array();
      foreach ($events as $event) {
        if ($event->getEpochStart() >= $epoch_end) {
          // This list is sorted, so we can stop looking.
          break;
        }
        if ($event->getEpochStart() < $epoch_end &&
            $event->getEpochEnd() > $epoch_start) {
          if ($event->getIsAllDay()) {
            $all_day_events[] = $event;
          } else {
            $list_events[] = $event;
          }
        }
      }

      $list = new PHUICalendarListView();
      $list->setUser($this->user);
      foreach ($all_day_events as $item) {
        $list->addEvent($item);
      }
      foreach ($list_events as $item) {
        $list->addEvent($item);
      }

      $markup[] = array(
        'list' => $list,
        'date' => $day,
        'class' => $class,
        );
    }

    $table = array();
    $rows = array_chunk($markup, 7);

    foreach ($rows as $row) {
      $cells = array();
      while (count($row) < 7) {
        $row[] = $empty_cell;
      }
      foreach ($row as $cell) {
        $cell_list = $cell['list'];
        $class = $cell['class'];
        $cells[] = phutil_tag(
          'td',
          array(
            'class' => 'phui-calendar-month-event-list '.$class,
          ),
          $cell_list);
      }
      $table[] = phutil_tag('tr', array(), $cells);

      $cells = array();
      foreach ($row as $cell) {
        $class = $cell['class'];

        if ($cell['date']) {
          $cell_day = $cell['date'];

          $uri = $this->getBrowseURI();
          $date = $cell['date'];
          $uri = $uri.$date->format('Y').'/'.
            $date->format('m').'/'.
            $date->format('d').'/';

          $cell_day = phutil_tag(
            'a',
            array(
              'class' => 'phui-calendar-date-number',
              'href' => $uri,
            ),
            $cell_day->format('j'));
        } else {
          $cell_day = null;
        }

        $cells[] = phutil_tag(
          'td',
          array(
            'class' => 'phui-calendar-date-number-container '.$class,
          ),
          $cell_day);
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

    $warnings = $this->getQueryRangeWarning();

    $box = id(new PHUIObjectBoxView())
      ->setHeader($this->renderCalendarHeader($first))
      ->appendChild($table)
      ->setFormErrors($warnings);
    if ($this->error) {
      $box->setInfoView($this->error);

    }

    return $box;
  }

  private function renderCalendarHeader(DateTime $date) {
    $button_bar = null;

    // check for a browseURI, which means we need "fancy" prev / next UI
    $uri = $this->getBrowseURI();
    if ($uri) {
      list($prev_year, $prev_month) = $this->getPrevYearAndMonth();
      $prev_uri = $uri.$prev_year.'/'.$prev_month.'/';

      list($next_year, $next_month) = $this->getNextYearAndMonth();
      $next_uri = $uri.$next_year.'/'.$next_month.'/';

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

  private function getQueryRangeWarning() {
    $errors = array();

    $range_start_epoch = $this->rangeStart->getEpoch();
    $range_end_epoch = $this->rangeEnd->getEpoch();

    $month_start = $this->getDateTime();
    $month_end = id(clone $month_start)->modify('+1 month');

    $month_start = $month_start->format('U');
    $month_end = $month_end->format('U') - 1;

    if (($range_start_epoch != null &&
        $range_start_epoch < $month_end &&
        $range_start_epoch > $month_start) ||
      ($range_end_epoch != null &&
        $range_end_epoch < $month_end &&
        $range_end_epoch > $month_start)) {
      $errors[] = pht('Part of the month is out of range');
    }

    if (($this->rangeEnd->getEpoch() != null &&
        $this->rangeEnd->getEpoch() < $month_start) ||
      ($this->rangeStart->getEpoch() != null &&
        $this->rangeStart->getEpoch() > $month_end)) {
      $errors[] = pht('Month is out of query range');
    }

    return $errors;
  }

  private function getNextYearAndMonth() {
    $next = $this->getDateTime();
    $next->modify('+1 month');
    return array(
      $next->format('Y'),
      $next->format('m'),
    );
  }

  private function getPrevYearAndMonth() {
    $prev = $this->getDateTime();
    $prev->modify('-1 month');
    return array(
      $prev->format('Y'),
      $prev->format('m'),
    );
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

  private function getDateTime() {
    $user = $this->user;
    $timezone = new DateTimeZone($user->getTimezoneIdentifier());

    $month = $this->month;
    $year = $this->year;

    $date = new DateTime("{$year}-{$month}-01 ", $timezone);

    return $date;
  }
}
