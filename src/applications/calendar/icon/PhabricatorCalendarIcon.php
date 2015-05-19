<?php

final class PhabricatorCalendarIcon extends Phobject {

  public static function getIconMap() {
    return
      array(
        'fa-calendar' => pht('Default'),
        'fa-glass' => pht('Party'),
        'fa-plane' => pht('Travel'),
        'fa-plus-square' => pht('Health / Appointment'),
        'fa-rocket' => pht('Sabatical / Leave'),
        'fa-home' => pht('Working From Home'),
        'fa-tree' => pht('Holiday'),
        'fa-gamepad' => pht('Staycation'),
        'fa-coffee' => pht('Coffee Meeting'),
        'fa-film' => pht('Movie'),
        'fa-users' => pht('Meeting'),
        'fa-cutlery' => pht('Meal'),
        'fa-paw' => pht('Pet Activity'),
        'fa-institution' => pht('Official Business'),
        'fa-bus' => pht('Field Trip'),
        'fa-microphone' => pht('Conference'),
      );
  }

  public static function getLabel($key) {
    $map = self::getIconMap();
    return $map[$key];
  }

  public static function getAPIName($key) {
    return substr($key, 3);
  }

  public static function renderIconForChooser($icon) {
    $calendar_icons = self::getIconMap();

    return phutil_tag(
      'span',
      array(),
      array(
        id(new PHUIIconView())->setIconFont($icon),
        ' ',
        idx($calendar_icons, $icon, pht('Unknown Icon')),
      ));
  }

}
