<?php

final class AlmanacDeviceProperty extends AlmanacDAO {

  protected $devicePHID;
  protected $key;
  protected $value;

  public function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'value'    => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'key' => 'text128',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_device' => array(
          'columns' => array('devicePHID', 'key'),
        ),
      ),
    ) + parent::getConfiguration();
  }

}
