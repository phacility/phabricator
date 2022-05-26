<?php

final class SlowvotePollStatus
  extends Phobject {

  const STATUS_OPEN = 'open';
  const STATUS_CLOSED = 'closed';

  private $key;

  public static function newStatusObject($key) {
    $object = new self();
    $object->key = $key;
    return $object;
  }

  public function getKey() {
    return $this->key;
  }

  public static function getAll() {
    $map = self::getMap();

    $result = array();
    foreach ($map as $key => $spec) {
      $result[$key] = self::newStatusObject($key);
    }

    return $result;
  }

  public function getName() {
    $name = $this->getProperty('name');

    if ($name === null) {
      $name = pht('Unknown ("%s")', $this->getKey());
    }

    return $name;
  }

  public function getHeaderTagIcon() {
    return $this->getProperty('header.tag.icon');
  }

  public function getHeaderTagColor() {
    return $this->getProperty('header.tag.color');
  }

  public function getTransactionIcon() {
    return $this->getProperty('transaction.icon');
  }

  private function getProperty($key, $default = null) {
    $spec = idx(self::getMap(), $this->getKey(), array());
    return idx($spec, $key, $default);
  }

  private static function getMap() {
    return array(
      self::STATUS_OPEN => array(
        'name' => pht('Open'),
        'header.tag.icon' => 'fa-square-o',
        'header.tag.color' => 'bluegrey',
        'transaction.icon' => 'fa-pencil',
      ),
      self::STATUS_CLOSED => array(
        'name' => pht('Closed'),
        'header.tag.icon' => 'fa-ban',
        'header.tag.color' => 'indigo',
        'transaction.icon' => 'fa-ban',
      ),
    );
  }

}
