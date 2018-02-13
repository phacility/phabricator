<?php

abstract class PhabricatorObjectStatus
  extends Phobject {

  private $key;
  private $properties;

  protected function __construct($key = null, array $properties = array()) {
    $this->key = $key;
    $this->properties = $properties;
  }

  protected function getStatusProperty($key) {
    if (!array_key_exists($key, $this->properties)) {
      throw new Exception(
        pht(
          'Attempting to access unknown status property ("%s").',
          $key));
    }

    return $this->properties[$key];
  }

  public function getKey() {
    return $this->key;
  }

  public function getIcon() {
    return $this->getStatusProperty('icon');
  }

  public function getDisplayName() {
    return $this->getStatusProperty('name');
  }

  public function getColor() {
    return $this->getStatusProperty('color');
  }

  protected function getStatusSpecification($status) {
    $map = self::getStatusSpecifications();
    if (isset($map[$status])) {
      return $map[$status];
    }

    return array(
      'key' => $status,
      'name' => pht('Unknown ("%s")', $status),
      'icon' => 'fa-question-circle',
      'color' => 'indigo',
    ) + $this->newUnknownStatusSpecification($status);
  }

  protected function getStatusSpecifications() {
    $map = $this->newStatusSpecifications();

    $result = array();
    foreach ($map as $item) {
      if (!array_key_exists('key', $item)) {
        throw new Exception(pht('Status specification has no "key".'));
      }

      $key = $item['key'];
      if (isset($result[$key])) {
        throw new Exception(
          pht(
            'Multiple status definitions share the same key ("%s").',
            $key));
      }

      $result[$key] = $item;
    }

    return $result;
  }

  abstract protected function newStatusSpecifications();

  protected function newUnknownStatusSpecification($status) {
    return array();
  }

}
