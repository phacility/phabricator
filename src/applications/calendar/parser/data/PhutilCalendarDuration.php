<?php

final class PhutilCalendarDuration extends Phobject {

  private $isNegative = false;
  private $weeks = 0;
  private $days = 0;
  private $hours = 0;
  private $minutes = 0;
  private $seconds = 0;

  public static function newFromDictionary(array $dict) {
    static $keys;
    if ($keys === null) {
      $keys = array_fuse(
        array(
          'isNegative',
          'weeks',
          'days',
          'hours',
          'minutes',
          'seconds',
        ));
    }

    foreach ($dict as $key => $value) {
      if (!isset($keys[$key])) {
        throw new Exception(
          pht(
            'Unexpected key "%s" in duration dictionary, expected keys: %s.',
            $key,
            implode(', ', array_keys($keys))));
      }
    }

    $duration = id(new self())
      ->setIsNegative(idx($dict, 'isNegative', false))
      ->setWeeks(idx($dict, 'weeks', 0))
      ->setDays(idx($dict, 'days', 0))
      ->setHours(idx($dict, 'hours', 0))
      ->setMinutes(idx($dict, 'minutes', 0))
      ->setSeconds(idx($dict, 'seconds', 0));

    return $duration;
  }

  public function toDictionary() {
    return array(
      'isNegative' => $this->getIsNegative(),
      'weeks' => $this->getWeeks(),
      'days' => $this->getDays(),
      'hours' => $this->getHours(),
      'minutes' => $this->getMinutes(),
      'seconds' => $this->getSeconds(),
    );
  }

  public static function newFromISO8601($value) {
    $pattern =
      '/^'.
      '(?P<sign>[+-])?'.
      'P'.
      '(?:'.
        '(?P<W>\d+)W'.
        '|'.
        '(?:(?:(?P<D>\d+)D)?'.
          '(?:T(?:(?P<H>\d+)H)?(?:(?P<M>\d+)M)?(?:(?P<S>\d+)S)?)?'.
        ')'.
      ')'.
      '\z/';

    $matches = null;
    $ok = preg_match($pattern, $value, $matches);
    if (!$ok) {
      throw new Exception(
        pht(
          'Expected ISO8601 duration in the format "P12DT3H4M5S", found '.
          '"%s".',
          $value));
    }

    $is_negative = (idx($matches, 'sign') == '-');

    return id(new self())
      ->setIsNegative($is_negative)
      ->setWeeks((int)idx($matches, 'W', 0))
      ->setDays((int)idx($matches, 'D', 0))
      ->setHours((int)idx($matches, 'H', 0))
      ->setMinutes((int)idx($matches, 'M', 0))
      ->setSeconds((int)idx($matches, 'S', 0));
  }

  public function toISO8601() {
    $parts = array();
    $parts[] = 'P';

    $weeks = $this->getWeeks();
    if ($weeks) {
      $parts[] = $weeks.'W';
    } else {
      $days = $this->getDays();
      if ($days) {
        $parts[] = $days.'D';
      }

      $parts[] = 'T';

      $hours = $this->getHours();
      if ($hours) {
        $parts[] = $hours.'H';
      }

      $minutes = $this->getMinutes();
      if ($minutes) {
        $parts[] = $minutes.'M';
      }

      $seconds = $this->getSeconds();
      if ($seconds) {
        $parts[] = $seconds.'S';
      }
    }

    return implode('', $parts);
  }

  public function setIsNegative($is_negative) {
    $this->isNegative = $is_negative;
    return $this;
  }

  public function getIsNegative() {
    return $this->isNegative;
  }

  public function setWeeks($weeks) {
    $this->weeks = $weeks;
    return $this;
  }

  public function getWeeks() {
    return $this->weeks;
  }

  public function setDays($days) {
    $this->days = $days;
    return $this;
  }

  public function getDays() {
    return $this->days;
  }

  public function setHours($hours) {
    $this->hours = $hours;
    return $this;
  }

  public function getHours() {
    return $this->hours;
  }

  public function setMinutes($minutes) {
    $this->minutes = $minutes;
    return $this;
  }

  public function getMinutes() {
    return $this->minutes;
  }

  public function setSeconds($seconds) {
    $this->seconds = $seconds;
    return $this;
  }

  public function getSeconds() {
    return $this->seconds;
  }

}
