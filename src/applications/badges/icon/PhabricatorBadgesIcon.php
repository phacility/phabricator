<?php

final class PhabricatorBadgesIcon extends Phobject {

  public static function getIconMap() {
    return
      array(
        'fa-star' => pht('Superstar'),
        'fa-user' => pht('Average Person'),
        'fa-bug' => pht('Ladybug'),
        'fa-users' => pht('Triplets'),

        'fa-book' => pht('Nominomicon'),
        'fa-rocket' => pht('Escape Route'),
        'fa-life-ring' => pht('Foam Circle'),
        'fa-birthday-cake' => pht('Cake Day'),

        'fa-camera-retro' => pht('Leica Enthusiast'),
        'fa-beer' => pht('Liquid Lunch'),
        'fa-gift' => pht('Free Stuff'),
        'fa-eye' => pht('Eye See You'),

        'fa-heart' => pht('Love is Love'),
        'fa-trophy' => pht('Winner at Things'),
        'fa-umbrella' => pht('Rain Defender'),
        'fa-graduation-cap' => pht('In Debt'),

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
    $badge_icons = self::getIconMap();

    return phutil_tag(
      'span',
      array(),
      array(
        id(new PHUIIconView())->setIconFont($icon),
        ' ',
        idx($badge_icons, $icon, pht('Unknown Icon')),
      ));
  }

}
