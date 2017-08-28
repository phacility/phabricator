<?php

final class PhabricatorNgramEngine extends Phobject {

  public function tokenizeString($value) {
    $value = trim($value, ' ');
    $value = preg_split('/ +/', $value);
    return $value;
  }

  public function getNgramsFromString($value, $mode) {
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

}
