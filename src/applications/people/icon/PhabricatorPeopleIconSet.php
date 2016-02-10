<?php

final class PhabricatorPeopleIconSet
  extends PhabricatorIconSet {

  const ICONSETKEY = 'people';

  public function getSelectIconTitleText() {
    return pht('Choose User Icon');
  }

  protected function newIcons() {
    $specifications = self::getIconSpecifications();

    $icons = array();
    foreach ($specifications as $spec) {
      $icons[] = id(new PhabricatorIconSetIcon())
        ->setKey($spec['key'])
        ->setIcon($spec['icon'])
        ->setLabel($spec['name']);
    }

    return $icons;
  }

  public static function getDefaultIconKey() {
    $specifications = self::getIconSpecifications();

    foreach ($specifications as $spec) {
      if (idx($spec, 'default')) {
        return $spec['key'];
      }
    }

    return null;
  }

  public static function getIconIcon($key) {
    $specifications = self::getIconSpecifications();
    $map = ipull($specifications, 'icon', 'key');
    return idx($map, $key);
  }

  public static function getIconName($key) {
    $specifications = self::getIconSpecifications();
    $map = ipull($specifications, 'name', 'key');
    return idx($map, $key);
  }

  private static function getIconSpecifications() {
    return self::getDefaultSpecifications();
  }

  private static function getDefaultSpecifications() {
    return array(
      array(
        'key' => 'person',
        'icon' => 'fa-user',
        'name' => pht('User'),
        'default' => true,
      ),
      array(
        'key' => 'engineering',
        'icon' => 'fa-code',
        'name' => pht('Engineering'),
      ),
      array(
        'key' => 'operations',
        'icon' => 'fa-space-shuttle',
        'name' => pht('Operations'),
      ),
      array(
        'key' => 'resources',
        'icon' => 'fa-heart',
        'name' => pht('Resources'),
      ),
      array(
        'key' => 'camera',
        'icon' => 'fa-camera-retro',
        'name' => pht('Design'),
      ),
      array(
        'key' => 'music',
        'icon' => 'fa-headphones',
        'name' => pht('Musician'),
      ),
      array(
        'key' => 'spy',
        'icon' => 'fa-user-secret',
        'name' => pht('Spy'),
      ),
      array(
        'key' => 'android',
        'icon' => 'fa-android',
        'name' => pht('Bot'),
      ),
      array(
        'key' => 'relationships',
        'icon' => 'fa-glass',
        'name' => pht('Relationships'),
      ),
      array(
        'key' => 'administration',
        'icon' => 'fa-fax',
        'name' => pht('Administration'),
      ),
      array(
        'key' => 'security',
        'icon' => 'fa-shield',
        'name' => pht('Security'),
      ),
      array(
        'key' => 'logistics',
        'icon' => 'fa-truck',
        'name' => pht('Logistics'),
      ),
      array(
        'key' => 'research',
        'icon' => 'fa-flask',
        'name' => pht('Research'),
      ),
      array(
        'key' => 'analysis',
        'icon' => 'fa-bar-chart-o',
        'name' => pht('Analysis'),
      ),
      array(
        'key' => 'executive',
        'icon' => 'fa-angle-double-up',
        'name' => pht('Executive'),
      ),
      array(
        'key' => 'animal',
        'icon' => 'fa-paw',
        'name' => pht('Animal'),
      ),
    );
  }

}
