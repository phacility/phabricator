<?php

abstract class PhabricatorCustomFieldNumericIndexStorage
  extends PhabricatorCustomFieldIndexStorage {

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'indexKey' => 'bytes12',
        'indexValue' => 'sint64',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_join' => array(
          'columns' => array('objectPHID', 'indexKey', 'indexValue'),
        ),
        'key_find' => array(
          'columns' => array('indexKey', 'indexValue'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function formatForInsert(AphrontDatabaseConnection $conn) {
    return qsprintf(
      $conn,
      '(%s, %s, %d)',
      $this->getObjectPHID(),
      $this->getIndexKey(),
      $this->getIndexValue());
  }

  public function getIndexValueType() {
    return 'int';
  }

}
