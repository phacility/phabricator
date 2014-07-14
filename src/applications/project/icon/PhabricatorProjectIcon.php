<?php

final class PhabricatorProjectIcon extends Phobject {

  public static function getIconMap() {
    return
      array(
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
  }

  public static function getLabel($key) {
    $map = self::getIconMap();
    return $map[$key];
  }

  public static function renderIconForChooser($icon) {
    $project_icons = PhabricatorProjectIcon::getIconMap();

    return phutil_tag(
      'span',
      array(),
      array(
        id(new PHUIIconView())->setIconFont($icon),
        ' ',
        idx($project_icons, $icon, pht('Unknown Icon')),
      ));
  }

}
