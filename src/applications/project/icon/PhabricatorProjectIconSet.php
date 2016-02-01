<?php

final class PhabricatorProjectIconSet
  extends PhabricatorIconSet {

  const ICONSETKEY = 'projects';

  const SPECIAL_MILESTONE = 'milestone';

  public function getSelectIconTitleText() {
    return pht('Choose Project Icon');
  }

  public static function getDefaultConfiguration() {
    return array(
      array(
        'key' => 'project',
        'icon' => 'fa-briefcase',
        'name' => pht('Project'),
        'default' => true,
      ),
      array(
        'key' => 'tag',
        'icon' => 'fa-tags',
        'name' => pht('Tag'),
      ),
      array(
        'key' => 'policy',
        'icon' => 'fa-lock',
        'name' => pht('Policy'),
      ),
      array(
        'key' => 'group',
        'icon' => 'fa-users',
        'name' => pht('Group'),
      ),
      array(
        'key' => 'folder',
        'icon' => 'fa-folder',
        'name' => pht('Folder'),
      ),
      array(
        'key' => 'timeline',
        'icon' => 'fa-calendar',
        'name' => pht('Timeline'),
      ),
      array(
        'key' => 'goal',
        'icon' => 'fa-flag-checkered',
        'name' => pht('Goal'),
      ),
      array(
        'key' => 'release',
        'icon' => 'fa-truck',
        'name' => pht('Release'),
      ),
      array(
        'key' => 'bugs',
        'icon' => 'fa-bug',
        'name' => pht('Bugs'),
      ),
      array(
        'key' => 'cleanup',
        'icon' => 'fa-trash-o',
        'name' => pht('Cleanup'),
      ),
      array(
        'key' => 'umbrella',
        'icon' => 'fa-umbrella',
        'name' => pht('Umbrella'),
      ),
      array(
        'key' => 'communication',
        'icon' => 'fa-envelope',
        'name' => pht('Communication'),
      ),
      array(
        'key' => 'organization',
        'icon' => 'fa-building',
        'name' => pht('Organization'),
      ),
      array(
        'key' => 'infrastructure',
        'icon' => 'fa-cloud',
        'name' => pht('Infrastructure'),
      ),
      array(
        'key' => 'account',
        'icon' => 'fa-credit-card',
        'name' => pht('Account'),
      ),
      array(
        'key' => 'experimental',
        'icon' => 'fa-flask',
        'name' => pht('Experimental'),
      ),
      array(
        'key' => 'milestone',
        'icon' => 'fa-map-marker',
        'name' => pht('Milestone'),
        'special' => self::SPECIAL_MILESTONE,
      ),
    );
  }


  protected function newIcons() {
    $map = self::getIconSpecifications();

    $icons = array();
    foreach ($map as $spec) {
      $special = idx($spec, 'special');

      if ($special === self::SPECIAL_MILESTONE) {
        continue;
      }

      $icons[] = id(new PhabricatorIconSetIcon())
        ->setKey($spec['key'])
        ->setIsDisabled(idx($spec, 'disabled'))
        ->setIcon($spec['icon'])
        ->setLabel($spec['name']);
    }

    return $icons;
  }

  private static function getIconSpecifications() {
    return PhabricatorEnv::getEnvConfig('projects.icons');
  }

  public static function getDefaultIconKey() {
    $icons = self::getIconSpecifications();
    foreach ($icons as $icon) {
      if (idx($icon, 'default')) {
        return $icon['key'];
      }
    }
    return null;
  }

  public static function getIconIcon($key) {
    $spec = self::getIconSpec($key);
    return idx($spec, 'icon', null);
  }

  public static function getIconName($key) {
    $spec = self::getIconSpec($key);
    return idx($spec, 'name', null);
  }

  private static function getIconSpec($key) {
    $icons = self::getIconSpecifications();
    foreach ($icons as $icon) {
      if (idx($icon, 'key') === $key) {
        return $icon;
      }
    }

    return array();
  }

  public static function getMilestoneIconKey() {
    $icons = self::getIconSpecifications();
    foreach ($icons as $icon) {
      if (idx($icon, 'special') === self::SPECIAL_MILESTONE) {
        return idx($icon, 'key');
      }
    }
    return null;
  }

  public static function validateConfiguration($config) {
    if (!is_array($config)) {
      throw new Exception(
        pht('Configuration must be a list of project icon specifications.'));
    }

    foreach ($config as $idx => $value) {
      if (!is_array($value)) {
        throw new Exception(
          pht(
            'Value for index "%s" should be a dictionary.',
            $idx));
      }

      PhutilTypeSpec::checkMap(
        $value,
        array(
          'key' => 'string',
          'name' => 'string',
          'icon' => 'string',
          'special' => 'optional string',
          'disabled' => 'optional bool',
          'default' => 'optional bool',
        ));

      if (!preg_match('/^[a-z]{1,32}\z/', $value['key'])) {
        throw new Exception(
          pht(
            'Icon key "%s" is not a valid icon key. Icon keys must be 1-32 '.
            'characters long and contain only lowercase letters. For example, '.
            '"%s" and "%s" are reasonable keys.',
            'tag',
            'group'));
      }

      $special = idx($value, 'special');
      $valid = array(
        self::SPECIAL_MILESTONE => true,
      );

      if ($special !== null) {
        if (empty($valid[$special])) {
          throw new Exception(
            pht(
              'Icon special attribute "%s" is not valid. Recognized special '.
              'attributes are: %s.',
              $special,
              implode(', ', array_keys($valid))));
        }
      }
    }

    $default = null;
    $milestone = null;
    $keys = array();
    foreach ($config as $idx => $value) {
      $key = $value['key'];
      if (isset($keys[$key])) {
        throw new Exception(
          pht(
            'Project icons must have unique keys, but two icons share the '.
            'same key ("%s").',
            $key));
      } else {
        $keys[$key] = true;
      }

      $is_disabled = idx($value, 'disabled');

      if (idx($value, 'default')) {
        if ($default === null) {
          if ($is_disabled) {
            throw new Exception(
              pht(
                'The project icon marked as the default icon ("%s") must not '.
                'be disabled.',
                $key));
          }
          $default = $value;
        } else {
          $original_key = $default['key'];
          throw new Exception(
            pht(
              'Two different icons ("%s", "%s") are marked as the default '.
              'icon. Only one icon may be marked as the default.',
              $key,
              $original_key));
        }
      }

      $special = idx($value, 'special');
      if ($special === self::SPECIAL_MILESTONE) {
        if ($milestone === null) {
          if ($is_disabled) {
            throw new Exception(
              pht(
                'The project icon ("%s") with special attribute "%s" must '.
                'not be disabled',
                $key,
                self::SPECIAL_MIILESTONE));
          }
          $milestone = $value;
        } else {
          $original_key = $milestone['key'];
          throw new Exception(
            pht(
              'Two different icons ("%s", "%s") are marked with special '.
              'attribute "%s". Only one icon may be marked with this '.
              'attribute.',
              $key,
              $original_key,
              self::SPECIAL_MILESTONE));
        }
      }
    }

    if ($default === null) {
      throw new Exception(
        pht(
          'Project icons must include one icon marked as the "%s" icon, '.
          'but no such icon exists.',
          'default'));
    }

    if ($milestone === null) {
      throw new Exception(
        pht(
          'Project icons must include one icon marked with special attribute '.
          '"%s", but no such icon exists.',
          self::SPECIAL_MILESTONE));
    }

  }

  private static function getColorSpecifications() {
    return PhabricatorEnv::getEnvConfig('projects.colors');
  }

  public static function getColorMap() {
    $specifications = self::getColorSpecifications();
    return ipull($specifications, 'name', 'key');
  }

  public static function getDefaultColorKey() {
    $specifications = self::getColorSpecifications();

    foreach ($specifications as $specification) {
      if (idx($specification, 'default')) {
        return $specification['key'];
      }
    }

    return null;
  }

  private static function getAvailableColorKeys() {
    $list = array();

    $specifications = self::getDefaultColorMap();
    foreach ($specifications as $specification) {
      $list[] = $specification['key'];
    }

    return $list;
  }

  public static function getColorName($color_key) {
    $map = self::getColorMap();
    return idx($map, $color_key);
  }

  public static function getDefaultColorMap() {
    return array(
      array(
        'key' => PHUITagView::COLOR_RED,
        'name' => pht('Red'),
      ),
      array(
        'key' => PHUITagView::COLOR_ORANGE,
        'name' => pht('Orange'),
      ),
      array(
        'key' => PHUITagView::COLOR_YELLOW,
        'name' => pht('Yellow'),
      ),
      array(
        'key' => PHUITagView::COLOR_GREEN,
        'name' => pht('Green'),
      ),
      array(
        'key' => PHUITagView::COLOR_BLUE,
        'name' => pht('Blue'),
        'default' => true,
      ),
      array(
        'key' => PHUITagView::COLOR_INDIGO,
        'name' => pht('Indigo'),
      ),
      array(
        'key' => PHUITagView::COLOR_VIOLET,
        'name' => pht('Violet'),
      ),
      array(
        'key' => PHUITagView::COLOR_PINK,
        'name' => pht('Pink'),
      ),
      array(
        'key' => PHUITagView::COLOR_GREY,
        'name' => pht('Grey'),
      ),
      array(
        'key' => PHUITagView::COLOR_CHECKERED,
        'name' => pht('Checkered'),
      ),
    );
  }

  public static function validateColorConfiguration($config) {
    if (!is_array($config)) {
      throw new Exception(
        pht('Configuration must be a list of project color specifications.'));
    }

    $available_keys = self::getAvailableColorKeys();
    $available_keys = array_fuse($available_keys);

    foreach ($config as $idx => $value) {
      if (!is_array($value)) {
        throw new Exception(
          pht(
            'Value for index "%s" should be a dictionary.',
            $idx));
      }

      PhutilTypeSpec::checkMap(
        $value,
        array(
          'key' => 'string',
          'name' => 'string',
          'default' => 'optional bool',
        ));

      $key = $value['key'];
      if (!isset($available_keys[$key])) {
        throw new Exception(
          pht(
            'Color key "%s" is not a valid color key. The supported color '.
            'keys are: %s.',
            $key,
            implode(', ', $available_keys)));
      }
    }

    $default = null;
    $keys = array();
    foreach ($config as $idx => $value) {
      $key = $value['key'];
      if (isset($keys[$key])) {
        throw new Exception(
          pht(
            'Project colors must have unique keys, but two icons share the '.
            'same key ("%s").',
            $key));
      } else {
        $keys[$key] = true;
      }

      if (idx($value, 'default')) {
        if ($default === null) {
          $default = $value;
        } else {
          $original_key = $default['key'];
          throw new Exception(
            pht(
              'Two different colors ("%s", "%s") are marked as the default '.
              'color. Only one color may be marked as the default.',
              $key,
              $original_key));
        }
      }
    }

    if ($default === null) {
      throw new Exception(
        pht(
          'Project colors must include one color marked as the "%s" color, '.
          'but no such color exists.',
          'default'));
    }

  }

}
