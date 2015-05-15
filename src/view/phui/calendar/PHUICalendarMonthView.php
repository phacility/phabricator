<?php

final class PHUICalendarMonthView extends AphrontView {
  private $rangeStart;
  private $rangeEnd;

  private $day;
  private $month;
  private $year;
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
      throw new PhutilInvalidStateException('setUser');
    }

    $events = msort($this->events, 'getEpochStart');
    $days = $this->getDatesInMonth();

    $cell_lists = array();
    $empty_cell = array(
        'list' => null,
        'date' => null,
        'uri' => null,
        'count' => 0,
        'class' => null,
      );

    require_celerity_resource('phui-calendar-month-css');

    $first = reset($days);
    $start_of_week = 0;

    $empty = $first->format('w');

    for ($ii = 0; $ii < $empty; $ii++) {
      $cell_lists[] = $empty_cell;
    }

    foreach ($days as $day) {
      $day_number = $day->format('j');

      $class = 'phui-calendar-month-day';
      $weekday = $day->format('w');

      $day->setTime(0, 0, 0);
      $day_start_epoch = $day->format('U');
      $day_end_epoch = id(clone $day)->modify('+1 day')->format('U');

      $list_events = array();
      $all_day_events = array();

      foreach ($events as $event) {
        if ($event->getEpochStart() >= $day_end_epoch) {
          break;
        }
        if ($event->getEpochStart() < $day_end_epoch &&
            $event->getEpochEnd() > $day_start_epoch) {
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

      $uri = $this->getBrowseURI();
      $uri = $uri.$day->format('Y').'/'.
        $day->format('m').'/'.
        $day->format('d').'/';

      $cell_lists[] = array(
        'list' => $list,
        'date' => $day,
        'uri' => $uri,
        'count' => count($all_day_events) + count($list_events),
        'class' => $class,
        );
    }

    $rows = array();
    $cell_lists_by_week = array_chunk($cell_lists, 7);

    foreach ($cell_lists_by_week as $week_of_cell_lists) {
      $cells = array();
      while (count($week_of_cell_lists) < 7) {
        $week_of_cell_lists[] = $empty_cell;
      }
      foreach ($week_of_cell_lists as $cell_list) {
        $cells[] = $this->getEventListCell($cell_list);
      }
      $rows[] = phutil_tag('tr', array(), $cells);

      $cells = array();
      foreach ($week_of_cell_lists as $cell_list) {
        $cells[] = $this->getDayNumberCell($cell_list);
      }
      $rows[] = phutil_tag('tr', array(), $cells);
    }

    $header = $this->getDayNamesHeader();

    $table = phutil_tag(
      'table',
      array('class' => 'phui-calendar-view'),
      array(
        $header,
        $rows,
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

  private function getEventListCell($event_list) {
    $list = $event_list['list'];
    $class = $event_list['class'];
    $uri = $event_list['uri'];
    $count = $event_list['count'];

    $viewer_is_invited = $list->getIsViewerInvitedOnList();

    $event_count_badge = $this->getEventCountBadge($count, $viewer_is_invited);
    $cell_day_secret_link = $this->getHiddenDayLink($uri);

    $cell_data_div = phutil_tag(
      'div',
      array(
        'class' => 'phui-calendar-month-cell-div',
      ),
      array(
        $cell_day_secret_link,
        $event_count_badge,
        $list,
      ));

    return phutil_tag(
      'td',
      array(
        'class' => 'phui-calendar-month-event-list '.$class,
      ),
      $cell_data_div);
  }

  private function getDayNumberCell($event_list) {
    $class = $event_list['class'];
    $date = $event_list['date'];
    $cell_day_secret_link = null;

    if ($date) {
      $uri = $event_list['uri'];
      $cell_day_secret_link = $this->getHiddenDayLink($uri);

      $cell_day = phutil_tag(
        'a',
        array(
          'class' => 'phui-calendar-date-number',
          'href' => $uri,
        ),
        $date->format('j'));
    } else {
      $cell_day = null;
    }

    if ($date && $date->format('j') == $this->day) {
      $today_class = 'phui-calendar-today-slot phui-calendar-today';
    } else {
      $today_class = 'phui-calendar-today-slot';
    }

    $today_slot = phutil_tag (
      'div',
      array(
        'class' => $today_class,
      ),
      null);

    $cell_div = phutil_tag(
      'div',
      array(
        'class' => 'phui-calendar-month-cell-div',
      ),
      array(
        $cell_day_secret_link,
        $cell_day,
        $today_slot,
      ));

    return phutil_tag(
      'td',
      array(
        'class' => 'phui-calendar-date-number-container '.$class,
      ),
      $cell_div);
  }

  private function getEventCountBadge($count, $viewer_is_invited) {
    $class = 'phui-calendar-month-count-badge';

    if ($viewer_is_invited) {
      $class = $class.' viewer-invited-day-badge';
    }

    $event_count = null;
    if ($count > 0) {
      $event_count = phutil_tag(
        'div',
        array(
          'class' => $class,
        ),
        $count);
    }

    return phutil_tag(
      'div',
      array(
        'class' => 'phui-calendar-month-event-count',
      ),
      $event_count);
  }

  private function getHiddenDayLink($uri) {
    return phutil_tag(
      'a',
      array(
        'class' => 'phui-calendar-month-secret-link',
        'href' => $uri,
      ),
      null);
  }

  private function getDayNamesHeader() {
    return phutil_tag(
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

    list($next_year, $next_month) = $this->getNextYearAndMonth();
    $end_date = new DateTime("{$next_year}-{$next_month}-01", $timezone);

    $start_of_week = 0;
    $end_of_week = 6 - $start_of_week;
    $days_in_month = id(clone $end_date)->modify('-1 day')->format('d');

    $first_month_day_date = new DateTime("{$year}-{$month}-01", $timezone);
    $last_month_day_date = id(clone $end_date)->modify('-1 day');

    $first_weekday_of_month = $first_month_day_date->format('w');
    $last_weekday_of_month = $last_month_day_date->format('w');

    $num_days_display = $days_in_month;
    if ($start_of_week < $first_weekday_of_month) {
      $num_days_display += $first_weekday_of_month;
    }
    if ($end_of_week > $last_weekday_of_month) {
      $num_days_display += (6 - $last_weekday_of_month);
      $end_date->modify('+'.(6 - $last_weekday_of_month).' days');
    }

    $days = array();
    $day_date = id(clone $first_month_day_date)
      ->modify('-'.$first_weekday_of_month.' days');

    for ($day = 1; $day <= $num_days_display; $day++) {
      $day_epoch = $day_date->format('U');
      $end_epoch = $end_date->format('U');
      if ($day_epoch >= $end_epoch) {
        break;
      } else {
        $days[] = clone $day_date;
      }
      $day_date->modify('+1 day');
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
