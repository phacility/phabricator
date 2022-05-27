<?php

final class SlowvotePollVotingMethod
  extends Phobject {

  const METHOD_PLURALITY = 'plurality';
  const METHOD_APPROVAL = 'approval';

  private $key;

  public static function newVotingMethodObject($key) {
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
      $result[$key] = self::newVotingMethodObject($key);
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
      self::METHOD_PLURALITY => array(
        'name' => pht('Plurality'),
        'name.edit' => pht('Plurality (Single Choice)'),
      ),
      self::METHOD_APPROVAL => array(
        'name' => pht('Approval'),
        'name.edit' => pht('Approval (Multiple Choice)'),
      ),
    );
  }

}
