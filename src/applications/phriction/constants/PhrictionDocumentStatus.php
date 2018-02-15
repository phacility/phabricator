<?php

final class PhrictionDocumentStatus
  extends PhabricatorObjectStatus {

  const STATUS_EXISTS = 'active';
  const STATUS_DELETED = 'deleted';
  const STATUS_MOVED = 'moved';
  const STATUS_STUB = 'stub';

  public static function getConduitConstant($const) {
    static $map = array(
      self::STATUS_EXISTS    => 'exists',
      self::STATUS_DELETED   => 'deleted',
      self::STATUS_MOVED     => 'moved',
      self::STATUS_STUB      => 'stubbed',
    );

    return idx($map, $const, 'unknown');
  }

  public static function newStatusObject($key) {
    return new self($key, id(new self())->getStatusSpecification($key));
  }

  public static function getStatusMap() {
    $map = id(new self())->getStatusSpecifications();
    return ipull($map, 'name', 'key');
  }

  public function isActive() {
    return ($this->getKey() == self::STATUS_EXISTS);
  }

  protected function newStatusSpecifications() {
    return array(
      array(
        'key' => self::STATUS_EXISTS,
        'name' => pht('Active'),
        'icon' => 'fa-file-text-o',
        'color' => 'bluegrey',
      ),
      array(
        'key' => self::STATUS_DELETED,
        'name' => pht('Deleted'),
        'icon' => 'fa-file-text-o',
        'color' => 'grey',
      ),
      array(
        'key' => self::STATUS_MOVED,
        'name' => pht('Moved'),
        'icon' => 'fa-arrow-right',
        'color' => 'grey',
      ),
      array(
        'key' => self::STATUS_STUB,
        'name' => pht('Stub'),
        'icon' => 'fa-file-text-o',
        'color' => 'grey',
      ),
    );
  }
}
