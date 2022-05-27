<?php

final class SlowvotePollResponseVisibility
  extends Phobject {

  const RESPONSES_VISIBLE = 'visible';
  const RESPONSES_VOTERS = 'voters';
  const RESPONSES_OWNER = 'owner';

  private $key;

  public static function newResponseVisibilityObject($key) {
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
      $result[$key] = self::newResponseVisibilityObject($key);
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

  public function getNameForEdit() {
    $name = $this->getProperty('name.edit');

    if ($name === null) {
      $name = pht('Unknown ("%s")', $this->getKey());
    }

    return $name;
  }

  private function getProperty($key, $default = null) {
    $spec = idx(self::getMap(), $this->getKey(), array());
    return idx($spec, $key, $default);
  }

  private static function getMap() {
    return array(
      self::RESPONSES_VISIBLE => array(
        'name' => pht('Always Visible'),
        'name.edit' => pht('Anyone can see the responses'),
      ),
      self::RESPONSES_VOTERS => array(
        'name' => pht('Voters'),
        'name.edit' => pht('Require a vote to see the responses'),
      ),
      self::RESPONSES_OWNER => array(
        'name' => pht('Owner'),
        'name.edit' => pht('Only the poll owner can see the responses'),
      ),
    );
  }

}
