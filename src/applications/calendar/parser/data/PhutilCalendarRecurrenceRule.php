<?php

final class PhutilCalendarRecurrenceRule
  extends PhutilCalendarRecurrenceSource {

  private $startDateTime;
  private $frequency;
  private $frequencyScale;
  private $interval = 1;
  private $bySecond = array();
  private $byMinute = array();
  private $byHour = array();
  private $byDay = array();
  private $byMonthDay = array();
  private $byYearDay = array();
  private $byWeekNumber = array();
  private $byMonth = array();
  private $bySetPosition = array();
  private $weekStart = self::WEEKDAY_MONDAY;
  private $count;
  private $until;

  private $cursorSecond;
  private $cursorMinute;
  private $cursorHour;
  private $cursorHourState;
  private $cursorWeek;
  private $cursorWeekday;
  private $cursorWeekState;
  private $cursorDay;
  private $cursorDayState;
  private $cursorMonth;
  private $cursorYear;

  private $setSeconds;
  private $setMinutes;
  private $setHours;
  private $setDays;
  private $setMonths;
  private $setWeeks;
  private $setYears;

  private $stateSecond;
  private $stateMinute;
  private $stateHour;
  private $stateDay;
  private $stateWeek;
  private $stateMonth;
  private $stateYear;

  private $baseYear;
  private $isAllDay;
  private $activeSet = array();
  private $nextSet = array();
  private $minimumEpoch;

  const FREQUENCY_SECONDLY = 'SECONDLY';
  const FREQUENCY_MINUTELY = 'MINUTELY';
  const FREQUENCY_HOURLY = 'HOURLY';
  const FREQUENCY_DAILY = 'DAILY';
  const FREQUENCY_WEEKLY = 'WEEKLY';
  const FREQUENCY_MONTHLY = 'MONTHLY';
  const FREQUENCY_YEARLY = 'YEARLY';

  const SCALE_SECONDLY = 1;
  const SCALE_MINUTELY = 2;
  const SCALE_HOURLY = 3;
  const SCALE_DAILY = 4;
  const SCALE_WEEKLY = 5;
  const SCALE_MONTHLY = 6;
  const SCALE_YEARLY = 7;

  const WEEKDAY_SUNDAY = 'SU';
  const WEEKDAY_MONDAY = 'MO';
  const WEEKDAY_TUESDAY = 'TU';
  const WEEKDAY_WEDNESDAY = 'WE';
  const WEEKDAY_THURSDAY = 'TH';
  const WEEKDAY_FRIDAY = 'FR';
  const WEEKDAY_SATURDAY = 'SA';

  const WEEKINDEX_SUNDAY = 0;
  const WEEKINDEX_MONDAY = 1;
  const WEEKINDEX_TUESDAY = 2;
  const WEEKINDEX_WEDNESDAY = 3;
  const WEEKINDEX_THURSDAY = 4;
  const WEEKINDEX_FRIDAY = 5;
  const WEEKINDEX_SATURDAY = 6;

  public function toDictionary() {
    $parts = array();

    $parts['FREQ'] = $this->getFrequency();

    $interval = $this->getInterval();
    if ($interval != 1) {
      $parts['INTERVAL'] = $interval;
    }

    $by_second = $this->getBySecond();
    if ($by_second) {
      $parts['BYSECOND'] = $by_second;
    }

    $by_minute = $this->getByMinute();
    if ($by_minute) {
      $parts['BYMINUTE'] = $by_minute;
    }

    $by_hour = $this->getByHour();
    if ($by_hour) {
      $parts['BYHOUR'] = $by_hour;
    }

    $by_day = $this->getByDay();
    if ($by_day) {
      $parts['BYDAY'] = $by_day;
    }

    $by_month = $this->getByMonth();
    if ($by_month) {
      $parts['BYMONTH'] = $by_month;
    }

    $by_monthday = $this->getByMonthDay();
    if ($by_monthday) {
      $parts['BYMONTHDAY'] = $by_monthday;
    }

    $by_yearday = $this->getByYearDay();
    if ($by_yearday) {
      $parts['BYYEARDAY'] = $by_yearday;
    }

    $by_weekno = $this->getByWeekNumber();
    if ($by_weekno) {
      $parts['BYWEEKNO'] = $by_weekno;
    }

    $by_setpos = $this->getBySetPosition();
    if ($by_setpos) {
      $parts['BYSETPOS'] = $by_setpos;
    }

    $wkst = $this->getWeekStart();
    if ($wkst != self::WEEKDAY_MONDAY) {
      $parts['WKST'] = $wkst;
    }

    $count = $this->getCount();
    if ($count) {
      $parts['COUNT'] = $count;
    }

    $until = $this->getUntil();
    if ($until) {
      $parts['UNTIL'] = $until->getISO8601();
    }

    return $parts;
  }

  public static function newFromDictionary(array $dict) {
    static $expect;
    if ($expect === null) {
      $expect = array_fuse(
        array(
          'FREQ',
          'INTERVAL',
          'BYSECOND',
          'BYMINUTE',
          'BYHOUR',
          'BYDAY',
          'BYMONTH',
          'BYMONTHDAY',
          'BYYEARDAY',
          'BYWEEKNO',
          'BYSETPOS',
          'WKST',
          'UNTIL',
          'COUNT',
        ));
    }

    foreach ($dict as $key => $value) {
      if (empty($expect[$key])) {
        throw new Exception(
          pht(
            'RRULE dictionary includes unknown key "%s". Expected keys '.
            'are: %s.',
            $key,
            implode(', ', array_keys($expect))));
      }
    }

    $rrule = id(new self())
      ->setFrequency(idx($dict, 'FREQ'))
      ->setInterval(idx($dict, 'INTERVAL', 1))
      ->setBySecond(idx($dict, 'BYSECOND', array()))
      ->setByMinute(idx($dict, 'BYMINUTE', array()))
      ->setByHour(idx($dict, 'BYHOUR', array()))
      ->setByDay(idx($dict, 'BYDAY', array()))
      ->setByMonth(idx($dict, 'BYMONTH', array()))
      ->setByMonthDay(idx($dict, 'BYMONTHDAY', array()))
      ->setByYearDay(idx($dict, 'BYYEARDAY', array()))
      ->setByWeekNumber(idx($dict, 'BYWEEKNO', array()))
      ->setBySetPosition(idx($dict, 'BYSETPOS', array()))
      ->setWeekStart(idx($dict, 'WKST', self::WEEKDAY_MONDAY));

    $count = idx($dict, 'COUNT');
    if ($count) {
      $rrule->setCount($count);
    }

    $until = idx($dict, 'UNTIL');
    if ($until) {
      $until = PhutilCalendarAbsoluteDateTime::newFromISO8601($until);
      $rrule->setUntil($until);
    }

    return $rrule;
  }

  public function toRRULE() {
    $dict = $this->toDictionary();

    $parts = array();
    foreach ($dict as $key => $value) {
      if (is_array($value)) {
        $value = implode(',', $value);
      }
      $parts[] = "{$key}={$value}";
    }

    return implode(';', $parts);
  }

  public static function newFromRRULE($rrule) {
    $parts = explode(';', $rrule);

    $dict = array();
    foreach ($parts as $part) {
      list($key, $value) = explode('=', $part, 2);
      switch ($key) {
        case 'FREQ':
        case 'INTERVAL':
        case 'WKST':
        case 'COUNT':
        case 'UNTIL';
          break;
        default:
          $value = explode(',', $value);
          break;
      }
      $dict[$key] = $value;
    }

    $int_lists = array_fuse(
      array(
        // NOTE: "BYDAY" is absent, and takes a list like "MO, TU, WE".
        'BYSECOND',
        'BYMINUTE',
        'BYHOUR',
        'BYMONTH',
        'BYMONTHDAY',
        'BYYEARDAY',
        'BYWEEKNO',
        'BYSETPOS',
      ));

    $int_values = array_fuse(
      array(
        'COUNT',
        'INTERVAL',
      ));

    foreach ($dict as $key => $value) {
      if (isset($int_values[$key])) {
        // None of these values may be negative.
        if (!preg_match('/^\d+\z/', $value)) {
          throw new Exception(
            pht(
              'Unexpected value "%s" in "%s" RULE property: expected an '.
              'integer.',
              $value,
              $key));
        }
        $dict[$key] = (int)$value;
      }

      if (isset($int_lists[$key])) {
        foreach ($value as $k => $v) {
          if (!preg_match('/^-?\d+\z/', $v)) {
            throw new Exception(
              pht(
                'Unexpected value "%s" in "%s" RRULE property: expected '.
                'only integers.',
                $v,
                $key));
          }
          $value[$k] = (int)$v;
        }
        $dict[$key] = $value;
      }
    }

    return self::newFromDictionary($dict);
  }

  private static function getAllWeekdayConstants() {
    return array_keys(self::getWeekdayIndexMap());
  }

  private static function getWeekdayIndexMap() {
    static $map = array(
      self::WEEKDAY_SUNDAY => self::WEEKINDEX_SUNDAY,
      self::WEEKDAY_MONDAY => self::WEEKINDEX_MONDAY,
      self::WEEKDAY_TUESDAY => self::WEEKINDEX_TUESDAY,
      self::WEEKDAY_WEDNESDAY => self::WEEKINDEX_WEDNESDAY,
      self::WEEKDAY_THURSDAY => self::WEEKINDEX_THURSDAY,
      self::WEEKDAY_FRIDAY => self::WEEKINDEX_FRIDAY,
      self::WEEKDAY_SATURDAY => self::WEEKINDEX_SATURDAY,
    );

    return $map;
  }

  private static function getWeekdayIndex($weekday) {
    $map = self::getWeekdayIndexMap();
    if (!isset($map[$weekday])) {
      $constants = array_keys($map);
      throw new Exception(
        pht(
          'Weekday "%s" is not a valid weekday constant. Valid constants '.
          'are: %s.',
          $weekday,
          implode(', ', $constants)));
    }

    return $map[$weekday];
  }

  public function setStartDateTime(PhutilCalendarDateTime $start) {
    $this->startDateTime = $start;
    return $this;
  }

  public function getStartDateTime() {
    return $this->startDateTime;
  }

  public function setCount($count) {
    if ($count < 1) {
      throw new Exception(
        pht(
          'RRULE COUNT value "%s" is invalid: count must be at least 1.',
          $count));
    }

    $this->count = $count;
    return $this;
  }

  public function getCount() {
    return $this->count;
  }

  public function setUntil(PhutilCalendarDateTime $until) {
    $this->until = $until;
    return $this;
  }

  public function getUntil() {
    return $this->until;
  }

  public function setFrequency($frequency) {
    static $map = array(
      self::FREQUENCY_SECONDLY => self::SCALE_SECONDLY,
      self::FREQUENCY_MINUTELY => self::SCALE_MINUTELY,
      self::FREQUENCY_HOURLY => self::SCALE_HOURLY,
      self::FREQUENCY_DAILY => self::SCALE_DAILY,
      self::FREQUENCY_WEEKLY => self::SCALE_WEEKLY,
      self::FREQUENCY_MONTHLY => self::SCALE_MONTHLY,
      self::FREQUENCY_YEARLY => self::SCALE_YEARLY,
    );

    if (empty($map[$frequency])) {
      throw new Exception(
        pht(
          'RRULE FREQ "%s" is invalid. Valid frequencies are: %s.',
          $frequency,
          implode(', ', array_keys($map))));
    }

    $this->frequency = $frequency;
    $this->frequencyScale = $map[$frequency];

    return $this;
  }

  public function getFrequency() {
    return $this->frequency;
  }

  public function getFrequencyScale() {
    return $this->frequencyScale;
  }

  public function setInterval($interval) {
    if (!is_int($interval)) {
      throw new Exception(
        pht(
          'RRULE INTERVAL "%s" is invalid: interval must be an integer.',
          $interval));
    }

    if ($interval < 1) {
      throw new Exception(
        pht(
          'RRULE INTERVAL "%s" is invalid: interval must be 1 or more.',
          $interval));
    }

    $this->interval = $interval;
    return $this;
  }

  public function getInterval() {
    return $this->interval;
  }

  public function setBySecond(array $by_second) {
    $this->assertByRange('BYSECOND', $by_second, 0, 60);
    $this->bySecond = array_fuse($by_second);
    return $this;
  }

  public function getBySecond() {
    return $this->bySecond;
  }

  public function setByMinute(array $by_minute) {
    $this->assertByRange('BYMINUTE', $by_minute, 0, 59);
    $this->byMinute = array_fuse($by_minute);
    return $this;
  }

  public function getByMinute() {
    return $this->byMinute;
  }

  public function setByHour(array $by_hour) {
    $this->assertByRange('BYHOUR', $by_hour, 0, 23);
    $this->byHour = array_fuse($by_hour);
    return $this;
  }

  public function getByHour() {
    return $this->byHour;
  }

  public function setByDay(array $by_day) {
    $constants = self::getAllWeekdayConstants();
    $constants = implode('|', $constants);

    $pattern = '/^(?:[+-]?([1-9]\d?))?('.$constants.')\z/';
    foreach ($by_day as $key => $value) {
      $matches = null;
      if (!preg_match($pattern, $value, $matches)) {
        throw new Exception(
          pht(
            'RRULE BYDAY value "%s" is invalid: rule part must be in the '.
            'expected form (like "MO", "-3TH", or "+2SU").',
            $value));
      }

      // The maximum allowed value is 53, which corresponds to "the 53rd
      // Monday every year" or similar when evaluated against a YEARLY rule.

      $maximum = 53;
      $magnitude = (int)$matches[1];
      if ($magnitude > $maximum) {
        throw new Exception(
          pht(
            'RRULE BYDAY value "%s" has an offset with magnitude "%s", but '.
            'the maximum permitted value is "%s".',
            $value,
            $magnitude,
            $maximum));
      }

      // Normalize "+3FR" into "3FR".
      $by_day[$key] = ltrim($value, '+');
    }

    $this->byDay = array_fuse($by_day);
    return $this;
  }

  public function getByDay() {
    return $this->byDay;
  }

  public function setByMonthDay(array $by_month_day) {
    $this->assertByRange('BYMONTHDAY', $by_month_day, -31, 31, false);
    $this->byMonthDay = array_fuse($by_month_day);
    return $this;
  }

  public function getByMonthDay() {
    return $this->byMonthDay;
  }

  public function setByYearDay($by_year_day) {
    $this->assertByRange('BYYEARDAY', $by_year_day, -366, 366, false);
    $this->byYearDay = array_fuse($by_year_day);
    return $this;
  }

  public function getByYearDay() {
    return $this->byYearDay;
  }

  public function setByMonth(array $by_month) {
    $this->assertByRange('BYMONTH', $by_month, 1, 12);
    $this->byMonth = array_fuse($by_month);
    return $this;
  }

  public function getByMonth() {
    return $this->byMonth;
  }

  public function setByWeekNumber(array $by_week_number) {
    $this->assertByRange('BYWEEKNO', $by_week_number, -53, 53, false);
    $this->byWeekNumber = array_fuse($by_week_number);
    return $this;
  }

  public function getByWeekNumber() {
    return $this->byWeekNumber;
  }

  public function setBySetPosition(array $by_set_position) {
    $this->assertByRange('BYSETPOS', $by_set_position, -366, 366, false);
    $this->bySetPosition = $by_set_position;
    return $this;
  }

  public function getBySetPosition() {
    return $this->bySetPosition;
  }

  public function setWeekStart($week_start) {
    // Make sure this is a valid weekday constant.
    self::getWeekdayIndex($week_start);

    $this->weekStart = $week_start;
    return $this;
  }

  public function getWeekStart() {
    return $this->weekStart;
  }

  public function resetSource() {
    $frequency = $this->getFrequency();

    if ($this->getByMonthDay()) {
      switch ($frequency) {
        case self::FREQUENCY_WEEKLY:
          // RFC5545: "The BYMONTHDAY rule part MUST NOT be specified when the
          // FREQ rule part is set to WEEKLY."
          throw new Exception(
            pht(
              'RRULE specifies BYMONTHDAY with FREQ set to WEEKLY, which '.
              'violates RFC5545.'));
          break;
        default:
          break;
      }

    }

    if ($this->getByYearDay()) {
      switch ($frequency) {
        case self::FREQUENCY_DAILY:
        case self::FREQUENCY_WEEKLY:
        case self::FREQUENCY_MONTHLY:
          // RFC5545: "The BYYEARDAY rule part MUST NOT be specified when the
          // FREQ rule part is set to DAILY, WEEKLY, or MONTHLY."
          throw new Exception(
            pht(
              'RRULE specifies BYYEARDAY with FREQ of DAILY, WEEKLY or '.
              'MONTHLY, which violates RFC5545.'));
        default:
          break;
      }
    }

    // TODO
    // RFC5545: "The BYDAY rule part MUST NOT be specified with a numeric
    // value when the FREQ rule part is not set to MONTHLY or YEARLY."
    // RFC5545: "Furthermore, the BYDAY rule part MUST NOT be specified with a
    // numeric value with the FREQ rule part set to YEARLY when the BYWEEKNO
    // rule part is specified."


    $date = $this->getStartDateTime();

    $this->cursorSecond = $date->getSecond();
    $this->cursorMinute = $date->getMinute();
    $this->cursorHour = $date->getHour();

    $this->cursorDay = $date->getDay();
    $this->cursorMonth = $date->getMonth();
    $this->cursorYear = $date->getYear();

    $year_map = $this->getYearMap($this->cursorYear, $this->getWeekStart());
    $key = $this->cursorMonth.'M'.$this->cursorDay.'D';
    $this->cursorWeek = $year_map['info'][$key]['week'];
    $this->cursorWeekday = $year_map['info'][$key]['weekday'];

    $this->setSeconds = array();
    $this->setMinutes = array();
    $this->setHours = array();
    $this->setDays = array();
    $this->setMonths = array();
    $this->setYears = array();

    $this->stateSecond = null;
    $this->stateMinute = null;
    $this->stateHour = null;
    $this->stateDay = null;
    $this->stateWeek = null;
    $this->stateMonth = null;
    $this->stateYear = null;

    // If we have a BYSETPOS, we need to generate the entire set before we
    // can filter it and return results. Normally, we start generating at
    // the start date, but we need to go back one interval to generate
    // BYSETPOS events so we can make sure the entire set is generated.
    if ($this->getBySetPosition()) {
      $interval = $this->getInterval();
      switch ($frequency) {
        case self::FREQUENCY_YEARLY:
          $this->cursorYear -= $interval;
          break;
        case self::FREQUENCY_MONTHLY:
          $this->cursorMonth -= $interval;
          $this->rewindMonth();
          break;
        case self::FREQUENCY_WEEKLY:
          $this->cursorWeek -= $interval;
          $this->rewindWeek();
          break;
        case self::FREQUENCY_DAILY:
          $this->cursorDay -= $interval;
          $this->rewindDay();
          break;
        case self::FREQUENCY_HOURLY:
          $this->cursorHour -= $interval;
          $this->rewindHour();
          break;
        case self::FREQUENCY_MINUTELY:
          $this->cursorMinute -= $interval;
          $this->rewindMinute();
          break;
        case self::FREQUENCY_SECONDLY:
        default:
          throw new Exception(
            pht(
              'RRULE specifies BYSETPOS with FREQ "%s", but this is invalid.',
              $frequency));
      }
    }

    // We can generate events from before the cursor when evaluating rules
    // with BYSETPOS or FREQ=WEEKLY.
    $this->minimumEpoch = $this->getStartDateTime()->getEpoch();

    $cursor_state = array(
      'year' => $this->cursorYear,
      'month' => $this->cursorMonth,
      'week' => $this->cursorWeek,
      'day' => $this->cursorDay,
      'hour' => $this->cursorHour,
    );

    $this->cursorDayState = $cursor_state;
    $this->cursorWeekState = $cursor_state;
    $this->cursorHourState = $cursor_state;

    $by_hour = $this->getByHour();
    $by_minute = $this->getByMinute();
    $by_second = $this->getBySecond();

    $scale = $this->getFrequencyScale();

    // We return all-day events if the start date is an all-day event and we
    // don't have more granular selectors or a more granular frequency.
    $this->isAllDay = $date->getIsAllDay()
      && !$by_hour
      && !$by_minute
      && !$by_second
      && ($scale > self::SCALE_HOURLY);
  }

  public function getNextEvent($cursor) {
    while (true) {
      $event = $this->generateNextEvent();
      if (!$event) {
        break;
      }

      $epoch = $event->getEpoch();
      if ($this->minimumEpoch) {
        if ($epoch < $this->minimumEpoch) {
          continue;
        }
      }

      if ($epoch < $cursor) {
        continue;
      }

      break;
    }

    return $event;
  }

  private function generateNextEvent() {
    if ($this->activeSet) {
      return array_pop($this->activeSet);
    }

    $this->baseYear = $this->cursorYear;

    $by_setpos = $this->getBySetPosition();
    if ($by_setpos) {
      $old_state = $this->getSetPositionState();
    }

    while (!$this->activeSet) {
      $this->activeSet = $this->nextSet;
      $this->nextSet = array();

      while (true) {
        if ($this->isAllDay) {
          $this->nextDay();
        } else {
          $this->nextSecond();
        }

        $result = id(new PhutilCalendarAbsoluteDateTime())
          ->setTimezone($this->getStartDateTime()->getTimezone())
          ->setViewerTimezone($this->getViewerTimezone())
          ->setYear($this->stateYear)
          ->setMonth($this->stateMonth)
          ->setDay($this->stateDay);

        if ($this->isAllDay) {
          $result->setIsAllDay(true);
        } else {
          $result
            ->setHour($this->stateHour)
            ->setMinute($this->stateMinute)
            ->setSecond($this->stateSecond);
        }

        // If we don't have BYSETPOS, we're all done. We put this into the
        // set and will immediately return it.
        if (!$by_setpos) {
          $this->activeSet[] = $result;
          break;
        }

        // Otherwise, check if we've completed a set. The set is complete if
        // the state has moved past the span we were examining (for example,
        // with a YEARLY event, if the state is now in the next year).
        $new_state = $this->getSetPositionState();
        if ($new_state == $old_state) {
          $this->activeSet[] = $result;
          continue;
        }

        $this->activeSet = $this->applySetPos($this->activeSet, $by_setpos);
        $this->activeSet = array_reverse($this->activeSet);
        $this->nextSet[] = $result;
        $old_state = $new_state;
        break;
      }
    }

    return array_pop($this->activeSet);
  }


  protected function nextSecond() {
    if ($this->setSeconds) {
      $this->stateSecond = array_pop($this->setSeconds);
      return;
    }

    $frequency = $this->getFrequency();
    $interval = $this->getInterval();
    $is_secondly = ($frequency == self::FREQUENCY_SECONDLY);
    $by_second = $this->getBySecond();

    while (!$this->setSeconds) {
      $this->nextMinute();

      if ($is_secondly || $by_second) {
        $seconds = $this->newSecondsSet(
          ($is_secondly ? $interval : 1),
          $by_second);
      } else {
        $seconds = array(
          $this->cursorSecond,
        );
      }

      $this->setSeconds = array_reverse($seconds);
    }

    $this->stateSecond = array_pop($this->setSeconds);
  }

  protected function nextMinute() {
    if ($this->setMinutes) {
      $this->stateMinute = array_pop($this->setMinutes);
      return;
    }

    $frequency = $this->getFrequency();
    $interval = $this->getInterval();
    $scale = $this->getFrequencyScale();
    $is_minutely = ($frequency === self::FREQUENCY_MINUTELY);
    $by_minute = $this->getByMinute();

    while (!$this->setMinutes) {
      $this->nextHour();

      if ($is_minutely || $by_minute) {
        $minutes = $this->newMinutesSet(
          ($is_minutely ? $interval : 1),
          $by_minute);
      } else if ($scale < self::SCALE_MINUTELY) {
        $minutes = $this->newMinutesSet(
          1,
          array());
      } else {
        $minutes = array(
          $this->cursorMinute,
        );
      }

      $this->setMinutes = array_reverse($minutes);
    }

    $this->stateMinute = array_pop($this->setMinutes);
  }

  protected function nextHour() {
    if ($this->setHours) {
      $this->stateHour = array_pop($this->setHours);
      return;
    }

    $frequency = $this->getFrequency();
    $interval = $this->getInterval();
    $scale = $this->getFrequencyScale();
    $is_hourly = ($frequency === self::FREQUENCY_HOURLY);
    $by_hour = $this->getByHour();

    while (!$this->setHours) {
      $this->nextDay();

      $is_dynamic = $is_hourly
        || $by_hour
        || ($scale < self::SCALE_HOURLY);

      if ($is_dynamic) {
        $hours = $this->newHoursSet(
          ($is_hourly ? $interval : 1),
          $by_hour);
      } else {
        $hours = array(
          $this->cursorHour,
        );
      }

      $this->setHours = array_reverse($hours);
    }

    $this->stateHour = array_pop($this->setHours);
  }

  protected function nextDay() {
    if ($this->setDays) {
      $info = array_pop($this->setDays);
      $this->setDayState($info);
      return;
    }

    $frequency = $this->getFrequency();
    $interval = $this->getInterval();
    $scale = $this->getFrequencyScale();
    $is_daily = ($frequency === self::FREQUENCY_DAILY);
    $is_weekly = ($frequency === self::FREQUENCY_WEEKLY);

    $by_day = $this->getByDay();
    $by_monthday = $this->getByMonthDay();
    $by_yearday = $this->getByYearDay();
    $by_weekno = $this->getByWeekNumber();
    $by_month = $this->getByMonth();
    $week_start = $this->getWeekStart();

    while (!$this->setDays) {
      if ($is_weekly) {
        $this->nextWeek();
      } else {
        $this->nextMonth();
      }

      // NOTE: We normally handle BYMONTH when iterating months, but it acts
      // like a filter if FREQ=WEEKLY.

      $is_dynamic = $is_daily
        || $is_weekly
        || $by_day
        || $by_monthday
        || $by_yearday
        || $by_weekno
        || ($by_month && $is_weekly)
        || ($scale < self::SCALE_DAILY);

      if ($is_dynamic) {
        $weeks = $this->newDaysSet(
          ($is_daily ? $interval : 1),
          $by_day,
          $by_monthday,
          $by_yearday,
          $by_weekno,
          $by_month,
          $week_start);
      } else {
        // The cursor day may not actually exist in the current month, so
        // make sure the day is valid before we generate a set which contains
        // it.
        $year_map = $this->getYearMap($this->stateYear, $week_start);
        if ($this->cursorDay > $year_map['monthDays'][$this->stateMonth]) {
          $weeks = array(
            array(),
          );
        } else {
          $key = $this->stateMonth.'M'.$this->cursorDay.'D';
          $weeks = array(
            array($year_map['info'][$key]),
          );
        }
      }

      // Unpack the weeks into days.
      $days = array_mergev($weeks);

      $this->setDays = array_reverse($days);
    }

    $info = array_pop($this->setDays);
    $this->setDayState($info);
  }

  private function setDayState(array $info) {
    $this->stateDay = $info['monthday'];
    $this->stateWeek = $info['week'];
    $this->stateMonth = $info['month'];
  }

  protected function nextMonth() {
    if ($this->setMonths) {
      $this->stateMonth = array_pop($this->setMonths);
      return;
    }

    $frequency = $this->getFrequency();
    $interval = $this->getInterval();
    $scale = $this->getFrequencyScale();
    $is_monthly = ($frequency === self::FREQUENCY_MONTHLY);

    $by_month = $this->getByMonth();

    // If we have a BYMONTHDAY, we consider that set of days in every month.
    // For example, "FREQ=YEARLY;BYMONTHDAY=3" means "the third day of every
    // month", so we need to expand the month set if the constraint is present.
    $by_monthday = $this->getByMonthDay();

    // Likewise, we need to generate all months if we have BYYEARDAY or
    // BYWEEKNO or BYDAY.
    $by_yearday = $this->getByYearDay();
    $by_weekno = $this->getByWeekNumber();
    $by_day = $this->getByDay();

    while (!$this->setMonths) {
      $this->nextYear();

      $is_dynamic = $is_monthly
        || $by_month
        || $by_monthday
        || $by_yearday
        || $by_weekno
        || $by_day
        || ($scale < self::SCALE_MONTHLY);

      if ($is_dynamic) {
        $months = $this->newMonthsSet(
          ($is_monthly ? $interval : 1),
          $by_month);
      } else {
        $months = array(
          $this->cursorMonth,
        );
      }

      $this->setMonths = array_reverse($months);
    }

    $this->stateMonth = array_pop($this->setMonths);
  }

  protected function nextWeek() {
    if ($this->setWeeks) {
      $this->stateWeek = array_pop($this->setWeeks);
      return;
    }

    $frequency = $this->getFrequency();
    $interval = $this->getInterval();
    $scale = $this->getFrequencyScale();
    $by_weekno = $this->getByWeekNumber();

    while (!$this->setWeeks) {
      $this->nextYear();

      $weeks = $this->newWeeksSet(
        $interval,
        $by_weekno);

      $this->setWeeks = array_reverse($weeks);
    }

    $this->stateWeek = array_pop($this->setWeeks);
  }

  protected function nextYear() {
    $this->stateYear = $this->cursorYear;

    $frequency = $this->getFrequency();
    $is_yearly = ($frequency === self::FREQUENCY_YEARLY);

    if ($is_yearly) {
      $interval = $this->getInterval();
    } else {
      $interval = 1;
    }

    $this->cursorYear = $this->cursorYear + $interval;

    if ($this->cursorYear > ($this->baseYear + 100)) {
      throw new Exception(
        pht(
          'RRULE evaluation failed to generate more events in the next 100 '.
          'years. This RRULE is likely invalid or degenerate.'));
    }

  }

  private function newSecondsSet($interval, $set) {
    // TODO: This doesn't account for leap seconds. In theory, it probably
    // should, although this shouldn't impact any real events.
    $seconds_in_minute = 60;

    if ($this->cursorSecond >= $seconds_in_minute) {
      $this->cursorSecond -= $seconds_in_minute;
      return array();
    }

    list($cursor, $result) = $this->newIteratorSet(
      $this->cursorSecond,
      $interval,
      $set,
      $seconds_in_minute);

    $this->cursorSecond = ($cursor - $seconds_in_minute);

    return $result;
  }

  private function newMinutesSet($interval, $set) {
    // NOTE: This value is legitimately a constant! Amazing!
    $minutes_in_hour = 60;

    if ($this->cursorMinute >= $minutes_in_hour) {
      $this->cursorMinute -= $minutes_in_hour;
      return array();
    }

    list($cursor, $result) = $this->newIteratorSet(
      $this->cursorMinute,
      $interval,
      $set,
      $minutes_in_hour);

    $this->cursorMinute = ($cursor - $minutes_in_hour);

    return $result;
  }

  private function newHoursSet($interval, $set) {
    // TODO: This doesn't account for hours caused by daylight savings time.
    // It probably should, although this seems unlikely to impact any real
    // events.
    $hours_in_day = 24;

    // If the hour cursor is behind the current time, we need to forward it in
    // INTERVAL increments so we end up with the right offset.
    list($skip, $this->cursorHourState) = $this->advanceCursorState(
      $this->cursorHourState,
      self::SCALE_HOURLY,
      $interval,
      $this->getWeekStart());

    if ($skip) {
      return array();
    }

    list($cursor, $result) = $this->newIteratorSet(
      $this->cursorHour,
      $interval,
      $set,
      $hours_in_day);

    $this->cursorHour = ($cursor - $hours_in_day);

    return $result;
  }

  private function newWeeksSet($interval, $set) {
    $week_start = $this->getWeekStart();

    list($skip, $this->cursorWeekState) = $this->advanceCursorState(
      $this->cursorWeekState,
      self::SCALE_WEEKLY,
      $interval,
      $week_start);

    if ($skip) {
      return array();
    }

    $year_map = $this->getYearMap($this->stateYear, $week_start);

    $result = array();
    while (true) {
      if (!isset($year_map['weekMap'][$this->cursorWeek])) {
        break;
      }
      $result[] = $this->cursorWeek;
      $this->cursorWeek += $interval;
    }

    $this->cursorWeek -= $year_map['weekCount'];

    return $result;
  }

  private function newDaysSet(
    $interval_day,
    $by_day,
    $by_monthday,
    $by_yearday,
    $by_weekno,
    $by_month,
    $week_start) {

    $frequency = $this->getFrequency();
    $is_yearly = ($frequency == self::FREQUENCY_YEARLY);
    $is_monthly = ($frequency == self::FREQUENCY_MONTHLY);
    $is_weekly = ($frequency == self::FREQUENCY_WEEKLY);

    $selection = array();
    if ($is_weekly) {
      $year_map = $this->getYearMap($this->stateYear, $week_start);

      if (isset($year_map['weekMap'][$this->stateWeek])) {
        foreach ($year_map['weekMap'][$this->stateWeek] as $key) {
          $selection[] = $year_map['info'][$key];
        }
      }
    } else {
      // If the day cursor is behind the current year and month, we need to
      // forward it in INTERVAL increments so we end up with the right offset
      // in the current month.
      list($skip, $this->cursorDayState) = $this->advanceCursorState(
        $this->cursorDayState,
        self::SCALE_DAILY,
        $interval_day,
        $week_start);

      if (!$skip) {
        $year_map = $this->getYearMap($this->stateYear, $week_start);
        while (true) {
          $month_idx = $this->stateMonth;
          $month_days = $year_map['monthDays'][$month_idx];
          if ($this->cursorDay > $month_days) {
            // NOTE: The year map is now out of date, but we're about to break
            // out of the loop anyway so it doesn't matter.
            break;
          }

          $day_idx = $this->cursorDay;

          $key = "{$month_idx}M{$day_idx}D";
          $selection[] = $year_map['info'][$key];

          $this->cursorDay += $interval_day;
        }
      }
    }

    // As a special case, BYDAY applies to relative month offsets if BYMONTH
    // is present in a YEARLY rule.
    if ($is_yearly) {
      if ($this->getByMonth()) {
        $is_yearly = false;
        $is_monthly = true;
      }
    }

    // As a special case, BYDAY makes us examine all week days. This doesn't
    // check BYMONTHDAY or BYYEARDAY because they are not valid with WEEKLY.
    $filter_weekday = true;
    if ($is_weekly) {
      if ($by_day) {
        $filter_weekday = false;
      }
    }

    $weeks = array();
    foreach ($selection as $key => $info) {
      if ($is_weekly) {
        if ($filter_weekday) {
          if ($info['weekday'] != $this->cursorWeekday) {
            continue;
          }
        }
      } else {
        if ($info['month'] != $this->stateMonth) {
          continue;
        }
      }

      if ($by_day) {
        if (empty($by_day[$info['weekday']])) {
          if ($is_yearly) {
            if (empty($by_day[$info['weekday.yearly']]) &&
                empty($by_day[$info['-weekday.yearly']])) {
              continue;
            }
          } else if ($is_monthly) {
            if (empty($by_day[$info['weekday.monthly']]) &&
                empty($by_day[$info['-weekday.monthly']])) {
              continue;
            }
          } else {
            continue;
          }
        }
      }

      if ($by_monthday) {
        if (empty($by_monthday[$info['monthday']]) &&
            empty($by_monthday[$info['-monthday']])) {
          continue;
        }
      }

      if ($by_yearday) {
        if (empty($by_yearday[$info['yearday']]) &&
            empty($by_yearday[$info['-yearday']])) {
          continue;
        }
      }

      if ($by_weekno) {
        if (empty($by_weekno[$info['week']]) &&
            empty($by_weekno[$info['-week']])) {
          continue;
        }
      }

      if ($by_month) {
        if (empty($by_month[$info['month']])) {
          continue;
        }
      }

      $weeks[$info['week']][] = $info;
    }

    return array_values($weeks);
  }

  private function newMonthsSet($interval, $set) {
    // NOTE: This value is also a real constant! Wow!
    $months_in_year = 12;

    if ($this->cursorMonth > $months_in_year) {
      $this->cursorMonth -= $months_in_year;
      return array();
    }

    list($cursor, $result) = $this->newIteratorSet(
      $this->cursorMonth,
      $interval,
      $set,
      $months_in_year + 1);

    $this->cursorMonth = ($cursor - $months_in_year);

    return $result;
  }

  public static function getYearMap($year, $week_start) {
    static $maps = array();

    $key = "{$year}/{$week_start}";
    if (isset($maps[$key])) {
      return $maps[$key];
    }

    $map = self::newYearMap($year, $week_start);
    $maps[$key] = $map;

    return $maps[$key];
  }

  private static function newYearMap($year, $weekday_start) {
    $weekday_index = self::getWeekdayIndex($weekday_start);

    $is_leap = (($year % 4 === 0) && ($year % 100 !== 0)) ||
               ($year % 400 === 0);

    // There may be some clever way to figure out which day of the week a given
    // year starts on and avoid the cost of a DateTime construction, but I
    // wasn't able to turn it up and we only need to do this once per year.
    $datetime = new DateTime("{$year}-01-01", new DateTimeZone('UTC'));
    $weekday = (int)$datetime->format('w');

    if ($is_leap) {
      $max_day = 366;
    } else {
      $max_day = 365;
    }

    $month_days = array(
      1 => 31,
      2 => $is_leap ? 29 : 28,
      3 => 31,
      4 => 30,
      5 => 31,
      6 => 30,
      7 => 31,
      8 => 31,
      9 => 30,
      10 => 31,
      11 => 30,
      12 => 31,
    );

    // Per the spec, the first week of the year must contain at least four
    // days. If the week starts on a Monday but the year starts on a Saturday,
    // the first couple of days don't count as a week. In this case, the first
    // week will begin on January 3.
    $first_week_size = 0;
    $first_weekday = $weekday;
    for ($year_day = 1; $year_day <= $max_day; $year_day++) {
      $first_weekday = ($first_weekday + 1) % 7;
      $first_week_size++;
      if ($first_weekday === $weekday_index) {
        break;
      }
    }

    if ($first_week_size >= 4) {
      $week_number = 1;
    } else {
      $week_number = 0;
    }

    $info_map = array();

    $weekday_map = self::getWeekdayIndexMap();
    $weekday_map = array_flip($weekday_map);

    $yearly_counts = array();
    $monthly_counts = array();

    $month_number = 1;
    $month_day = 1;
    for ($year_day = 1; $year_day <= $max_day; $year_day++) {
      $key = "{$month_number}M{$month_day}D";

      $short_day = $weekday_map[$weekday];
      if (empty($yearly_counts[$short_day])) {
        $yearly_counts[$short_day] = 0;
      }
      $yearly_counts[$short_day]++;

      if (empty($monthly_counts[$month_number][$short_day])) {
        $monthly_counts[$month_number][$short_day] = 0;
      }
      $monthly_counts[$month_number][$short_day]++;

      $info = array(
        'year' => $year,
        'key' => $key,
        'month' => $month_number,
        'monthday' => $month_day,
        '-monthday' => -$month_days[$month_number] + $month_day - 1,
        'yearday' => $year_day,
        '-yearday' => -$max_day + $year_day - 1,
        'week' => $week_number,
        'weekday' => $short_day,
        'weekday.yearly' => $yearly_counts[$short_day],
        'weekday.monthly' => $monthly_counts[$month_number][$short_day],
      );

      $info_map[$key] = $info;

      $weekday = ($weekday + 1) % 7;
      if ($weekday === $weekday_index) {
        $week_number++;
      }

      $month_day = ($month_day + 1);
      if ($month_day > $month_days[$month_number]) {
        $month_day = 1;
        $month_number++;
      }
    }

    // Check how long the final week is. If it doesn't have four days, this
    // is really the first week of the next year.
    $final_week = array();
    foreach ($info_map as $key => $info) {
      if ($info['week'] == $week_number) {
        $final_week[] = $key;
      }
    }

    if (count($final_week) < 4) {
      $week_number = $week_number - 1;
      $next_year = self::getYearMap($year + 1, $weekday_start);
      $next_year_weeks = $next_year['weekCount'];
    } else {
      $next_year_weeks = null;
    }

    if ($first_week_size < 4) {
      $last_year = self::getYearMap($year - 1, $weekday_start);
      $last_year_weeks = $last_year['weekCount'];
    } else {
      $last_year_weeks = null;
    }

    // Now that we know how many weeks the year has, we can compute the
    // negative offsets.
    foreach ($info_map as $key => $info) {
      $week = $info['week'];

      if ($week === 0) {
        // If this day is part of the first partial week of the year, give
        // it the week number of the last week of the prior year instead.
        $info['week'] = $last_year_weeks;
        $info['-week'] = -1;
      } else if ($week > $week_number) {
        // If this day is part of the last partial week of the year, give
        // it week numbers from the next year.
        $info['week'] = 1;
        $info['-week'] = -$next_year_weeks;
      } else {
        $info['-week'] = -$week_number + $week - 1;
      }

      // Do all the arithmetic to figure out if this is the -19th Thursday
      // in the year and such.
      $month_number = $info['month'];
      $short_day = $info['weekday'];
      $monthly_count = $monthly_counts[$month_number][$short_day];
      $monthly_index = $info['weekday.monthly'];
      $info['-weekday.monthly'] = -$monthly_count + $monthly_index - 1;
      $info['-weekday.monthly'] .= $short_day;
      $info['weekday.monthly'] .= $short_day;

      $yearly_count = $yearly_counts[$short_day];
      $yearly_index = $info['weekday.yearly'];
      $info['-weekday.yearly'] = -$yearly_count + $yearly_index - 1;
      $info['-weekday.yearly'] .= $short_day;
      $info['weekday.yearly'] .= $short_day;

      $info_map[$key] = $info;
    }

    $week_map = array();
    foreach ($info_map as $key => $info) {
      $week_map[$info['week']][] = $key;
    }

    return array(
      'info' => $info_map,
      'weekCount' => $week_number,
      'dayCount' => $max_day,
      'monthDays' => $month_days,
      'weekMap' => $week_map,
    );
  }

  private function newIteratorSet($cursor, $interval, $set, $limit) {
    if ($interval < 1) {
      throw new Exception(
        pht(
          'Invalid iteration interval ("%d"), must be at least 1.',
          $interval));
    }

    $result = array();
    $seen = array();

    $ii = $cursor;
    while (true) {
      if (!$set || isset($set[$ii])) {
        $result[] = $ii;
      }

      $ii = ($ii + $interval);

      if ($ii >= $limit) {
        break;
      }
    }

    sort($result);
    $result = array_values($result);

    return array($ii, $result);
  }

  private function applySetPos(array $values, array $setpos) {
    $select = array();

    $count = count($values);
    foreach ($setpos as $pos) {
      if ($pos > 0 && $pos <= $count) {
        $select[] = ($pos - 1);
      } else if ($pos < 0 && $pos >= -$count) {
        $select[] = ($count + $pos);
      }
    }

    sort($select);
    $select = array_unique($select);

    return array_select_keys($values, $select);
  }

  private function assertByRange(
    $source,
    array $values,
    $min,
    $max,
    $allow_zero = true) {

    foreach ($values as $value) {
      if (!is_int($value)) {
        throw new Exception(
          pht(
            'Value "%s" in RRULE "%s" parameter is invalid: values must be '.
            'integers.',
            $value,
            $source));
      }

      if ($value < $min || $value > $max) {
        throw new Exception(
          pht(
            'Value "%s" in RRULE "%s" parameter is invalid: it must be '.
            'between %s and %s.',
            $value,
            $source,
            $min,
            $max));
      }

      if (!$value && !$allow_zero) {
        throw new Exception(
          pht(
            'Value "%s" in RRULE "%s" parameter is invalid: it must not '.
            'be zero.',
            $value,
            $source));
      }
    }
  }

  private function getSetPositionState() {
    $scale = $this->getFrequencyScale();

    $parts = array();
    $parts[] = $this->stateYear;

    if ($scale == self::SCALE_WEEKLY) {
      $parts[] = $this->stateWeek;
    } else {
      if ($scale < self::SCALE_YEARLY) {
        $parts[] = $this->stateMonth;
      }
      if ($scale < self::SCALE_MONTHLY) {
        $parts[] = $this->stateDay;
      }
      if ($scale < self::SCALE_DAILY) {
        $parts[] = $this->stateHour;
      }
      if ($scale < self::SCALE_HOURLY) {
        $parts[] = $this->stateMinute;
      }
    }

    return implode('/', $parts);
  }

  private function rewindMonth() {
    while ($this->cursorMonth < 1) {
      $this->cursorYear--;
      $this->cursorMonth += 12;
    }
  }

  private function rewindWeek() {
    $week_start = $this->getWeekStart();
    while ($this->cursorWeek < 1) {
      $this->cursorYear--;
      $year_map = $this->getYearMap($this->cursorYear, $week_start);
      $this->cursorWeek += $year_map['weekCount'];
    }
  }

  private function rewindDay() {
    $week_start = $this->getWeekStart();
    while ($this->cursorDay < 1) {
      $year_map = $this->getYearMap($this->cursorYear, $week_start);
      $this->cursorDay += $year_map['monthDays'][$this->cursorMonth];
      $this->cursorMonth--;
      $this->rewindMonth();
    }
  }

  private function rewindHour() {
    while ($this->cursorHour < 0) {
      $this->cursorHour += 24;
      $this->cursorDay--;
      $this->rewindDay();
    }
  }

  private function rewindMinute() {
    while ($this->cursorMinute < 0) {
      $this->cursorMinute += 60;
      $this->cursorHour--;
      $this->rewindHour();
    }
  }

  private function advanceCursorState(
    array $cursor,
    $scale,
    $interval,
    $week_start) {

    $state = array(
      'year' => $this->stateYear,
      'month' => $this->stateMonth,
      'week' => $this->stateWeek,
      'day' => $this->stateDay,
      'hour' => $this->stateHour,
    );

    // In the common case when the interval is 1, we'll visit every possible
    // value so we don't need to do any math and can just jump to the first
    // hour, day, etc.
    if ($interval == 1) {
      if ($this->isCursorBehind($cursor, $state, $scale)) {
        switch ($scale) {
          case self::SCALE_DAILY:
            $this->cursorDay = 1;
            break;
          case self::SCALE_HOURLY:
            $this->cursorHour = 0;
            break;
          case self::SCALE_WEEKLY:
            $this->cursorWeek = 1;
            break;
        }
      }

      return array(false, $state);
    }

    $year_map = $this->getYearMap($cursor['year'], $week_start);
    while ($this->isCursorBehind($cursor, $state, $scale)) {
      switch ($scale) {
        case self::SCALE_DAILY:
          $cursor['day'] += $interval;
          break;
        case self::SCALE_HOURLY:
          $cursor['hour'] += $interval;
          break;
        case self::SCALE_WEEKLY:
          $cursor['week'] += $interval;
          break;
      }

      if ($scale <= self::SCALE_HOURLY) {
        while ($cursor['hour'] >= 24) {
          $cursor['hour'] -= 24;
          $cursor['day']++;
        }
      }

      if ($scale == self::SCALE_WEEKLY) {
        while ($cursor['week'] > $year_map['weekCount']) {
          $cursor['week'] -= $year_map['weekCount'];
          $cursor['year']++;
          $year_map = $this->getYearMap($cursor['year'], $week_start);
        }
      }

      if ($scale <= self::SCALE_DAILY) {
        while ($cursor['day'] > $year_map['monthDays'][$cursor['month']]) {
          $cursor['day'] -= $year_map['monthDays'][$cursor['month']];
          $cursor['month']++;
          if ($cursor['month'] > 12) {
            $cursor['month'] -= 12;
            $cursor['year']++;
            $year_map = $this->getYearMap($cursor['year'], $week_start);
          }
        }
      }
    }

    switch ($scale) {
      case self::SCALE_DAILY:
        $this->cursorDay = $cursor['day'];
        break;
      case self::SCALE_HOURLY:
        $this->cursorHour = $cursor['hour'];
        break;
      case self::SCALE_WEEKLY:
        $this->cursorWeek = $cursor['week'];
        break;
    }

    $skip = $this->isCursorBehind($state, $cursor, $scale);

    return array($skip, $cursor);
  }

  private function isCursorBehind(array $cursor, array $state, $scale) {
    if ($cursor['year'] < $state['year']) {
      return true;
    } else if ($cursor['year'] > $state['year']) {
      return false;
    }

    if ($scale == self::SCALE_WEEKLY) {
      return false;
    }

    if ($cursor['month'] < $state['month']) {
      return true;
    } else if ($cursor['month'] > $state['month']) {
      return false;
    }

    if ($scale >= self::SCALE_DAILY) {
      return false;
    }

    if ($cursor['day'] < $state['day']) {
      return true;
    } else if ($cursor['day'] > $state['day']) {
      return false;
    }

    if ($scale >= self::SCALE_HOURLY) {
      return false;
    }

    if ($cursor['hour'] < $state['hour']) {
      return true;
    } else if ($cursor['hour'] > $state['hour']) {
      return false;
    }

    return false;
  }


}
