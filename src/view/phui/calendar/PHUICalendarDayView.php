<?php

final class PHUICalendarDayView extends AphrontView {
  private $rangeStart;
  private $rangeEnd;

  private $day;
  private $month;
  private $year;
  private $browseURI;
  private $events = array();
  private $todayEvents = array();

  private $allDayEvents = array();

  public function addEvent(AphrontCalendarEventView $event) {
    $this->events[] = $event;
    return $this;
  }

  public function setBrowseURI($browse_uri) {
    $this->browseURI = $browse_uri;
    return $this;
  }
  private function getBrowseURI() {
    return $this->browseURI;
  }

  public function __construct(
    $range_start,
    $range_end,
    $year,
    $month,
    $day = null) {

    $this->rangeStart = $range_start;
    $this->rangeEnd = $range_end;

    $this->day = $day;
    $this->month = $month;
    $this->year = $year;
  }

  public function render() {
    require_celerity_resource('phui-calendar-day-css');

    $hours = $this->getHoursOfDay();
    $hourly_events = array();

    $first_event_hour = null;

    $all_day_events = $this->getAllDayEvents();
    $today_all_day_events = array();

    $day_start = $this->getDateTime();
    $day_end = id(clone $day_start)->modify('+1 day');

    $day_start = $day_start->format('U');
    $day_end = $day_end->format('U') - 1;

    foreach ($all_day_events as $all_day_event) {
      $all_day_start = $all_day_event->getEpochStart();
      $all_day_end = $all_day_event->getEpochEnd();

      if ($all_day_start < $day_end && $all_day_end > $day_start) {
        $today_all_day_events[] = $all_day_event;
      }
    }

    foreach ($hours as $hour) {
      $current_hour_events = array();
      $hour_start = $hour->format('U');
      $hour_end = id(clone $hour)->modify('+1 hour')->format('U');

      foreach ($this->events as $event) {
          if ($event->getIsAllDay()) {
            continue;
          }
        if ($event->getEpochStart() >= $hour_start
          && $event->getEpochStart() < $hour_end) {
          $current_hour_events[] = $event;
          $this->todayEvents[] = $event;
        }
      }
      foreach ($current_hour_events as $event) {
        $event_start = $event->getEpochStart();
        $event_end = min($event->getEpochEnd(), $day_end);

        $top = (($event_start - $hour_start) / ($hour_end - $hour_start))
          * 100;
        $top = max(0, $top);

        $height = (($event_end - $event_start) / ($hour_end - $hour_start))
          * 100;
        $height = min(2400, $height);

        if ($first_event_hour === null) {
          $first_event_hour = $hour;
        }

        $hourly_events[$event->getEventID()] = array(
          'hour' => $hour,
          'event' => $event,
          'offset' => '0',
          'width' => '100%',
          'top' => $top.'%',
          'height' => $height.'%',
        );
      }
    }

    $clusters = $this->findTodayClusters();
    foreach ($clusters as $cluster) {
      $hourly_events = $this->updateEventsFromCluster(
        $cluster,
        $hourly_events);
    }

    $rows = array();

    foreach ($hours as $hour) {
      $early_hours = array(8);
      if ($first_event_hour) {
        $early_hours[] = $first_event_hour->format('G');
      }
      if ($hour->format('G') < min($early_hours)) {
        continue;
      }

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
      $rows);

    $all_day_event_box = new PHUIBoxView();
    foreach ($today_all_day_events as $all_day_event) {
      $all_day_event_box->appendChild(
        $this->drawAllDayEvent($all_day_event));
    }

    $header = $this->renderDayViewHeader();
    $sidebar = $this->renderSidebar();
    $warnings = $this->getQueryRangeWarning();

    $table_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->appendChild($all_day_event_box)
      ->appendChild($table)
      ->setFormErrors($warnings)
      ->setFlush(true);

    $layout = id(new AphrontMultiColumnView())
      ->addColumn($sidebar, 'third')
      ->addColumn($table_box, 'thirds')
      ->setFluidLayout(true)
      ->setGutter(AphrontMultiColumnView::GUTTER_MEDIUM);

    return phutil_tag(
      'div',
        array(
          'class' => 'ml',
        ),
        $layout);
  }

  private function getAllDayEvents() {
    $all_day_events = array();

    foreach ($this->events as $event) {
      if ($event->getIsAllDay()) {
        $all_day_events[] = $event;
      }
    }

    $all_day_events = array_values(msort($all_day_events, 'getEpochStart'));
    return $all_day_events;
  }

  private function getQueryRangeWarning() {
    $errors = array();

    $range_start_epoch = $this->rangeStart->getEpoch();
    $range_end_epoch = $this->rangeEnd->getEpoch();

    $day_start = $this->getDateTime();
    $day_end = id(clone $day_start)->modify('+1 day');

    $day_start = $day_start->format('U');
    $day_end = $day_end->format('U') - 1;

    if (($range_start_epoch != null &&
        $range_start_epoch < $day_end &&
        $range_start_epoch > $day_start) ||
      ($range_end_epoch != null &&
        $range_end_epoch < $day_end &&
        $range_end_epoch > $day_start)) {
      $errors[] = pht('Part of the day is out of range');
    }

    if (($this->rangeEnd->getEpoch() != null &&
        $this->rangeEnd->getEpoch() < $day_start) ||
      ($this->rangeStart->getEpoch() != null &&
        $this->rangeStart->getEpoch() > $day_end)) {
      $errors[] = pht('Day is out of query range');
    }
    return $errors;
  }

  private function renderSidebar() {
    $this->events = msort($this->events, 'getEpochStart');
    $week_of_boxes = $this->getWeekOfBoxes();
    $filled_boxes = array();

    foreach ($week_of_boxes as $day_box) {
      $box_start = $day_box['start'];
      $box_end = id(clone $box_start)->modify('+1 day');

      $box_start = $box_start->format('U');
      $box_end = $box_end->format('U');

      $box_events = array();

      foreach ($this->events as $event) {
        $event_start = $event->getEpochStart();
        $event_end = $event->getEpochEnd();

        if ($event_start < $box_end && $event_end > $box_start) {
          $box_events[] = $event;
        }
      }

      $filled_boxes[] = $this->renderSidebarBox(
        $box_events,
        $day_box['title']);
    }

    return $filled_boxes;
  }

  private function renderSidebarBox($events, $title) {
    $widget = id(new PHUICalendarWidgetView())
      ->addClass('calendar-day-view-sidebar');

    $list = id(new PHUICalendarListView())
      ->setUser($this->user);

    if (count($events) == 0) {
      $list->showBlankState(true);
    } else {
      $sorted_events = msort($events, 'getEpochStart');
      foreach ($sorted_events as $event) {
        $list->addEvent($event);
      }
    }

    $widget
      ->setCalendarList($list)
      ->setHeader($title);
    return $widget;
  }

  private function getWeekOfBoxes() {
    $sidebar_day_boxes = array();

    $display_start_day = $this->getDateTime();
    $display_end_day = id(clone $display_start_day)->modify('+6 day');

    $box_start_time = clone $display_start_day;

    $today_time = PhabricatorTime::getTodayMidnightDateTime($this->user);
    $tomorrow_time = clone $today_time;
    $tomorrow_time->modify('+1 day');

    while ($box_start_time <= $display_end_day) {
      if ($box_start_time == $today_time) {
        $title = pht('Today');
      } else if ($box_start_time == $tomorrow_time) {
        $title = pht('Tomorrow');
      } else {
        $title = $box_start_time->format('l');
      }

      $sidebar_day_boxes[] = array(
        'title' => $title,
        'start' => clone $box_start_time,
        );

      $box_start_time->modify('+1 day');
    }
    return $sidebar_day_boxes;
  }

  private function renderDayViewHeader() {
    $button_bar = null;
    $uri = $this->getBrowseURI();
    if ($uri) {
      list($prev_year, $prev_month, $prev_day) = $this->getPrevDay();
      $prev_uri = $uri.$prev_year.'/'.$prev_month.'/'.$prev_day.'/';

      list($next_year, $next_month, $next_day) = $this->getNextDay();
      $next_uri = $uri.$next_year.'/'.$next_month.'/'.$next_day.'/';

      $button_bar = new PHUIButtonBarView();

      $left_icon = id(new PHUIIconView())
          ->setIconFont('fa-chevron-left bluegrey');
      $left = id(new PHUIButtonView())
        ->setTag('a')
        ->setColor(PHUIButtonView::GREY)
        ->setHref($prev_uri)
        ->setTitle(pht('Previous Day'))
        ->setIcon($left_icon);

      $right_icon = id(new PHUIIconView())
          ->setIconFont('fa-chevron-right bluegrey');
      $right = id(new PHUIButtonView())
        ->setTag('a')
        ->setColor(PHUIButtonView::GREY)
        ->setHref($next_uri)
        ->setTitle(pht('Next Day'))
        ->setIcon($right_icon);

      $button_bar->addButton($left);
      $button_bar->addButton($right);

    }

    $display_day = $this->getDateTime();
    $header_text = $display_day->format('l, F j, Y');

    $header = id(new PHUIHeaderView())
      ->setHeader($header_text);

    if ($button_bar) {
      $header->setButtonBar($button_bar);
    }

    return $header;
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

  private function drawAllDayEvent(AphrontCalendarEventView $event) {
    $name = phutil_tag(
      'a',
      array(
        'class' => 'day-view-all-day',
        'href' => $event->getURI(),
      ),
      $event->getName());

    $all_day_label = phutil_tag(
      'span',
      array(
        'class' => 'phui-calendar-all-day-label',
      ),
      pht('All Day'));

    $div = phutil_tag(
      'div',
      array(
        'class' => 'phui-calendar-day-event',
      ),
      array(
        $all_day_label,
        $name,
      ));

    return $div;
  }

  private function drawEvent(
    AphrontCalendarEventView $event,
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

  private function getPrevDay() {
    $prev = $this->getDateTime();
    $prev->modify('-1 day');
    return array(
      $prev->format('Y'),
      $prev->format('m'),
      $prev->format('d'),
    );
  }

  private function getNextDay() {
    $next = $this->getDateTime();
    $next->modify('+1 day');
    return array(
      $next->format('Y'),
      $next->format('m'),
      $next->format('d'),
    );
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

  private function findTodayClusters() {
    $events = msort($this->todayEvents, 'getEpochStart');
    $clusters = array();

    foreach ($events as $event) {
      $destination_cluster_key = null;
      $event_start = $event->getEpochStart() - (30 * 60);
      $event_end = $event->getEpochEnd() + (30 * 60);

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
