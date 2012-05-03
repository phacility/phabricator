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

final class PhabricatorCalendarHoliday extends PhabricatorCalendarDAO {

  protected $day;
  protected $name;

  public function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
    ) + parent::getConfiguration();
  }

  public static function getNthBusinessDay($epoch, $n) {
    // Sadly, there are not many holidays. So we can load all of them.
    $holidays = id(new PhabricatorCalendarHoliday())->loadAll();
    $holidays = mpull($holidays, null, 'getDay');
    $interval = ($n > 0 ? 1 : -1) * 24 * 60 * 60;

    $return = $epoch;
    for ($i = abs($n); $i > 0; ) {
      $return += $interval;
      $weekday = date('w', $return);
      if ($weekday != 0 && $weekday != 6 && // Sunday and Saturday
          !isset($holidays[date('Y-m-d', $return)])) {
        $i--;
      }
    }
    return $return;
  }

}
