<?php

final class PhabricatorAuthFactorProviderStatus
  extends Phobject {

  private $key;
  private $spec = array();

  const STATUS_ACTIVE = 'active';
  const STATUS_DEPRECATED = 'deprecated';
  const STATUS_DISABLED = 'disabled';

  public static function newForStatus($status) {
    $result = new self();

    $result->key = $status;
    $result->spec = self::newSpecification($status);

    return $result;
  }

  public function getName() {
    return idx($this->spec, 'name', $this->key);
  }

  public function getStatusHeaderIcon() {
    return idx($this->spec, 'header.icon');
  }

  public function getStatusHeaderColor() {
    return idx($this->spec, 'header.color');
  }

  public function isActive() {
    return ($this->key === self::STATUS_ACTIVE);
  }

  public function getListIcon() {
    return idx($this->spec, 'list.icon');
  }

  public function getListColor() {
    return idx($this->spec, 'list.color');
  }

  public function getFactorIcon() {
    return idx($this->spec, 'factor.icon');
  }

  public function getFactorColor() {
    return idx($this->spec, 'factor.color');
  }

  public function getOrder() {
    return idx($this->spec, 'order', 0);
  }

  public static function getMap() {
    $specs = self::newSpecifications();
    return ipull($specs, 'name');
  }

  private static function newSpecification($key) {
    $specs = self::newSpecifications();
    return idx($specs, $key, array());
  }

  private static function newSpecifications() {
    return array(
      self::STATUS_ACTIVE => array(
        'name' => pht('Active'),
        'header.icon' => 'fa-check',
        'header.color' => null,
        'list.icon' => null,
        'list.color' => null,
        'factor.icon' => 'fa-check',
        'factor.color' => 'green',
        'order' => 1,
      ),
      self::STATUS_DEPRECATED => array(
        'name' => pht('Deprecated'),
        'header.icon' => 'fa-ban',
        'header.color' => 'indigo',
        'list.icon' => 'fa-ban',
        'list.color' => 'indigo',
        'factor.icon' => 'fa-ban',
        'factor.color' => 'indigo',
        'order' => 2,
      ),
      self::STATUS_DISABLED => array(
        'name' => pht('Disabled'),
        'header.icon' => 'fa-times',
        'header.color' => 'red',
        'list.icon' => 'fa-times',
        'list.color' => 'red',
        'factor.icon' => 'fa-times',
        'factor.color' => 'grey',
        'order' => 3,
      ),
    );
  }

}
