<?php

abstract class PhutilCalendarRecurrenceSource
  extends Phobject {

  private $isExceptionSource;
  private $viewerTimezone;

  public function setIsExceptionSource($is_exception_source) {
    $this->isExceptionSource = $is_exception_source;
    return $this;
  }

  public function getIsExceptionSource() {
    return $this->isExceptionSource;
  }

  public function setViewerTimezone($viewer_timezone) {
    $this->viewerTimezone = $viewer_timezone;
    return $this;
  }

  public function getViewerTimezone() {
    return $this->viewerTimezone;
  }

  public function resetSource() {
    return;
  }

  abstract public function getNextEvent($cursor);


}
