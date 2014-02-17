<?php

/**
 * @group maniphest
 */
final class ManiphestTaskStatus extends ManiphestConstants {

  const STATUS_OPEN               = 0;
  const STATUS_CLOSED_RESOLVED    = 1;
  const STATUS_CLOSED_WONTFIX     = 2;
  const STATUS_CLOSED_INVALID     = 3;
  const STATUS_CLOSED_DUPLICATE   = 4;
  const STATUS_CLOSED_SPITE       = 5;

  const COLOR_STATUS_OPEN = 'status';
  const COLOR_STATUS_CLOSED = 'status-dark';

  public static function getTaskStatusMap() {
    $open = pht('Open');
    $resolved = pht('Resolved');
    $wontfix = pht('Wontfix');
    $invalid = pht('Invalid');
    $duplicate = pht('Duplicate');
    $spite = pht('Spite');

    return array(
      self::STATUS_OPEN                 => $open,
      self::STATUS_CLOSED_RESOLVED      => $resolved,
      self::STATUS_CLOSED_WONTFIX       => $wontfix,
      self::STATUS_CLOSED_INVALID       => $invalid,
      self::STATUS_CLOSED_DUPLICATE     => $duplicate,
      self::STATUS_CLOSED_SPITE         => $spite,
    );
  }

  public static function getTaskStatusFullName($status) {
    $open = pht('Open');
    $resolved = pht('Closed, Resolved');
    $wontfix = pht('Closed, Wontfix');
    $invalid = pht('Closed, Invalid');
    $duplicate = pht('Closed, Duplicate');
    $spite = pht('Closed, Spite');

    $map = array(
      self::STATUS_OPEN                 => $open,
      self::STATUS_CLOSED_RESOLVED      => $resolved,
      self::STATUS_CLOSED_WONTFIX       => $wontfix,
      self::STATUS_CLOSED_INVALID       => $invalid,
      self::STATUS_CLOSED_DUPLICATE     => $duplicate,
      self::STATUS_CLOSED_SPITE         => $spite,
    );
    return idx($map, $status, '???');
  }

  public static function getTaskStatusColor($status) {
    $default = self::COLOR_STATUS_OPEN;

    $map = array(
      self::STATUS_OPEN             => self::COLOR_STATUS_OPEN,
      self::STATUS_CLOSED_RESOLVED  => self::COLOR_STATUS_CLOSED,
      self::STATUS_CLOSED_WONTFIX   => self::COLOR_STATUS_CLOSED,
      self::STATUS_CLOSED_INVALID   => self::COLOR_STATUS_CLOSED,
      self::STATUS_CLOSED_DUPLICATE => self::COLOR_STATUS_CLOSED,
      self::STATUS_CLOSED_SPITE     => self::COLOR_STATUS_CLOSED,
    );
    return idx($map, $status, $default);
  }

  public static function getIcon($status) {
    $default = 'oh-open';
    $map = array(
      self::STATUS_OPEN             => 'oh-open',
      self::STATUS_CLOSED_RESOLVED  => 'oh-closed-dark',
      self::STATUS_CLOSED_WONTFIX   => 'oh-closed-dark',
      self::STATUS_CLOSED_INVALID   => 'oh-closed-dark',
      self::STATUS_CLOSED_DUPLICATE => 'oh-closed-dark',
      self::STATUS_CLOSED_SPITE     => 'oh-closed-dark',
    );
    return idx($map, $status, $default);
  }

  public static function renderFullDescription($status) {
    $color = self::getTaskStatusColor($status);

    $img = id(new PHUIIconView())
      ->setSpriteSheet(PHUIIconView::SPRITE_STATUS)
      ->setSpriteIcon(self::getIcon($status));

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

  public static function getDefaultStatus() {
    return self::STATUS_OPEN;
  }

  public static function getOpenStatusConstants() {
    return array(
      self::STATUS_OPEN,
    );
  }

  public static function isOpenStatus($status) {
    foreach (self::getOpenStatusConstants() as $constant) {
      if ($status == $constant) {
        return true;
      }
    }
    return false;
  }

  public static function getStatusPrefixMap() {
    return array(
      'resolve'       => self::STATUS_CLOSED_RESOLVED,
      'resolves'      => self::STATUS_CLOSED_RESOLVED,
      'resolved'      => self::STATUS_CLOSED_RESOLVED,
      'fix'           => self::STATUS_CLOSED_RESOLVED,
      'fixes'         => self::STATUS_CLOSED_RESOLVED,
      'fixed'         => self::STATUS_CLOSED_RESOLVED,
      'wontfix'       => self::STATUS_CLOSED_WONTFIX,
      'wontfixes'     => self::STATUS_CLOSED_WONTFIX,
      'wontfixed'     => self::STATUS_CLOSED_WONTFIX,
      'spite'         => self::STATUS_CLOSED_SPITE,
      'spites'        => self::STATUS_CLOSED_SPITE,
      'spited'        => self::STATUS_CLOSED_SPITE,
      'invalidate'    => self::STATUS_CLOSED_INVALID,
      'invaldiates'   => self::STATUS_CLOSED_INVALID,
      'invalidated'   => self::STATUS_CLOSED_INVALID,
      'close'         => self::STATUS_CLOSED_RESOLVED,
      'closes'        => self::STATUS_CLOSED_RESOLVED,
      'closed'        => self::STATUS_CLOSED_RESOLVED,
      'ref'           => null,
      'refs'          => null,
      'references'    => null,
      'cf.'           => null,
    );
  }

  public static function getStatusSuffixMap() {
    return array(
      'as resolved'   => self::STATUS_CLOSED_RESOLVED,
      'as fixed'      => self::STATUS_CLOSED_RESOLVED,
      'as wontfix'    => self::STATUS_CLOSED_WONTFIX,
      'as spite'      => self::STATUS_CLOSED_SPITE,
      'out of spite'  => self::STATUS_CLOSED_SPITE,
      'as invalid'    => self::STATUS_CLOSED_INVALID,
    );
  }


}
