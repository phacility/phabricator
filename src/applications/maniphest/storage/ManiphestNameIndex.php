<?php

/**
 * Denormalizes object names to support queries which need to be ordered or
 * grouped by things like projects.
 */
final class ManiphestNameIndex extends ManiphestDAO {

  protected $indexedObjectPHID;
  protected $indexedObjectName;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'indexedObjectName' => 'sort128',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_phid' => array(
          'columns' => array('indexedObjectPHID'),
          'unique' => true,
        ),
        'key_name' => array(
          'columns' => array('indexedObjectName'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public static function updateIndex($phid, $name) {
    $table = new ManiphestNameIndex();
    $conn_w = $table->establishConnection('w');
    queryfx(
      $conn_w,
      'INSERT INTO %T (indexedObjectPHID, indexedObjectName) VALUES (%s, %s)
        ON DUPLICATE KEY UPDATE indexedObjectName = VALUES(indexedObjectName)',
      $table->getTableName(),
      $phid,
      $name);
  }

}
