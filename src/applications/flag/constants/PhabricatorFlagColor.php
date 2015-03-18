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
      self::COLOR_RED       => pht('Red'),
      self::COLOR_ORANGE    => pht('Orange'),
      self::COLOR_YELLOW    => pht('Yellow'),
      self::COLOR_GREEN     => pht('Green'),
      self::COLOR_BLUE      => pht('Blue'),
      self::COLOR_PINK      => pht('Pink'),
      self::COLOR_PURPLE    => pht('Purple'),
      self::COLOR_CHECKERED => pht('Checkered'),
    );
  }

  public static function getColorName($color) {
    return idx(self::getColorNameMap(), $color, pht('Unknown'));
  }

  public static function getCSSClass($color) {
    return 'phabricator-flag-color-'.(int)$color;
  }

  public static function getIcon($color) {
    $map = array(
      self::COLOR_RED       => 'fa-flag red',
      self::COLOR_ORANGE    => 'fa-flag orange',
      self::COLOR_YELLOW    => 'fa-flag yellow',
      self::COLOR_GREEN     => 'fa-flag green',
      self::COLOR_BLUE      => 'fa-flag blue',
      self::COLOR_PINK      => 'fa-flag pink',
      self::COLOR_PURPLE    => 'fa-flag violet',
      self::COLOR_CHECKERED => 'fa-flag-checkered',
    );
    return idx($map, $color);
  }

}
