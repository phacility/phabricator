<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

final class AphrontCalendarMonthView extends AphrontView {

  private $user;
  private $month;
  private $year;
  private $holidays = array();

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function setHolidays(array $holidays) {
    assert_instances_of($holidays, 'PhabricatorCalendarHoliday');
    $this->holidays = mpull($holidays, null, 'getDay');
    return $this;
  }

  public function __construct($month, $year) {
    $this->month = $month;
    $this->year = $year;
  }

  public function render() {
    if (empty($this->user)) {
      throw new Exception("Call setUser() before render()!");
    }

    $days = $this->getDatesInMonth();

    require_celerity_resource('aphront-calendar-view-css');

    $first = reset($days);
    $empty = $first->format('w');

    $markup = array();

    for ($ii = 0; $ii < $empty; $ii++) {
      $markup[] = null;
    }

    foreach ($days as $day) {
      $holiday = idx($this->holidays, $day->format('Y-m-d'));
      $class = 'aphront-calendar-day-of-month';
      $weekday = $day->format('w');
      if ($holiday || $weekday == 0 || $weekday == 6) {
        $class .= ' aphront-calendar-not-work-day';
      }
      $markup[] =
        '<div class="'.$class.'">'.
          $day->format('j').
          ($holiday ? '<br />'.phutil_escape_html($holiday->getName()) : '').
        '</div>';
    }

    $table = array();
    $rows = array_chunk($markup, 7);
    foreach ($rows as $row) {
      $table[] = '<tr>';
      while (count($row) < 7) {
        $row[] = null;
      }
      foreach ($row as $cell) {
        $table[] = '<td>'.$cell.'</td>';
      }
      $table[] = '</tr>';
    }
    $table =
      '<table class="aphront-calendar-view">'.
        '<tr class="aphront-calendar-month-year-header">'.
          '<th colspan="7">'.$first->format('F Y').'</th>'.
        '</tr>'.
        '<tr class="aphront-calendar-day-of-week-header">'.
          '<th>Sun</th>'.
          '<th>Mon</th>'.
          '<th>Tue</th>'.
          '<th>Wed</th>'.
          '<th>Thu</th>'.
          '<th>Fri</th>'.
          '<th>Sat</th>'.
        '</tr>'.
        implode("\n", $table).
      '</table>';

    return $table;
  }

  /**
   * Return a DateTime object representing the first moment in each day in the
   * month, according to the user's locale.
   *
   * @return list List of DateTimes, one for each day.
   */
  private function getDatesInMonth() {
    $user = $this->user;

    $timezone = new DateTimeZone($user->getTimezoneIdentifier());

    $month = $this->month;
    $year = $this->year;

    // Find the year and month numbers of the following month, so we can
    // determine when this month ends.
    $next_year = $year;
    $next_month = $month + 1;
    if ($next_month == 13) {
      $next_year = $year + 1;
      $next_month = 1;
    }

    $end_date = new DateTime("{$next_year}-{$next_month}-01", $timezone);
    $end_epoch = $end_date->format('U');

    $days = array();
    for ($day = 1; $day <= 31; $day++) {
      $day_date = new DateTime("{$year}-{$month}-{$day}", $timezone);
      $day_epoch = $day_date->format('U');
      if ($day_epoch >= $end_epoch) {
        break;
      } else {
        $days[] = $day_date;
      }
    }

    return $days;
  }
}
