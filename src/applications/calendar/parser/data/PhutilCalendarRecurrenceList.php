<?php

final class PhutilCalendarRecurrenceList
  extends PhutilCalendarRecurrenceSource {

  private $dates = array();
  private $order;

  public function setDates(array $dates) {
    assert_instances_of($dates, 'PhutilCalendarDateTime');
    $this->dates = $dates;
    return $this;
  }

  public function getDates() {
    return $this->dates;
  }

  public function resetSource() {
    foreach ($this->getDates() as $date) {
      $date->setViewerTimezone($this->getViewerTimezone());
    }

    $order = msort($this->getDates(), 'getEpoch');
    $order = array_reverse($order);
    $this->order = $order;

    return $this;
  }

  public function getNextEvent($cursor) {
    while ($this->order) {
      $next = array_pop($this->order);
      if ($next->getEpoch() >= $cursor) {
        return $next;
      }
    }

    return null;
  }


}
