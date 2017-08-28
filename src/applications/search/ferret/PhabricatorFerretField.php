<?php

abstract class PhabricatorFerretField
  extends PhabricatorSearchDAO {

  protected $documentID;
  protected $fieldKey;
  protected $rawCorpus;
  protected $normalCorpus;

  abstract public function getIndexKey();

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'documentID' => 'uint32',
        'fieldKey' => 'text4',
        'rawCorpus' => 'sort',
        'normalCorpus' => 'sort',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_document' => array(
          'columns' => array('documentID', 'fieldKey'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getTableName() {
    $application = $this->getApplicationName();
    $key = $this->getIndexKey();
    return "{$application}_{$key}_ffield";
  }

}
