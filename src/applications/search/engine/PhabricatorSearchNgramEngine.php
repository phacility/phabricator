<?php

final class PhabricatorSearchNgramEngine
  extends Phobject {

  public function tokenizeNgramString($value) {
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
    $tokens = $this->tokenizeNgramString($value);

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

}
