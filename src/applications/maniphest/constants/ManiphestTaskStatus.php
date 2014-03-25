<?php

final class ManiphestTaskStatus extends ManiphestConstants {

  const STATUS_OPEN               = 0;
  const STATUS_CLOSED_RESOLVED    = 1;
  const STATUS_CLOSED_WONTFIX     = 2;
  const STATUS_CLOSED_INVALID     = 3;
  const STATUS_CLOSED_DUPLICATE   = 4;
  const STATUS_CLOSED_SPITE       = 5;

  const SPECIAL_DEFAULT     = 'default';
  const SPECIAL_CLOSED      = 'closed';
  const SPECIAL_DUPLICATE   = 'duplicate';

  private static function getStatusConfig() {
    return array(
      self::STATUS_OPEN => array(
        'name' => pht('Open'),
        'special' => self::SPECIAL_DEFAULT,
      ),
      self::STATUS_CLOSED_RESOLVED => array(
        'name' => pht('Resolved'),
        'name.full' => pht('Closed, Resolved'),
        'closed' => true,
        'special' => self::SPECIAL_CLOSED,
        'prefixes' => array(
          'closed',
          'closes',
          'close',
          'fix',
          'fixes',
          'fixed',
          'resolve',
          'resolves',
          'resolved',
        ),
        'suffixes' => array(
          'as resolved',
          'as fixed',
        ),
      ),
      self::STATUS_CLOSED_WONTFIX => array(
        'name' => pht('Wontfix'),
        'name.full' => pht('Closed, Wontfix'),
        'closed' => true,
        'prefixes' => array(
          'wontfix',
          'wontfixes',
          'wontfixed',
        ),
        'suffixes' => array(
          'as wontfix',
        ),
      ),
      self::STATUS_CLOSED_INVALID => array(
        'name' => pht('Invalid'),
        'name.full' => pht('Closed, Invalid'),
        'closed' => true,
        'prefixes' => array(
          'invalidate',
          'invalidates',
          'invalidated',
        ),
        'suffixes' => array(
          'as invalid',
        ),
      ),
      self::STATUS_CLOSED_DUPLICATE => array(
        'name' => pht('Duplicate'),
        'name.full' => pht('Closed, Duplicate'),
        'transaction.icon' => 'delete',
        'special' => self::SPECIAL_DUPLICATE,
        'closed' => true,
      ),
      self::STATUS_CLOSED_SPITE => array(
        'name' => pht('Spite'),
        'name.full' => pht('Closed, Spite'),
        'name.action' => pht('Spited'),
        'transaction.icon' => 'dislike',
        'silly' => true,
        'closed' => true,
        'prefixes' => array(
          'spite',
          'spites',
          'spited',
        ),
        'suffixes' => array(
          'out of spite',
          'as spite',
        ),
      ),
    );
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

  public static function renderFullDescription($status) {
    if (self::isOpenStatus($status)) {
      $color = 'status';
      $icon = 'oh-open';
    } else {
      $color = 'status-dark';
      $icon = 'oh-closed-dark';
    }

    $img = id(new PHUIIconView())
      ->setSpriteSheet(PHUIIconView::SPRITE_STATUS)
      ->setSpriteIcon($icon);

    $tag = phutil_tag(
      'span',
      array(
        'class' => 'phui-header-'.$color.' plr',
      ),
      array(
        $img,
        self::getTaskStatusFullName($status),
      ));

    return $tag;
  }

  private static function getSpecialStatus($special) {
    foreach (self::getEnabledStatusMap() as $const => $status) {
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

  public static function isClosedStatus($status) {
    return !self::isOpenStatus($status);
  }

  public static function getStatusActionName($status) {
    return self::getStatusAttribute($status, 'name.action');
  }

  public static function getStatusColor($status) {
    return self::getStatusAttribute($status, 'transaction.color');
  }

  public static function getStatusIcon($status) {
    return self::getStatusAttribute($status, 'transaction.icon');
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

}
