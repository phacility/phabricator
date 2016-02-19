<?php

final class PhabricatorProjectWorkboardBackgroundColor extends Phobject {

  public static function getOptions() {
    $options = array(
      array(
        'key' => '',
        'name' => pht('Use Parent Background (Default)'),
        'special' => 'parent',
        'icon' => 'fa-chevron-circle-up',
        'group' => 'basic',
      ),
      array(
        'key' => 'none',
        'name' => pht('No Background'),
        'special' => 'none',
        'icon' => 'fa-ban',
        'group' => 'basic',
      ),
      array(
        'key' => 'red',
        'name' => pht('Red'),
      ),
      array(
        'key' => 'orange',
        'name' => pht('Orange'),
      ),
      array(
        'key' => 'yellow',
        'name' => pht('Yellow'),
      ),
      array(
        'key' => 'green',
        'name' => pht('Green'),
      ),
      array(
        'key' => 'blue',
        'name' => pht('Blue'),
      ),
      array(
        'key' => 'indigo',
        'name' => pht('Indigo'),
      ),
      array(
        'key' => 'violet',
        'name' => pht('Violet'),
      ),
      array(
        'key' => 'sky',
        'name' => pht('Sky'),
      ),
      array(
        'key' => 'pink',
        'name' => pht('Pink'),
      ),
      array(
        'key' => 'fire',
        'name' => pht('Fire'),
      ),
      array(
        'key' => 'grey',
        'name' => pht('Grey'),
      ),
      array(
        'key' => 'gradient-red',
        'name' => pht('Ripe Peach'),
      ),
      array(
        'key' => 'gradient-orange',
        'name' => pht('Ripe Orange'),
      ),
      array(
        'key' => 'gradient-yellow',
        'name' => pht('Ripe Mango'),
      ),
      array(
        'key' => 'gradient-green',
        'name' => pht('Shallows'),
      ),
      array(
        'key' => 'gradient-blue',
        'name' => pht('Reef'),
      ),
      array(
        'key' => 'gradient-bluegrey',
        'name' => pht('Depths'),
      ),
      array(
        'key' => 'gradient-indigo',
        'name' => pht('This One Is Purple'),
      ),
      array(
        'key' => 'gradient-violet',
        'name' => pht('Unripe Plum'),
      ),
      array(
        'key' => 'gradient-sky',
        'name' => pht('Blue Sky'),
      ),
      array(
        'key' => 'gradient-pink',
        'name' => pht('Intensity'),
      ),
      array(
        'key' => 'gradient-grey',
        'name' => pht('Into The Expanse'),
      ),
    );

    foreach ($options as $key => $option) {
      if (empty($option['group'])) {
        if (preg_match('/^gradient/', $option['key'])) {
          $option['group'] = 'gradient';
        } else {
          $option['group'] = 'solid';
        }
      }
      $options[$key] = $option;
    }

    return ipull($options, null, 'key');
  }
}
