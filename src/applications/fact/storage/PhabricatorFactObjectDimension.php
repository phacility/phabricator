<?php

final class PhabricatorFactObjectDimension
  extends PhabricatorFactDimension {

  protected $objectPHID;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(),
      self::CONFIG_KEY_SCHEMA => array(
        'key_object' => array(
          'columns' => array('objectPHID'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  protected function getDimensionColumnName() {
    return 'objectPHID';
  }

}
