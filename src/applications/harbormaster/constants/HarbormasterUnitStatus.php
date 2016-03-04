<?php

final class HarbormasterUnitStatus
  extends Phobject {

  public static function getUnitStatusIcon($status) {
    $map = self::getUnitStatusDictionary($status);
    $default = 'fa-question-circle';
    return idx($map, 'icon', $default);
  }

  public static function getUnitStatusColor($status) {
    $map = self::getUnitStatusDictionary($status);
    $default = 'violet';
    return idx($map, 'color', $default);
  }

  public static function getUnitStatusLabel($status) {
    $map = self::getUnitStatusDictionary($status);
    $default = pht('Unknown Status ("%s")', $status);
    return idx($map, 'label', $default);
  }

  public static function getUnitStatusSort($status) {
    $map = self::getUnitStatusDictionary($status);
    $default = 'N';
    return idx($map, 'sort', $default);
  }

  private static function getUnitStatusDictionary($status) {
    $map = self::getUnitStatusMap();
    $default = array();
    return idx($map, $status, $default);
  }

  public static function getUnitStatusCountLabel($status, $count) {
    $count = new PhutilNumber($count);

    switch ($status) {
      case ArcanistUnitTestResult::RESULT_FAIL:
        return pht('%s Failed Test(s)', $count);
      case ArcanistUnitTestResult::RESULT_BROKEN:
        return pht('%s Broken Test(s)', $count);
      case ArcanistUnitTestResult::RESULT_UNSOUND:
        return pht('%s Unsound Test(s)', $count);
      case ArcanistUnitTestResult::RESULT_PASS:
        return pht('%s Passed Test(s)', $count);
      case ArcanistUnitTestResult::RESULT_SKIP:
        return pht('%s Skipped Test(s)', $count);
    }

    return pht('%s Other Test(s)', $count);
  }

  private static function getUnitStatusMap() {
    return array(
      ArcanistUnitTestResult::RESULT_FAIL => array(
        'label' => pht('Failed'),
        'icon' => 'fa-times',
        'color' => 'red',
        'sort' => 'A',
      ),
      ArcanistUnitTestResult::RESULT_BROKEN => array(
        'label' => pht('Broken'),
        'icon' => 'fa-bomb',
        'color' => 'indigo',
        'sort' => 'B',
      ),
      ArcanistUnitTestResult::RESULT_UNSOUND => array(
        'label' => pht('Unsound'),
        'icon' => 'fa-exclamation-triangle',
        'color' => 'yellow',
        'sort' => 'C',
      ),
      ArcanistUnitTestResult::RESULT_PASS => array(
        'label' => pht('Passed'),
        'icon' => 'fa-check',
        'color' => 'green',
        'sort' => 'D',
      ),
      ArcanistUnitTestResult::RESULT_SKIP => array(
        'label' => pht('Skipped'),
        'icon' => 'fa-fast-forward',
        'color' => 'blue',
        'sort' => 'E',
      ),
    );
  }

}
