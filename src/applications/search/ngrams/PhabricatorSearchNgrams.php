<?php

abstract class PhabricatorSearchNgrams
  extends PhabricatorSearchDAO {

  protected $objectID;
  protected $ngram;

  private $value;

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

  final public function tokenizeString($value) {
    $value = trim($value, ' ');
    $value = preg_split('/ +/', $value);
    return $value;
  }

  final public function getNgramsFromString($value, $mode) {
    $tokens = $this->tokenizeString($value);

    $ngrams = array();
    foreach ($tokens as $token) {
      $token = phutil_utf8_strtolower($token);

      switch ($mode) {
        case 'query':
          break;
        case 'index':
          $token = ' '.$token.' ';
          break;
        case 'prefix':
          $token = ' '.$token;
          break;
      }

      $len = (strlen($token) - 2);
      for ($ii = 0; $ii < $len; $ii++) {
        $ngram = substr($token, $ii, 3);
        $ngrams[$ngram] = $ngram;
      }
    }

    ksort($ngrams);

    return array_keys($ngrams);
  }

  final public function writeNgram($object_id) {
    $ngrams = $this->getNgramsFromString($this->getValue(), 'index');
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
        'INSERT INTO %T (objectID, ngram) VALUES %Q',
        $this->getTableName(),
        implode(', ', $sql));
    }

    return $this;
  }

}
