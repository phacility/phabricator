<?php

/**
 * Check if a password is extremely common. Preventing use of the most common
 * passwords is an attempt to mitigate slow botnet attacks against an entire
 * userbase. See T4143 for discussion.
 *
 * @task common Checking Common Passwords
 */
final class PhabricatorCommonPasswords extends Phobject {


/* -(  Checking Common Passwords  )------------------------------------------ */


  /**
   * Check if a password is extremely common.
   *
   * @param   string  Password to test.
   * @return  bool    True if the password is pathologically weak.
   *
   * @task common
   */
  public static function isCommonPassword($password) {
    static $list;
    if ($list === null) {
      $list = self::loadWordlist();
    }

    return isset($list[strtolower($password)]);
  }


  /**
   * Load the common password wordlist.
   *
   * @return map<string, bool>  Map of common passwords.
   *
   * @task common
   */
  private static function loadWordlist() {
    $root = dirname(phutil_get_library_root('phabricator'));
    $file = $root.'/externals/wordlist/password.lst';
    $data = Filesystem::readFile($file);

    $words = phutil_split_lines($data, $retain_endings = false);

    $map = array();
    foreach ($words as $key => $word) {
      // The wordlist file has some comments at the top, strip those out.
      if (preg_match('/^#!comment:/', $word)) {
        continue;
      }
      $map[strtolower($word)] = true;
    }

    // Add in some application-specific passwords.
    $map += array(
      'phabricator' => true,
      'phab' => true,
      'devtools' => true,
      'differential' => true,
      'codereview' => true,
      'review' => true,
    );

    return $map;
  }

}
