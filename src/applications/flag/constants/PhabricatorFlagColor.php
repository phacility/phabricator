<?php

final class PhabricatorFlagColor extends PhabricatorFlagConstants {

  const COLOR_RED       = 0;
  const COLOR_ORANGE    = 1;
  const COLOR_YELLOW    = 2;
  const COLOR_GREEN     = 3;
  const COLOR_BLUE      = 4;
  const COLOR_PINK      = 5;
  const COLOR_PURPLE    = 6;
  const COLOR_CHECKERED = 7;

  public static function getColorNameMap() {
    return array(
      self::COLOR_RED       => 'Red',
      self::COLOR_ORANGE    => 'Orange',
      self::COLOR_YELLOW    => 'Yellow',
      self::COLOR_GREEN     => 'Green',
      self::COLOR_BLUE      => 'Blue',
      self::COLOR_PINK      => 'Pink',
      self::COLOR_PURPLE    => 'Purple',
      self::COLOR_CHECKERED => 'Checkered',
    );
  }

  public static function getColorName($color) {
    return idx(self::getColorNameMap(), $color, 'Unknown');
  }

  public static function getCSSClass($color) {
    return 'phabricator-flag-color-'.(int)$color;
  }

}
