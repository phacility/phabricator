<?php

abstract class PhabricatorCustomFieldStringIndexStorage
  extends PhabricatorCustomFieldIndexStorage {

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'indexKey' => 'bytes12',
        'indexValue' => 'sort',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_join' => array(
          'columns' => array('objectPHID', 'indexKey', 'indexValue(64)'),
        ),
        'key_find' => array(
          'columns' => array('indexKey', 'indexValue(64)'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function formatForInsert(AphrontDatabaseConnection $conn) {
    return qsprintf(
      $conn,
      '(%s, %s, %s)',
      $this->getObjectPHID(),
      $this->getIndexKey(),
      $this->getIndexValue());
  }

  public function getIndexValueType() {
    return 'string';
  }

}
