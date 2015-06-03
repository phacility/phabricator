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

    require_celerity_resource('phui-calendar-month-css');

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

      $max_daily = 15;
      $counter = 0;

      $list = new PHUICalendarListView();
      $list->setUser($this->user);
      foreach ($all_day_events as $item) {
        if ($counter <= $max_daily) {
          $list->addEvent($item);
        }
        $counter++;
      }
      foreach ($list_events as $item) {
        if ($counter <= $max_daily) {
          $list->addEvent($item);
        }
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
      $max_count = $this->getMaxDailyEventsForWeek($week_of_cell_lists);

      foreach ($week_of_cell_lists as $cell_list) {
        $cells[] = $this->getEventListCell($cell_list, $max_count);
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
      ->setHeader($this->renderCalendarHeader($this->getDateTime()))
      ->appendChild($table)
      ->setFormErrors($warnings);
    if ($this->error) {
      $box->setInfoView($this->error);

    }

    return $box;
  }

  private function getMaxDailyEventsForWeek($week_of_cell_lists) {
    $max_count = 0;

    foreach ($week_of_cell_lists as $cell_list) {
      if ($cell_list['count'] > $max_count) {
        $max_count = $cell_list['count'];
      }
    }

    return $max_count;
  }

  private function getEventListCell($event_list, $max_count = 0) {
    $list = $event_list['list'];
    $class = $event_list['class'];
    $uri = $event_list['uri'];
    $count = $event_list['count'];

    $viewer_is_invited = $list->getIsViewerInvitedOnList();

    $event_count_badge = $this->getEventCountBadge($count, $viewer_is_invited);
    $cell_day_secret_link = $this->getHiddenDayLink($uri, $max_count, 125);

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
    $week_number = null;

    if ($date) {
      $uri = $event_list['uri'];
      $cell_day_secret_link = $this->getHiddenDayLink($uri, 0, 25);

      $cell_day = phutil_tag(
        'a',
        array(
          'class' => 'phui-calendar-date-number',
          'href' => $uri,
        ),
        $date->format('j'));

      if ($date->format('w') == 1) {
        $week_number = phutil_tag(
          'a',
          array(
            'class' => 'phui-calendar-week-number',
            'href' => $uri,
          ),
          $date->format('W'));
      }
    } else {
      $cell_day = null;
    }

    if ($date && $date->format('j') == $this->day &&
      $date->format('m') == $this->month) {
      $today_class = 'phui-calendar-today-slot phui-calendar-today';
    } else {
      $today_class = 'phui-calendar-today-slot';
    }

    if ($this->isDateInCurrentWeek($date)) {
      $today_class .= ' phui-calendar-this-week';
    }

    $last_week_day = 6;
    if ($date->format('w') == $last_week_day) {
      $today_class .= ' last-weekday';
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
        $week_number,
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

  private function isDateInCurrentWeek($date) {
    list($week_start_date, $week_end_date) = $this->getThisWeekRange();

    if ($date->format('U') < $week_end_date->format('U') &&
      $date->format('U') >= $week_start_date->format('U')) {
      return true;
    }
    return false;
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

  private function getHiddenDayLink($uri, $count, $max_height) {
    // approximately the height of the tallest cell
    $height = 18 * $count + 5;
    $height = ($height > $max_height) ? $height : $max_height;
    $height_style = 'height: '.$height.'px';
    return phutil_tag(
      'a',
      array(
        'class' => 'phui-calendar-month-secret-link',
        'style' => $height_style,
        'href' => $uri,
      ),
      null);
  }

  private function getDayNamesHeader() {
    list($week_start, $week_end) = $this->getWeekStartAndEnd();

    $weekday_names = array(
      $this->getDayHeader(pht('Sun'), pht('Sunday'), true),
      $this->getDayHeader(pht('Mon'), pht('Monday')),
      $this->getDayHeader(pht('Tue'), pht('Tuesday')),
      $this->getDayHeader(pht('Wed'), pht('Wednesday')),
      $this->getDayHeader(pht('Thu'), pht('Thursday')),
      $this->getDayHeader(pht('Fri'), pht('Friday')),
      $this->getDayHeader(pht('Sat'), pht('Saturday'), true),
    );

    $sorted_weekday_names = array();

    for ($i = $week_start; $i < ($week_start + 7); $i++) {
      $sorted_weekday_names[] = $weekday_names[$i % 7];
    }

    return phutil_tag(
      'tr',
      array('class' => 'phui-calendar-day-of-week-header'),
      $sorted_weekday_names);
  }

  private function getDayHeader($short, $long, $is_weekend = false) {
    $class = null;
    if ($is_weekend) {
      $class = 'weekend-day-header';
    }
    $day = array();
    $day[] = phutil_tag(
      'span',
      array(
        'class' => 'long-weekday-name',
      ),
      $long);
    $day[] = phutil_tag(
      'span',
      array(
        'class' => 'short-weekday-name',
      ),
      $short);
    return phutil_tag(
      'th',
      array(
        'class' => $class,
      ),
      $day);
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

    list($start_of_week, $end_of_week) = $this->getWeekStartAndEnd();

    $days_in_month = id(clone $end_date)->modify('-1 day')->format('d');

    $first_month_day_date = new DateTime("{$year}-{$month}-01", $timezone);
    $last_month_day_date = id(clone $end_date)->modify('-1 day');

    $first_weekday_of_month = $first_month_day_date->format('w');
    $last_weekday_of_month = $last_month_day_date->format('w');

    $day_date = id(clone $first_month_day_date);

    $num_days_display = $days_in_month;
    if ($start_of_week !== $first_weekday_of_month) {
      $interim_start_num = ($first_weekday_of_month + 7 - $start_of_week) % 7;
      $num_days_display += $interim_start_num;

      $day_date->modify('-'.$interim_start_num.' days');
    }
    if ($end_of_week !== $last_weekday_of_month) {
      $interim_end_day_num = ($end_of_week - $last_weekday_of_month + 7) % 7;
      $num_days_display += $interim_end_day_num;
      $end_date->modify('+'.$interim_end_day_num.' days');
    }

    $days = array();

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

  private function getTodayMidnight() {
    $viewer = $this->getUser();
    $today = new DateTime('@'.time());
    $today->setTimeZone($viewer->getTimeZone());
    $today->setTime(0, 0, 0);

    return $today;
  }

  private function getThisWeekRange() {
    list($week_start, $week_end) = $this->getWeekStartAndEnd();

    $today = $this->getTodayMidnight();
    $date_weekday = $today->format('w');

    $days_from_week_start = ($date_weekday + 7 - $week_start) % 7;
    $days_to_week_end = 7 - $days_from_week_start;

    $modify = '-'.$days_from_week_start.' days';
    $week_start_date = id(clone $today)->modify($modify);

    $modify = '+'.$days_to_week_end.' days';
    $week_end_date = id(clone $today)->modify($modify);

    return array($week_start_date, $week_end_date);
  }

  private function getWeekStartAndEnd() {
    $preferences = $this->user->loadPreferences();
    $pref_week_start = PhabricatorUserPreferences::PREFERENCE_WEEK_START_DAY;

    $week_start = $preferences->getPreference($pref_week_start, 0);
    $week_end = ($week_start + 6) % 7;

    return array($week_start, $week_end);
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
