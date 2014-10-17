<?php

final class AlmanacNames extends Phobject {

  public static function validateServiceOrDeviceName($name) {
    if (strlen($name) < 3) {
      throw new Exception(
        pht(
          'Almanac service and device names must be at least 3 '.
          'characters long.'));
    }

    if (!preg_match('/^[a-z0-9.-]+\z/', $name)) {
      throw new Exception(
        pht(
          'Almanac service and device names may only contain lowercase '.
          'letters, numbers, hyphens, and periods.'));
    }

    if (preg_match('/(^|\\.)\d+(\z|\\.)/', $name)) {
      throw new Exception(
        pht(
          'Almanac service and device names may not have any segments '.
          'containing only digits.'));
    }

    if (preg_match('/\.\./', $name)) {
      throw new Exception(
        pht(
          'Almanac service and device names may not contain multiple '.
          'consecutive periods.'));
    }

    if (preg_match('/\\.-|-\\./', $name)) {
      throw new Exception(
        pht(
          'Amanac service and device names may not contain hyphens adjacent '.
          'to periods.'));
    }

    if (preg_match('/--/', $name)) {
      throw new Exception(
        pht(
          'Almanac service and device names may not contain multiple '.
          'consecutive hyphens.'));
    }

    if (!preg_match('/^[a-z0-9].*[a-z0-9]\z/', $name)) {
      throw new Exception(
        pht(
          'Almanac service and device names must begin and end with a letter '.
          'or number.'));
    }
  }

}
