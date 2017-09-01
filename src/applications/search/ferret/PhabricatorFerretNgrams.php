<?php

abstract class PhabricatorFerretNgrams
  extends PhabricatorSearchDAO {

  protected $documentID;
  protected $ngram;

  abstract public function getIndexKey();

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'documentID' => 'uint32',
        'ngram' => 'char3',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_ngram' => array(
          'columns' => array('ngram', 'documentID'),
        ),
        'key_object' => array(
          'columns' => array('documentID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getTableName() {
    $application = $this->getApplicationName();
    $key = $this->getIndexKey();
    return "{$application}_{$key}_fngrams";
  }

}
