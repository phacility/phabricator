<?php

final class PhabricatorProjectIconSet
  extends PhabricatorIconSet {

  const ICONSETKEY = 'projects';

  public function getSelectIconTitleText() {
    return pht('Choose Project Icon');
  }

  protected function newIcons() {
    $map = array(
      'fa-briefcase' => pht('Briefcase'),
      'fa-tags' => pht('Tag'),
      'fa-folder' => pht('Folder'),
      'fa-users' => pht('Team'),

      'fa-bug' => pht('Bug'),
      'fa-trash-o' => pht('Garbage'),
      'fa-calendar' => pht('Deadline'),
      'fa-flag-checkered' => pht('Goal'),

      'fa-envelope' => pht('Communication'),
      'fa-truck' => pht('Release'),
      'fa-lock' => pht('Policy'),
      'fa-umbrella' => pht('An Umbrella'),

      'fa-cloud' => pht('The Cloud'),
      'fa-building' => pht('Company'),
      'fa-credit-card' => pht('Accounting'),
      'fa-flask' => pht('Experimental'),
    );

    $icons = array();
    foreach ($map as $key => $label) {
      $icons[] = id(new PhabricatorIconSetIcon())
        ->setKey($key)
        ->setLabel($label);
    }

    return $icons;
  }

  public static function getColorMap() {
    $shades = PHUITagView::getShadeMap();
    $shades = array_select_keys(
      $shades,
      array(PhabricatorProject::DEFAULT_COLOR)) + $shades;
    unset($shades[PHUITagView::COLOR_DISABLED]);

    return $shades;
  }

}
