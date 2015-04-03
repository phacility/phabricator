<?php

final class PhabricatorHash extends Phobject {

  const INDEX_DIGEST_LENGTH = 12;

  /**
   * Digest a string for general use, including use which relates to security.
   *
   * @param   string  Input string.
   * @return  string  32-byte hexidecimal SHA1+HMAC hash.
   */
  public static function digest($string, $key = null) {
    if ($key === null) {
      $key = PhabricatorEnv::getEnvConfig('security.hmac-key');
    }

    if (!$key) {
      throw new Exception(
        "Set a 'security.hmac-key' in your Phabricator configuration!");
    }

    return hash_hmac('sha1', $string, $key);
  }


  /**
   * Digest a string into a password hash. This is similar to @{method:digest},
   * but requires a salt and iterates the hash to increase cost.
   */
  public static function digestPassword(PhutilOpaqueEnvelope $envelope, $salt) {
    $result = $envelope->openEnvelope();
    if (!$result) {
      throw new Exception('Trying to digest empty password!');
    }

    for ($ii = 0; $ii < 1000; $ii++) {
      $result = PhabricatorHash::digest($result, $salt);
    }

    return $result;
  }


  /**
   * Digest a string for use in, e.g., a MySQL index. This produces a short
   * (12-byte), case-sensitive alphanumeric string with 72 bits of entropy,
   * which is generally safe in most contexts (notably, URLs).
   *
   * This method emphasizes compactness, and should not be used for security
   * related hashing (for general purpose hashing, see @{method:digest}).
   *
   * @param   string  Input string.
   * @return  string  12-byte, case-sensitive alphanumeric hash of the string
   *                  which
   */
  public static function digestForIndex($string) {
    $hash = sha1($string, $raw_output = true);

    static $map;
    if ($map === null) {
      $map = '0123456789'.
             'abcdefghij'.
             'klmnopqrst'.
             'uvwxyzABCD'.
             'EFGHIJKLMN'.
             'OPQRSTUVWX'.
             'YZ._';
    }

    $result = '';
    for ($ii = 0; $ii < self::INDEX_DIGEST_LENGTH; $ii++) {
      $result .= $map[(ord($hash[$ii]) & 0x3F)];
    }

    return $result;
  }


  /**
   * Shorten a string to a maximum byte length in a collision-resistant way
   * while retaining some degree of human-readability.
   *
   * This function converts an input string into a prefix plus a hash. For
   * example, a very long string beginning with "crabapplepie..." might be
   * digested to something like "crabapp-N1wM1Nz3U84k".
   *
   * This allows the maximum length of identifiers to be fixed while
   * maintaining a high degree of collision resistance and a moderate degree
   * of human readability.
   *
   * @param string The string to shorten.
   * @param int Maximum length of the result.
   * @return string String shortened in a collision-resistant way.
   */
  public static function digestToLength($string, $length) {
    // We need at least two more characters than the hash length to fit in a
    // a 1-character prefix and a separator.
    $min_length = self::INDEX_DIGEST_LENGTH + 2;
    if ($length < $min_length) {
      throw new Exception(
        pht(
          'Length parameter in digestToLength() must be at least %s, '.
          'but %s was provided.',
          new PhutilNumber($min_length),
          new PhutilNumber($length)));
    }

    // We could conceivably return the string unmodified if it's shorter than
    // the specified length. Instead, always hash it. This makes the output of
    // the method more recognizable and consistent (no surprising new behavior
    // once you hit a string longer than `$length`) and prevents an attacker
    // who can control the inputs from intentionally using the hashed form
    // of a string to cause a collision.

    $hash = PhabricatorHash::digestForIndex($string);

    $prefix = substr($string, 0, ($length - ($min_length - 1)));

    return $prefix.'-'.$hash;
  }


}
