<?php

final class PhabricatorBadgesIconSet
  extends PhabricatorIconSet {

  const ICONSETKEY = 'badges';

  public function getSelectIconTitleText() {
    return pht('Choose Badge Icon');
  }

  protected function newIcons() {
    $map = array(
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

    $icons = array();
    foreach ($map as $key => $label) {
      $icons[] = id(new PhabricatorIconSetIcon())
        ->setKey($key)
        ->setLabel($label);
    }

    return $icons;
  }

}
