<?php

abstract class PhabricatorFerretDocument
  extends PhabricatorSearchDAO {

  protected $objectPHID;
  protected $isClosed;
  protected $authorPHID;
  protected $ownerPHID;
  protected $epochCreated;
  protected $epochModified;

  abstract public function getIndexKey();

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'isClosed' => 'bool',
        'authorPHID' => 'phid?',
        'ownerPHID' => 'phid?',
        'epochCreated' => 'epoch',
        'epochModified' => 'epoch',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_object' => array(
          'columns' => array('objectPHID'),
          'unique' => true,
        ),
        'key_author' => array(
          'columns' => array('authorPHID'),
        ),
        'key_owner' => array(
          'columns' => array('ownerPHID'),
        ),
        'key_created' => array(
          'columns' => array('epochCreated'),
        ),
        'key_modified' => array(
          'columns' => array('epochModified'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getTableName() {
    $application = $this->getApplicationName();
    $key = $this->getIndexKey();
    return "{$application}_{$key}_fdocument";
  }

}
