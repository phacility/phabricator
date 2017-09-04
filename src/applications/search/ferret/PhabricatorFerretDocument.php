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
      ),
    ) + parent::getConfiguration();
  }

  public function getTableName() {
    $application = $this->getApplicationName();
    $key = $this->getIndexKey();
    return "{$application}_{$key}_fdocument";
  }

}
