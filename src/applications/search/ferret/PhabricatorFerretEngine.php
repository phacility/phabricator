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
    $value = phutil_utf8_strtolower($value);
    $tokens = $this->tokenizeString($value);

    // First, extract unique tokens from the string. This reduces the number
    // of `phutil_utf8v()` calls we need to make if we are indexing a large
    // corpus with redundant terms.
    $unique_tokens = array();
    foreach ($tokens as $token) {
      if ($as_term) {
        $token = ' '.$token.' ';
      }

      $unique_tokens[$token] = true;
    }

    $ngrams = array();
    foreach ($unique_tokens as $token => $ignored) {
      $token_v = phutil_utf8v($token);
      $length = count($token_v);

      // NOTE: We're being somewhat clever here to micro-optimize performance,
      // especially for very long strings. See PHI87.

      $token_l = array();
      for ($ii = 0; $ii < $length; $ii++) {
        $token_l[$ii] = strlen($token_v[$ii]);
      }

      $ngram_count = $length - 2;
      $cursor = 0;
      for ($ii = 0; $ii < $ngram_count; $ii++) {
        $ngram_l = $token_l[$ii] + $token_l[$ii + 1] + $token_l[$ii + 2];

        $ngram = substr($token, $cursor, $ngram_l);
        $ngrams[$ngram] = $ngram;

        $cursor += $token_l[$ii];
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

  public function getCommonNgramsTableName() {
    $application = $this->getApplicationName();
    $scope = $this->getScopeName();

    return "{$application}_{$scope}_fngrams_common";
  }

  public function getCommonNgramsSchemaColumns() {
    return array(
      'id' => 'auto',
      'ngram' => 'char3',
      'needsCollection' => 'bool',
    );
  }

  public function getCommonNgramsSchemaKeys() {
    return array(
      'PRIMARY' => array(
        'columns' => array('id'),
        'unique' => true,
      ),
      'key_ngram' => array(
        'columns' => array('ngram'),
        'unique' => true,
      ),
      'key_collect' => array(
        'columns' => array('needsCollection'),
      ),
    );
  }

}
