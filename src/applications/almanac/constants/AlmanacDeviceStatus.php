<?php

final class AlmanacDeviceStatus
  extends Phobject {

  const ACTIVE = 'active';
  const DISABLED = 'disabled';

  private $value;

  public static function newStatusFromValue($value) {
    $status = new self();
    $status->value = $value;
    return $status;
  }

  public function getValue() {
    return $this->value;
  }

  public function getName() {
    $name = $this->getDeviceStatusProperty('name');

    if ($name === null) {
      $name = pht('Unknown Almanac Device Status ("%s")', $this->getValue());
    }

    return $name;
  }

  public function getIconIcon() {
    return $this->getDeviceStatusProperty('icon.icon');
  }

  public function getIconColor() {
    return $this->getDeviceStatusProperty('icon.color');
  }

  public function isDisabled() {
    return ($this->getValue() === self::DISABLED);
  }

  public function hasStatusTag() {
    return ($this->getStatusTagIcon() !== null);
  }

  public function getStatusTagIcon() {
    return $this->getDeviceStatusProperty('status-tag.icon');
  }

  public function getStatusTagColor() {
    return $this->getDeviceStatusProperty('status-tag.color');
  }

  public static function getStatusMap() {
    $result = array();

    foreach (self::newDeviceStatusMap() as $status_value => $ignored) {
      $result[$status_value] = self::newStatusFromValue($status_value);
    }

    return $result;
  }

  public static function getActiveStatusList() {
    $results = array();
    foreach (self::newDeviceStatusMap() as $status_value => $status) {
      if (empty($status['disabled'])) {
        $results[] = $status_value;
      }
    }
    return $results;
  }

  public static function getDisabledStatusList() {
    $results = array();
    foreach (self::newDeviceStatusMap() as $status_value => $status) {
      if (!empty($status['disabled'])) {
        $results[] = $status_value;
      }
    }
    return $results;
  }

  private function getDeviceStatusProperty($key, $default = null) {
    $map = self::newDeviceStatusMap();
    $properties = idx($map, $this->getValue(), array());
    return idx($properties, $key, $default);
  }

  private static function newDeviceStatusMap() {
    return array(
      self::ACTIVE => array(
        'name' => pht('Active'),
        'icon.icon' => 'fa-server',
        'icon.color' => 'green',
      ),
      self::DISABLED => array(
        'name' => pht('Disabled'),
        'icon.icon' => 'fa-times',
        'icon.color' => 'grey',
        'status-tag.icon' => 'fa-times',
        'status-tag.color' => 'indigo',
        'disabled' => true,
      ),
    );
  }


}
