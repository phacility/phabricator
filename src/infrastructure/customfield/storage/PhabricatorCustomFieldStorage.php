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


  /**
   * Get a key which uniquely identifies this storage source.
   *
   * When loading custom fields, fields using sources with the same source key
   * are loaded in bulk.
   *
   * @return string Source identifier.
   */
  final public function getStorageSourceKey() {
    return $this->getApplicationName().'/'.$this->getTableName();
  }


  /**
   * Load stored data for custom fields.
   *
   * Given a map of fields, return a map with any stored data for those fields.
   * The keys in the result should correspond to the keys in the input. The
   * fields in the list may belong to different objects.
   *
   * @param map<string, PhabricatorCustomField> Map of fields.
   * @return map<String, PhabricatorCustomField> Map of available field data.
   */
  final public function loadStorageSourceData(array $fields) {
    $map = array();
    $indexes = array();
    $object_phids = array();

    foreach ($fields as $key => $field) {
      $index = $field->getFieldIndex();
      $object_phid = $field->getObject()->getPHID();

      $map[$index][$object_phid] = $key;
      $indexes[$index] = $index;
      $object_phids[$object_phid] = $object_phid;
    }

    if (!$indexes) {
      return array();
    }

    $conn = $this->establishConnection('r');
    $rows = queryfx_all(
      $conn,
      'SELECT objectPHID, fieldIndex, fieldValue FROM %T
        WHERE objectPHID IN (%Ls) AND fieldIndex IN (%Ls)',
      $this->getTableName(),
      $object_phids,
      $indexes);

    $result = array();
    foreach ($rows as $row) {
      $index = $row['fieldIndex'];
      $object_phid = $row['objectPHID'];
      $value = $row['fieldValue'];

      $key = $map[$index][$object_phid];
      $result[$key] = $value;
    }

    return $result;
  }

}
