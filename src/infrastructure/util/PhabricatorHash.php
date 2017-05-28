<?php

final class PhabricatorHash extends Phobject {

  const INDEX_DIGEST_LENGTH = 12;

  /**
   * Digest a string using HMAC+SHA1.
   *
   * Because a SHA1 collision is now known, this method should be considered
   * weak. Callers should prefer @{method:digestWithNamedKey}.
   *
   * @param   string  Input string.
   * @return  string  32-byte hexidecimal SHA1+HMAC hash.
   */
  public static function weakDigest($string, $key = null) {
    if ($key === null) {
      $key = PhabricatorEnv::getEnvConfig('security.hmac-key');
    }

    if (!$key) {
      throw new Exception(
        pht(
          "Set a '%s' in your Phabricator configuration!",
          'security.hmac-key'));
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
      throw new Exception(pht('Trying to digest empty password!'));
    }

    for ($ii = 0; $ii < 1000; $ii++) {
      $result = self::weakDigest($result, $salt);
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

  public static function digestToRange($string, $min, $max) {
    if ($min > $max) {
      throw new Exception(pht('Maximum must be larger than minimum.'));
    }

    if ($min == $max) {
      return $min;
    }

    $hash = sha1($string, $raw_output = true);
    // Make sure this ends up positive, even on 32-bit machines.
    $value = head(unpack('L', $hash)) & 0x7FFFFFFF;

    return $min + ($value % (1 + $max - $min));
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
          'Length parameter in %s must be at least %s, '.
          'but %s was provided.',
          'digestToLength()',
          new PhutilNumber($min_length),
          new PhutilNumber($length)));
    }

    // We could conceivably return the string unmodified if it's shorter than
    // the specified length. Instead, always hash it. This makes the output of
    // the method more recognizable and consistent (no surprising new behavior
    // once you hit a string longer than `$length`) and prevents an attacker
    // who can control the inputs from intentionally using the hashed form
    // of a string to cause a collision.

    $hash = self::digestForIndex($string);

    $prefix = substr($string, 0, ($length - ($min_length - 1)));

    return $prefix.'-'.$hash;
  }

  public static function digestWithNamedKey($message, $key_name) {
    $key_bytes = self::getNamedHMACKey($key_name);
    return self::digestHMACSHA256($message, $key_bytes);
  }

  public static function digestHMACSHA256($message, $key) {
    if (!strlen($key)) {
      throw new Exception(
        pht('HMAC-SHA256 requires a nonempty key.'));
    }

    $result = hash_hmac('sha256', $message, $key, $raw_output = false);

    if ($result === false) {
      throw new Exception(
        pht('Unable to compute HMAC-SHA256 digest of message.'));
    }

    return $result;
  }


/* -(  HMAC Key Management  )------------------------------------------------ */


  private static function getNamedHMACKey($hmac_name) {
    $cache = PhabricatorCaches::getImmutableCache();

    $cache_key = "hmac.key({$hmac_name})";

    $hmac_key = $cache->getKey($cache_key);
    if (!strlen($hmac_key)) {
      $hmac_key = self::readHMACKey($hmac_name);

      if ($hmac_key === null) {
        $hmac_key = self::newHMACKey($hmac_name);
        self::writeHMACKey($hmac_name, $hmac_key);
      }

      $cache->setKey($cache_key, $hmac_key);
    }

    // The "hex2bin()" function doesn't exist until PHP 5.4.0 so just
    // implement it inline.
    $result = '';
    for ($ii = 0; $ii < strlen($hmac_key); $ii += 2) {
      $result .= pack('H*', substr($hmac_key, $ii, 2));
    }

    return $result;
  }

  private static function newHMACKey($hmac_name) {
    $hmac_key = Filesystem::readRandomBytes(64);
    return bin2hex($hmac_key);
  }

  private static function writeHMACKey($hmac_name, $hmac_key) {
    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

      id(new PhabricatorAuthHMACKey())
        ->setKeyName($hmac_name)
        ->setKeyValue($hmac_key)
        ->save();

    unset($unguarded);
  }

  private static function readHMACKey($hmac_name) {
    $table = new PhabricatorAuthHMACKey();
    $conn = $table->establishConnection('r');

    $row = queryfx_one(
      $conn,
      'SELECT keyValue FROM %T WHERE keyName = %s',
      $table->getTableName(),
      $hmac_name);
    if (!$row) {
      return null;
    }

    return $row['keyValue'];
  }


}
