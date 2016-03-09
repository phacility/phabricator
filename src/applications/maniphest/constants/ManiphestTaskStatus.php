<?php

/**
 * @task validate Configuration Validation
 */
final class ManiphestTaskStatus extends ManiphestConstants {

  const STATUS_OPEN               = 'open';
  const STATUS_CLOSED_RESOLVED    = 'resolved';
  const STATUS_CLOSED_WONTFIX     = 'wontfix';
  const STATUS_CLOSED_INVALID     = 'invalid';
  const STATUS_CLOSED_DUPLICATE   = 'duplicate';
  const STATUS_CLOSED_SPITE       = 'spite';

  const SPECIAL_DEFAULT     = 'default';
  const SPECIAL_CLOSED      = 'closed';
  const SPECIAL_DUPLICATE   = 'duplicate';

  private static function getStatusConfig() {
    return PhabricatorEnv::getEnvConfig('maniphest.statuses');
  }

  private static function getEnabledStatusMap() {
    $spec = self::getStatusConfig();

    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');
    foreach ($spec as $const => $status) {
      if ($is_serious && !empty($status['silly'])) {
        unset($spec[$const]);
        continue;
      }
    }

    return $spec;
  }

  public static function getTaskStatusMap() {
    return ipull(self::getEnabledStatusMap(), 'name');
  }


  /**
   * Get the statuses and their command keywords.
   *
   * @return map Statuses to lists of command keywords.
   */
  public static function getTaskStatusKeywordsMap() {
    $map = self::getEnabledStatusMap();
    foreach ($map as $key => $spec) {
      $words = idx($spec, 'keywords', array());
      if (!is_array($words)) {
        $words = array($words);
      }

      // For statuses, we include the status name because it's usually
      // at least somewhat meaningful.
      $words[] = $key;

      foreach ($words as $word_key => $word) {
        $words[$word_key] = phutil_utf8_strtolower($word);
      }

      $words = array_unique($words);

      $map[$key] = $words;
    }

    return $map;
  }


  public static function getTaskStatusName($status) {
    return self::getStatusAttribute($status, 'name', pht('Unknown Status'));
  }

  public static function getTaskStatusFullName($status) {
    $name = self::getStatusAttribute($status, 'name.full');
    if ($name !== null) {
      return $name;
    }

    return self::getStatusAttribute($status, 'name', pht('Unknown Status'));
  }

  public static function renderFullDescription($status, $priority) {
    if (self::isOpenStatus($status)) {
      $name = pht('%s, %s', self::getTaskStatusFullName($status), $priority);
      $color = 'grey';
      $icon = 'fa-square-o';
    } else {
      $name = self::getTaskStatusFullName($status);
      $color = 'indigo';
      $icon = 'fa-check-square-o';
    }

    $tag = id(new PHUITagView())
      ->setName($name)
      ->setIcon($icon)
      ->setType(PHUITagView::TYPE_SHADE)
      ->setShade($color);

    return $tag;
  }

  private static function getSpecialStatus($special) {
    foreach (self::getStatusConfig() as $const => $status) {
      if (idx($status, 'special') == $special) {
        return $const;
      }
    }
    return null;
  }

  public static function getDefaultStatus() {
    return self::getSpecialStatus(self::SPECIAL_DEFAULT);
  }

  public static function getDefaultClosedStatus() {
    return self::getSpecialStatus(self::SPECIAL_CLOSED);
  }

  public static function getDuplicateStatus() {
    return self::getSpecialStatus(self::SPECIAL_DUPLICATE);
  }

  public static function getOpenStatusConstants() {
    $result = array();
    foreach (self::getEnabledStatusMap() as $const => $status) {
      if (empty($status['closed'])) {
        $result[] = $const;
      }
    }
    return $result;
  }

  public static function getClosedStatusConstants() {
    $all = array_keys(self::getTaskStatusMap());
    $open = self::getOpenStatusConstants();
    return array_diff($all, $open);
  }

  public static function isOpenStatus($status) {
    foreach (self::getOpenStatusConstants() as $constant) {
      if ($status == $constant) {
        return true;
      }
    }
    return false;
  }

  public static function isClaimStatus($status) {
    return self::getStatusAttribute($status, 'claim', true);
  }

  public static function isClosedStatus($status) {
    return !self::isOpenStatus($status);
  }

  public static function getStatusActionName($status) {
    return self::getStatusAttribute($status, 'name.action');
  }

  public static function getStatusColor($status) {
    return self::getStatusAttribute($status, 'transaction.color');
  }

  public static function isDisabledStatus($status) {
    return self::getStatusAttribute($status, 'disabled');
  }

  public static function getStatusIcon($status) {
    $icon = self::getStatusAttribute($status, 'transaction.icon');
    if ($icon) {
      return $icon;
    }

    if (self::isOpenStatus($status)) {
      return 'fa-exclamation-circle';
    } else {
      return 'fa-check-square-o';
    }
  }

  public static function getStatusPrefixMap() {
    $map = array();
    foreach (self::getEnabledStatusMap() as $const => $status) {
      foreach (idx($status, 'prefixes', array()) as $prefix) {
        $map[$prefix] = $const;
      }
    }

    $map += array(
      'ref'           => null,
      'refs'          => null,
      'references'    => null,
      'cf.'           => null,
    );

    return $map;
  }

  public static function getStatusSuffixMap() {
    $map = array();
    foreach (self::getEnabledStatusMap() as $const => $status) {
      foreach (idx($status, 'suffixes', array()) as $prefix) {
        $map[$prefix] = $const;
      }
    }
    return $map;
  }

  private static function getStatusAttribute($status, $key, $default = null) {
    $config = self::getStatusConfig();

    $spec = idx($config, $status);
    if ($spec) {
      return idx($spec, $key, $default);
    }

    return $default;
  }


/* -(  Configuration Validation  )------------------------------------------- */


  /**
   * @task validate
   */
  public static function isValidStatusConstant($constant) {
    if (strlen($constant) > 12) {
      return false;
    }
    if (!preg_match('/^[a-z0-9]+\z/', $constant)) {
      return false;
    }
    return true;
  }


  /**
   * @task validate
   */
  public static function validateConfiguration(array $config) {
    foreach ($config as $key => $value) {
      if (!self::isValidStatusConstant($key)) {
        throw new Exception(
          pht(
            'Key "%s" is not a valid status constant. Status constants must '.
            'be 1-12 characters long and contain only lowercase letters (a-z) '.
            'and digits (0-9). For example, "%s" or "%s" are reasonable '.
            'choices.',
            $key,
            'open',
            'closed'));
      }
      if (!is_array($value)) {
        throw new Exception(
          pht(
            'Value for key "%s" should be a dictionary.',
            $key));
      }

      PhutilTypeSpec::checkMap(
        $value,
        array(
          'name' => 'string',
          'name.full' => 'optional string',
          'name.action' => 'optional string',
          'closed' => 'optional bool',
          'special' => 'optional string',
          'transaction.icon' => 'optional string',
          'transaction.color' => 'optional string',
          'silly' => 'optional bool',
          'prefixes' => 'optional list<string>',
          'suffixes' => 'optional list<string>',
          'keywords' => 'optional list<string>',
          'disabled' => 'optional bool',
          'claim' => 'optional bool',
        ));
    }

    $special_map = array();
    foreach ($config as $key => $value) {
      $special = idx($value, 'special');
      if (!$special) {
        continue;
      }

      if (isset($special_map[$special])) {
        throw new Exception(
          pht(
            'Configuration has two statuses both marked with the special '.
            'attribute "%s" ("%s" and "%s"). There should be only one.',
            $special,
            $special_map[$special],
            $key));
      }

      switch ($special) {
        case self::SPECIAL_DEFAULT:
          if (!empty($value['closed'])) {
            throw new Exception(
              pht(
                'Status "%s" is marked as default, but it is a closed '.
                'status. The default status should be an open status.',
                $key));
          }
          break;
        case self::SPECIAL_CLOSED:
          if (empty($value['closed'])) {
            throw new Exception(
              pht(
                'Status "%s" is marked as the default status for closing '.
                'tasks, but is not a closed status. It should be a closed '.
                'status.',
                $key));
          }
          break;
        case self::SPECIAL_DUPLICATE:
          if (empty($value['closed'])) {
            throw new Exception(
              pht(
                'Status "%s" is marked as the status for closing tasks as '.
                'duplicates, but it is not a closed status. It should '.
                'be a closed status.',
                $key));
          }
          break;
      }

      $special_map[$special] = $key;
    }

    // NOTE: We're not explicitly validating that we have at least one open
    // and one closed status, because the DEFAULT and CLOSED specials imply
    // that to be true. If those change in the future, that might become a
    // reasonable thing to validate.

    $required = array(
      self::SPECIAL_DEFAULT,
      self::SPECIAL_CLOSED,
      self::SPECIAL_DUPLICATE,
    );

    foreach ($required as $required_special) {
      if (!isset($special_map[$required_special])) {
        throw new Exception(
          pht(
            'Configuration defines no task status with special attribute '.
            '"%s", but you must specify a status which fills this special '.
            'role.',
            $required_special));
      }
    }
  }

}
