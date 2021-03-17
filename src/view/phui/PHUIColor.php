<?php

final class PHUIColor extends Phobject {

  public static function getWebColorFromANSIColor($ansi_color) {
    $map = array(
      'cyan' => 'sky',
      'magenta' => 'pink',
    );

    return idx($map, $ansi_color, $ansi_color);
  }
}
