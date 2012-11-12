<?php

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
