<?php

final class PhabricatorProfilePanelIconSet
  extends PhabricatorIconSet {

  const ICONSETKEY = 'profilepanel';

  public function getSelectIconTitleText() {
    return pht('Choose Item Icon');
  }

  protected function newIcons() {
    $list = array(
      array(
        'key' => 'link',
        'icon' => 'fa-link',
        'name' => pht('Link'),
      ),
      array(
        'key' => 'maniphest',
        'icon' => 'fa-anchor',
        'name' => pht('Maniphest'),
      ),
      array(
        'key' => 'feed',
        'icon' => 'fa-newspaper-o',
        'name' => pht('Feed'),
      ),
    );

    $icons = array();
    foreach ($list as $spec) {
      $icons[] = id(new PhabricatorIconSetIcon())
        ->setKey($spec['key'])
        ->setIcon($spec['icon'])
        ->setLabel($spec['name']);
    }

    return $icons;
  }

}
