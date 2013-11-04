<?php

final class PhabricatorHash {


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
      throw new Exception("Trying to digest empty password!");
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
      $map = "0123456789".
             "abcdefghij".
             "klmnopqrst".
             "uvwxyzABCD".
             "EFGHIJKLMN".
             "OPQRSTUVWX".
             "YZ._";
    }

    $result = '';
    for ($ii = 0; $ii < 12; $ii++) {
      $result .= $map[(ord($hash[$ii]) & 0x3F)];
    }

    return $result;
  }

}
