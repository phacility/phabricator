<?php

final class PhutilSearchStemmer
  extends Phobject {

  public function stemToken($token) {
    $token = $this->normalizeToken($token);
    return $this->applyStemmer($token);
  }

  public function stemCorpus($corpus) {
    $corpus = $this->normalizeCorpus($corpus);
    $tokens = preg_split('/[^a-zA-Z0-9\x7F-\xFF._]+/', $corpus);

    $words = array();
    foreach ($tokens as $key => $token) {
      $token = trim($token, '._');

      if (strlen($token) < 3) {
        continue;
      }

      $words[$token] = $token;
    }

    $stems = array();
    foreach ($words as $word) {
      $stems[] = $this->applyStemmer($word);
    }

    return implode(' ', $stems);
  }

  private function normalizeToken($token) {
    return phutil_utf8_strtolower($token);
  }

  private function normalizeCorpus($corpus) {
    return phutil_utf8_strtolower($corpus);
  }

  /**
   * @phutil-external-symbol class Porter
   */
  private function applyStemmer($normalized_token) {
    // If the token has internal punctuation, handle it literally. This
    // deals with things like domain names, Conduit API methods, and other
    // sorts of informal tokens.
    if (preg_match('/[._]/', $normalized_token)) {
      return $normalized_token;
    }

    static $loaded;

    if ($loaded === null) {
      $root = dirname(phutil_get_library_root('phabricator'));
      require_once $root.'/externals/porter-stemmer/src/Porter.php';
      $loaded = true;
    }


    $stem = Porter::stem($normalized_token);

    // If the stem is too short, it won't be a candidate for indexing. These
    // tokens are also likely to be acronyms (like "DNS") rather than real
    // English words.
    if (strlen($stem) < 3) {
      return $normalized_token;
    }

    return $stem;
  }

}
