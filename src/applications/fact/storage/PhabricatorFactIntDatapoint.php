<?php

final class PhabricatorFactIntDatapoint extends PhabricatorFactDAO {

  protected $keyID;
  protected $objectID;
  protected $dimensionID;
  protected $value;
  protected $epoch;

  private $key;
  private $objectPHID;
  private $dimensionPHID;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'id' => 'auto64',
        'dimensionID' => 'id?',
        'value' => 'sint64',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_dimension' => array(
          'columns' => array('keyID', 'dimensionID'),
        ),
        'key_object' => array(
          'columns' => array('objectID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function setKey($key) {
    $this->key = $key;
    return $this;
  }

  public function getKey() {
    return $this->key;
  }

  public function setObjectPHID($object_phid) {
    $this->objectPHID = $object_phid;
    return $this;
  }

  public function getObjectPHID() {
    return $this->objectPHID;
  }

  public function setDimensionPHID($dimension_phid) {
    $this->dimensionPHID = $dimension_phid;
    return $this;
  }

  public function getDimensionPHID() {
    return $this->dimensionPHID;
  }

}
