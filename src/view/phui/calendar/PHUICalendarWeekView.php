<?php

final class PHUICalendarWeekView extends AphrontView {
  private $events;
  private $dateTime;
  private $weekLength = 7;
  private $view = 'day';

  public function setEvents($events) {
    $this->events = $events;
    return $this;
  }

  public function setDateTime($date_time) {
    $this->dateTime = $date_time;
    return $this;
  }

  private function getDateTime() {
    if ($this->dateTime) {
      return $this->dateTime;
    }
    return $this->getDefaultDateTime();
  }

  public function setWeekLength($week_length) {
    $this->weekLength = $week_length;
    return $this;
  }

  public function setView($view) {
    $this->view = $view;
    return $this;
  }

  private function getView() {
    return $this->view;
  }

  public function render() {
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
      ->setUser($this->getViewer())
      ->setView($this->getView());

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
    $day_boxes = array();
    $week_length = $this->weekLength - 1;

    $display_start_day = $this->getDateTime();
    $display_end_day = id(clone $display_start_day)
      ->modify('+'.$week_length.' day');

    $box_start_time = clone $display_start_day;

    $today_time = PhabricatorTime::getTodayMidnightDateTime($this->getViewer());
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

      $day_boxes[] = array(
        'title' => $title,
        'start' => clone $box_start_time,
        );

      $box_start_time->modify('+1 day');
    }
    return $day_boxes;
  }

  private function getDefaultDateTime() {
    return PhabricatorTime::getTodayMidnightDateTime($this->getViewer());
  }

}
