<?php

abstract class PhabricatorFerretEngine extends Phobject {

  abstract public function newNgramsObject();
  abstract public function newDocumentObject();
  abstract public function newFieldObject();
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
    $value = preg_split('/ +/', $value);
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

}
