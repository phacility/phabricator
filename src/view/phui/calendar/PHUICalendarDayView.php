<?php

final class PHUICalendarDayView extends AphrontView {

  private $day;
  private $month;
  private $year;
  private $browseURI;
  private $events = array();

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

  public function __construct($year, $month, $day = null) {
    $this->day = $day;
    $this->month = $month;
    $this->year = $year;
  }

  public function render() {
    require_celerity_resource('phui-calendar-day-css');

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

    $header = $this->renderDayViewHeader();
    $sidebar = $this->renderSidebar();

    $table_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->appendChild($table)
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

  private function renderSidebar() {
    $this->events = msort($this->events, 'getEpochStart');
    $week_of_boxes = $this->getWeekOfBoxes();
    $filled_boxes = array();

    foreach ($week_of_boxes as $weekly_box) {
      $start = $weekly_box['start'];
      $end = id(clone $start)->modify('+1 day');

      $box_events = array();

      foreach ($this->events as $event) {
        if ($event->getEpochStart() >= $start->format('U') &&
        $event->getEpochStart() < $end->format('U')) {
          $box_events[] = $event;
        }
      }
      $filled_boxes[] = $this->renderSidebarBox(
        $box_events,
        $weekly_box['title']);
    }

    return $filled_boxes;
  }

  private function renderSidebarBox($events, $title) {
    $widget = new PHUICalendarWidgetView();

    $list = id(new PHUICalendarListView())
      ->setUser($this->user);

    if (count($events) == 0) {
      $list->showBlankState(true);
    } else {
      foreach ($events as $event) {
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

    // check for a browseURI, which means we need "fancy" prev / next UI
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

    $day_of_week = $this->getDayOfWeek();
    $header_text = $this->getDateTime()->format('F j, Y');
    $header_text = $day_of_week.', '.$header_text;

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
