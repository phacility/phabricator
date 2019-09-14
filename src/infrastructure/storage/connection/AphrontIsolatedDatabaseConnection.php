<?php

final class AphrontIsolatedDatabaseConnection
  extends AphrontDatabaseConnection {

  private $configuration;
  private static $nextInsertID;
  private $insertID;

  private $transcript = array();

  private $allResults;
  private $affectedRows;

  public function __construct(array $configuration) {
    $this->configuration = $configuration;

    if (self::$nextInsertID === null) {
      // Generate test IDs into a distant ID space to reduce the risk of
      // collisions and make them distinctive.
      self::$nextInsertID = 55555000000 + mt_rand(0, 1000);
    }
  }

  public function openConnection() {
    return;
  }

  public function close() {
    return;
  }

  public function escapeUTF8String($string) {
    return '<S>';
  }

  public function escapeBinaryString($string) {
    return '<B>';
  }

  public function escapeColumnName($name) {
    return '<C>';
  }

  public function escapeMultilineComment($comment) {
    return '<K>';
  }

  public function escapeStringForLikeClause($value) {
    return '<L>';
  }

  private function getConfiguration($key, $default = null) {
    return idx($this->configuration, $key, $default);
  }

  public function getInsertID() {
    return $this->insertID;
  }

  public function getAffectedRows() {
    return $this->affectedRows;
  }

  public function selectAllResults() {
    return $this->allResults;
  }

  public function executeQuery(PhutilQueryString $query) {

    // NOTE: "[\s<>K]*" allows any number of (properly escaped) comments to
    // appear prior to the allowed keyword, since this connection escapes
    // them as "<K>" (above).

    $display_query = $query->getMaskedString();
    $raw_query = $query->getUnmaskedString();

    $keywords = array(
      'INSERT',
      'UPDATE',
      'DELETE',
      'START',
      'SAVEPOINT',
      'COMMIT',
      'ROLLBACK',
    );
    $preg_keywords = array();
    foreach ($keywords as $key => $word) {
      $preg_keywords[] = preg_quote($word, '/');
    }
    $preg_keywords = implode('|', $preg_keywords);

    if (!preg_match('/^[\s<>K]*('.$preg_keywords.')\s*/i', $raw_query)) {
      throw new AphrontNotSupportedQueryException(
        pht(
          "Database isolation currently only supports some queries. You are ".
          "trying to issue a query which does not begin with an allowed ".
          "keyword (%s): '%s'.",
          implode(', ', $keywords),
          $display_query));
    }

    $this->transcript[] = $display_query;

    // NOTE: This method is intentionally simplified for now, since we're only
    // using it to stub out inserts/updates. In the future it will probably need
    // to grow more powerful.

    $this->allResults = array();

    // NOTE: We jitter the insert IDs to keep tests honest; a test should cover
    // the relationship between objects, not their exact insertion order. This
    // guarantees that IDs are unique but makes it impossible to hard-code tests
    // against this specific implementation detail.
    self::$nextInsertID += mt_rand(1, 10);
    $this->insertID = self::$nextInsertID;
    $this->affectedRows = 1;
  }

  public function executeRawQueries(array $raw_queries) {
    $results = array();
    foreach ($raw_queries as $id => $raw_query) {
      $results[$id] = array();
    }
    return $results;
  }

  public function getQueryTranscript() {
    return $this->transcript;
  }

}
