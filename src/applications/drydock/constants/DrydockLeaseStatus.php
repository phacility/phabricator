<?php

final class DrydockLeaseStatus
  extends PhabricatorObjectStatus {

  const STATUS_PENDING = 'pending';
  const STATUS_ACQUIRED = 'acquired';
  const STATUS_ACTIVE = 'active';
  const STATUS_RELEASED = 'released';
  const STATUS_BROKEN = 'broken';
  const STATUS_DESTROYED = 'destroyed';

  public static function newStatusObject($key) {
    return new self($key, id(new self())->getStatusSpecification($key));
  }

  public static function getStatusMap() {
    $map = id(new self())->getStatusSpecifications();
    return ipull($map, 'name', 'key');
  }

  public static function getNameForStatus($status) {
    $map = id(new self())->getStatusSpecification($status);
    return $map['name'];
  }

  public static function getAllStatuses() {
    return array_keys(id(new self())->getStatusSpecifications());
  }

  public function isActivating() {
    return $this->getStatusProperty('isActivating');
  }

  public function isActive() {
    return ($this->getKey() === self::STATUS_ACTIVE);
  }

  public function canRelease() {
    return $this->getStatusProperty('isReleasable');
  }

  public function canReceiveCommands() {
    return $this->getStatusProperty('isCommandable');
  }

  protected function newStatusSpecifications() {
    return array(
      array(
        'key' => self::STATUS_PENDING,
        'name' => pht('Pending'),
        'icon' => 'fa-clock-o',
        'color' => 'blue',
        'isReleasable' => true,
        'isCommandable' => true,
        'isActivating' => true,
      ),
      array(
        'key' => self::STATUS_ACQUIRED,
        'name' => pht('Acquired'),
        'icon' => 'fa-refresh',
        'color' => 'blue',
        'isReleasable' => true,
        'isCommandable' => true,
        'isActivating' => true,
      ),
      array(
        'key' => self::STATUS_ACTIVE,
        'name' => pht('Active'),
        'icon' => 'fa-check',
        'color' => 'green',
        'isReleasable' => true,
        'isCommandable' => true,
        'isActivating' => false,
      ),
      array(
        'key' => self::STATUS_RELEASED,
        'name' => pht('Released'),
        'icon' => 'fa-circle-o',
        'color' => 'blue',
        'isReleasable' => false,
        'isCommandable' => false,
        'isActivating' => false,
      ),
      array(
        'key' => self::STATUS_BROKEN,
        'name' => pht('Broken'),
        'icon' => 'fa-times',
        'color' => 'indigo',
        'isReleasable' => true,
        'isCommandable' => true,
        'isActivating' => false,
      ),
      array(
        'key' => self::STATUS_DESTROYED,
        'name' => pht('Destroyed'),
        'icon' => 'fa-times',
        'color' => 'grey',
        'isReleasable' => false,
        'isCommandable' => false,
        'isActivating' => false,
      ),
    );
  }

  protected function newUnknownStatusSpecification($status) {
    return array(
      'isReleasable' => false,
      'isCommandable' => false,
      'isActivating' => false,
    );
  }

}
