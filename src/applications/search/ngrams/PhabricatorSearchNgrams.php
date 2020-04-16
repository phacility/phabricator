<?php

abstract class PhabricatorSearchNgrams
  extends PhabricatorSearchDAO {

  protected $objectID;
  protected $ngram;

  private $value;
  private $ngramEngine;

  abstract public function getNgramKey();
  abstract public function getColumnName();

  final public function setValue($value) {
    $this->value = $value;
    return $this;
  }

  final public function getValue() {
    return $this->value;
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'objectID' => 'uint32',
        'ngram' => 'char3',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_ngram' => array(
          'columns' => array('ngram', 'objectID'),
        ),
        'key_object' => array(
          'columns' => array('objectID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getTableName() {
    $application = $this->getApplicationName();
    $key = $this->getNgramKey();
    return "{$application}_{$key}_ngrams";
  }

  final public function writeNgram($object_id) {
    $ngram_engine = $this->getNgramEngine();
    $ngrams = $ngram_engine->getTermNgramsFromString($this->getValue());

    $conn_w = $this->establishConnection('w');

    $sql = array();
    foreach ($ngrams as $ngram) {
      $sql[] = qsprintf(
        $conn_w,
        '(%d, %s)',
        $object_id,
        $ngram);
    }

    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE objectID = %d',
      $this->getTableName(),
      $object_id);

    if ($sql) {
      queryfx(
        $conn_w,
        'INSERT INTO %T (objectID, ngram) VALUES %LQ',
        $this->getTableName(),
        $sql);
    }

    return $this;
  }

  private function getNgramEngine() {
    if (!$this->ngramEngine) {
      $this->ngramEngine = new PhabricatorSearchNgramEngine();
    }

    return $this->ngramEngine;
  }

}
