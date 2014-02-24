<?php

/**
 * @group calendar
 */
final class CalendarColors extends CalendarConstants {

  const COLOR_RED = 'red';
  const COLOR_ORANGE = 'orange';
  const COLOR_YELLOW = 'yellow';
  const COLOR_GREEN = 'green';
  const COLOR_BLUE = 'blue';
  const COLOR_SKY = 'sky';
  const COLOR_INDIGO = 'indigo';
  const COLOR_VIOLET = 'violet';

  public static function getColors() {
    return array(
      self::COLOR_SKY,
      self::COLOR_GREEN,
      self::COLOR_VIOLET,
      self::COLOR_ORANGE,
      self::COLOR_BLUE,
      self::COLOR_INDIGO,
      self::COLOR_RED,
      self::COLOR_YELLOW,
    );
  }

}
