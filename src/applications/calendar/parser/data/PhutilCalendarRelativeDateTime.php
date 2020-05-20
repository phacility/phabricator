<?php

final class PhutilCalendarRelativeDateTime
  extends PhutilCalendarProxyDateTime {

  private $duration;

  public function setOrigin(PhutilCalendarDateTime $origin) {
    return $this->setProxy($origin);
  }

  public function getOrigin() {
    return $this->getProxy();
  }

  public function setDuration(PhutilCalendarDuration $duration) {
    $this->duration = $duration;
    return $this;
  }

  public function getDuration() {
    return $this->duration;
  }

  public function newPHPDateTime() {
    $datetime = parent::newPHPDateTime();
    $duration = $this->getDuration();

    if ($duration->getIsNegative()) {
      $sign = '-';
    } else {
      $sign = '+';
    }

    $map = array(
      'weeks' => $duration->getWeeks(),
      'days' => $duration->getDays(),
      'hours' => $duration->getHours(),
      'minutes' => $duration->getMinutes(),
      'seconds' => $duration->getSeconds(),
    );

    foreach ($map as $unit => $value) {
      if (!$value) {
        continue;
      }
      $datetime->modify("{$sign}{$value} {$unit}");
    }

    return $datetime;
  }

  public function newAbsoluteDateTime() {
    $clone = clone $this;

    if ($clone->getTimezone()) {
      $clone->setViewerTimezone(null);
    }

    $datetime = $clone->newPHPDateTime();

    return id(new PhutilCalendarAbsoluteDateTime())
      ->setYear((int)$datetime->format('Y'))
      ->setMonth((int)$datetime->format('m'))
      ->setDay((int)$datetime->format('d'))
      ->setHour((int)$datetime->format('H'))
      ->setMinute((int)$datetime->format('i'))
      ->setSecond((int)$datetime->format('s'))
      ->setIsAllDay($clone->getIsAllDay())
      ->setTimezone($clone->getTimezone())
      ->setViewerTimezone($this->getViewerTimezone());
  }

}
