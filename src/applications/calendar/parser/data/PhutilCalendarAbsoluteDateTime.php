<?php

final class PhutilCalendarAbsoluteDateTime
  extends PhutilCalendarDateTime {

  private $year;
  private $month;
  private $day;
  private $hour = 0;
  private $minute = 0;
  private $second = 0;
  private $timezone;

  public static function newFromISO8601($value, $timezone = 'UTC') {
    $pattern =
      '/^'.
      '(?P<y>\d{4})(?P<m>\d{2})(?P<d>\d{2})'.
      '(?:'.
        'T(?P<h>\d{2})(?P<i>\d{2})(?P<s>\d{2})(?<z>Z)?'.
      ')?'.
      '\z/';

    $matches = null;
    $ok = preg_match($pattern, $value, $matches);
    if (!$ok) {
      throw new Exception(
        pht(
          'Expected ISO8601 datetime in the format "19990105T112233Z", '.
          'found "%s".',
          $value));
    }

    if (isset($matches['z'])) {
      if ($timezone != 'UTC') {
        throw new Exception(
          pht(
            'ISO8601 date ends in "Z" indicating UTC, but a timezone other '.
            'than UTC ("%s") was specified.',
            $timezone));
      }
    }

    $datetime = id(new self())
      ->setYear((int)$matches['y'])
      ->setMonth((int)$matches['m'])
      ->setDay((int)$matches['d'])
      ->setTimezone($timezone);

    if (isset($matches['h'])) {
      $datetime
        ->setHour((int)$matches['h'])
        ->setMinute((int)$matches['i'])
        ->setSecond((int)$matches['s']);
    } else {
      $datetime
        ->setIsAllDay(true);
    }

    return $datetime;
  }

  public static function newFromEpoch($epoch, $timezone = 'UTC') {
    $date = new DateTime('@'.$epoch);

    $zone = new DateTimeZone($timezone);
    $date->setTimezone($zone);

    return id(new self())
      ->setYear((int)$date->format('Y'))
      ->setMonth((int)$date->format('m'))
      ->setDay((int)$date->format('d'))
      ->setHour((int)$date->format('H'))
      ->setMinute((int)$date->format('i'))
      ->setSecond((int)$date->format('s'))
      ->setTimezone($timezone);
  }

  public static function newFromDictionary(array $dict) {
    static $keys;
    if ($keys === null) {
      $keys = array_fuse(
        array(
          'kind',
          'year',
          'month',
          'day',
          'hour',
          'minute',
          'second',
          'timezone',
          'isAllDay',
        ));
    }

    foreach ($dict as $key => $value) {
      if (!isset($keys[$key])) {
        throw new Exception(
          pht(
            'Unexpected key "%s" in datetime dictionary, expected keys: %s.',
            $key,
            implode(', ', array_keys($keys))));
      }
    }

    if (idx($dict, 'kind') !== 'absolute') {
      throw new Exception(
        pht(
          'Expected key "%s" with value "%s" in datetime dictionary.',
          'kind',
          'absolute'));
    }

    if (!isset($dict['year'])) {
      throw new Exception(
        pht(
          'Expected key "%s" in datetime dictionary.',
          'year'));
    }

    $datetime = id(new self())
      ->setYear(idx($dict, 'year'))
      ->setMonth(idx($dict, 'month', 1))
      ->setDay(idx($dict, 'day', 1))
      ->setHour(idx($dict, 'hour', 0))
      ->setMinute(idx($dict, 'minute', 0))
      ->setSecond(idx($dict, 'second', 0))
      ->setTimezone(idx($dict, 'timezone'))
      ->setIsAllDay((bool)idx($dict, 'isAllDay', false));

    return $datetime;
  }

  public function newRelativeDateTime($duration) {
    if (is_string($duration)) {
      $duration = PhutilCalendarDuration::newFromISO8601($duration);
    }

    if (!($duration instanceof PhutilCalendarDuration)) {
      throw new Exception(
        pht(
          'Expected "PhutilCalendarDuration" object or ISO8601 duration '.
          'string.'));
    }

    return id(new PhutilCalendarRelativeDateTime())
      ->setOrigin($this)
      ->setDuration($duration);
  }

  public function toDictionary() {
    return array(
      'kind' => 'absolute',
      'year' => (int)$this->getYear(),
      'month' => (int)$this->getMonth(),
      'day' => (int)$this->getDay(),
      'hour' => (int)$this->getHour(),
      'minute' => (int)$this->getMinute(),
      'second' => (int)$this->getSecond(),
      'timezone' => $this->getTimezone(),
      'isAllDay' => (bool)$this->getIsAllDay(),
    );
  }

  public function setYear($year) {
    $this->year = $year;
    return $this;
  }

  public function getYear() {
    return $this->year;
  }

  public function setMonth($month) {
    $this->month = $month;
    return $this;
  }

  public function getMonth() {
    return $this->month;
  }

  public function setDay($day) {
    $this->day = $day;
    return $this;
  }

  public function getDay() {
    return $this->day;
  }

  public function setHour($hour) {
    $this->hour = $hour;
    return $this;
  }

  public function getHour() {
    return $this->hour;
  }

  public function setMinute($minute) {
    $this->minute = $minute;
    return $this;
  }

  public function getMinute() {
    return $this->minute;
  }

  public function setSecond($second) {
    $this->second = $second;
    return $this;
  }

  public function getSecond() {
    return $this->second;
  }

  public function setTimezone($timezone) {
    $this->timezone = $timezone;
    return $this;
  }

  public function getTimezone() {
    return $this->timezone;
  }

  private function getEffectiveTimezone() {
    $date_timezone = $this->getTimezone();
    $viewer_timezone = $this->getViewerTimezone();

    // Because all-day events are always "floating", the effective timezone
    // is the viewer timezone if it is available. Otherwise, we'll return a
    // DateTime object with the correct values, but it will be incorrectly
    // adjusted forward or backward to the viewer's zone later.

    $zones = array();
    if ($this->getIsAllDay()) {
      $zones[] = $viewer_timezone;
      $zones[] = $date_timezone;
    } else {
      $zones[] = $date_timezone;
      $zones[] = $viewer_timezone;
    }
    $zones = array_filter($zones);

    if (!$zones) {
      throw new Exception(
        pht(
          'Datetime has no timezone or viewer timezone.'));
    }

    return head($zones);
  }

  public function newPHPDateTimeZone() {
    $zone = $this->getEffectiveTimezone();
    return new DateTimeZone($zone);
  }

  public function newPHPDateTime() {
    $zone = $this->newPHPDateTimeZone();

    $y = $this->getYear();
    $m = $this->getMonth();
    $d = $this->getDay();

    if ($this->getIsAllDay()) {
      $h = 0;
      $i = 0;
      $s = 0;
    } else {
      $h = $this->getHour();
      $i = $this->getMinute();
      $s = $this->getSecond();
    }

    $format = sprintf('%04d-%02d-%02d %02d:%02d:%02d', $y, $m, $d, $h, $i, $s);

    return new DateTime($format, $zone);
  }


  public function newAbsoluteDateTime() {
    return clone $this;
  }

}
