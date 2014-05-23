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
      );
  }

  public static function getLabel($key) {
    $map = self::getIconMap();
    return $map[$key];
  }
}
