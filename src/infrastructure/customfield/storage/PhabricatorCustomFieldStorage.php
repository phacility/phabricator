<?php

abstract class PhabricatorCustomFieldStorage
  extends PhabricatorLiskDAO {

  protected $objectPHID;
  protected $fieldIndex;
  protected $fieldValue;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'fieldIndex' => 'bytes12',
        'fieldValue' => 'text',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'objectPHID' => array(
          'columns' => array('objectPHID', 'fieldIndex'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

}
