<?php

final class PhabricatorSearchDocument extends PhabricatorSearchDAO {

  protected $documentType;
  protected $documentTitle;
  protected $documentCreated;
  protected $documentModified;

  const STOPWORDS_TABLE = 'stopwords';

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_IDS        => self::IDS_MANUAL,
      self::CONFIG_COLUMN_SCHEMA => array(
        'documentType' => 'text4',
        'documentTitle' => 'text255',
        'documentCreated' => 'epoch',
        'documentModified' => 'epoch',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_phid' => null,
        'PRIMARY' => array(
          'columns' => array('phid'),
          'unique' => true,
        ),
        'documentCreated' => array(
          'columns' => array('documentCreated'),
        ),
        'key_type' => array(
          'columns' => array('documentType', 'documentCreated'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getIDKey() {
    return 'phid';
  }

  public static function newQueryCompiler() {
    $compiler = new PhutilSearchQueryCompiler();

    if (self::isInnoDBFulltextEngineAvailable()) {
      // The InnoDB fulltext boolean operators are always the same as the
      // default MyISAM operators, so we do not need to adjust the compiler.
    } else {
      $table = new self();
      $conn = $table->establishConnection('r');

      $operators = queryfx_one(
        $conn,
        'SELECT @@ft_boolean_syntax AS syntax');
      if ($operators) {
        $compiler->setOperators($operators['syntax']);
      }
    }

    return $compiler;
  }

  public static function isInnoDBFulltextEngineAvailable() {
    static $available;

    if ($available === null) {
      $table = new self();
      $conn = $table->establishConnection('r');

      // If this system variable exists, we can use InnoDB fulltext. If it
      // does not, this query will throw and we're stuck with MyISAM.
      try {
        queryfx_one(
          $conn,
          'SELECT @@innodb_ft_max_token_size');
        $available = true;
      } catch (AphrontQueryException $x) {
        $available = false;
      }
    }

    return $available;
  }

}
