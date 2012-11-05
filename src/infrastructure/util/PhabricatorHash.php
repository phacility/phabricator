<?php

final class PhabricatorHash {

  public static function digest($string) {
    $key = PhabricatorEnv::getEnvConfig('security.hmac-key');
    if (!$key) {
      throw new Exception(
        "Set a 'security.hmac-key' in your Phabricator configuration!");
    }

    return hash_hmac('sha1', $string, $key);
  }

}
