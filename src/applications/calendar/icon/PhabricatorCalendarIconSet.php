<?php

final class PhabricatorCalendarIconSet
  extends PhabricatorIconSet {

  const ICONSETKEY = 'calendar.event';

  public function getSelectIconTitleText() {
    return pht('Choose Event Icon');
  }

  protected function newIcons() {
    $map = array(
      'fa-calendar' => pht('Default'),
      'fa-glass' => pht('Party'),
      'fa-plane' => pht('Travel'),
      'fa-plus-square' => pht('Health / Appointment'),

      'fa-rocket' => pht('Sabbatical / Leave'),
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

    $icons = array();
    foreach ($map as $key => $label) {
      $icons[] = id(new PhabricatorIconSetIcon())
        ->setKey($key)
        ->setLabel($label);
    }

    return $icons;
  }

}
