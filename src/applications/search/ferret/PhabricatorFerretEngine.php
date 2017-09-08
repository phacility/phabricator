<?php

abstract class PhabricatorFerretEngine extends Phobject {

  abstract public function getApplicationName();
  abstract public function getScopeName();
  abstract public function newSearchEngine();

  public function getDefaultFunctionKey() {
    return 'all';
  }

  public function getObjectTypeRelevance() {
    return 1000;
  }

  public function getFieldForFunction($function) {
    $function = phutil_utf8_strtolower($function);

    $map = $this->getFunctionMap();
    if (!isset($map[$function])) {
      throw new PhutilSearchQueryCompilerSyntaxException(
        pht(
          'Unknown search function "%s". Supported functions are: %s.',
          $function,
          implode(', ', array_keys($map))));
    }

    return $map[$function]['field'];
  }

  public function getAllFunctionFields() {
    $map = $this->getFunctionMap();

    $fields = array();
    foreach ($map as $key => $spec) {
      $fields[] = $spec['field'];
    }

    return $fields;
  }

  protected function getFunctionMap() {
    return array(
      'all' => array(
        'field' => PhabricatorSearchDocumentFieldType::FIELD_ALL,
        'aliases' => array(
          'any',
        ),
      ),
      'title' => array(
        'field' => PhabricatorSearchDocumentFieldType::FIELD_TITLE,
        'aliases' => array(),
      ),
      'body' => array(
        'field' => PhabricatorSearchDocumentFieldType::FIELD_BODY,
        'aliases' => array(),
      ),
      'core' => array(
        'field' => PhabricatorSearchDocumentFieldType::FIELD_CORE,
        'aliases' => array(),
      ),
      'comment' => array(
        'field' => PhabricatorSearchDocumentFieldType::FIELD_COMMENT,
        'aliases' => array(
          'comments',
        ),
      ),
    );
  }

  public function newStemmer() {
    return new PhutilSearchStemmer();
  }

  public function tokenizeString($value) {
    $value = trim($value, ' ');
    $value = preg_split('/\s+/u', $value);
    return $value;
  }

  public function getTermNgramsFromString($string) {
    return $this->getNgramsFromString($string, true);
  }

  public function getSubstringNgramsFromString($string) {
    return $this->getNgramsFromString($string, false);
  }

  private function getNgramsFromString($value, $as_term) {
    $tokens = $this->tokenizeString($value);

    $ngrams = array();
    foreach ($tokens as $token) {
      $token = phutil_utf8_strtolower($token);

      if ($as_term) {
        $token = ' '.$token.' ';
      }

      $token_v = phutil_utf8v($token);
      $len = (count($token_v) - 2);
      for ($ii = 0; $ii < $len; $ii++) {
        $ngram = array_slice($token_v, $ii, 3);
        $ngram = implode('', $ngram);
        $ngrams[$ngram] = $ngram;
      }
    }

    ksort($ngrams);

    return array_keys($ngrams);
  }

  public function newTermsCorpus($raw_corpus) {
    $term_corpus = strtr(
      $raw_corpus,
      array(
        '!' => ' ',
        '"' => ' ',
        '#' => ' ',
        '$' => ' ',
        '%' => ' ',
        '&' => ' ',
        '(' => ' ',
        ')' => ' ',
        '*' => ' ',
        '+' => ' ',
        ',' => ' ',
        '-' => ' ',
        '/' => ' ',
        ':' => ' ',
        ';' => ' ',
        '<' => ' ',
        '=' => ' ',
        '>' => ' ',
        '?' => ' ',
        '@' => ' ',
        '[' => ' ',
        '\\' => ' ',
        ']' => ' ',
        '^' => ' ',
        '`' => ' ',
        '{' => ' ',
        '|' => ' ',
        '}' => ' ',
        '~' => ' ',
        '.' => ' ',
        '_' => ' ',
        "\n" => ' ',
        "\r" => ' ',
        "\t" => ' ',
      ));

    // NOTE: Single quotes divide terms only if they're at a word boundary.
    // In contractions, like "whom'st've", the entire word is a single term.
    $term_corpus = preg_replace('/(^| )[\']+/', ' ', $term_corpus);
    $term_corpus = preg_replace('/[\']+( |$)/', ' ', $term_corpus);

    $term_corpus = preg_replace('/\s+/u', ' ', $term_corpus);
    $term_corpus = trim($term_corpus, ' ');

    if (strlen($term_corpus)) {
      $term_corpus = ' '.$term_corpus.' ';
    }

    return $term_corpus;
  }

/* -(  Schema  )------------------------------------------------------------- */

  public function getDocumentTableName() {
    $application = $this->getApplicationName();
    $scope = $this->getScopeName();

    return "{$application}_{$scope}_fdocument";
  }

  public function getDocumentSchemaColumns() {
    return array(
      'id' => 'auto',
      'objectPHID' => 'phid',
      'isClosed' => 'bool',
      'authorPHID' => 'phid?',
      'ownerPHID' => 'phid?',
      'epochCreated' => 'epoch',
      'epochModified' => 'epoch',
    );
  }

  public function getDocumentSchemaKeys() {
    return array(
      'PRIMARY' => array(
        'columns' => array('id'),
        'unique' => true,
      ),
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
    );
  }

  public function getFieldTableName() {
    $application = $this->getApplicationName();
    $scope = $this->getScopeName();

    return "{$application}_{$scope}_ffield";
  }

  public function getFieldSchemaColumns() {
    return array(
      'id' => 'auto',
      'documentID' => 'uint32',
      'fieldKey' => 'text4',
      'rawCorpus' => 'sort',
      'termCorpus' => 'sort',
      'normalCorpus' => 'sort',
    );
  }

  public function getFieldSchemaKeys() {
    return array(
      'PRIMARY' => array(
        'columns' => array('id'),
        'unique' => true,
      ),
      'key_documentfield' => array(
        'columns' => array('documentID', 'fieldKey'),
        'unique' => true,
      ),
    );
  }

  public function getNgramsTableName() {
    $application = $this->getApplicationName();
    $scope = $this->getScopeName();

    return "{$application}_{$scope}_fngrams";
  }

  public function getNgramsSchemaColumns() {
    return array(
      'id' => 'auto',
      'documentID' => 'uint32',
      'ngram' => 'char3',
    );
  }

  public function getNgramsSchemaKeys() {
    return array(
      'PRIMARY' => array(
        'columns' => array('id'),
        'unique' => true,
      ),
      'key_ngram' => array(
        'columns' => array('ngram', 'documentID'),
      ),
      'key_object' => array(
        'columns' => array('documentID'),
      ),
    );
  }

}
