<?php

abstract class PhutilCalendarDateTime
  extends Phobject {

  private $viewerTimezone;
  private $isAllDay = false;

  public function setViewerTimezone($viewer_timezone) {
    $this->viewerTimezone = $viewer_timezone;
    return $this;
  }

  public function getViewerTimezone() {
    return $this->viewerTimezone;
  }

  public function setIsAllDay($is_all_day) {
    $this->isAllDay = $is_all_day;
    return $this;
  }

  public function getIsAllDay() {
    return $this->isAllDay;
  }

  public function getEpoch() {
    $datetime = $this->newPHPDateTime();
    return (int)$datetime->format('U');
  }

  public function getISO8601() {
    $datetime = $this->newPHPDateTime();

    if ($this->getIsAllDay()) {
      return $datetime->format('Ymd');
    } else if ($this->getTimezone()) {
      // With a timezone, the event occurs at a specific second universally.
      // We return the UTC representation of that point in time.
      $datetime->setTimezone(new DateTimeZone('UTC'));
      return $datetime->format('Ymd\\THis\\Z');
    } else {
      // With no timezone, events are "floating" and occur at local time.
      // We return a representation without the "Z".
      return $datetime->format('Ymd\\THis');
    }
  }

  abstract public function newPHPDateTimeZone();
  abstract public function newPHPDateTime();
  abstract public function newAbsoluteDateTime();

  abstract public function getTimezone();
}
